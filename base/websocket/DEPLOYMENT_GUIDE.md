# WebSocket Server - Separate Machine Deployment Guide

**Status:** 🚀 Ready to deploy on completely separate machine
**Date:** November 11, 2025
**System:** Node.js + Socket.io + Redis

---

## 📋 Quick Summary

This WebSocket server runs independently from your main CIS site. It:
- ✅ Handles all real-time messaging
- ✅ Works even if main site is down
- ✅ Scales horizontally with Redis
- ✅ Can be on different machine/domain
- ✅ Uses secure authentication
- ✅ Provides fallback mechanisms

---

## 🖥️ Server Requirements

### Minimum Specs
- **OS:** Linux (Ubuntu 20.04+, CentOS 8+)
- **Node.js:** 18.0.0 or higher
- **RAM:** 512MB (1GB+ recommended)
- **Disk:** 1GB free space
- **CPU:** 1 core (2+ cores for scaling)
- **Network:** Static IP or domain name

### Dependencies
- Node.js Package Manager (npm or yarn)
- Redis (for pub/sub and scaling)
- SSL Certificate (for HTTPS/WSS)
- Firewall rules for port 8080 (or custom)

### Recommended Setup
```
Machine 1: Main CIS Site (PHP/Apache)
  └─ staff.vapeshed.co.nz:443

Machine 2: WebSocket Server (Node.js) ← NEW
  └─ ws.ecigdis.co.nz:8080

Machine 3: Redis Server (optional, for scaling)
  └─ redis.internal.ecigdis.co.nz:6379
```

---

## 📦 Step 1: Prepare New Machine

### 1.1 SSH into new server
```bash
ssh root@NEW_SERVER_IP_ADDRESS
```

### 1.2 Update system packages
```bash
apt update && apt upgrade -y
```

### 1.3 Install Node.js
```bash
# Using NodeSource repository
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
apt install -y nodejs

# Verify installation
node --version  # Should show v20.x.x
npm --version   # Should show 10.x.x
```

### 1.4 Install Redis (optional, for scaling)
```bash
apt install -y redis-server

# Verify Redis
redis-cli ping  # Should respond with PONG
```

### 1.5 Install PM2 (process manager)
```bash
npm install -g pm2

# Verify
pm2 --version
```

---

## 📥 Step 2: Deploy WebSocket Server

### 2.1 Create application directory
```bash
mkdir -p /opt/ecigdis-ws
cd /opt/ecigdis-ws
```

### 2.2 Copy server files
```bash
# Copy from your development machine
scp -r /path/to/modules/base/websocket/* root@NEW_SERVER:/opt/ecigdis-ws/

# OR clone from Git repository
cd /opt/ecigdis-ws
git clone https://github.com/pearcestephens/ecigdis-websocket.git .
```

### 2.3 Install Node dependencies
```bash
cd /opt/ecigdis-ws
npm install

# Verify installation
npm list  # Should show socket.io, redis, express, etc.
```

---

## ⚙️ Step 3: Configure Environment

### 3.1 Create .env file
```bash
cp .env.example .env
nano .env  # Edit configuration
```

### 3.2 Configure for separate machine
```bash
# .env content for separate machine

# Server
WS_PORT=8080
WS_HOST=0.0.0.0
NODE_ENV=production
USE_HTTPS=true
CERT_PATH=/etc/ssl/certs/server.crt
KEY_PATH=/etc/ssl/private/server.key

# Redis (same machine)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Main site URL (for authentication)
MAIN_SITE_URL=https://staff.vapeshed.co.nz

# JWT Secret (MUST MATCH main site!)
JWT_SECRET=your-jwt-secret-key

# CORS allowed origins
CORS_ORIGINS=https://staff.vapeshed.co.nz,https://www.vapeshed.co.nz

# Logging
LOG_LEVEL=info
```

⚠️ **CRITICAL:** `JWT_SECRET` must match the JWT secret on your main CIS site!

### 3.3 Verify configuration
```bash
cat .env  # Review settings
```

---

## 🔐 Step 4: SSL/HTTPS Configuration

### 4.1 Obtain SSL certificate
```bash
# Option A: Using Let's Encrypt (free)
apt install -y certbot python3-certbot-standalone

certbot certonly \
  --standalone \
  -d ws.ecigdis.co.nz \
  --agree-tos \
  -m admin@ecigdis.co.nz

# Certificates will be in /etc/letsencrypt/live/ws.ecigdis.co.nz/

# Option B: Using existing certificate
# Copy your certificate and key to:
# /etc/ssl/certs/server.crt
# /etc/ssl/private/server.key
```

### 4.2 Update .env with certificate paths
```bash
# Update .env
CERT_PATH=/etc/letsencrypt/live/ws.ecigdis.co.nz/fullchain.pem
KEY_PATH=/etc/letsencrypt/live/ws.ecigdis.co.nz/privkey.pem
USE_HTTPS=true
```

### 4.3 Set correct permissions
```bash
chmod 644 /etc/letsencrypt/live/ws.ecigdis.co.nz/fullchain.pem
chmod 644 /etc/letsencrypt/live/ws.ecigdis.co.nz/privkey.pem
```

---

## 🚀 Step 5: Start WebSocket Server

### 5.1 Test locally
```bash
cd /opt/ecigdis-ws

# Development mode (test first)
NODE_ENV=development npm start

# You should see:
# ╔════════════════════════════════════════════════════════════════╗
# ║     🚀 Ecigdis Chat WebSocket Server Started Successfully       ║
# ║ Server URL: wss://ws.ecigdis.co.nz:8080                        ║
# ╚════════════════════════════════════════════════════════════════╝

# Press Ctrl+C to stop
```

### 5.2 Test endpoints
```bash
# In another terminal
curl -i http://localhost:8080/health
# Should return 200 OK with health status
```

### 5.3 Start with PM2 (production)
```bash
pm2 start server.js --name "ecigdis-ws" --env production

# Verify it's running
pm2 list
pm2 logs ecigdis-ws

# Enable auto-restart on reboot
pm2 startup
pm2 save
```

---

## 📊 Step 6: Monitoring & Health Checks

### 6.1 Check server status
```bash
# Check if process is running
pm2 status

# View logs
pm2 logs ecigdis-ws

# Monitor real-time
pm2 monit
```

### 6.2 HTTP health endpoints
```bash
# Health check (is server running?)
curl https://ws.ecigdis.co.nz/health

# Status (how many users connected?)
curl https://ws.ecigdis.co.nz/status

# Metrics (detailed stats)
curl https://ws.ecigdis.co.nz/metrics
```

### 6.3 Redis health
```bash
# Check Redis is running
redis-cli ping  # Should respond PONG

# Check Redis stats
redis-cli INFO stats
```

### 6.4 Setup monitoring dashboard
```bash
# Install web-based monitoring
npm install -g pm2-web

# Start monitoring dashboard
pm2-web

# Access at http://localhost:9615
```

---

## 🔗 Step 7: Configure Main Site to Use WebSocket

### 7.1 Update ChatManager.js configuration
```javascript
// In your main site code
const chatManager = new ChatManager({
  wsServer: 'wss://ws.ecigdis.co.nz:8080',  // Point to WebSocket server
  httpFallback: '/api/messenger/poll',        // Fallback to main site
  mainSiteUrl: 'https://staff.vapeshed.co.nz'
});
```

### 7.2 Update .env on main site
```bash
# On main CIS site server
WEBSOCKET_SERVER_URL=wss://ws.ecigdis.co.nz:8080
JWT_SECRET=same-secret-as-websocket-server
```

### 7.3 Test connectivity from main site
```bash
# From main site server, test WebSocket connectivity
curl https://ws.ecigdis.co.nz/health

# Should return 200 OK
```

---

## 🧪 Step 8: Testing

### 8.1 Test from browser
```javascript
// In browser console
const socket = io('wss://ws.ecigdis.co.nz:8080');

socket.on('connected', (data) => {
  console.log('Connected!', data);
});

socket.on('error', (err) => {
  console.error('Connection error:', err);
});
```

### 8.2 Test message flow
```javascript
// Authenticate
socket.emit('authenticate', { token: 'your-jwt-token' });

// Join conversation
socket.emit('conversation:join', { conversationId: 123 });

// Send message
socket.emit('message:send', {
  conversationId: 123,
  text: 'Hello from WebSocket!',
  messageId: `msg_${Date.now()}`
});

// Listen for messages
socket.on('message:new', (msg) => {
  console.log('New message:', msg);
});
```

### 8.3 Load testing
```bash
# Install load testing tool
npm install -g artillery

# Create test file: load-test.yml
cat > load-test.yml <<EOF
config:
  target: "wss://ws.ecigdis.co.nz:8080"
  phases:
    - duration: 60
      arrivalRate: 10
      name: "Ramp up"
scenarios:
  - name: "Chat messages"
    flow:
      - think: 5
      - emit:
          channel: "message:send"
          data: { text: "test message" }
EOF

# Run load test
artillery run load-test.yml
```

---

## 🔄 Step 9: Auto-Restart & Logging

### 9.1 Enable PM2 auto-restart
```bash
# Restart on crashes
pm2 install pm2-auto-restart

# Restart on file changes (development)
pm2 watch

# Restart daily at 2 AM
pm2 install pm2-cron
pm2 set 'cron-restart' '0 2 * * *'
```

### 9.2 Setup log rotation
```bash
# Install log rotation
pm2 install pm2-logrotate

# Configure rotation
pm2 set pm2-logrotate:interval 100  # Rotate every 100 lines
pm2 set pm2-logrotate:max-size 100M
pm2 set pm2-logrotate:retain 30
```

### 9.3 View logs
```bash
# Real-time logs
pm2 logs ecigdis-ws

# Last 100 lines
pm2 logs ecigdis-ws --lines 100

# Flush logs
pm2 flush
```

---

## 🚨 Step 10: Troubleshooting

### Port already in use
```bash
# Find what's using port 8080
lsof -i :8080

# Kill the process
kill -9 <PID>

# Or use different port in .env
WS_PORT=8081
```

### Can't connect from main site
```bash
# Check firewall
ufw status
ufw allow 8080/tcp

# Check if server is listening
netstat -tuln | grep 8080

# Check logs
pm2 logs ecigdis-ws
```

### Redis connection failed
```bash
# Check Redis is running
systemctl status redis-server

# Restart Redis
systemctl restart redis-server

# Test Redis
redis-cli ping  # Should respond PONG
```

### SSL certificate errors
```bash
# Verify certificate
openssl x509 -in /etc/ssl/certs/server.crt -text -noout

# Test HTTPS connection
curl -vI https://ws.ecigdis.co.nz/health
```

### Token verification failing
```bash
# Check JWT_SECRET matches main site
cat .env | grep JWT_SECRET

# Verify token format
# Token should be Bearer token from login endpoint
```

---

## 📈 Step 11: Monitoring & Maintenance

### 11.1 Setup performance monitoring
```bash
# Install monitoring tools
pm2 install pm2-monitoring
pm2 monitoring

# View dashboard
open https://app.pm2.io
```

### 11.2 Regular backups
```bash
# Backup configuration
mkdir -p /backup/ecigdis-ws
cp /opt/ecigdis-ws/.env /backup/ecigdis-ws/

# Schedule daily backup
crontab -e
# Add: 0 2 * * * cp /opt/ecigdis-ws/.env /backup/ecigdis-ws/.env.$(date +\%Y\%m\%d)
```

### 11.3 Updates
```bash
# Check for updates
cd /opt/ecigdis-ws
npm outdated

# Update dependencies
npm update

# Test after update
npm start

# Restart production
pm2 restart ecigdis-ws
```

---

## 🎯 Deployment Checklist

- [ ] Node.js 18+ installed
- [ ] Project files copied to /opt/ecigdis-ws
- [ ] npm install completed
- [ ] .env configured with correct values
- [ ] SSL certificate obtained
- [ ] HTTPS enabled in .env
- [ ] Redis running (systemctl status redis-server)
- [ ] Server started with PM2
- [ ] Health endpoint responds (/health)
- [ ] Can connect from browser
- [ ] Messages flow both directions
- [ ] PM2 configured for auto-restart
- [ ] Logs configured with rotation
- [ ] Firewall allows port 8080
- [ ] Main site ChatManager configured to use ws server
- [ ] Load testing completed
- [ ] Monitoring setup

---

## 🚀 Quick Reference

```bash
# Daily operations
pm2 list                          # Check status
pm2 logs ecigdis-ws              # View logs
pm2 restart ecigdis-ws           # Restart
pm2 stop ecigdis-ws              # Stop
pm2 start ecigdis-ws             # Start

# Updates
cd /opt/ecigdis-ws && npm update

# Health check
curl https://ws.ecigdis.co.nz/health

# Performance metrics
curl https://ws.ecigdis.co.nz/metrics
```

---

## 📞 Support

For issues:
1. Check logs: `pm2 logs ecigdis-ws`
2. Verify health: `curl https://ws.ecigdis.co.nz/health`
3. Check firewall: `ufw status`
4. Check Redis: `redis-cli ping`
5. Verify .env: `cat .env`

---

**Status:** ✅ Ready for deployment on separate machine
**Last Updated:** November 11, 2025
