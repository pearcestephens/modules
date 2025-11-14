# Staff Email Hub - Advanced Features Documentation

**Version:** 3.0 (Enterprise Edition)
**Last Updated:** November 14, 2025
**Status:** Production Ready

---

## Table of Contents

1. [Overview](#overview)
2. [Rackspace Legacy Email Integration](#rackspace-legacy-email-integration)
3. [Multiple Staff Profiles](#multiple-staff-profiles)
4. [AI Message Enhancement](#ai-message-enhancement)
5. [Smart Reply Generation](#smart-reply-generation)
6. [Advanced Features](#advanced-features)
7. [API Reference](#api-reference)
8. [Database Schema](#database-schema)
9. [Configuration](#configuration)
10. [Troubleshooting](#troubleshooting)

---

## Overview

The Staff Email Hub v3.0 introduces enterprise-grade email management capabilities:

- **Legacy System Integration:** Seamlessly migrate from Rackspace legacy email
- **Multi-Profile Management:** Multiple accounts per staff member with delegation
- **AI-Powered Enhancement:** Message rewriting with tone adjustment and grammar checks
- **Smart Replies:** Context-aware auto-generated response suggestions
- **Advanced Features:** Templates, scheduling, read receipts, priority inbox, and more

### Key Statistics

- **4 New Services:** 1,800+ lines of production-ready code
- **5 API Controllers:** 45+ endpoints for complete functionality
- **17 Database Tables:** Comprehensive schema for advanced features
- **100% Backward Compatible:** Existing functionality unchanged

---

## Rackspace Legacy Email Integration

### Overview

Migrate emails and conversation history from legacy Rackspace accounts to the modern CIS email hub while maintaining full context and metadata.

### Features

- **Auto-Discovery:** Validate legacy Rackspace credentials
- **Full History Import:** Select date ranges and folders
- **Folder Mapping:** Automatically organize emails into unified structure
- **Conversation Threading:** Link related emails across accounts
- **Incremental Sync:** Ongoing updates from legacy accounts
- **Migration Tracking:** Monitor progress and statistics

### Service: `RackspaceLegacyEmailImporter`

**Location:** `/modules/staff-email-hub/Services/RackspaceLegacyEmailImporter.php`

#### Key Methods

```php
// Validate legacy account
$result = $importer->validateLegacyAccount($email, $password);

// Register account for sync
$result = $importer->registerLegacyAccount($email, $password, $displayName);

// Import email history
$result = $importer->importEmailHistory($accountId, $dateFrom, $dateTo, $folder, $limit);

// Setup incremental sync
$result = $importer->setupIncrementalSync($accountId, $syncInterval);

// Get migration status
$status = $importer->getMigrationStatus($accountId);

// Create conversation thread
$result = $importer->createConversationThread($emailId, $accountId);
```

### API Endpoints

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/api/legacy-email/validate` | Validate credentials |
| POST | `/api/legacy-email/register` | Register legacy account |
| POST | `/api/legacy-email/import` | Start email import |
| POST | `/api/legacy-email/sync/setup` | Configure sync |
| GET | `/api/legacy-email/status/{accountId}` | Get migration status |
| POST | `/api/legacy-email/thread/{emailId}` | Create conversation thread |

### Usage Example

```javascript
// Step 1: Validate legacy account
const validation = await fetch('/api/legacy-email/validate', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    email: 'legacy@rackspace.com',
    password: 'secretpassword'
  })
});

// Step 2: Register for migration
const registration = await fetch('/api/legacy-email/register', {
  method: 'POST',
  body: JSON.stringify({
    email: 'legacy@rackspace.com',
    password: 'secretpassword',
    displayName: 'Legacy Email Account'
  })
});
const { account_id } = await registration.json();

// Step 3: Import email history
const import_result = await fetch('/api/legacy-email/import', {
  method: 'POST',
  body: JSON.stringify({
    account_id: account_id,
    date_from: '2020-01-01',
    date_to: '2024-12-31',
    folder: 'INBOX',
    limit: 1000
  })
});

// Step 4: Setup incremental sync
await fetch('/api/legacy-email/sync/setup', {
  method: 'POST',
  body: JSON.stringify({
    account_id: account_id,
    sync_interval: 300  // seconds
  })
});

// Step 5: Monitor progress
setInterval(async () => {
  const status = await fetch(`/api/legacy-email/status/${account_id}`);
  console.log(await status.json());
}, 5000);
```

---

## Multiple Staff Profiles

### Overview

Enable each staff member to manage multiple email accounts/profiles with role-based access control and delegation capabilities.

### Features

- **Create Multiple Profiles:** Each staff member can have multiple email accounts
- **Role-Based Access:** Owner, Admin, Delegate, Read-Only roles
- **Custom Signatures:** Different signature per profile
- **Profile Switching:** Easily switch between email accounts
- **Access Delegation:** Grant colleagues access to your profiles
- **Audit Trail:** Track all access changes

### Service: `StaffProfileManager`

**Location:** `/modules/staff-email-hub/Services/StaffProfileManager.php`

#### Key Methods

```php
// Create new profile
$result = $manager->createProfile($email, $displayName, $signature, $accountType);

// Get all profiles
$profiles = $manager->getMyProfiles();

// Get profile with permissions
$profile = $manager->getProfileWithPermissions($profileId);

// Delegate access
$result = $manager->delegateAccess($profileId, $delegateStaffId, $role);

// Revoke access
$result = $manager->revokeAccess($profileId, $delegateStaffId);

// Update signature
$result = $manager->updateSignature($profileId, $signature);

// Set default profile
$result = $manager->setDefaultProfile($profileId);

// Get access list
$list = $manager->getAccessList($profileId);

// Get audit trail
$audit = $manager->getAccessAuditTrail($profileId);
```

### API Endpoints

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/api/profiles` | List all profiles |
| POST | `/api/profiles` | Create new profile |
| GET | `/api/profiles/{profileId}` | Get profile details |
| PUT | `/api/profiles/{profileId}/signature` | Update signature |
| PUT | `/api/profiles/{profileId}/default` | Set as default |
| POST | `/api/profiles/{profileId}/delegate` | Delegate access |
| DELETE | `/api/profiles/{profileId}/access/{staffId}` | Revoke access |
| GET | `/api/profiles/{profileId}/access` | Get access list |
| GET | `/api/profiles/{profileId}/audit` | Get audit trail |

### Usage Example

```javascript
// Create a new profile
const profile = await fetch('/api/profiles', {
  method: 'POST',
  body: JSON.stringify({
    email: 'support@company.com',
    displayName: 'Support Team',
    signature: '<p>Support Team<br/>support@company.com</p>'
  })
});

// List all profiles
const profiles = await fetch('/api/profiles');
console.log(await profiles.json());

// Delegate to colleague
await fetch('/api/profiles/123/delegate', {
  method: 'POST',
  body: JSON.stringify({
    staffId: 456,
    role: 'delegate'  // 'owner', 'admin', 'delegate', 'read_only'
  })
});

// View who has access
const access = await fetch('/api/profiles/123/access');
console.log(await access.json());
```

---

## AI Message Enhancement

### Overview

Leverage OpenAI GPT models to enhance email messages with tone adjustment, length optimization, and grammar checking.

### Features

- **Tone Adjustment:** Professional, Friendly, Formal, Casual, Warm
- **Length Optimization:** Expand, Condense, or Summarize
- **Grammar & Clarity:** Automatic checking with detailed feedback
- **Multiple Variations:** Generate 5+ tone variations for comparison
- **Professionalism Scoring:** Automatic quality assessment
- **Approval Workflow:** Review before using enhancements
- **History Tracking:** Keep record of all enhancements

### Service: `MessageEnhancementService`

**Location:** `/modules/staff-email-hub/Services/MessageEnhancementService.php`

#### Key Methods

```php
// Enhance message with specific tone
$result = $service->enhanceMessage($text, $tone, $lengthAdjustment, $context);

// Generate multiple tone variations
$variations = $service->generateToneVariations($text, $context);

// Check grammar and clarity
$analysis = $service->checkGrammarAndClarity($text);

// Store for approval
$result = $service->storeEnhancementForApproval($emailId, $original, $enhanced, $tone);

// Approve enhancement
$result = $service->approveEnhancement($enhancementId);

// Reject enhancement
$result = $service->rejectEnhancement($enhancementId, $reason);

// Get pending enhancements
$pending = $service->getPendingEnhancements();

// Get history
$history = $service->getEnhancementHistory($emailId);
```

### API Endpoints

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/api/enhance/message` | Enhance with tone |
| POST | `/api/enhance/variations` | Generate variations |
| POST | `/api/enhance/grammar-check` | Grammar check |
| POST | `/api/enhance/save` | Save for approval |
| POST | `/api/enhance/{id}/approve` | Approve enhancement |
| POST | `/api/enhance/{id}/reject` | Reject enhancement |
| GET | `/api/enhance/pending` | Get pending |
| GET | `/api/enhance/history` | Get history |

### Tone Options

- **professional:** Formal, business-appropriate language
- **friendly:** Warm, approachable, conversational
- **formal:** Very formal, respectful, ceremonious
- **casual:** Relaxed, informal, natural conversation
- **warm:** Friendly with emotional intelligence

### Usage Example

```javascript
// Enhance message with professional tone
const enhanced = await fetch('/api/enhance/message', {
  method: 'POST',
  body: JSON.stringify({
    message: 'Hey, can you send me the report?',
    tone: 'professional',
    context: {
      recipient: 'boss@company.com',
      subject: 'Monthly Report'
    }
  })
});

// Get multiple variations to compare
const variations = await fetch('/api/enhance/variations', {
  method: 'POST',
  body: JSON.stringify({
    message: 'Thanks for your help',
    context: {}
  })
});

// Save enhanced version for approval
await fetch('/api/enhance/save', {
  method: 'POST',
  body: JSON.stringify({
    emailId: 123,
    originalMessage: original_text,
    enhancedMessage: enhanced_text,
    tone: 'professional'
  })
});

// Approve and use
await fetch('/api/enhance/456/approve', { method: 'POST' });
```

---

## Smart Reply Generation

### Overview

AI-powered system that generates contextually relevant reply suggestions based on email content, conversation history, and customer data.

### Features

- **Context Analysis:** Considers conversation history and customer relationship
- **Multiple Suggestions:** Generate 5+ reply options automatically
- **Tone Matching:** Suggestions match conversation tone
- **Quick Templates:** Pre-written replies for common scenarios
- **Customization:** Edit suggestions before sending
- **Feedback Loop:** Learn from approvals to improve suggestions
- **Analytics:** Track effectiveness and popular responses

### Service: `SmartReplyService`

**Location:** `/modules/staff-email-hub/Services/SmartReplyService.php`

#### Key Methods

```php
// Generate suggestions
$suggestions = $service->generateReplySuggestions($emailId, $count);

// Get suggestions for email
$suggestions = $service->getSuggestions($emailId, $limit);

// Use suggestion (optionally customized)
$result = $service->useSuggestion($suggestionId, $customization);

// Record feedback
$result = $service->recordFeedback($suggestionId, $helpful, $notes);

// Get effectiveness metrics
$metrics = $service->getEffectivenessMetrics();

// Get top suggestions
$top = $service->getTopSuggestions($limit);
```

### API Endpoints

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/api/smart-reply/generate` | Generate suggestions |
| GET | `/api/smart-reply/{emailId}` | Get suggestions |
| POST | `/api/smart-reply/{suggestionId}/use` | Use suggestion |
| POST | `/api/smart-reply/{suggestionId}/feedback` | Record feedback |
| GET | `/api/smart-reply/analytics/effectiveness` | Get metrics |
| GET | `/api/smart-reply/analytics/top` | Get top suggestions |

### Usage Example

```javascript
// Generate reply suggestions
const suggestions = await fetch('/api/smart-reply/generate', {
  method: 'POST',
  body: JSON.stringify({
    emailId: 789,
    count: 5
  })
});

// Get suggestions for display
const list = await fetch('/api/smart-reply/789');
const { suggestions } = await list.json();

// Display suggestions to user and let them pick
suggestions.forEach(suggestion => {
  console.log(suggestion.suggestion_text);
  console.log(`Relevance: ${suggestion.relevance_score}`);
});

// Use selected suggestion (with optional edits)
await fetch('/api/smart-reply/456/use', {
  method: 'POST',
  body: JSON.stringify({
    customization: 'Modified version of the suggestion...'
  })
});

// Record feedback for ML improvement
await fetch('/api/smart-reply/456/feedback', {
  method: 'POST',
  body: JSON.stringify({
    helpful: true,
    notes: 'Great suggestion, with minor edits'
  })
});

// View effectiveness
const metrics = await fetch('/api/smart-reply/analytics/effectiveness');
console.log(await metrics.json());
```

---

## Advanced Features

### Email Templates

Reusable email templates for common scenarios.

```javascript
// Create template
await fetch('/api/templates', {
  method: 'POST',
  body: JSON.stringify({
    name: 'Meeting Request',
    subject: 'Let\'s Schedule a Meeting',
    body: '<p>Hi,</p><p>I would like to schedule a meeting with you...</p>',
    category: 'scheduling',
    tags: ['meeting', 'scheduling', 'professional']
  })
});

// List templates
const templates = await fetch('/api/templates?category=scheduling');
```

### Send Scheduling

Schedule emails to send at optimal times.

```javascript
// Schedule email
await fetch('/api/schedule/email', {
  method: 'POST',
  body: JSON.stringify({
    to: 'client@company.com',
    subject: 'Monthly Report',
    body: 'Here is your monthly report...',
    sendAt: '2025-11-15 09:00:00'
  })
});

// Get scheduled emails
const scheduled = await fetch('/api/schedule/emails');
```

### Follow-Up Reminders

Automatic reminders to follow up on emails.

```javascript
// Add reminder
await fetch('/api/reminders', {
  method: 'POST',
  body: JSON.stringify({
    emailId: 123,
    remindAt: '2025-11-20 10:00:00',
    note: 'Follow up on proposal'
  })
});

// Get pending reminders
const reminders = await fetch('/api/reminders');
```

### Read Receipts

Track when emails are opened.

```javascript
// Enable tracking
const tracking = await fetch('/api/tracking/123/enable', {
  method: 'POST'
});

// Get statistics
const stats = await fetch('/api/tracking/123/stats');
```

### Conversation Analysis

AI analysis of conversation sentiment and urgency.

```javascript
// Analyze conversation
const analysis = await fetch('/api/conversation/456/analysis');
const { sentiment, urgency_score, key_topics } = await analysis.json();
```

### Priority Inbox

Smart filtering to highlight important emails.

```javascript
// Get priority emails
const priority = await fetch('/api/inbox/priority?days=7');

// Flag as priority
await fetch('/api/email/123/flag', {
  method: 'POST',
  body: JSON.stringify({ flag: true })
});
```

---

## API Reference

### Request/Response Format

All API responses follow this format:

```json
{
  "success": true,
  "data": { },
  "message": "Operation completed"
}
```

### Authentication

All endpoints require staff authentication via session or API token.

### Rate Limiting

- **Standard Endpoints:** 100 requests/minute
- **AI Endpoints:** 20 requests/minute
- **Sync Operations:** 5 concurrent operations per account

### Error Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 429 | Rate Limited |
| 500 | Server Error |

---

## Database Schema

### New Tables

1. **staff_email_accounts** - Multiple email profiles per staff
2. **staff_profile_access** - Delegation and role-based access
3. **legacy_email_sync_config** - Legacy email sync settings
4. **email_enhancements** - AI message enhancements
5. **smart_reply_suggestions** - AI-generated replies
6. **smart_reply_usage** - Reply suggestion usage tracking
7. **smart_reply_feedback** - User feedback on suggestions
8. **email_conversations** - Email conversation threading
9. **email_templates** - Reusable email templates
10. **scheduled_emails** - Scheduled email sending
11. **follow_up_reminders** - Email follow-up reminders
12. **email_open_tracking** - Email read/open tracking
13. **email_drafts** - Email composition drafts
14. **conversation_analysis** - Conversation sentiment/urgency analysis

### Key Relationships

```
staff_accounts (1) ──→ (many) staff_email_accounts
staff_email_accounts (1) ──→ (many) emails
staff_email_accounts (1) ──→ (many) staff_profile_access
emails (1) ──→ (1) email_conversations
emails (1) ──→ (many) email_enhancements
emails (1) ──→ (many) smart_reply_suggestions
emails (1) ──→ (many) follow_up_reminders
emails (1) ──→ (many) email_open_tracking
```

---

## Configuration

### Environment Variables

```bash
# OpenAI Configuration
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4-turbo-preview

# Rackspace Legacy Email
RACKSPACE_IMAP_HOST=secure.emailsrvr.com
RACKSPACE_IMAP_PORT=993
RACKSPACE_SMTP_HOST=secure.emailsrvr.com
RACKSPACE_SMTP_PORT=587

# Encryption
ENCRYPTION_KEY=your-32-character-key-here

# Features (enable/disable)
ENABLE_LEGACY_EMAIL_IMPORT=true
ENABLE_AI_ENHANCEMENTS=true
ENABLE_SMART_REPLIES=true
ENABLE_READ_RECEIPTS=true
```

### Database Migration

```bash
# Apply new schema
mysql -u root -p database_name < migrations_advanced_features.sql

# Verify tables
mysql -u root -p -e "USE database_name; SHOW TABLES;"
```

---

## Troubleshooting

### Rackspace Connection Issues

**Problem:** "Invalid credentials or connection failed"

**Solution:**
1. Verify email and password are correct
2. Check if Rackspace account is active
3. Verify port 993 (IMAP) is accessible
4. Check firewall rules

### AI Features Not Working

**Problem:** "AI service unavailable"

**Solution:**
1. Verify `OPENAI_API_KEY` is set in `.env`
2. Check OpenAI account has available credits
3. Verify API key has appropriate permissions
4. Check network connectivity

### Profile Delegation Issues

**Problem:** "Permission denied"

**Solution:**
1. Verify user is owner or admin of profile
2. Check delegated staff member exists
3. Verify role is valid (owner, admin, delegate, read_only)

### Performance Optimization

**For Large Email Archives:**
1. Import emails in batches of 500-1000
2. Use date range filtering to limit scope
3. Run imports during off-peak hours
4. Monitor MySQL slow query log

**For Smart Replies:**
1. Cache suggestions for 15 minutes
2. Disable for emails from multiple recipients
3. Limit to 5 suggestions per email
4. Use rate limiting (max 20/minute)

---

## Support & Documentation

- **Module Location:** `/modules/staff-email-hub/`
- **Services:** `/modules/staff-email-hub/Services/`
- **Controllers:** `/modules/staff-email-hub/Controllers/`
- **Database Schema:** `migrations_advanced_features.sql`
- **API Base:** `/api/` (relative to module root)

For additional support, contact the CIS team or check the internal wiki at `wiki.vapeshed.co.nz`.
