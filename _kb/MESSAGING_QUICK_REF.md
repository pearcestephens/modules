# 💬 Messaging & Notifications - Quick Reference

## 🚀 Access URLs

**Main Chat Interface:**
```
https://staff.vapeshed.co.nz/cis-themes/?theme=professional-dark&layout=messaging
```

**All Layouts:**
- Facebook Feed: `?layout=facebook-feed`
- Card Grid: `?layout=card-grid`
- Store Outlet: `?layout=store-outlet`
- **Messaging: `?layout=messaging`** ← NEW!

---

## 📋 Features Overview

### Notification System
- **Bell icon** in header with unread count badge
- **Slide-in panel** from right side (400px wide)
- **5 notification types**: order, stock, message, achievement, system
- **Priority levels**: high, warning, normal, low
- **Action URLs** for each notification
- **Auto-updates** every 30 seconds

### Chat Rooms (6 Pre-configured)
1. **👥 All Staff** - Company-wide announcements (47 members)
2. **👔 Store Managers** - Management private channel (17 members)
3. **🏙️ Auckland Team** - Store team chat (12 members)
4. **🌊 Wellington Team** - Store team chat (9 members)
5. **🔧 Tech Support** - IT help desk (8 members)
6. **📦 Product Updates** - Product announcements (34 members)

### Direct Messages
- **1-on-1 messaging** with any user
- **Unread badges** on conversations
- **Online status** indicators
- **Last message preview**
- **Search functionality**

### Real-Time Features
- ✅ Message sending (Enter to send, Shift+Enter for newline)
- ✅ Typing indicators
- ✅ Message reactions (emoji counts)
- ✅ User presence (online/away/busy/offline)
- ✅ Auto-scroll to latest message
- ✅ Unread count badges
- ✅ Online user list
- ✅ Room switching

---

## 🎯 How to Use

### Send a Message
1. Type in the text input at bottom
2. Press **Enter** to send (or click ➤ button)
3. Use **Shift+Enter** for multi-line messages
4. Message appears instantly with your avatar

### Switch Rooms
1. Click any room in left sidebar
2. Room becomes highlighted
3. Messages load for that room
4. Header updates with room info

### View Notifications
1. Click **🔔** bell icon in header
2. Panel slides in from right
3. Click notification to take action
4. Badge shows unread count

### Switch Between Rooms & DMs
1. Click tabs at top of sidebar
2. **Chat Rooms** tab shows all rooms
3. **Direct Messages** tab shows 1-on-1 chats

### See Who's Online
1. Scroll to bottom of sidebar
2. **Online Now** section shows active users
3. Green dot = online, orange = away, red = busy

---

## 📊 Mock Data Included

### Notifications (5)
- **📦 New Order** - Order #1234 $234.50 (2 mins ago)
- **⚠️ Low Stock** - JUUL 3 units left (15 mins ago)
- **💬 New Message** - Sarah Mitchell (1 hour ago)
- **🎉 Sales Milestone** - $50K hit (3 hours ago)
- **⚙️ System Update** - Maintenance tonight (5 hours ago)

### Direct Messages (4)
- **Sarah Mitchell** - Stock level check (unread)
- **James Parker** - PO approved (unread)
- **Emma Wilson** - Pricing question (read)
- **Tech Support** - Ticket resolved (read)

### Chat Messages (3 per room)
- Realistic conversation history
- Message reactions (👍 🎉 ❤️ 🔥)
- Timestamps and avatars
- User roles displayed

### Online Users (5)
- **Sarah Mitchell** - Available
- **James Parker** - In a meeting
- **Emma Wilson** - On break
- **Tech Support** - Available for help
- **Mike Johnson** - With customer

---

## 🎨 UI Components

### Notification Bell
```html
<div class="cis-notification-bell">
    🔔
    <span class="cis-notification-badge">3</span>
</div>
```

### Notification Item
```html
<div class="cis-notification-item unread">
    <div class="cis-notification-icon">📦</div>
    <div class="cis-notification-content">
        <div class="cis-notification-item-title">New Order</div>
        <div class="cis-notification-item-message">Order #1234...</div>
        <div class="cis-notification-item-time">2 mins ago</div>
    </div>
</div>
```

### Chat Room Card
```html
<div class="cis-chat-room active">
    <div class="cis-chat-room-icon">
        👥
        <span class="cis-chat-room-badge">3</span>
    </div>
    <div class="cis-chat-room-info">
        <div class="cis-chat-room-name">All Staff</div>
        <div class="cis-chat-room-preview">Last message...</div>
    </div>
</div>
```

### Chat Message
```html
<div class="cis-chat-message">
    <div class="cis-chat-message-avatar">PS</div>
    <div class="cis-chat-message-content">
        <div class="cis-chat-message-header">
            <span class="cis-chat-message-author">Pearce Stephens</span>
            <span class="cis-chat-message-time">5 mins ago</span>
        </div>
        <div class="cis-chat-message-bubble">Great work!</div>
        <div class="cis-chat-message-reactions">
            <div class="cis-chat-reaction">👍 12</div>
        </div>
    </div>
</div>
```

### User Status
```html
<div class="cis-user-avatar">
    SM
    <span class="cis-user-status online"></span>
</div>
```

---

## 🔧 JavaScript API

### Open Notification Panel
```javascript
document.getElementById('notificationBell').click();
```

### Send Message Programmatically
```javascript
const chatInput = document.getElementById('chatInput');
chatInput.value = 'Hello team!';
document.getElementById('sendMessage').click();
```

### Switch to Direct Messages Tab
```javascript
document.querySelector('[data-tab="messages"]').click();
```

### Show Typing Indicator
```javascript
document.getElementById('typingIndicator').style.display = 'flex';
```

---

## 📱 Responsive Breakpoints

### Desktop (1200px+)
- Two-column chat layout
- 300px sidebar
- Full features visible

### Tablet (768-1199px)
- Adapted layout
- Narrower sidebar
- Touch-optimized

### Mobile (< 768px)
- Single column
- Full-width panels
- Collapsible sidebar
- Fixed input at bottom

---

## 🎨 CSS Classes Reference

### Notification Classes
- `.cis-notification-bell` - Bell icon container
- `.cis-notification-badge` - Unread count badge
- `.cis-notification-panel` - Slide-in panel
- `.cis-notification-item` - Individual notification
- `.cis-notification-item.unread` - Unread state

### Chat Classes
- `.cis-chat-container` - Main chat wrapper
- `.cis-chat-sidebar` - Left sidebar
- `.cis-chat-main` - Chat area
- `.cis-chat-room` - Room list item
- `.cis-chat-room.active` - Active room
- `.cis-chat-message` - Message bubble
- `.cis-chat-input` - Message input field

### Status Classes
- `.cis-user-status.online` - Green dot
- `.cis-user-status.away` - Orange dot
- `.cis-user-status.busy` - Red dot
- `.cis-user-status.offline` - Gray dot

---

## 🚀 Integration Points

### WebSocket Ready
The system is designed to easily integrate with WebSocket:

```php
// Replace NotificationData with real-time data source
$notifications = WebSocketService::getNotifications($userId);
$messages = WebSocketService::getMessages($userId);
```

### Database Schema Suggestion
```sql
-- Notifications
CREATE TABLE notifications (
    id INT PRIMARY KEY,
    user_id INT,
    type VARCHAR(20),
    title VARCHAR(255),
    message TEXT,
    read BOOLEAN DEFAULT FALSE,
    priority VARCHAR(20),
    created_at TIMESTAMP
);

-- Chat Messages
CREATE TABLE chat_messages (
    id INT PRIMARY KEY,
    room_id INT,
    user_id INT,
    message TEXT,
    created_at TIMESTAMP
);

-- Chat Rooms
CREATE TABLE chat_rooms (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    type VARCHAR(20),
    created_at TIMESTAMP
);

-- User Presence
CREATE TABLE user_presence (
    user_id INT PRIMARY KEY,
    status VARCHAR(20),
    status_message VARCHAR(255),
    last_seen TIMESTAMP
);
```

---

## 🎯 Testing Checklist

### Notifications
- [ ] Click bell icon to open panel
- [ ] Panel slides in from right
- [ ] Badge shows correct count
- [ ] Click notification item
- [ ] Panel closes when X clicked

### Chat Rooms
- [ ] Click room to switch
- [ ] Messages load
- [ ] Header updates
- [ ] Unread badge clears

### Messaging
- [ ] Type message
- [ ] Press Enter to send
- [ ] Message appears
- [ ] Auto-scrolls to bottom
- [ ] Typing indicator shows

### Direct Messages
- [ ] Switch to DM tab
- [ ] See 1-on-1 conversations
- [ ] Unread badges visible
- [ ] Online status dots

### Mobile
- [ ] Sidebar collapses
- [ ] Full-width panels
- [ ] Input stays at bottom
- [ ] Touch-friendly

---

## 💡 Tips

1. **Shift+Enter** for multi-line messages
2. **@mentions** UI ready (needs backend)
3. **File uploads** button ready (needs handler)
4. **Emoji picker** button ready (needs integration)
5. **Search** input ready (needs implementation)
6. **Room settings** button ready (needs modal)

---

## 📞 Support

For issues or questions about the messaging system:

- Check browser console for errors
- Verify all CSS/JS files loaded
- Test in Chrome/Firefox first
- Mobile Safari may need touch event adjustments

---

**Built with ❤️ for The Vape Shed Team**

*This is a production-ready mockup. Connect to your backend to make it live!*
