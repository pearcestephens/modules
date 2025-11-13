# Ecigdis Chat WebSocket Server

**Real-time messaging server - Runs independently on separate machine**

🚀 **Status:** Production-Ready | ✅ Tested | 📦 Ready to Deploy

---

## 🎯 What Is This?

A standalone Node.js WebSocket server that handles:
- ✅ Real-time message delivery
- ✅ User presence tracking
- ✅ Typing indicators
- ✅ Read receipts
- ✅ Message reactions
- ✅ Works when main site is down
- ✅ Horizontal scaling with Redis

**Key Feature:** Runs on a completely separate machine from your main CIS site.

---

## 🚀 Quick Start (5 minutes)

```bash
# Development
cd modules/base/websocket
npm install
npm run dev

# Production (separate machine)
scp -r modules/base/websocket/* root@NEW_SERVER:/opt/ecigdis-ws/
ssh root@NEW_SERVER
cd /opt/ecigdis-ws
npm install && npm run prod
```

See `QUICK_START.md` for detailed setup.

---

## 📋 Files in This Directory

| File | Purpose |
|------|---------|
| **server.js** | Main WebSocket server (production-ready) |
| **package.json** | Node.js dependencies and scripts |
| **.env.example** | Environment configuration template |
| **DEPLOYMENT_GUIDE.md** | Complete step-by-step setup guide |
| **QUICK_START.md** | Fast 10-minute setup |
| **test-connection.js** | Automated connection tester |
| **README.md** | This file |

---

## 🏗️ Architecture

```
┌─────────────────────────────┐
│   Main CIS Site             │
│   staff.vapeshed.co.nz      │
│   (PHP/Apache)              │
└────────────┬────────────────┘
             │ (HTTP REST API)
             │ /api/messenger/poll
             │
    ┌────────▼──────────┐
    │   ChatManager.js   │
    │   (Smart Router)   │
    └────────┬──────────┘
             │
    ┌────────┴──────────────┐
    │ (WebSocket - Primary) │ (HTTP - Fallback)
    │                       │
    ▼                       ▼
┌──────────────────┐  ┌─────────────┐
│ WebSocket Server │  │ CIS Site    │
│ (Separate        │  │ (Backup)    │
│  Machine)        │  │             │
│                  │  └─────────────┘
│ ws.ecigdis.      │
│ co.nz:8080       │
│                  │
│ Node.js          │
│ Socket.io        │
│ Redis Pub/Sub    │
└──────┬───────────┘
       │ (Shared Database)
       │
       ▼
   ┌────────────┐
   │ MariaDB    │
   │            │
   │ Messages   │
   │ Users      │
   │ Prefs      │
   └────────────┘
```

---

## 🔧 Configuration

### Environment Variables
```bash
# Server
WS_PORT=8080
WS_HOST=0.0.0.0
NODE_ENV=production

# Security
USE_HTTPS=true
CERT_PATH=/etc/ssl/certs/server.crt
KEY_PATH=/etc/ssl/private/server.key

# Redis (for pub/sub)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Authentication
MAIN_SITE_URL=https://staff.vapeshed.co.nz
JWT_SECRET=your-jwt-secret-key

# CORS
CORS_ORIGINS=https://staff.vapeshed.co.nz
```

Copy `.env.example` to `.env` and customize for your environment.

---

## 📡 Socket.IO Events

### Client → Server

| Event | Data | Purpose |
|-------|------|---------|
| `authenticate` | `{ token }` | Authenticate user |
| `conversation:join` | `{ conversationId }` | Join a conversation |
| `conversation:leave` | `{ conversationId }` | Leave a conversation |
| `message:send` | `{ conversationId, text, messageId }` | Send message |
| `typing:start` | `{ conversationId }` | User started typing |
| `typing:stop` | `{ conversationId }` | User stopped typing |
| `message:read` | `{ conversationId, messageId }` | Mark message as read |
| `message:react` | `{ conversationId, messageId, emoji }` | React to message |
| `presence:request` | - | Get online users |

### Server → Client

| Event | Data | Purpose |
|-------|------|---------|
| `connected` | `{ serverId, timestamp }` | Server welcome message |
| `authenticated` | `{ userId }` | Authentication successful |
| `message:new` | Message object | New message received |
| `message:ack` | `{ messageId, status }` | Message acknowledgement |
| `typing:indicator` | `{ userId, isTyping }` | User typing status |
| `message:read-receipt` | `{ messageId, userId }` | Read receipt |
| `message:reaction` | `{ messageId, emoji, userId }` | Message reaction |
| `user:online` | `{ userId, name }` | User came online |
| `user:offline` | `{ userId }` | User went offline |
| `user:joined` | `{ userId, conversationId }` | User joined conversation |
| `user:left` | `{ userId, conversationId }` | User left conversation |
| `presence:list` | `{ users: [...], count }` | Online users list |
| `error` | `{ code, message }` | Error occurred |

---

## 🧪 Testing

### Test Connection
```bash
npm install
node test-connection.js
```

### Manual Testing
```bash
# Development
npm run dev

# In another terminal
node test-connection.js
```

### Load Testing
```bash
npm install -g artillery

# Create load-test.yml with test scenarios
artillery run load-test.yml
```

---

## 📊 Health Checks

### Endpoints

**Health Check**
```bash
curl http://localhost:8080/health
# Returns: { status: 'ok', uptime: 123.45, redis: 'connected' }
```

**Status**
```bash
curl http://localhost:8080/status
# Returns: { connectedClients: 42, uniqueUsers: 35, uptime: 123.45 }
```

**Metrics**
```bash
curl http://localhost:8080/metrics
# Returns: Detailed statistics about connections
```

---

## 🚀 Deployment

### Development
```bash
npm run dev
```

### Production (with PM2)
```bash
npm install -g pm2
pm2 start server.js --name "ecigdis-ws"
pm2 startup
pm2 save
pm2 logs ecigdis-ws
```

### Full Documentation
See `DEPLOYMENT_GUIDE.md` for:
- Step-by-step setup on separate machine
- SSL/HTTPS configuration
- Redis setup
- PM2 process management
- Monitoring and logging
- Troubleshooting guide

---

## 🔗 Integration with Main Site

### Update ChatManager.js
```javascript
const chatManager = new ChatManager({
  wsServer: 'wss://ws.ecigdis.co.nz:8080',  // WebSocket server
  httpFallback: '/api/messenger/poll',       // Fallback to HTTP
  mainSiteUrl: 'https://staff.vapeshed.co.nz'
});
```

### Update Main Site .env
```bash
WEBSOCKET_SERVER_URL=wss://ws.ecigdis.co.nz:8080
JWT_SECRET=your-jwt-secret-key  # MUST MATCH WebSocket server
```

---

## 🔐 Security Features

- ✅ JWT token authentication
- ✅ HTTPS/WSS support
- ✅ CORS origin validation
- ✅ Rate limiting ready
- ✅ User isolation via rooms
- ✅ Token verification with main site

---

## 📈 Scaling

### Single Server
- Uses Node.js clustering
- ~10,000 concurrent connections per server

### Multiple Servers
- Configure Redis for pub/sub
- Deploy multiple instances behind load balancer
- All servers share same Redis instance

### Configuration
```bash
# Multiple servers (with Redis)
REDIS_HOST=redis.internal.ecigdis.co.nz
REDIS_PORT=6379
```

---

## 📝 Logging

View logs with PM2:
```bash
# Real-time logs
pm2 logs ecigdis-ws

# Last 100 lines
pm2 logs ecigdis-ws --lines 100

# Flush logs
pm2 flush
```

Configure log level:
```bash
LOG_LEVEL=debug   # Verbose
LOG_LEVEL=info    # Normal
LOG_LEVEL=warn    # Warnings only
LOG_LEVEL=error   # Errors only
```

---

## 🆘 Troubleshooting

### Server won't start
```bash
# Check logs
pm2 logs ecigdis-ws

# Check if port in use
lsof -i :8080

# Check Node.js version
node --version  # Should be 18+
```

### Can't connect from browser
```bash
# Check firewall
ufw status
ufw allow 8080/tcp

# Test connectivity
curl http://localhost:8080/health

# Check CORS settings in .env
```

### Token verification fails
```bash
# Verify JWT_SECRET matches main site
cat .env | grep JWT_SECRET

# Check token format
# Should be valid JWT token from login endpoint
```

See `DEPLOYMENT_GUIDE.md` for more troubleshooting.

---

## 📚 Documentation

- **QUICK_START.md** - Get running in 10 minutes
- **DEPLOYMENT_GUIDE.md** - Complete setup for separate machine
- **README.md** - This file
- **server.js** - Well-commented source code

---

## 🤝 Contributing

To modify this server:
1. Make changes to `server.js`
2. Test locally: `npm run dev`
3. Run tests: `node test-connection.js`
4. Commit changes
5. Deploy to production when ready

---

## 📞 Support

For help:
1. Check logs: `pm2 logs ecigdis-ws`
2. Check health: `curl http://localhost:8080/health`
3. Read DEPLOYMENT_GUIDE.md
4. Review server.js comments

---

## 📋 Summary

| Aspect | Details |
|--------|---------|
| **Purpose** | Real-time messaging server |
| **Technology** | Node.js, Socket.io, Redis |
| **Deployment** | Separate machine (can be different IP/domain) |
| **Availability** | Works when main site is down |
| **Scalability** | Horizontal scaling with Redis |
| **Security** | JWT auth, HTTPS/WSS, CORS validation |
| **Monitoring** | Health endpoints, PM2 monitoring |
| **Reliability** | 99.9% uptime target |

---

**Status:** ✅ Production Ready
**Last Updated:** November 11, 2025
**Maintained By:** Ecigdis Engineering Team
