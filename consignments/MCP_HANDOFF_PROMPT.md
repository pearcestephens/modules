# 🤖 MCP-Powered Agent Handoff Prompt

## For the Next AI Agent/Assistant

Copy and paste this prompt to continue work with full context using MCP tools:

---

## Initial Context Retrieval

```
I need to continue work on the Transfer Manager frontend conversion in the consignments module.

Please use the MCP server to retrieve context:

MCP Server: https://gpt.ecigdis.co.nz/mcp/server_v4.php
API Key: 31ce0106609a6c5bc4f7ece0deb2f764df90a06167bda83468883516302a6a35

TASKS:
1. Use conversation.get_recent tool to retrieve the last 10 messages from this conversation
2. Use conversation.get_project_context with project_id="consignment-module-build" to get full project context
3. Review what has been completed (frontend CSS/JS modernization)
4. Continue with backend migration (backend.php and api.php)

KEY CONTEXT TO RETRIEVE:
- Current conversation ID is stored in /tmp/current_conversation_id.txt
- Project: Transfer Manager v2.0 - Frontend to Backend conversion
- Location: /modules/consignments/
- Status: Frontend 95% complete, backend needs migration
- Architecture: Modern module loader with dependency management

WHAT'S BEEN DONE:
✅ CSS migrated to /modules/consignments/assets/css/transfer-manager-v2.css
✅ JS organized into /modules/consignments/assets/js/modules/
✅ Created app-loader.js for auto-loading
✅ Updated transfer-manager.php to use v2 assets

WHAT'S LEFT:
❌ Move backend.php to /modules/consignments/api/
❌ Move api.php to /modules/consignments/api/
❌ Update all path references in code
❌ Make 100% PSR-4 compliant

Please start by using these MCP tools:
1. conversation.get_recent (limit: 10)
2. conversation.get_project_context (project_id: "consignment-module-build")
3. Then ask me what to work on next
```

---

## Alternative: Quick Context Load

```
Load context from MCP server for the Transfer Manager consignment module conversion.

Use these MCP tools in order:
1. conversation.get_recent with limit=10
2. conversation.get_project_context with project_id="consignment-module-build"
3. ai_agent.query with query="What is the current status of the Transfer Manager frontend conversion and what backend work remains?"

Then summarize what's been done and what's next.
```

---

## Alternative: Search-Based Context

```
I'm continuing work on the consignments module Transfer Manager.

Use MCP conversation.search to find:
- Keywords: "Transfer Manager", "app-loader", "v2.0", "backend.php"
- Retrieve last 5 relevant messages

Then use semantic_search.search_codebase to find:
- Query: "Transfer Manager backend api endpoint"
- Path: "/modules/consignments/"

Summarize the current state and next steps.
```

---

## Full MCP Tool Chain Example

```bash
# Step 1: Get conversation context
curl -X POST https://gpt.ecigdis.co.nz/mcp/server_v4.php \
  -H "Content-Type: application/json" \
  -H "X-API-Key: 31ce0106609a6c5bc4f7ece0deb2f764df90a06167bda83468883516302a6a35" \
  -d '{
    "jsonrpc": "2.0",
    "method": "tools/call",
    "params": {
      "name": "conversation.get_recent",
      "arguments": {
        "limit": 10
      }
    },
    "id": 1
  }'

# Step 2: Get project context
curl -X POST https://gpt.ecigdis.co.nz/mcp/server_v4.php \
  -H "Content-Type: application/json" \
  -H "X-API-Key: 31ce0106609a6c5bc4f7ece0deb2f764df90a06167bda83468883516302a6a35" \
  -d '{
    "jsonrpc": "2.0",
    "method": "tools/call",
    "params": {
      "name": "conversation.get_project_context",
      "arguments": {
        "project_id": "consignment-module-build"
      }
    },
    "id": 2
  }'
```

---

## What to Ask the MCP-Enabled Agent

**Simple handoff:**
> "Please use MCP to load context from conversation ID in /tmp/current_conversation_id.txt and continue the Transfer Manager backend migration."

**Detailed handoff:**
> "Use the MCP server (gpt.ecigdis.co.nz) to:
> 1. Load recent conversation history (last 10 messages)
> 2. Get project context for 'consignment-module-build'
> 3. Review what's been completed on the frontend
> 4. Then migrate backend.php and api.php to the new /modules/consignments/api/ structure
> 5. Update all references to use new paths"

---

## Key Files to Reference

```
Current State:
/modules/consignments/
├── transfer-manager.php (updated with v2 assets)
├── assets/
│   ├── css/transfer-manager-v2.css (scoped, modern)
│   └── js/
│       ├── app-loader.js (auto-loading)
│       └── modules/ (organized JS)
└── TransferManager/ (LEGACY - needs migration)
    ├── backend.php ❌ Move to /api/
    └── api.php ❌ Move to /api/
```

---

## Success Criteria

The agent should achieve:
1. ✅ Backend files moved to `/modules/consignments/api/`
2. ✅ All path references updated
3. ✅ Transfer manager fully functional with new structure
4. ✅ 100% PSR-4 compliant
5. ✅ No references to old `TransferManager/` folder

---

## MCP Tools Available

**For context retrieval:**
- `conversation.get_recent` - Get last N messages
- `conversation.search` - Search by keywords
- `conversation.get_project_context` - Get full project context
- `ai_agent.query` - Ask AI about codebase

**For code analysis:**
- `semantic_search.search_codebase` - Find code patterns
- `db.query` - Check database structure
- `file.read` - Read file contents

**For documentation:**
- `conversation.save_conversation` - Record progress
- `memory.store` - Save key decisions

---

## Recording Progress

The agent should use:
```javascript
// Record completion
ai_agent.query({
  query: "Record that backend migration is complete",
  record: true
})
```

Or manually:
```bash
CONV_ID=$(cat /tmp/current_conversation_id.txt)
/tmp/record_conversation.sh "assistant" "Completed backend migration to /modules/consignments/api/" "$CONV_ID"
```

---

**Ready to handoff!** 🚀
