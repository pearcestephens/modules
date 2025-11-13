/**
 * Ecigdis Chat WebSocket Server
 * Standalone server for real-time messaging
 *
 * Can run on completely different machine from main CIS site
 * Uses Redis for pub/sub and horizontal scaling
 *
 * Usage:
 *   NODE_ENV=production node server.js
 *
 * Default Port: 8080 (HTTP), 8443 (HTTPS if configured)
 *
 * Environment Variables:
 *   WS_PORT=8080
 *   WS_HOST=0.0.0.0
 *   NODE_ENV=production
 *   REDIS_HOST=127.0.0.1
 *   REDIS_PORT=6379
 *   MAIN_SITE_URL=https://staff.vapeshed.co.nz
 *   JWT_SECRET=your-jwt-secret
 *   LOG_LEVEL=info
 */

import express from 'express';
import { createServer } from 'http';
import { createSecureServer } from 'http2';
import { Server } from 'socket.io';
import { createClient } from 'redis';
import { createAdapter } from '@socket.io/redis-adapter';
import cors from 'cors';
import dotenv from 'dotenv';
import pino from 'pino';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import axios from 'axios';

// Load environment variables
dotenv.config();

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// Initialize logger
const logger = pino(
  process.env.NODE_ENV === 'production'
    ? {}
    : { transport: { target: 'pino-pretty' } }
);

// Configuration
const config = {
  port: parseInt(process.env.WS_PORT || '8080'),
  host: process.env.WS_HOST || '0.0.0.0',
  nodeEnv: process.env.NODE_ENV || 'development',
  redisHost: process.env.REDIS_HOST || '127.0.0.1',
  redisPort: parseInt(process.env.REDIS_PORT || '6379'),
  mainSiteUrl: process.env.MAIN_SITE_URL || 'https://staff.vapeshed.co.nz',
  jwtSecret: process.env.JWT_SECRET || 'your-secret-key-change-me',
  corsOrigins: process.env.CORS_ORIGINS
    ? process.env.CORS_ORIGINS.split(',')
    : ['https://staff.vapeshed.co.nz', 'http://localhost:3000'],
  useHttps: process.env.USE_HTTPS === 'true',
  certPath: process.env.CERT_PATH || '/etc/ssl/certs/server.crt',
  keyPath: process.env.KEY_PATH || '/etc/ssl/private/server.key',
  logLevel: process.env.LOG_LEVEL || 'info'
};

logger.info('WebSocket Server Configuration:', {
  port: config.port,
  host: config.host,
  environment: config.nodeEnv,
  redis: `${config.redisHost}:${config.redisPort}`,
  cors: config.corsOrigins
});

// Express app
const app = express();
app.use(cors({ origin: config.corsOrigins, credentials: true }));
app.use(express.json());

// Health check endpoint
app.get('/health', (req, res) => {
  res.json({
    status: 'ok',
    timestamp: new Date().toISOString(),
    uptime: process.uptime(),
    environment: config.nodeEnv,
    redis: redisConnected ? 'connected' : 'disconnected'
  });
});

// Status endpoint
app.get('/status', async (req, res) => {
  const stats = io.engine.clientsCount;
  const connectedUsers = Array.from(io.sockets.sockets.values())
    .map(socket => socket.userId)
    .filter(Boolean);

  res.json({
    status: 'online',
    connectedClients: stats,
    connectedUsers: connectedUsers.length,
    uniqueUsers: new Set(connectedUsers).size,
    uptime: process.uptime(),
    redis: redisConnected ? 'connected' : 'disconnected'
  });
});

// Metrics endpoint (for monitoring)
app.get('/metrics', (req, res) => {
  const sockets = Array.from(io.sockets.sockets.values());
  const userMap = {};

  sockets.forEach(socket => {
    const userId = socket.userId;
    if (userId) {
      if (!userMap[userId]) {
        userMap[userId] = { sockets: [], conversations: [] };
      }
      userMap[userId].sockets.push({
        socketId: socket.id,
        connectedAt: socket.connectedAt
      });
    }
  });

  res.json({
    totalConnections: sockets.length,
    uniqueUsers: Object.keys(userMap).length,
    users: userMap,
    timestamp: new Date().toISOString()
  });
});

// HTTP server (with optional HTTPS)
let server;
if (config.useHttps && fs.existsSync(config.certPath) && fs.existsSync(config.keyPath)) {
  logger.info('Using HTTPS for WebSocket server');
  const cert = fs.readFileSync(config.certPath);
  const key = fs.readFileSync(config.keyPath);
  server = createSecureServer({ cert, key }, app);
} else {
  logger.info('Using HTTP for WebSocket server (HTTPS not configured)');
  server = createServer(app);
}

// Socket.IO with Redis adapter for scaling
const io = new Server(server, {
  cors: {
    origin: config.corsOrigins,
    credentials: true,
    methods: ['GET', 'POST']
  },
  transports: ['websocket', 'polling'],
  maxHttpBufferSize: 1e6, // 1MB
  pingInterval: 25000,
  pingTimeout: 60000,
  serveClient: false
});

// Redis clients for pub/sub
let pubClient;
let subClient;
let redisConnected = false;

/**
 * Initialize Redis connection and adapter
 */
async function initializeRedis() {
  try {
    pubClient = createClient({
      host: config.redisHost,
      port: config.redisPort
    });

    subClient = pubClient.duplicate();

    pubClient.on('error', (err) => {
      logger.error('Redis Pub Client Error:', err);
      redisConnected = false;
    });

    subClient.on('error', (err) => {
      logger.error('Redis Sub Client Error:', err);
      redisConnected = false;
    });

    pubClient.on('connect', () => {
      logger.info('✅ Redis Pub Client Connected');
      redisConnected = true;
    });

    subClient.on('connect', () => {
      logger.info('✅ Redis Sub Client Connected');
    });

    await Promise.all([
      pubClient.connect(),
      subClient.connect()
    ]);

    // Set up Redis adapter for Socket.IO (allows scaling across multiple servers)
    io.adapter(createAdapter(pubClient, subClient));

    logger.info('✅ Redis adapter initialized for Socket.IO');
  } catch (err) {
    logger.error('Failed to initialize Redis:', err);
    logger.warn('Continuing without Redis (single-server mode)');
  }
}

/**
 * Verify user token with main CIS site
 */
async function verifyToken(token) {
  try {
    const response = await axios.post(
      `${config.mainSiteUrl}/api/auth/verify-token`,
      { token },
      {
        timeout: 5000,
        headers: {
          'Content-Type': 'application/json'
        }
      }
    );

    return response.data.user;
  } catch (err) {
    logger.warn('Token verification failed:', {
      error: err.message,
      token: token.substring(0, 10) + '...'
    });
    return null;
  }
}

/**
 * Socket.IO Events and Handlers
 */

io.on('connection', (socket) => {
  logger.info(`📱 Client connected: ${socket.id}`);
  socket.connectedAt = new Date();

  // Emit server welcome message
  socket.emit('connected', {
    serverId: 'ecigdis-ws-01',
    timestamp: new Date().toISOString(),
    message: 'Welcome to Ecigdis Chat WebSocket Server'
  });

  /**
   * User authentication
   * Client must send token to authenticate
   */
  socket.on('authenticate', async (data) => {
    try {
      const { token } = data;

      if (!token) {
        socket.emit('error', {
          code: 'NO_TOKEN',
          message: 'Authentication token required'
        });
        socket.disconnect();
        return;
      }

      // Verify token with main site
      const user = await verifyToken(token);

      if (!user) {
        socket.emit('error', {
          code: 'INVALID_TOKEN',
          message: 'Token verification failed'
        });
        socket.disconnect();
        return;
      }

      // Store user info on socket
      socket.userId = user.id;
      socket.userEmail = user.email;
      socket.userName = user.name;

      // Join user-specific room
      socket.join(`user:${user.id}`);

      logger.info(`✅ User authenticated: ${user.email} (${socket.id})`);

      socket.emit('authenticated', {
        userId: user.id,
        message: 'Successfully authenticated'
      });

      // Broadcast user online status
      io.emit('user:online', {
        userId: user.id,
        name: user.name,
        timestamp: new Date().toISOString()
      });

    } catch (err) {
      logger.error('Authentication error:', err);
      socket.emit('error', {
        code: 'AUTH_ERROR',
        message: 'Authentication failed'
      });
      socket.disconnect();
    }
  });

  /**
   * Join conversation (subscribe to messages)
   */
  socket.on('conversation:join', (data) => {
    try {
      const { conversationId } = data;

      if (!socket.userId) {
        socket.emit('error', {
          code: 'NOT_AUTHENTICATED',
          message: 'Must authenticate first'
        });
        return;
      }

      socket.join(`conversation:${conversationId}`);
      logger.debug(`User ${socket.userId} joined conversation ${conversationId}`);

      // Notify others in conversation
      io.to(`conversation:${conversationId}`).emit('user:joined', {
        conversationId,
        userId: socket.userId,
        userName: socket.userName,
        timestamp: new Date().toISOString()
      });
    } catch (err) {
      logger.error('Error joining conversation:', err);
    }
  });

  /**
   * Leave conversation
   */
  socket.on('conversation:leave', (data) => {
    try {
      const { conversationId } = data;
      socket.leave(`conversation:${conversationId}`);

      io.to(`conversation:${conversationId}`).emit('user:left', {
        conversationId,
        userId: socket.userId,
        timestamp: new Date().toISOString()
      });
    } catch (err) {
      logger.error('Error leaving conversation:', err);
    }
  });

  /**
   * Send message to conversation
   */
  socket.on('message:send', async (data) => {
    try {
      const { conversationId, messageId, text, mentions } = data;

      if (!socket.userId) {
        socket.emit('error', { code: 'NOT_AUTHENTICATED' });
        return;
      }

      const message = {
        messageId: messageId || `msg_${Date.now()}`,
        conversationId,
        senderId: socket.userId,
        senderName: socket.userName,
        text,
        mentions: mentions || [],
        timestamp: new Date().toISOString(),
        status: 'delivered'
      };

      // Broadcast to conversation
      io.to(`conversation:${conversationId}`).emit('message:new', message);

      // Acknowledge to sender
      socket.emit('message:ack', {
        messageId: message.messageId,
        status: 'delivered'
      });

      logger.debug(`Message sent to conversation ${conversationId}`);
    } catch (err) {
      logger.error('Error sending message:', err);
      socket.emit('error', {
        code: 'MESSAGE_ERROR',
        message: 'Failed to send message'
      });
    }
  });

  /**
   * Typing indicator
   */
  socket.on('typing:start', (data) => {
    try {
      const { conversationId } = data;
      io.to(`conversation:${conversationId}`).emit('typing:indicator', {
        conversationId,
        userId: socket.userId,
        userName: socket.userName,
        isTyping: true
      });
    } catch (err) {
      logger.error('Error in typing indicator:', err);
    }
  });

  socket.on('typing:stop', (data) => {
    try {
      const { conversationId } = data;
      io.to(`conversation:${conversationId}`).emit('typing:indicator', {
        conversationId,
        userId: socket.userId,
        userName: socket.userName,
        isTyping: false
      });
    } catch (err) {
      logger.error('Error stopping typing:', err);
    }
  });

  /**
   * Message read receipt
   */
  socket.on('message:read', (data) => {
    try {
      const { conversationId, messageId } = data;
      io.to(`conversation:${conversationId}`).emit('message:read-receipt', {
        messageId,
        conversationId,
        userId: socket.userId,
        timestamp: new Date().toISOString()
      });
    } catch (err) {
      logger.error('Error in read receipt:', err);
    }
  });

  /**
   * Message reaction (emoji)
   */
  socket.on('message:react', (data) => {
    try {
      const { conversationId, messageId, emoji } = data;
      io.to(`conversation:${conversationId}`).emit('message:reaction', {
        messageId,
        conversationId,
        userId: socket.userId,
        emoji,
        timestamp: new Date().toISOString()
      });
    } catch (err) {
      logger.error('Error in reaction:', err);
    }
  });

  /**
   * Request presence (who's online)
   */
  socket.on('presence:request', () => {
    try {
      const onlineUsers = Array.from(io.sockets.sockets.values())
        .filter(s => s.userId)
        .map(s => ({
          userId: s.userId,
          userName: s.userName,
          connectedAt: s.connectedAt
        }));

      socket.emit('presence:list', {
        users: onlineUsers,
        count: onlineUsers.length,
        timestamp: new Date().toISOString()
      });
    } catch (err) {
      logger.error('Error in presence request:', err);
    }
  });

  /**
   * Disconnect handler
   */
  socket.on('disconnect', () => {
    logger.info(`❌ Client disconnected: ${socket.id}`);

    if (socket.userId) {
      // Broadcast user offline status
      io.emit('user:offline', {
        userId: socket.userId,
        timestamp: new Date().toISOString()
      });
    }
  });

  /**
   * Error handler
   */
  socket.on('error', (err) => {
    logger.error('Socket error:', err);
  });
});

/**
 * Start the server
 */
async function start() {
  try {
    // Initialize Redis
    await initializeRedis();

    // Start HTTP server
    server.listen(config.port, config.host, () => {
      logger.info(`
╔════════════════════════════════════════════════════════════════╗
║     🚀 Ecigdis Chat WebSocket Server Started Successfully       ║
╠════════════════════════════════════════════════════════════════╣
║ Server URL: ${config.useHttps ? 'wss' : 'ws'}://${config.host}:${config.port}                              ║
║ Environment: ${config.nodeEnv.toUpperCase().padEnd(50)} ║
║ Health Check: http://${config.host}:${config.port}/health        ║
║ Status: http://${config.host}:${config.port}/status             ║
║ Metrics: http://${config.host}:${config.port}/metrics           ║
╚════════════════════════════════════════════════════════════════╝
      `);

      logger.info('📡 Listening for connections...');
      logger.info('✅ WebSocket server ready to accept clients');
    });

    // Graceful shutdown
    process.on('SIGTERM', () => {
      logger.info('SIGTERM received, shutting down gracefully...');
      server.close(() => {
        logger.info('Server closed');
        if (pubClient) pubClient.quit();
        if (subClient) subClient.quit();
        process.exit(0);
      });
    });

  } catch (err) {
    logger.error('Failed to start server:', err);
    process.exit(1);
  }
}

// Start server
start();

export { io, config, logger };
