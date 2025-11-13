# CIS Notification & Messaging System
## Complete Architecture & Implementation Guide

**Date:** November 11, 2025
**Version:** 1.0
**Status:** Ready for Implementation
**Scope:** Unified notification + messaging + group chat system

---

## 🎯 EXECUTIVE OVERVIEW

Building a **Facebook-like unified communication platform** that integrates:
- ✅ **Notification System** - News, alerts, important issues (separate from messages)
- ✅ **Direct Messaging** - 1-on-1 conversations between staff
- ✅ **Group Chat Rooms** - Department/team conversations
- ✅ **Real-time Updates** - WebSocket streaming
- ✅ **Live Feed Integration** - News/announcements flow
- ✅ **AI Assistant Chat** - Separate from user messaging
- ✅ **Unified Interface** - All in one cohesive system

---

## 📊 SYSTEM ARCHITECTURE

```
┌────────────────────────────────────────────────────────────┐
│                   UNIFIED COMMUNICATION HUB                │
│              (Single Dashboard - Facebook Style)           │
├────────────────────────────────────────────────────────────┤
│                                                            │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐   │
│  │ Notifications│  │   Messaging  │  │ Group Chats  │   │
│  │  (News Tab)  │  │  (DM Tab)    │  │  (Rooms Tab) │   │
│  └──────────────┘  └──────────────┘  └──────────────┘   │
│  ┌──────────────┐                                        │
│  │ AI Assistant │                                        │
│  │  (Bot Tab)   │                                        │
│  └──────────────┘                                        │
│                                                            │
│  REAL-TIME UPDATES (WebSocket)                           │
│  ├─ Live notifications                                   │
│  ├─ Message delivery confirmations                       │
│  ├─ Typing indicators                                    │
│  ├─ User online status                                   │
│  └─ Group chat updates                                   │
│                                                            │
└────────────────────────────────────────────────────────────┘
         │
         │ Backend Services
         ▼
┌────────────────────────────────────────────────────────────┐
│                   BACKEND SERVICES                         │
├────────────────────────────────────────────────────────────┤
│                                                            │
│  ┌─────────────────────────────────────────────────────┐ │
│  │ Notification Service                              │ │
│  │ ├─ News aggregation                               │ │
│  │ ├─ Alert system (issues, emergencies)             │ │
│  │ ├─ Push notifications                             │ │
│  │ └─ Notification preferences management            │ │
│  └─────────────────────────────────────────────────────┘ │
│                                                            │
│  ┌─────────────────────────────────────────────────────┐ │
│  │ Messaging Service (Direct Messages)               │ │
│  │ ├─ 1-on-1 conversation management                 │ │
│  │ ├─ Message encryption                             │ │
│  │ ├─ Read receipts & delivery status                │ │
│  │ ├─ Message search & history                       │ │
│  │ └─ Attachment support                             │ │
│  └─────────────────────────────────────────────────────┘ │
│                                                            │
│  ┌─────────────────────────────────────────────────────┐ │
│  │ Group Chat Service (Chat Rooms)                    │ │
│  │ ├─ Room creation & management                      │ │
│  │ ├─ Member management (add/remove)                  │ │
│  │ ├─ Room roles (admin, moderator, member)          │ │
│  │ ├─ Thread-based conversations                      │ │
│  │ ├─ File sharing & media                            │ │
│  │ └─ Room analytics (engagement, activity)          │ │
│  └─────────────────────────────────────────────────────┘ │
│                                                            │
│  ┌─────────────────────────────────────────────────────┐ │
│  │ Real-time Communication Layer                      │ │
│  │ ├─ WebSocket server (Socket.io compatible)         │ │
│  │ ├─ Presence tracking (online/offline)              │ │
│  │ ├─ Typing indicators                               │ │
│  │ ├─ Message acknowledgments                         │ │
│  │ └─ Live activity feeds                             │ │
│  └─────────────────────────────────────────────────────┘ │
│                                                            │
└────────────────────────────────────────────────────────────┘
         │
         │ Data Persistence
         ▼
┌────────────────────────────────────────────────────────────┐
│                   DATABASE SCHEMA                          │
├────────────────────────────────────────────────────────────┤
│                                                            │
│  NOTIFICATIONS (News & Alerts)                           │
│  ├─ notifications (id, user_id, type, title, content)    │
│  ├─ notification_preferences (user_id, settings)         │
│  ├─ news_items (id, title, content, source)              │
│  └─ system_alerts (id, type, level, message)             │
│                                                            │
│  DIRECT MESSAGING                                        │
│  ├─ direct_messages (id, sender_id, recipient_id)        │
│  ├─ message_threads (id, user1_id, user2_id)             │
│  ├─ message_attachments (message_id, file_path)          │
│  └─ message_read_receipts (message_id, read_at)          │
│                                                            │
│  GROUP CHAT ROOMS                                        │
│  ├─ chat_rooms (id, name, description, created_by)       │
│  ├─ room_members (room_id, user_id, role, joined_at)     │
│  ├─ room_messages (id, room_id, user_id, message)        │
│  ├─ room_threads (message_id, parent_id)                 │
│  ├─ room_files (room_id, file_id, filename)              │
│  └─ room_activity (room_id, action, user_id)             │
│                                                            │
│  PRESENCE & STATUS                                       │
│  ├─ user_presence (user_id, status, last_active)         │
│  ├─ user_typing (user_id, chat_id, typing_at)            │
│  └─ user_online_history (user_id, login, logout)         │
│                                                            │
└────────────────────────────────────────────────────────────┘
```

---

## 1️⃣ NOTIFICATION SYSTEM

### 1.1 Types of Notifications

```
NOTIFICATION TYPES:
│
├─ NEWS & UPDATES (Low Priority - Informational)
│  ├─ Company announcements
│  ├─ Policy updates
│  ├─ Event notifications
│  ├─ Team milestones
│  └─ General information
│
├─ SYSTEM ALERTS (Medium Priority - Actionable)
│  ├─ Shift reminders
│  ├─ Schedule changes
│  ├─ Task assignments
│  ├─ Performance insights
│  └─ Required actions
│
├─ IMPORTANT ISSUES (High Priority - Urgent)
│  ├─ Payroll problems
│  ├─ Security alerts
│  ├─ System errors
│  ├─ Compliance issues
│  ├─ Customer complaints
│  └─ Critical alerts
│
└─ URGENT (Critical Priority - Emergency)
   ├─ Store emergency (robbery, injury)
   ├─ System down
   ├─ Security breach
   ├─ Immediate action required
   └─ CEO/Management alerts
```

### 1.2 Notification Preferences

Users can customize:
- Notification channels (in-app, email, SMS, push)
- Notification frequency (real-time, digest, off)
- Notification categories (which types to receive)
- Quiet hours (no notifications between X and Y)
- Priority levels (high, medium, low, off)

### 1.3 Notification Delivery System

```php
// Notification Delivery Flow
User Action Triggers Notification Event
  │
  ├─ Check user notification preferences
  │
  ├─ Determine notification priority
  │
  ├─ Choose delivery channels:
  │  ├─ In-app notification (always)
  │  ├─ Push notification (if enabled)
  │  ├─ Email digest (if enabled)
  │  ├─ SMS alert (if high priority & enabled)
  │  └─ Slack/Teams (if enabled)
  │
  ├─ Store in database (notification_log)
  │
  ├─ Send via WebSocket (real-time)
  │
  └─ Track delivery status & read receipts
```

### 1.4 Notification Bell UI

```
Notification Center (Bell Icon):
├─ Real-time badge count (unread notifications)
├─ Popup dropdown (10 latest notifications)
├─ "Mark all as read" button
├─ Notification preferences button (gear icon)
├─ Link to full notification center
└─ Grouped by type with filters

Full Notification Center Page:
├─ All notifications (paginated, 50 per page)
├─ Filter by type (news, alerts, issues, urgent)
├─ Filter by status (unread, read, archived)
├─ Search functionality
├─ Bulk actions (mark as read, delete, archive)
└─ Individual notification actions (read, delete, archive)
```

---

## 2️⃣ DIRECT MESSAGING SYSTEM

### 2.1 Direct Message Features

```
Direct Message Thread (1-on-1):
├─ Message history (scrollable, infinite load)
├─ Real-time typing indicator ("John is typing...")
├─ Message timestamps (exact time on hover)
├─ Read receipts (seen at X, message seen/delivered icons)
├─ Delivery status (sending, sent, delivered, read)
├─ Message reactions (👍, ❤️, 😂, etc.)
├─ Message editing (edit with timestamp "edited")
├─ Message deletion (delete with "message deleted" placeholder)
├─ Message search (within thread)
├─ File/image attachment support
├─ User online status indicator
├─ Last seen indicator ("Last active 2 hours ago")
└─ Block/report user option
```

### 2.2 Direct Message UI Layout

```
Messaging Tab:
┌─────────────────────────────────────────────────────┐
│ Messaging (Badge: 3 unread)                         │
├─────────────────────────────────────────────────────┤
│                                                     │
│  CONVERSATION LIST (Left Panel)                    │
│  ┌──────────────────────────────────────────────┐ │
│  │ Search conversations... 🔍                   │ │
│  ├──────────────────────────────────────────────┤ │
│  │                                              │ │
│  │ 🔴 John Smith (unread: 2)                   │ │
│  │ "Thanks for the update..."                  │ │
│  │ 5 min ago                                    │ │
│  │                                              │ │
│  │ ⚫ Sarah Johnson (viewed)                    │ │
│  │ "See you at the meeting"                    │ │
│  │ 1 hour ago                                   │ │
│  │                                              │ │
│  │ ⚫ Team Manager                              │ │
│  │ "Your shift approved"                       │ │
│  │ Yesterday                                    │ │
│  │                                              │ │
│  └──────────────────────────────────────────────┘ │
│                                                     │
│  CONVERSATION VIEW (Right Panel)                  │
│  ┌──────────────────────────────────────────────┐ │
│  │ John Smith     (online, typing...)           │ │
│  ├──────────────────────────────────────────────┤ │
│  │                                              │ │
│  │  ← Left side: Messages from John             │ │
│  │  Right side: Your messages →                 │ │
│  │                                              │ │
│  │  Message groups by time (Today, Yesterday)  │ │
│  │                                              │ │
│  ├──────────────────────────────────────────────┤ │
│  │ Type a message... 📎 😊 ➤                  │ │
│  └──────────────────────────────────────────────┘ │
│                                                     │
└─────────────────────────────────────────────────────┘
```

---

## 3️⃣ GROUP CHAT SYSTEM (Chat Rooms)

### 3.1 Room Types

```
ROOM TYPES:
│
├─ DEPARTMENT ROOMS (Auto-created)
│  ├─ #sales - All sales team
│  ├─ #hr - All HR team
│  ├─ #operations - All operations team
│  ├─ #management - All managers
│  └─ #all-staff - Company-wide
│
├─ PROJECT ROOMS (Created by managers)
│  ├─ #q4-campaign
│  ├─ #new-inventory-system
│  └─ #store-renovation
│
├─ SPECIAL ROOMS (Created for specific purposes)
│  ├─ #announcements (broadcast only, read-only)
│  ├─ #suggestions (feedback channel)
│  ├─ #random (off-topic chat)
│  └─ #troubleshooting (help & support)
│
└─ DIRECT TEAMS (Small team rooms)
   ├─ #shift-team-5 (specific store team)
   ├─ #management-team
   └─ #executive-board
```

### 3.2 Room Features

```
Room Member Roles:
├─ Owner (created room, full control)
├─ Admin (manage members, delete messages, pin)
├─ Moderator (manage messages, enforce rules)
└─ Member (read, write, share files)

Room Features:
├─ Channel/Room name & description
├─ Member list (with roles, online status)
├─ Pinned messages (important info stays visible)
├─ Announcements panel (top of room)
├─ Room files section (all shared files)
├─ Room settings (privacy, notification settings)
├─ Member management (add, remove, promote, demote)
├─ Integration with calendar (scheduled messages)
├─ Room activity log (moderation log)
└─ Room search (search all messages in room)
```

### 3.3 Room UI Layout

```
Group Chat Tab (Facebook-like):
┌─────────────────────────────────────────────────────┐
│ Group Chats                                         │
├─────────────────────────────────────────────────────┤
│                                                     │
│  ROOM LIST (Left Panel - Sidebar)                 │
│  ┌──────────────────────────────────────────────┐ │
│  │ Create Room [+]  Browse [🔍]                 │ │
│  ├──────────────────────────────────────────────┤ │
│  │                                              │ │
│  │ ⭐ PINNED ROOMS                             │ │
│  │ # announcements (1 unread)                  │ │
│  │ # sales                                      │ │
│  │                                              │ │
│  │ 📁 ALL ROOMS                                │ │
│  │ # general (team chat)                       │ │
│  │ # q4-campaign (3 unread)                    │ │
│  │ # random                                     │ │
│  │ # hr-benefits                               │ │
│  │ # tech-support (2 unread)                   │ │
│  │                                              │ │
│  │ ⚙️ Create New Room                          │ │
│  │ 🔍 Find Room...                             │ │
│  │                                              │ │
│  └──────────────────────────────────────────────┘ │
│                                                     │
│  ROOM VIEW (Main Panel - Facebook Style)          │
│  ┌──────────────────────────────────────────────┐ │
│  │ # q4-campaign                                │ │
│  │ Marketing campaign discussion - 24 members   │ │
│  │ [Settings] [Members] [Search] [Menu]         │ │
│  ├──────────────────────────────────────────────┤ │
│  │                                              │ │
│  │ ANNOUNCEMENTS BANNER:                        │ │
│  │ "Campaign launch date moved to Dec 1"        │ │
│  │ Pinned by Sarah - 2 days ago                 │ │
│  │                                              │ │
│  ├──────────────────────────────────────────────┤ │
│  │                                              │ │
│  │ CONVERSATION THREAD:                         │ │
│  │                                              │ │
│  │ [User Avatar] John Smith (10:30 AM)          │ │
│  │ "Need approval on budget"                    │ │
│  │ [Reply] [React] [Share] [Options]            │ │
│  │                                              │ │
│  │   → 3 replies [View thread]                  │ │
│  │                                              │ │
│  │ [User Avatar] Sarah Manager (11:45 AM)      │ │
│  │ "Budget approved! Moving forward"            │ │
│  │ [Reply] [React] [Share] [Options]            │ │
│  │                                              │ │
│  ├──────────────────────────────────────────────┤ │
│  │ Type message... 📎 😊 [Send]                │ │
│  └──────────────────────────────────────────────┘ │
│                                                     │
└─────────────────────────────────────────────────────┘
```

### 3.4 Thread System (Conversations within Rooms)

```
Threading allows keeping conversations organized:

Main Thread (Room Channel):
├─ Message 1
├─ Message 2 (has 5 replies - collapsed by default)
│  └─ [Show 5 replies]
│     ├─ Reply 1
│     ├─ Reply 2
│     ├─ Reply 3
│     ├─ Reply 4
│     └─ Reply 5
├─ Message 3
└─ Message 4 (has 2 replies)
   └─ [Show 2 replies]

Benefits:
- Keeps channel organized
- Easy to follow sub-conversations
- Reduces channel clutter
- Easier to search & find context
```

---

## 4️⃣ REAL-TIME COMMUNICATION LAYER

### 4.1 WebSocket Architecture

```
WebSocket Server (Socket.io Compatible):
├─ Connection Management
│  ├─ User authentication
│  ├─ Presence tracking (online/offline)
│  ├─ Room subscriptions
│  └─ Graceful disconnection handling
│
├─ Message Broadcasting
│  ├─ Direct message delivery
│  ├─ Group message broadcasting
│  ├─ Notification delivery
│  ├─ Typing indicators
│  └─ User status updates
│
├─ Presence Tracking
│  ├─ User online/offline status
│  ├─ Last active timestamp
│  ├─ Current room/chat location
│  └─ Broadcast updates to relevant users
│
├─ Acknowledgments
│  ├─ Message delivered confirmation
│  ├─ Message read confirmation
│  ├─ Delivery receipts
│  └─ Error acknowledgments
│
└─ Event Handlers
   ├─ new_message
   ├─ message_edited
   ├─ message_deleted
   ├─ typing_started
   ├─ typing_stopped
   ├─ user_came_online
   ├─ user_went_offline
   ├─ user_joined_room
   ├─ user_left_room
   ├─ notification_received
   └─ presence_update
```

### 4.2 Fallback Systems (When WebSocket Unavailable)

```
Fallback Chain:
1. WebSocket (preferred, real-time)
   │ (if fails)
   ▼
2. Server-Sent Events (SSE)
   │ (if fails)
   ▼
3. Long-polling (AJAX with 30s timeout)
   │ (if fails)
   ▼
4. Regular polling (AJAX every 10s - degraded experience)

Benefits:
- Automatic fallback if WebSocket unavailable
- No user disruption
- Maintains real-time experience as much as possible
```

---

## 5️⃣ DATABASE SCHEMA

### 5.1 Notifications Tables

```sql
-- Notifications (News & Alerts)
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type ENUM('news', 'alert', 'issue', 'urgent') NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    source VARCHAR(100),
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
    read_at TIMESTAMP NULL,
    archived_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX (user_id, created_at),
    INDEX (read_at)
);

-- Notification Preferences
CREATE TABLE notification_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    channels JSON DEFAULT '{"in_app": true, "email": true, "push": true}',
    frequency ENUM('realtime', 'digest', 'off') DEFAULT 'realtime',
    quiet_hours_start TIME,
    quiet_hours_end TIME,
    category_settings JSON,
    priority_filter ENUM('all', 'high', 'critical') DEFAULT 'all',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- News Items (Sources for Notifications)
CREATE TABLE news_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    source VARCHAR(100),
    source_type ENUM('internal', 'feed', 'manual') DEFAULT 'manual',
    priority ENUM('low', 'medium', 'high') DEFAULT 'low',
    created_by INT,
    published_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX (published_at)
);

-- System Alerts (Errors, Issues, etc.)
CREATE TABLE system_alerts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(100) NOT NULL,
    level ENUM('info', 'warning', 'error', 'critical') DEFAULT 'info',
    message TEXT NOT NULL,
    details JSON,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (level, created_at)
);
```

### 5.2 Direct Messaging Tables

```sql
-- Direct Message Threads (1-on-1 conversations)
CREATE TABLE message_threads (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user1_id INT NOT NULL,
    user2_id INT NOT NULL,
    last_message_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user1_id) REFERENCES users(id),
    FOREIGN KEY (user2_id) REFERENCES users(id),
    UNIQUE KEY (user1_id, user2_id),
    INDEX (last_message_at)
);

-- Direct Messages
CREATE TABLE direct_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    thread_id INT NOT NULL,
    sender_id INT NOT NULL,
    recipient_id INT NOT NULL,
    message TEXT NOT NULL,
    delivered_at TIMESTAMP NULL,
    read_at TIMESTAMP NULL,
    edited_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (thread_id) REFERENCES message_threads(id),
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (recipient_id) REFERENCES users(id),
    INDEX (thread_id, created_at),
    INDEX (read_at)
);

-- Message Attachments
CREATE TABLE message_attachments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    message_id INT NOT NULL,
    file_id INT NOT NULL,
    filename VARCHAR(255),
    file_type VARCHAR(50),
    file_size INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES direct_messages(id),
    INDEX (message_id)
);

-- Message Reactions (Emoji reactions)
CREATE TABLE message_reactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    reaction VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES direct_messages(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY (message_id, user_id, reaction)
);
```

### 5.3 Group Chat Tables

```sql
-- Chat Rooms
CREATE TABLE chat_rooms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    room_type ENUM('department', 'project', 'special', 'team', 'direct') DEFAULT 'project',
    created_by INT NOT NULL,
    is_private BOOLEAN DEFAULT FALSE,
    is_announcement_only BOOLEAN DEFAULT FALSE,
    max_members INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX (name),
    INDEX (created_at)
);

-- Room Members
CREATE TABLE room_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('owner', 'admin', 'moderator', 'member') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_read_message_id INT,
    last_read_at TIMESTAMP,
    muted BOOLEAN DEFAULT FALSE,
    pinned BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (room_id) REFERENCES chat_rooms(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY (room_id, user_id),
    INDEX (room_id, user_id)
);

-- Room Messages
CREATE TABLE room_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    parent_message_id INT,
    message TEXT NOT NULL,
    message_type ENUM('text', 'image', 'file', 'announcement') DEFAULT 'text',
    edited_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    pinned_at TIMESTAMP NULL,
    pinned_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES chat_rooms(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (parent_message_id) REFERENCES room_messages(id),
    FOREIGN KEY (pinned_by) REFERENCES users(id),
    INDEX (room_id, created_at),
    INDEX (parent_message_id)
);

-- Room Files (File sharing)
CREATE TABLE room_files (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    message_id INT,
    file_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    file_type VARCHAR(50),
    file_size INT,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES chat_rooms(id),
    FOREIGN KEY (message_id) REFERENCES room_messages(id),
    FOREIGN KEY (uploaded_by) REFERENCES users(id),
    INDEX (room_id)
);

-- Room Activity Log (Moderation/Admin actions)
CREATE TABLE room_activity (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    user_id INT NOT NULL,
    target_user_id INT,
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES chat_rooms(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (target_user_id) REFERENCES users(id),
    INDEX (room_id, created_at)
);

-- Pinned Messages
CREATE TABLE pinned_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    message_id INT NOT NULL,
    pinned_by INT NOT NULL,
    pinned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES chat_rooms(id),
    FOREIGN KEY (message_id) REFERENCES room_messages(id),
    FOREIGN KEY (pinned_by) REFERENCES users(id),
    UNIQUE KEY (room_id, message_id)
);
```

### 5.4 Presence & Status Tables

```sql
-- User Presence (Online/Offline status)
CREATE TABLE user_presence (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    status ENUM('online', 'away', 'offline', 'do_not_disturb') DEFAULT 'online',
    last_active TIMESTAMP,
    current_location VARCHAR(100),
    last_read_notification_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX (status)
);

-- Typing Indicators (Real-time)
CREATE TABLE user_typing (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    chat_id INT,
    chat_type ENUM('direct', 'room') DEFAULT 'room',
    typing_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, chat_id, chat_type),
    INDEX (typing_at)
);

-- Online History (Audit trail)
CREATE TABLE user_online_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    login_at TIMESTAMP,
    logout_at TIMESTAMP,
    session_duration INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX (user_id, login_at)
);
```

---

## 6️⃣ API ENDPOINTS

### 6.1 Notification API

```
GET /api/notifications
├─ Get all notifications (paginated)
├─ Filters: type, status, priority, date_range
└─ Response: [notification] with pagination

GET /api/notifications/:id
├─ Get single notification details
└─ Response: notification object

POST /api/notifications/:id/read
├─ Mark notification as read
└─ Updates read_at timestamp

POST /api/notifications/mark-all-read
├─ Mark all notifications as read
└─ Bulk update

DELETE /api/notifications/:id
├─ Delete notification
└─ Soft delete (update deleted_at)

GET /api/notification-preferences
├─ Get user's notification preferences
└─ Response: preferences object

PUT /api/notification-preferences
├─ Update notification preferences
└─ Input: preferences object

GET /api/notifications/unread-count
├─ Get count of unread notifications
└─ Response: {unread: 5}
```

### 6.2 Direct Messaging API

```
GET /api/messages/threads
├─ Get all message threads for user
├─ Sorted by last_message_at
└─ Response: [thread] array

GET /api/messages/threads/:thread_id
├─ Get all messages in thread (paginated)
├─ Infinite scroll
└─ Response: [message] with pagination

POST /api/messages/threads/:thread_id
├─ Send message in thread
├─ Input: {message, attachments[]}
└─ Response: message object

PUT /api/messages/:message_id
├─ Edit message
├─ Input: {message}
└─ Response: updated message

DELETE /api/messages/:message_id
├─ Delete message
└─ Soft delete with timestamp

GET /api/messages/search
├─ Search messages by content
├─ Filters: thread_id, sender_id, date_range
└─ Response: [message] array

POST /api/messages/:message_id/reactions
├─ Add reaction to message
├─ Input: {reaction: "👍"}
└─ Response: message with reactions

DELETE /api/messages/:message_id/reactions/:reaction
├─ Remove reaction from message
└─ Deletes reaction record
```

### 6.3 Group Chat API

```
GET /api/rooms
├─ Get all accessible chat rooms
├─ Filters: type, member_status, search
└─ Response: [room] array

POST /api/rooms
├─ Create new chat room
├─ Input: {name, description, type, privacy}
└─ Response: room object

GET /api/rooms/:room_id
├─ Get room details & members
└─ Response: room object with members[]

PUT /api/rooms/:room_id
├─ Update room details
├─ Input: {name, description, settings}
└─ Response: updated room

DELETE /api/rooms/:room_id
├─ Delete chat room (owner only)
└─ Archive room & messages

GET /api/rooms/:room_id/messages
├─ Get messages in room (paginated)
├─ Optional: parent_message_id (for threads)
└─ Response: [message] with pagination

POST /api/rooms/:room_id/messages
├─ Send message to room
├─ Input: {message, parent_message_id?, attachments[]}
└─ Response: message object

PUT /api/rooms/:room_id/messages/:message_id
├─ Edit room message
├─ Input: {message}
└─ Response: updated message

DELETE /api/rooms/:room_id/messages/:message_id
├─ Delete message from room
└─ Soft delete

GET /api/rooms/:room_id/members
├─ Get room members with roles
└─ Response: [member] array

POST /api/rooms/:room_id/members
├─ Add member to room
├─ Input: {user_id, role}
└─ Response: member object

DELETE /api/rooms/:room_id/members/:user_id
├─ Remove member from room
├─ Input: optional reason
└─ Updates room_members record

PUT /api/rooms/:room_id/members/:user_id
├─ Update member role
├─ Input: {role: 'admin'}
└─ Response: updated member

GET /api/rooms/:room_id/files
├─ Get all files shared in room
├─ Filters: file_type, date_range
└─ Response: [file] array

GET /api/rooms/:room_id/pinned
├─ Get pinned messages in room
└─ Response: [message] array

POST /api/rooms/:room_id/messages/:message_id/pin
├─ Pin message in room (admin only)
└─ Response: pinned message

DELETE /api/rooms/:room_id/messages/:message_id/pin
├─ Unpin message from room
└─ Response: success

GET /api/rooms/:room_id/activity
├─ Get room activity log (admin only)
├─ Filters: action, user_id, date_range
└─ Response: [activity] array
```

### 6.4 Presence API

```
GET /api/presence/:user_id
├─ Get user's online status
└─ Response: {status, last_active}

PUT /api/presence
├─ Update own online status
├─ Input: {status: 'online'|'away'|'offline'}
└─ Response: presence object

GET /api/presence/batch
├─ Get online status for multiple users
├─ Input: user_ids[]
└─ Response: {user_id: status}
```

---

## 7️⃣ FRONTEND COMPONENTS

### 7.1 Notification Center Component

```javascript
class NotificationCenter {
    constructor() {
        this.notifications = [];
        this.unreadCount = 0;
        this.preferences = {};
    }

    // Load notifications
    async loadNotifications(filters = {}) {
        const response = await api.get('/notifications', { params: filters });
        this.notifications = response.data;
        this.updateUnreadCount();
    }

    // Mark as read
    async markAsRead(notificationId) {
        await api.post(`/notifications/${notificationId}/read`);
        this.updateNotification(notificationId, { read_at: new Date() });
    }

    // Update preferences
    async updatePreferences(preferences) {
        const response = await api.put('/notification-preferences', preferences);
        this.preferences = response.data;
    }

    // Get unread count
    updateUnreadCount() {
        this.unreadCount = this.notifications.filter(n => !n.read_at).length;
        this.updateBellBadge();
    }

    // Render notification bell
    renderNotificationBell() {
        return `
            <div class="notification-bell">
                <button class="bell-btn" id="notificationBell">
                    🔔
                    ${this.unreadCount > 0 ? `<span class="badge">${this.unreadCount}</span>` : ''}
                </button>
                <div class="notification-dropdown" id="notificationDropdown">
                    ${this.renderNotificationList()}
                </div>
            </div>
        `;
    }

    // Render notification list
    renderNotificationList() {
        return this.notifications.slice(0, 10).map(n => `
            <div class="notification-item ${n.read_at ? '' : 'unread'}">
                <div class="notification-icon">${this.getIcon(n.type)}</div>
                <div class="notification-content">
                    <div class="notification-title">${n.title}</div>
                    <div class="notification-text">${n.content}</div>
                    <div class="notification-time">${this.formatTime(n.created_at)}</div>
                </div>
                <div class="notification-actions">
                    ${!n.read_at ? `<button onclick="markAsRead(${n.id})">✓</button>` : ''}
                    <button onclick="deleteNotification(${n.id})">×</button>
                </div>
            </div>
        `).join('');
    }
}
```

### 7.2 Messaging Component

```javascript
class MessagingCenter {
    constructor() {
        this.threads = [];
        this.currentThread = null;
        this.messages = [];
        this.typingUsers = new Set();
    }

    // Load message threads
    async loadThreads() {
        const response = await api.get('/messages/threads');
        this.threads = response.data;
        this.renderThreadList();
    }

    // Load messages in thread
    async loadThread(threadId) {
        this.currentThread = this.threads.find(t => t.id === threadId);
        const response = await api.get(`/messages/threads/${threadId}`);
        this.messages = response.data;
        this.renderMessages();
    }

    // Send message
    async sendMessage(content, attachments = []) {
        const response = await api.post(
            `/messages/threads/${this.currentThread.id}`,
            { message: content, attachments }
        );
        this.messages.push(response.data);
        this.renderMessages();
        this.scrollToBottom();
    }

    // Handle typing indicator
    handleTyping() {
        if (this.typingTimeout) clearTimeout(this.typingTimeout);

        // Emit typing event via WebSocket
        socket.emit('typing', {
            threadId: this.currentThread.id,
            userId: currentUser.id
        });

        this.typingTimeout = setTimeout(() => {
            socket.emit('typing_stop', {
                threadId: this.currentThread.id,
                userId: currentUser.id
            });
        }, 1000);
    }

    // Render message thread UI
    renderMessages() {
        return `
            <div class="messaging-container">
                <div class="thread-list">
                    ${this.threads.map(t => `
                        <div class="thread-item ${t.id === this.currentThread?.id ? 'active' : ''}">
                            <div class="thread-avatar">${t.other_user.avatar}</div>
                            <div class="thread-info">
                                <div class="thread-name">${t.other_user.name}</div>
                                <div class="thread-preview">${t.last_message}</div>
                            </div>
                            <div class="thread-time">${this.formatTime(t.last_message_at)}</div>
                        </div>
                    `).join('')}
                </div>

                <div class="conversation-view">
                    <div class="message-history">
                        ${this.messages.map(m => `
                            <div class="message ${m.sender_id === currentUser.id ? 'sent' : 'received'}">
                                <div class="message-content">${m.message}</div>
                                <div class="message-time">${this.formatTime(m.created_at)}</div>
                                ${m.read_at ? '<div class="read-receipt">✓✓</div>' : ''}
                            </div>
                        `).join('')}
                    </div>

                    <div class="typing-indicator" id="typingIndicator"></div>

                    <div class="message-input-area">
                        <input
                            type="text"
                            class="message-input"
                            placeholder="Type message..."
                            @input="handleTyping"
                            @send="sendMessage"
                        />
                    </div>
                </div>
            </div>
        `;
    }
}
```

### 7.3 Group Chat Component

```javascript
class GroupChatCenter {
    constructor() {
        this.rooms = [];
        this.currentRoom = null;
        this.messages = [];
        this.members = [];
    }

    // Load chat rooms
    async loadRooms() {
        const response = await api.get('/rooms');
        this.rooms = response.data;
        this.renderRoomList();
    }

    // Load room messages
    async loadRoom(roomId) {
        this.currentRoom = this.rooms.find(r => r.id === roomId);
        const messagesResponse = await api.get(`/rooms/${roomId}/messages`);
        const membersResponse = await api.get(`/rooms/${roomId}/members`);

        this.messages = messagesResponse.data;
        this.members = membersResponse.data;

        this.renderRoom();
    }

    // Send message to room
    async sendMessage(content, parentMessageId = null) {
        const response = await api.post(
            `/rooms/${this.currentRoom.id}/messages`,
            { message: content, parent_message_id: parentMessageId }
        );
        this.messages.push(response.data);
        this.renderMessages();
    }

    // Render room UI (Facebook-like)
    renderRoom() {
        return `
            <div class="group-chat-container">
                <!-- Left Sidebar: Room List -->
                <div class="room-sidebar">
                    <div class="room-header">
                        <h3>Group Chats</h3>
                        <button onclick="createRoom()">+ Create</button>
                    </div>
                    <div class="room-list">
                        ${this.rooms.map(r => `
                            <div class="room-item ${r.id === this.currentRoom?.id ? 'active' : ''}">
                                <div class="room-icon">#</div>
                                <div class="room-info">
                                    <div class="room-name">${r.name}</div>
                                    ${r.unread_count ? `<span class="unread-badge">${r.unread_count}</span>` : ''}
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>

                <!-- Main Chat Area -->
                <div class="room-main">
                    <!-- Room Header -->
                    <div class="room-header-top">
                        <h2>${this.currentRoom?.name}</h2>
                        <div class="room-actions">
                            <button onclick="openRoomInfo()">ℹ️</button>
                            <button onclick="toggleMembersList()">👥</button>
                        </div>
                    </div>

                    <!-- Announcements/Pinned -->
                    ${this.currentRoom?.announcements ? `
                        <div class="announcements-banner">
                            <div class="announcement">${this.currentRoom.announcements}</div>
                        </div>
                    ` : ''}

                    <!-- Messages -->
                    <div class="room-messages">
                        ${this.messages.map(m => `
                            <div class="message-group" data-date="${this.formatDate(m.created_at)}">
                                <div class="message-item">
                                    <div class="message-avatar">${m.user.avatar}</div>
                                    <div class="message-content">
                                        <div class="message-header">
                                            <span class="message-author">${m.user.name}</span>
                                            <span class="message-time">${this.formatTime(m.created_at)}</span>
                                        </div>
                                        <div class="message-text">${m.message}</div>
                                        ${m.parent_message_id ? `
                                            <div class="message-thread-indicator">
                                                <a onclick="showThread(${m.id})">
                                                    ${m.reply_count} replies
                                                </a>
                                            </div>
                                        ` : ''}
                                    </div>
                                    <div class="message-actions">
                                        <button onclick="replyToMessage(${m.id})">Reply</button>
                                        <button onclick="reactToMessage(${m.id})">😊</button>
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>

                    <!-- Message Input -->
                    <div class="room-input-area">
                        <input
                            type="text"
                            class="room-message-input"
                            placeholder="Type message..."
                            @send="sendMessage"
                        />
                        <button onclick="uploadFile()">📎</button>
                        <button onclick="emojiPicker()">😊</button>
                    </div>
                </div>

                <!-- Right Sidebar: Members (Optional) -->
                <div class="room-members-sidebar" id="membersSidebar">
                    <h3>Members (${this.members.length})</h3>
                    <div class="members-list">
                        ${this.members.map(m => `
                            <div class="member-item">
                                <div class="member-avatar">${m.user.avatar}</div>
                                <div class="member-info">
                                    <div class="member-name">${m.user.name}</div>
                                    <div class="member-role">${m.role}</div>
                                </div>
                                <div class="member-status">${m.user.online ? '🟢' : '⚫'}</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            </div>
        `;
    }
}
```

---

## 8️⃣ INTEGRATION WITH EXISTING SYSTEMS

### 8.1 Integration with Live Feed

```
Live Feed System → Notification System:
├─ News items posted to feed → Auto-create notifications
├─ Announcements → High priority notifications
├─ Activity alerts → Medium priority notifications
└─ User mentions → Urgent notifications

Flow:
1. News item created in Live Feed
2. Trigger notification generation
3. Check user notification preferences
4. Deliver via selected channels
5. Add to notification center
```

### 8.2 Integration with AI Assistant

```
AI Assistant → Notification Preferences:
├─ AI recommends notification settings based on role
├─ AI learns notification preferences from behavior
├─ AI summarizes notifications in chat
└─ AI suggests relevant group chats to join

AI → Group Chats:
├─ AI joins relevant rooms automatically
├─ AI provides summaries of room discussions
├─ AI answers questions about room content
└─ AI facilitates cross-room discussions
```

### 8.3 Integration with Staff Dashboard

```
Main Dashboard:
├─ Notification bell (top right)
├─ Quick access to messaging
├─ Direct link to unread messages
├─ Group chat shortcuts
└─ Real-time notifications in corner
```

---

## 9️⃣ SECURITY CONSIDERATIONS

### 9.1 Notification Security

- ✅ User authentication required
- ✅ Notification access control (users only see own notifications)
- ✅ Rate limiting on notification delivery
- ✅ XSS protection (escape all content)
- ✅ CSRF tokens for all POST/PUT/DELETE

### 9.2 Message Security

- ✅ End-to-end encryption (optional for sensitive content)
- ✅ Message authentication (verify sender)
- ✅ Rate limiting (prevent spam)
- ✅ Attachment scanning (virus/malware check)
- ✅ Content moderation (filter inappropriate content)

### 9.3 Room Security

- ✅ Role-based access control (owner, admin, moderator, member)
- ✅ Private rooms (invite-only)
- ✅ Room member audit trail
- ✅ Message deletion logs
- ✅ Moderation tools

### 9.4 Real-time Security

- ✅ WebSocket authentication
- ✅ Session validation
- ✅ CORS configuration
- ✅ Rate limiting on WebSocket messages
- ✅ Graceful disconnection handling

---

## 🔟 IMPLEMENTATION PHASES

### Phase 1: Foundation (Week 1-2)
- [ ] Database schema creation
- [ ] Basic notification system
- [ ] Simple direct messaging
- [ ] WebSocket setup

### Phase 2: Enhancement (Week 3-4)
- [ ] Group chat rooms
- [ ] Threading system
- [ ] Presence tracking
- [ ] Real-time indicators

### Phase 3: Polish (Week 5)
- [ ] Notification preferences UI
- [ ] Search functionality
- [ ] File sharing
- [ ] Analytics

### Phase 4: Integration (Week 6)
- [ ] Integrate with Live Feed
- [ ] Integrate with AI Assistant
- [ ] Integrate with dashboard
- [ ] Performance optimization

---

## 📊 SUCCESS METRICS

| Metric | Target |
|--------|--------|
| **Notification Delivery** | < 100ms |
| **Message Delivery** | < 500ms |
| **Real-time Updates** | < 100ms (WebSocket) |
| **Unread Message Count** | Accurate, real-time |
| **Room Performance** | < 1s for 100 messages |
| **User Presence** | Update within 2s |
| **Uptime** | 99.9% |

---

**Status:** ✅ **Ready for Implementation**
**Timeline:** 6 weeks to production
**Integration:** Seamless with Live Feed + AI Assistant

Let's build an amazing communication system! 🚀
