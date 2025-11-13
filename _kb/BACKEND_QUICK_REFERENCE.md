# Backend Implementation Quick Reference

**Last Updated:** November 11, 2025
**Status:** ✅ Production-Ready

---

## 📂 File Structure

```
modules/base/
├── lib/
│   ├── NotificationEngine.php        (400 lines) - Core engines
│   └── WebSocketEventHandler.php     (300 lines) - Real-time events
├── api/
│   ├── notifications.php             (200 lines) - Notification API
│   └── messenger.php                 (300 lines) - Messenger API
├── sql/
│   └── notification_messenger_schema.sql  (400 lines) - DB schema
└── BACKEND_IMPLEMENTATION_GUIDE.md   (500 lines) - Full documentation
```

---

## 🚀 Deploy in 5 Minutes

### 1. Database Setup
```bash
mysql -u root -p database_name < modules/base/sql/notification_messenger_schema.sql
```

### 2. Bootstrap Configuration
```php
// In config/bootstrap.php
require_once __DIR__ . '/../modules/base/lib/NotificationEngine.php';
require_once __DIR__ . '/../modules/base/lib/WebSocketEventHandler.php';
```

### 3. Router Configuration
```php
// In your router/index.php
$app->route('/api/notifications', 'modules/base/api/notifications.php');
$app->route('/api/messenger', 'modules/base/api/messenger.php');
```

### 4. Test Connection
```bash
curl -H "Authorization: Bearer TOKEN" http://localhost/api/notifications/unread
```

---

## 🔥 Most Used Code Snippets

### Trigger Notification
```php
$engine = new \CIS\Notifications\NotificationEngine();
$notifId = $engine->trigger('message', 'new_message', [
    'user_id' => 456,
    'triggered_by_user_id' => 123,
    'title' => 'New Message',
    'message' => 'You got a new message',
    'priority' => 'high',
    'action_url' => '/messenger/123',
]);
```

### Send Message
```php
$messenger = new \CIS\Notifications\MessengerEngine();
$msgId = $messenger->sendMessage([
    'conversation_id' => 123,
    'sender_user_id' => 456,
    'message_text' => 'Hello!',
    'mentions' => [789],
]);
```

### Get User Preferences
```php
$engine = new \CIS\Notifications\NotificationEngine();
$prefs = $engine->getUserPreferences($userId);
// $prefs['email_enabled'], $prefs['dnd_enabled'], etc.
```

### Update Typing Status
```php
$messenger->updateTypingIndicator($conversationId, $userId, true);
// true = typing, false = stopped typing
```

### Add Reaction
```php
$messenger->addReaction($messageId, $userId, '👍', true);
// true = add, false = remove
```

### Get Unread Count
```php
$counts = $engine->getUnreadCount($userId);
// ['total' => 5, 'message' => 2, 'news' => 1, 'issue' => 2]
```

---

## 📡 API Endpoints Cheat Sheet

### Notifications

```
GET  /api/notifications
GET  /api/notifications/unread
GET  /api/notifications/preferences
POST /api/notifications/:id/read
POST /api/notifications/preferences
POST /api/notifications/trigger (admin only)
```

**Examples:**
```bash
# Get unread count
curl http://localhost/api/notifications/unread -H "Authorization: Bearer TOKEN"

# Mark as read
curl -X POST http://localhost/api/notifications/123/read \
  -H "Authorization: Bearer TOKEN"

# Save preferences
curl -X POST http://localhost/api/notifications/preferences \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"email_enabled": true, "push_enabled": false}'
```

### Messenger

```
GET  /api/messenger/conversations
POST /api/messenger/conversations
GET  /api/messenger/conversations/:id
POST /api/messenger/conversations/:id/messages
POST /api/messenger/messages/:id/read
POST /api/messenger/messages/:id/react
POST /api/messenger/conversations/:id/typing
GET  /api/messenger/messages/search
```

**Examples:**
```bash
# List conversations
curl http://localhost/api/messenger/conversations \
  -H "Authorization: Bearer TOKEN"

# Create conversation
curl -X POST http://localhost/api/messenger/conversations \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "group",
    "name": "Team",
    "participant_ids": [2, 3, 4]
  }'

# Send message
curl -X POST http://localhost/api/messenger/conversations/123/messages \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"message_text": "Hello!"}'

# Add reaction
curl -X POST http://localhost/api/messenger/messages/456/react \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"emoji": "👍", "add": true}'

# Search messages
curl "http://localhost/api/messenger/messages/search?q=meeting&limit=50" \
  -H "Authorization: Bearer TOKEN"
```

---

## 🔌 WebSocket Events Reference

### Send From Client
```json
{
  "event": "message:new",
  "payload": {
    "conversation_id": 123,
    "message_text": "Hello!",
    "mentions": [456, 789],
    "reply_to_message_id": null
  }
}
```

### Receive From Server
```json
{
  "event": "message:new",
  "data": {
    "message_id": 999,
    "conversation_id": 123,
    "sender_user_id": 456,
    "text": "Hello!",
    "created_at": "2025-11-11T10:30:00Z"
  }
}
```

### All Event Types
```
Messaging:
  - message:new           (send new message)
  - message:edited        (edit message)
  - message:deleted       (delete message)
  - typing:start          (user starts typing)
  - typing:stop           (user stops typing)
  - reaction:added        (emoji reaction)
  - reaction:removed      (remove reaction)

Presence:
  - user:online           (user came online)
  - user:offline          (user went offline)
  - notification:new      (new notification)

Group:
  - conversation:created  (new group created)
  - member:joined         (member joined)
  - member:left           (member left)
```

---

## 🗄️ Database Tables at a Glance

| Table | Purpose | Key Columns |
|-------|---------|------------|
| `notifications` | All sent notifications | `user_id`, `category`, `priority`, `is_read` |
| `notification_preferences` | User settings | `user_id`, `email_enabled`, `dnd_enabled` |
| `notification_delivery_queue` | Async delivery | `notification_id`, `channel`, `status` |
| `chat_conversations` | Chat metadata | `type`, `created_by_user_id`, `member_count` |
| `chat_messages` | Individual messages | `conversation_id`, `sender_user_id`, `message_text` |
| `chat_message_read_receipts` | Read tracking | `message_id`, `user_id`, `read_at` |
| `chat_group_members` | Group membership | `conversation_id`, `user_id`, `is_admin` |
| `chat_typing_indicators` | Typing status | `conversation_id`, `user_id`, `is_typing` |
| `chat_blocked_users` | Blocked users | `user_id`, `blocked_user_id` |
| `notification_messenger_links` | Integration | `notification_id`, `conversation_id` |

---

## 🔐 Security Checklist

- ✅ All API endpoints require Bearer token or session
- ✅ All SQL queries use prepared statements
- ✅ Users can only access their own data
- ✅ Access control verified on conversations
- ✅ Soft deletion maintains audit trail
- ✅ Rate limiting recommended (100/hour notifications, 50/hour messages)

**To Implement Rate Limiting:**
```php
// In API endpoints
$userKey = "api_user_{$userId}";
$count = $cache->increment($userKey);
if ($count > 100) {
    Response::error('Rate limit exceeded', 429);
}
$cache->expire($userKey, 3600); // 1 hour TTL
```

---

## 🧪 Quick Test Suite

### Test Notification
```bash
curl -X POST http://localhost/api/notifications/trigger \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "category": "test",
    "event": "system_test",
    "target_user_id": 1,
    "title": "Test Notification",
    "message": "This is a test",
    "priority": "normal"
  }'
```

### Test Messenger
```bash
# 1. Create conversation
CONV=$(curl -X POST http://localhost/api/messenger/conversations \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"type":"group","name":"Test","participant_ids":[2,3]}' | jq -r '.conversation_id')

# 2. Send message
curl -X POST http://localhost/api/messenger/conversations/$CONV/messages \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"message_text":"Hello test!"}'

# 3. Get messages
curl http://localhost/api/messenger/conversations/$CONV \
  -H "Authorization: Bearer TOKEN" | jq '.messages'
```

---

## 🎯 Integration Checklist

- [ ] Database schema created (10 tables)
- [ ] NotificationEngine.php included in bootstrap
- [ ] WebSocketEventHandler.php included in bootstrap
- [ ] API routes configured
- [ ] Authentication system verified
- [ ] Tested notification trigger
- [ ] Tested messenger send
- [ ] Tested API endpoints with curl
- [ ] Connected to frontend (ChatManager.js + APIClient.php)
- [ ] WebSocket server running (if real-time needed)
- [ ] Monitoring/logging configured

---

## 💡 Common Issues & Solutions

**Notifications not appearing?**
→ Check user preferences: `$engine->getUserPreferences($userId)`

**Messages 404?**
→ Verify user is member: `SELECT * FROM chat_group_members WHERE conversation_id=X AND user_id=Y`

**WebSocket not real-time?**
→ Check WebSocket server is running on port 8080

**API returns 401?**
→ Verify Authorization header and JWT token are valid

**Database errors?**
→ Run schema again, check foreign key constraints

---

## 📚 Documentation Structure

1. **NOTIFICATION_MESSENGER_SYSTEM.md** (60+ KB)
   - Complete architecture and design

2. **BACKEND_IMPLEMENTATION_GUIDE.md** (500 lines)
   - Full PHP implementation guide

3. **BACKEND_QUICK_REFERENCE.md** (this file)
   - Developer cheat sheet

4. **Code Comments**
   - Inline documentation in PHP files

---

## 🚀 Your Next Move

1. **Run schema:** Create database tables
2. **Test API:** Use curl examples above
3. **Integrate frontend:** Connect ChatManager.js to `/api/messenger`
4. **Enable WebSocket:** Set up real-time server
5. **Go live:** Monitor and scale

---

**Everything is production-ready. You can start using it immediately!** 🎉
