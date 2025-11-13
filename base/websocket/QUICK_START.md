# WebSocket Server - Quick Start Guide

**🚀 Get WebSocket Server Running in 10 Minutes**

---

## For Development (Local Testing)

### 1. Install dependencies
```bash
cd /path/to/modules/base/websocket
npm install
```

### 2. Start server
```bash
npm run dev
```

**Expected output:**
```
╔════════════════════════════════════════════════════════════════╗
║     🚀 Ecigdis Chat WebSocket Server Started Successfully       ║
║ Server URL: ws://localhost:8080                                 ║
╚════════════════════════════════════════════════════════════════╝
```

### 3. Test connection
```bash
# In another terminal
node test-connection.js
```

---

## For Production (Separate Machine)

### 1. SSH into new server
```bash
ssh root@NEW_SERVER_IP
```

### 2. Prepare server (one-time setup)
```bash
apt update && apt upgrade -y
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
apt install -y nodejs redis-server
npm install -g pm2
```

### 3. Deploy files
```bash
# Copy from development machine
scp -r /path/to/modules/base/websocket/* root@NEW_SERVER:/opt/ecigdis-ws/

# Or clone from repo
cd /opt/ecigdis-ws
git clone https://github.com/pearcestephens/ecigdis-websocket.git .
```

### 4. Install dependencies
```bash
cd /opt/ecigdis-ws
npm install
```

### 5. Configure .env
```bash
cp .env.example .env
nano .env

# Key settings to change:
# WS_HOST=0.0.0.0
# NODE_ENV=production
# USE_HTTPS=true
# MAIN_SITE_URL=https://staff.vapeshed.co.nz
# JWT_SECRET=your-jwt-secret-key
```

### 6. Setup SSL (HTTPS)
```bash
# Option A: Let's Encrypt
apt install -y certbot
certbot certonly --standalone -d ws.ecigdis.co.nz

# Then update .env with certificate paths:
# CERT_PATH=/etc/letsencrypt/live/ws.ecigdis.co.nz/fullchain.pem
# KEY_PATH=/etc/letsencrypt/live/ws.ecigdis.co.nz/privkey.pem
```

### 7. Start with PM2
```bash
pm2 start server.js --name "ecigdis-ws"
pm2 startup
pm2 save
```

### 8. Verify it's running
```bash
# Check status
pm2 status

# View logs
pm2 logs ecigdis-ws

# Test health endpoint
curl https://ws.ecigdis.co.nz/health
```

---

## 🔗 Connect Main Site to WebSocket Server

### Update main site ChatManager.js
```javascript
const chatManager = new ChatManager({
  wsServer: 'wss://ws.ecigdis.co.nz:8080',  // Your WebSocket server
  httpFallback: '/api/messenger/poll',
  mainSiteUrl: 'https://staff.vapeshed.co.nz'
});
```

---

## 📊 Monitor Server

```bash
# Check if running
pm2 list

# View logs
pm2 logs ecigdis-ws

# View metrics
pm2 monit

# Monitor dashboard
pm2-web  # Access at http://localhost:9615
```

---

## 🧪 Test It Works

### From Browser Console
```javascript
const socket = io('wss://ws.ecigdis.co.nz:8080');

socket.on('connected', () => console.log('Connected!'));
socket.on('error', (err) => console.error('Error:', err));

// Authenticate
socket.emit('authenticate', { token: 'your-jwt-token' });

// Send test message
socket.emit('message:send', {
  conversationId: 1,
  text: 'Hello WebSocket!',
  messageId: 'test_' + Date.now()
});
```

---

## ⚡ Common Commands

```bash
# Start server (development)
npm run dev

# Start server (production)
npm run prod

# Start with PM2
pm2 start server.js

# Stop server
pm2 stop ecigdis-ws

# Restart server
pm2 restart ecigdis-ws

# View logs
pm2 logs ecigdis-ws

# Test endpoints
curl http://localhost:8080/health
curl http://localhost:8080/status
curl http://localhost:8080/metrics
```

---

## ✅ Success Indicators

- ✅ Server starts without errors
- ✅ Health endpoint returns 200 OK
- ✅ Can connect from browser
- ✅ Authenticated messages work
- ✅ Messages broadcast to others
- ✅ PM2 shows "online" status

---

## ❌ Troubleshooting

### Server won't start
```bash
# Check logs
pm2 logs ecigdis-ws

# Check if port is in use
lsof -i :8080

# Try different port
WS_PORT=8081 npm start
```

### Can't connect from main site
```bash
# Check firewall
ufw status
ufw allow 8080/tcp

# Test connectivity
curl http://NEW_SERVER_IP:8080/health

# Check JWT_SECRET matches
# (main site .env must have same JWT_SECRET as ws .env)
```

### Token verification fails
```bash
# Check JWT_SECRET in both places
# Main site: /config/.env
# WS server: /opt/ecigdis-ws/.env

# Must be EXACTLY the same
```

---

## 📖 Full Documentation

See `DEPLOYMENT_GUIDE.md` for complete step-by-step setup.

---

**Status:** ✅ Ready to deploy
**Questions?** Check DEPLOYMENT_GUIDE.md or server logs (`pm2 logs`)
