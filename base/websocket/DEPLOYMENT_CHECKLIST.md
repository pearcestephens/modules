# WebSocket Server Deployment Checklist

**Use this checklist to deploy WebSocket server to a new machine**

---

## Phase 1: Server Preparation ☐

- [ ] SSH access to new server confirmed
- [ ] Server has public IP or domain name
- [ ] Server has static IP (won't change)
- [ ] OS is Linux (Ubuntu 20.04+ or CentOS 8+)
- [ ] Server has at least 512MB RAM (1GB+ recommended)
- [ ] Server has at least 1GB free disk space
- [ ] Internet connection is stable
- [ ] Firewall access configured (can open port 8080)

---

## Phase 2: System Setup ☐

- [ ] `apt update && apt upgrade -y` completed
- [ ] Node.js 18+ installed (`node --version` shows v18+)
- [ ] npm installed (`npm --version` shows 10+)
- [ ] Redis installed (`systemctl status redis-server` shows active)
- [ ] Redis responding (`redis-cli ping` returns PONG)
- [ ] PM2 installed globally (`pm2 --version` works)
- [ ] Basic firewall rules set (`ufw allow 22`, `ufw allow 8080`)

**Command to verify all:**
```bash
node --version && npm --version && redis-cli ping && pm2 --version
```

---

## Phase 3: WebSocket Server Setup ☐

- [ ] Created `/opt/ecigdis-ws` directory
- [ ] Copied all server files to `/opt/ecigdis-ws`
- [ ] `npm install` completed without errors
- [ ] `.env.example` copied to `.env`
- [ ] All required `.env` variables set:
  - [ ] `WS_PORT=8080`
  - [ ] `WS_HOST=0.0.0.0`
  - [ ] `NODE_ENV=production`
  - [ ] `MAIN_SITE_URL=https://staff.vapeshed.co.nz`
  - [ ] `JWT_SECRET=<correct-secret>`
  - [ ] `REDIS_HOST=127.0.0.1` (or remote Redis)
  - [ ] `CORS_ORIGINS=https://staff.vapeshed.co.nz`

**Verification:**
```bash
cat /opt/ecigdis-ws/.env | grep -E "^WS_|^NODE_ENV|^MAIN_SITE|^JWT_SECRET|^REDIS_|^CORS"
```

---

## Phase 4: SSL/HTTPS Setup ☐

**Option A: Let's Encrypt (Recommended)**
- [ ] `certbot` installed
- [ ] SSL certificate obtained (`certbot certonly --standalone -d ws.ecigdis.co.nz`)
- [ ] Certificate files exist:
  - [ ] `/etc/letsencrypt/live/ws.ecigdis.co.nz/fullchain.pem`
  - [ ] `/etc/letsencrypt/live/ws.ecigdis.co.nz/privkey.pem`
- [ ] `.env` updated with certificate paths:
  ```
  USE_HTTPS=true
  CERT_PATH=/etc/letsencrypt/live/ws.ecigdis.co.nz/fullchain.pem
  KEY_PATH=/etc/letsencrypt/live/ws.ecigdis.co.nz/privkey.pem
  ```
- [ ] Certificate renewal cron job set up

**Option B: Existing Certificate**
- [ ] Certificate file available
- [ ] Key file available
- [ ] `.env` points to correct paths
- [ ] File permissions correct (644)

**Verification:**
```bash
curl -I https://ws.ecigdis.co.nz/health
# Should return 200 OK
```

---

## Phase 5: Server Testing ☐

**Start Server:**
- [ ] Server started: `pm2 start /opt/ecigdis-ws/server.js --name "ecigdis-ws"`
- [ ] PM2 shows "online" status: `pm2 list` confirms running
- [ ] No errors in logs: `pm2 logs ecigdis-ws` shows normal startup

**Health Checks:**
- [ ] Health endpoint responds: `curl https://ws.ecigdis.co.nz/health` returns 200
- [ ] Status endpoint responds: `curl https://ws.ecigdis.co.nz/status`
- [ ] Metrics endpoint responds: `curl https://ws.ecigdis.co.nz/metrics`
- [ ] Server shows 0 connected clients initially

**Port Verification:**
- [ ] Server listening on port 8080: `netstat -tuln | grep 8080`
- [ ] Port accessible from outside: `telnet ws.ecigdis.co.nz 8080` (may timeout, that's ok)

---

## Phase 6: Test Connection from Browser ☐

Open browser console and run:
```javascript
const socket = io('wss://ws.ecigdis.co.nz:8080');

socket.on('connected', (data) => {
  console.log('✅ Connected!', data);
});

socket.on('error', (err) => {
  console.error('❌ Error:', err);
});

socket.on('authenticated', (data) => {
  console.log('✅ Authenticated!', data);
});

// Authenticate (replace with real token)
socket.emit('authenticate', { token: 'test-token' });
```

Expected results:
- [ ] "connected" event received within 2 seconds
- [ ] No console errors
- [ ] "authenticated" event received (or auth error if test token invalid)

---

## Phase 7: Integration with Main Site ☐

**Update Main Site ChatManager.js:**
```javascript
const chatManager = new ChatManager({
  wsServer: 'wss://ws.ecigdis.co.nz:8080',
  httpFallback: '/api/messenger/poll',
  mainSiteUrl: 'https://staff.vapeshed.co.nz'
});
```

**Update Main Site .env:**
```bash
WEBSOCKET_SERVER_URL=wss://ws.ecigdis.co.nz:8080
JWT_SECRET=your-jwt-secret-key  # MUST MATCH WebSocket server!
```

**Verification:**
- [ ] JWT_SECRET on main site matches WebSocket server
- [ ] CORS_ORIGINS on WebSocket server includes main site domain
- [ ] Main site can reach WebSocket health endpoint
- [ ] Browser can connect to WebSocket server

---

## Phase 8: Production Hardening ☐

**PM2 Configuration:**
- [ ] PM2 ecosystem file created (optional)
- [ ] PM2 startup enabled: `pm2 startup`
- [ ] PM2 saved: `pm2 save`
- [ ] Server auto-restarts on reboot

**Logging & Monitoring:**
- [ ] PM2 log rotation installed: `pm2 install pm2-logrotate`
- [ ] Log rotation configured:
  ```bash
  pm2 set pm2-logrotate:interval 100
  pm2 set pm2-logrotate:max-size 100M
  pm2 set pm2-logrotate:retain 30
  ```
- [ ] Monitoring dashboard configured (optional): `pm2-web`

**Security:**
- [ ] JWT_SECRET is strong (32+ characters)
- [ ] .env file permissions set: `chmod 600 /opt/ecigdis-ws/.env`
- [ ] .env file NOT in git repository
- [ ] No sensitive data in logs
- [ ] HTTPS/WSS enabled

**Firewall:**
- [ ] UFW enabled: `ufw enable`
- [ ] SSH (22) allowed: `ufw allow 22/tcp`
- [ ] WebSocket port (8080) allowed: `ufw allow 8080/tcp`
- [ ] Only necessary ports open
- [ ] Firewall status: `ufw status` shows active

---

## Phase 9: Performance Verification ☐

**Single User Test:**
- [ ] Connect 1 user successfully
- [ ] Send message works
- [ ] Message delivery < 100ms
- [ ] Typing indicator works
- [ ] Read receipt works
- [ ] Disconnect clean

**Multi-User Test:**
- [ ] Connect 5+ users simultaneously
- [ ] Messages broadcast correctly
- [ ] No connection timeouts
- [ ] Memory usage reasonable (< 200MB)
- [ ] CPU usage low (< 30%)

**Load Test:**
- [ ] Run load test: `artillery run load-test.yml`
- [ ] Server handles 100 concurrent connections
- [ ] Messages still deliver in < 500ms
- [ ] No errors in logs

---

## Phase 10: Monitoring Setup ☐

**Health Checks:**
- [ ] Cron job checks health every 5 minutes:
  ```bash
  */5 * * * * curl -s https://ws.ecigdis.co.nz/health | grep ok > /dev/null || echo "WebSocket health check failed" | mail -s "Alert: WebSocket Down" admin@ecigdis.co.nz
  ```

**PM2 Monitoring:**
- [ ] PM2 dashboard configured
- [ ] PM2 notifications enabled (optional)
- [ ] PM2 restart policy set:
  ```bash
  pm2 set "max-memory-restart" 200M
  ```

**Logs:**
- [ ] Logs monitored for errors
- [ ] Log location known: `/home/master/.pm2/logs/`
- [ ] Log archival strategy defined

**Uptime Monitoring:**
- [ ] Pingdom/Uptime monitoring configured
- [ ] Alert emails set up
- [ ] Escalation path defined

---

## Phase 11: Backup & Recovery ☐

**Configuration Backup:**
- [ ] `.env` file backed up securely
- [ ] Server configuration backed up
- [ ] Backup location: `/backup/ecigdis-ws/`
- [ ] Backup automated daily
- [ ] Recovery procedure documented

**Database:**
- [ ] MariaDB still has database (no changes needed)
- [ ] Database backups still running
- [ ] Test database restore procedure

---

## Phase 12: Documentation ☐

**Deployment Record:**
- [ ] Server IP/domain documented: `ws.ecigdis.co.nz`
- [ ] Server specs documented (RAM, CPU, etc.)
- [ ] Deployment date recorded: `Nov 11, 2025`
- [ ] Deployed by: `<your-name>`
- [ ] Configuration saved

**Runbooks:**
- [ ] Restart procedure documented
- [ ] Troubleshooting guide reviewed
- [ ] Emergency contacts listed
- [ ] Escalation path defined

---

## Phase 13: Cutover & Monitoring ☐

**Announcement:**
- [ ] Team notified of WebSocket server deployment
- [ ] Users informed of improved messaging
- [ ] Support team briefed on new system

**Initial Monitoring:**
- [ ] Monitor logs for first 24 hours
- [ ] Watch error rates
- [ ] Monitor connection counts
- [ ] Check message delivery rates

**First Week:**
- [ ] No critical errors
- [ ] Performance stable
- [ ] All features working
- [ ] Users reporting improved messaging

---

## Final Sign-Off ☐

**Production Ready Confirmation:**
- [ ] All checklist items complete
- [ ] Server healthy and stable
- [ ] Integration with main site successful
- [ ] Team trained on operation
- [ ] Monitoring active
- [ ] Runbooks in place
- [ ] Documentation complete

**Approval Sign-Off:**
- [ ] Deployment approved by: _________________
- [ ] Date approved: _________________
- [ ] Deploy by: _________________

---

## Post-Deployment

### First 24 Hours
- Monitor logs closely
- Watch for connection issues
- Verify message delivery
- Check performance metrics

### First Week
- Observe stability
- Gather user feedback
- Monitor resource usage
- Fine-tune configuration if needed

### First Month
- Full performance analysis
- Load testing review
- Capacity planning
- Security audit

---

## Quick Reference Commands

```bash
# Start/Stop/Status
pm2 start /opt/ecigdis-ws/server.js --name "ecigdis-ws"
pm2 stop ecigdis-ws
pm2 restart ecigdis-ws
pm2 list

# Logs
pm2 logs ecigdis-ws
pm2 logs ecigdis-ws --lines 100
pm2 logs ecigdis-ws --err

# Health Checks
curl https://ws.ecigdis.co.nz/health
curl https://ws.ecigdis.co.nz/status
curl https://ws.ecigdis.co.nz/metrics

# Configuration
cat /opt/ecigdis-ws/.env
pm2 show ecigdis-ws

# Updates
cd /opt/ecigdis-ws && npm update
pm2 restart ecigdis-ws
```

---

**Status:** Ready for deployment
**Last Updated:** November 11, 2025
