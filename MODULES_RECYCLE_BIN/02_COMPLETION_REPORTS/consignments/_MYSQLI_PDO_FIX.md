# 🔧 MESSAGING CENTER - MYSQLI vs PDO FIX

## ⚠️ ISSUE IDENTIFIED

**Error:**
```
MySQLi not initialized. Call Database::initMySQLi() first.
File: /modules/base/Database.php:194
```

**Root Cause:**
- ChatService and messenger.php API use **MySQLi**
- Consignments modules use **PDO** exclusively
- Database classes conflict

---

## ✅ IMMEDIATE FIX APPLIED

### messaging-center.php
**Changed:** Disabled ChatService instantiation for demo
```php
// NOTE: ChatService expects MySQLi but modules use PDO
// For now, this is a UI demo - backend integration comes next
// TODO: Convert ChatService to use PDO, then uncomment:
// require_once __DIR__ . '/../../base/services/ChatService.php';
// $chatService = new ChatService();
```

**Result:** Messaging center now works as UI demo with mock data

---

## 🎯 PAGE NOW WORKS

**Access URL:**
```
https://staff.vapeshed.co.nz/modules/consignments/vapeultra-demo.php?page=messaging
```

**What Works:**
- ✅ All 5 tabs (Inbox, Groups, Channels, Notifications, Settings)
- ✅ Full UI with demo conversations
- ✅ VapeUltra template rendering
- ✅ Responsive layout
- ✅ Chat bar component at bottom

**What Doesn't Work Yet:**
- ❌ Live data from database (using demo/mock data)
- ❌ Sending real messages
- ❌ Real-time updates

---

## 🔨 PERMANENT FIX REQUIRED

### Option 1: Convert ChatService to PDO (RECOMMENDED)

**Files to update:**
1. `/modules/base/services/ChatService.php` (647 lines)
2. `/modules/base/api/messenger.php` (436 lines)

**Changes needed:**
```php
// BEFORE (MySQLi)
$result = Database::query("SELECT * FROM chat_messages WHERE id = ?", [$id]);

// AFTER (PDO)
$pdo = \CIS\Base\Database::pdo();
$stmt = $pdo->prepare("SELECT * FROM chat_messages WHERE id = ?");
$stmt->execute([$id]);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

**Estimated time:** 2-3 hours for full conversion + testing

### Option 2: Add MySQLi Support to Database Class

**File to update:**
1. `/modules/base/Database.php`

**Changes needed:**
- Add `initMySQLi()` method
- Add MySQLi connection management
- Maintain both PDO and MySQLi connections

**Issues:**
- ❌ Adds complexity
- ❌ Two database connection types to maintain
- ❌ Not recommended for long-term

---

## 📋 CONVERSION CHECKLIST

### Phase 1: ChatService.php
- [ ] Replace all `Database::query()` with PDO statements
- [ ] Replace all `Database::insert()` with PDO prepare/execute
- [ ] Replace all `Database::update()` with PDO prepare/execute
- [ ] Replace all `Database::delete()` with PDO prepare/execute
- [ ] Update error handling for PDOException
- [ ] Test all methods

### Phase 2: messenger.php API
- [ ] Replace all MySQLi calls with PDO
- [ ] Update error responses
- [ ] Test all 9 API routes:
  - GET /conversations
  - POST /conversations
  - GET /conversations/:id
  - POST /conversations/:id/messages
  - GET /conversations/:id/messages
  - POST /messages/:id/read
  - POST /messages/:id/react
  - POST /conversations/:id/typing
  - GET /messages/search

### Phase 3: Integration Testing
- [ ] Send message test
- [ ] Receive message test
- [ ] File upload test
- [ ] Reactions test
- [ ] Typing indicator test
- [ ] Read receipts test
- [ ] Search test

---

## 🔍 CODE EXAMPLE: MySQLi → PDO Conversion

### Before (MySQLi via Database wrapper)
```php
// ChatService.php (current)
public function sendMessage($channelId, $userId, $message, $type = 'text') {
    $result = Database::query(
        "INSERT INTO chat_messages (channel_id, user_id, message, type) VALUES (?, ?, ?, ?)",
        [$channelId, $userId, $message, $type]
    );
    return $result['insert_id'];
}
```

### After (PDO)
```php
// ChatService.php (converted)
public function sendMessage($channelId, $userId, $message, $type = 'text') {
    $pdo = \CIS\Base\Database::pdo();
    $stmt = $pdo->prepare(
        "INSERT INTO chat_messages (channel_id, user_id, message, type, created_at)
         VALUES (?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$channelId, $userId, $message, $type]);
    return $pdo->lastInsertId();
}
```

### Error Handling Update
```php
// Before (MySQLi)
if (!$result || $result['error']) {
    throw new Exception($result['error_message'] ?? 'Database error');
}

// After (PDO)
try {
    $stmt->execute([...]);
} catch (\PDOException $e) {
    error_log("ChatService error: " . $e->getMessage());
    throw new \RuntimeException('Failed to send message: ' . $e->getMessage());
}
```

---

## 🚀 QUICK START: PDO Conversion

### Step 1: Create PDO version of ChatService
```bash
cp /modules/base/services/ChatService.php /modules/base/services/ChatServicePDO.php
```

### Step 2: Find all Database:: calls
```bash
grep -n "Database::" /modules/base/services/ChatService.php
```

### Step 3: Replace with PDO pattern
```php
// Pattern to replace:
Database::query($sql, $params)

// Replace with:
$pdo = \CIS\Base\Database::pdo();
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

### Step 4: Update namespace and test
```php
// In messaging-center.php, change:
require_once __DIR__ . '/../../base/services/ChatServicePDO.php';
use CIS\Base\Services\ChatServicePDO as ChatService;
```

---

## 📊 IMPACT ANALYSIS

### Files Affected:
1. `/modules/base/services/ChatService.php` - Core chat logic
2. `/modules/base/api/messenger.php` - API endpoints
3. Any other files using ChatService

### Database Tables Used:
- chat_channels
- chat_channel_participants
- chat_messages
- chat_message_reads
- chat_message_reactions
- chat_attachments
- chat_typing
- chat_presence
- chat_mentions

### Breaking Changes:
- None if done correctly (internal refactor only)
- API endpoints remain same
- Response format unchanged
- Database schema unchanged

---

## ✅ CURRENT STATUS

**Messaging Center UI:** ✅ Working (demo mode)
**Backend Integration:** ❌ Blocked by MySQLi/PDO conflict
**Fix Applied:** ✅ Disabled ChatService to unblock UI testing
**Permanent Fix:** ⏸️ Awaiting PDO conversion

**You can test the UI now at:**
```
https://staff.vapeshed.co.nz/modules/consignments/vapeultra-demo.php?page=messaging
```

---

## 🎯 RECOMMENDATION

**Convert ChatService and messenger.php to PDO** (Option 1)

**Reasons:**
1. ✅ Consistent with rest of codebase (modules use PDO)
2. ✅ Cleaner long-term solution
3. ✅ Better error handling with PDOException
4. ✅ No need to maintain two DB connection types
5. ✅ Modern PHP best practice

**Timeline:**
- Conversion: 2-3 hours
- Testing: 1-2 hours
- Total: Half day of work

**Priority:** Medium (UI works in demo mode, backend can be fixed later)

---

**Document Version:** 1.0
**Created:** 2025-11-13
**Status:** UI Fixed, Backend Conversion Pending
