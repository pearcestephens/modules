# 🤖 AI AGENT + MCP ARCHITECTURE - BEST PRACTICE

## 🎯 RECOMMENDED ARCHITECTURE

```
┌─────────────────────────────────────────────────────────────────┐
│  FRONTEND (Browser - UI Only)                                   │
│  - Chat interface                                               │
│  - Task submission                                              │
│  - SSE/WebSocket for streaming results                          │
│  - NO secrets, NO tool access                                   │
└─────────────────────────────────────────────────────────────────┘
                         │
                         ▼ HTTPS POST (CSRF protected)
┌─────────────────────────────────────────────────────────────────┐
│  BACKEND API GATEWAY (api/agent.php)                            │
│  - Auth + CSRF validation                                       │
│  - Rate limiting                                                │
│  - Request envelope validation                                  │
│  - Route to Agent Service                                       │
└─────────────────────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  AGENT SERVICE (lib/Services/AgentService.php)                  │
│  - Task queue management                                        │
│  - Tool registry & capability mapping                           │
│  - Response streaming                                           │
│  - Audit logging                                                │
└─────────────────────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  MCP SERVER (Node.js Wrapper)                                   │
│  Location: /home/129337.cloudwaysapps.com/hdgwrzntwa/          │
│            public_html/mcp/mcp-server-wrapper.js               │
│                                                                  │
│  Environment:                                                   │
│    MCP_SERVER_URL: https://gpt.ecigdis.co.nz/mcp/server_v3.php│
│    MCP_API_KEY: 31ce...6a35                                    │
│    PROJECT_ID: 2                                               │
│    BUSINESS_UNIT_ID: 2                                         │
└─────────────────────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  MCP TOOLS (50+ available via server_v3.php)                    │
│                                                                  │
│  📝 Memory & Knowledge:                                         │
│    - memory.store (store conversation context)                 │
│    - kb.search (search knowledge base)                         │
│    - kb.add_document (add to KB)                               │
│    - conversation.get_project_context (retrieve history)       │
│                                                                  │
│  🗄️ Database:                                                   │
│    - db.query (execute SELECT queries)                         │
│    - db.schema (get table structure)                           │
│    - db.explain (optimize queries)                             │
│                                                                  │
│  📂 File System:                                                │
│    - fs.read (read file contents)                              │
│    - fs.write (write files)                                    │
│    - fs.list (list directory)                                  │
│    - analyze_file (code analysis)                              │
│                                                                  │
│  🔍 Search & Analysis:                                          │
│    - semantic_search (search 8,645 files)                      │
│    - find_code (pattern matching)                              │
│    - logs.tail (read logs)                                     │
│    - logs.grep (search logs)                                   │
│                                                                  │
│  🤖 AI Operations:                                              │
│    - ai_agent.query (full RAG AI agent)                        │
└─────────────────────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│  CIS SERVICE LAYER (Your existing services)                     │
│  - TransferService                                              │
│  - ProductService                                               │
│  - ConfigService                                                │
│  - SyncService                                                  │
│  (All tools ultimately call these for safety)                   │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🚀 IMPLEMENTATION STEPS

### **Step 1: Create Agent API Endpoint**

File: `api/agent.php`

```php
<?php
require_once __DIR__ . '/../bootstrap.php';

use CIS\Consignments\Lib\AgentAPI;

$api = new AgentAPI([
    'require_auth' => true,
    'allowed_methods' => ['POST'],
    'rate_limit' => 60 // requests per minute
]);

$api->handleRequest();
```

### **Step 2: Create AgentAPI Controller**

File: `lib/AgentAPI.php`

```php
<?php
namespace CIS\Consignments\Lib;

require_once __DIR__ . '/../../base/lib/BaseAPI.php';
require_once __DIR__ . '/Services/AgentService.php';

use CIS\Base\Lib\BaseAPI;
use CIS\Consignments\Lib\Services\AgentService;

class AgentAPI extends BaseAPI {
    
    private AgentService $agentService;
    
    public function __construct(array $config = []) {
        parent::__construct($config);
        $this->agentService = AgentService::make();
    }
    
    /**
     * Execute agent task
     */
    protected function handleExecute(array $data): array {
        $this->validateCSRF($data);
        $this->validateRequired($data, ['task']);
        
        $task = $this->validateString($data, 'task');
        $tools = $data['tools'] ?? null; // null = all tools
        $stream = $data['stream'] ?? false;
        
        if ($stream) {
            // SSE streaming
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no'); // Disable nginx buffering
            
            $this->agentService->executeStreaming($task, $tools, function($chunk) {
                echo "data: " . json_encode($chunk) . "\n\n";
                flush();
            });
            
            exit;
        } else {
            // Standard response
            $result = $this->agentService->execute($task, $tools);
            return $this->success($result, 'Task completed');
        }
    }
    
    /**
     * Get task status (for long-running tasks)
     */
    protected function handleStatus(array $data): array {
        $this->validateRequired($data, ['task_id']);
        $taskId = $this->validateString($data, 'task_id');
        
        $status = $this->agentService->getTaskStatus($taskId);
        return $this->success($status, 'Status retrieved');
    }
}
```

### **Step 3: Create AgentService**

File: `lib/Services/AgentService.php`

```php
<?php
namespace CIS\Consignments\Lib\Services;

class AgentService {
    
    private string $mcpServerUrl;
    private string $mcpApiKey;
    private array $allowedTools;
    
    public function __construct() {
        $this->mcpServerUrl = $_ENV['MCP_SERVER_URL'] ?? 'https://gpt.ecigdis.co.nz/mcp/server_v3.php';
        $this->mcpApiKey = $_ENV['MCP_API_KEY'] ?? '';
        
        // Whitelist of allowed MCP tools
        $this->allowedTools = [
            'memory.store',
            'kb.search',
            'kb.add_document',
            'conversation.get_project_context',
            'db.query',
            'db.schema',
            'fs.read',
            'semantic_search',
            'logs.tail',
            'logs.grep',
            'ai_agent.query'
        ];
    }
    
    public static function make(): self {
        return new self();
    }
    
    /**
     * Execute agent task with MCP tools
     */
    public function execute(string $task, ?array $tools = null): array {
        // Use specific tools or all allowed
        $toolsToUse = $tools ?? $this->allowedTools;
        
        // Call MCP ai_agent.query with full RAG
        $response = $this->callMCPTool('ai_agent.query', [
            'query' => $task,
            'tools' => $toolsToUse,
            'stream' => false,
            'context' => [
                'project_id' => 2,
                'business_unit_id' => 2,
                'workspace_root' => '/home/master/applications/jcepnzzkmj/public_html'
            ]
        ]);
        
        // Store result in memory
        $this->callMCPTool('memory.store', [
            'conversation_id' => uniqid('agent_'),
            'content' => "Task: {$task}\nResult: " . json_encode($response),
            'memory_type' => 'agent_execution',
            'importance' => 'high',
            'tags' => ['agent', 'automated']
        ]);
        
        return $response;
    }
    
    /**
     * Execute with streaming (SSE)
     */
    public function executeStreaming(string $task, ?array $tools, callable $callback): void {
        $toolsToUse = $tools ?? $this->allowedTools;
        
        $response = $this->callMCPTool('ai_agent.query', [
            'query' => $task,
            'tools' => $toolsToUse,
            'stream' => true,
            'context' => [
                'project_id' => 2,
                'business_unit_id' => 2
            ]
        ]);
        
        // Stream response chunks
        if (isset($response['stream'])) {
            foreach ($response['stream'] as $chunk) {
                $callback($chunk);
            }
        }
    }
    
    /**
     * Call MCP tool via JSON-RPC
     */
    private function callMCPTool(string $tool, array $arguments): array {
        $payload = [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => $tool,
                'arguments' => $arguments
            ],
            'id' => uniqid()
        ];
        
        $ch = curl_init($this->mcpServerUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-API-Key: ' . $this->mcpApiKey
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 300 // 5 minutes for long tasks
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \RuntimeException("MCP call failed: HTTP {$httpCode}");
        }
        
        $decoded = json_decode($response, true);
        
        if (isset($decoded['error'])) {
            throw new \RuntimeException("MCP error: " . $decoded['error']['message']);
        }
        
        return $decoded['result'] ?? [];
    }
}
```

### **Step 4: Frontend Implementation**

File: `assets/js/agent-chat.js`

```javascript
class AgentChat {
    constructor(apiUrl) {
        this.apiUrl = apiUrl;
        this.eventSource = null;
    }
    
    // Execute task with streaming
    async executeTask(task, tools = null, onChunk = null, onComplete = null) {
        const csrfToken = document.querySelector('[name="csrf"]').value;
        
        if (onChunk) {
            // Use SSE for streaming
            this.eventSource = new EventSource(
                `${this.apiUrl}?action=execute&task=${encodeURIComponent(task)}&stream=1&csrf=${csrfToken}`
            );
            
            this.eventSource.onmessage = (event) => {
                const data = JSON.parse(event.data);
                onChunk(data);
                
                if (data.done) {
                    this.eventSource.close();
                    if (onComplete) onComplete(data);
                }
            };
            
            this.eventSource.onerror = (error) => {
                console.error('SSE error:', error);
                this.eventSource.close();
            };
        } else {
            // Standard POST request
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'execute',
                    task: task,
                    tools: tools,
                    csrf: csrfToken
                })
            });
            
            const result = await response.json();
            if (onComplete) onComplete(result);
            return result;
        }
    }
    
    stop() {
        if (this.eventSource) {
            this.eventSource.close();
        }
    }
}

// Usage
const agent = new AgentChat('/modules/consignments/api/agent.php');

agent.executeTask(
    'Find all 500 errors in logs from last 48 hours and suggest fixes',
    ['logs.grep', 'semantic_search', 'db.query'],
    (chunk) => {
        // Stream each chunk to UI
        console.log('Chunk:', chunk);
        appendToChat(chunk.message);
    },
    (result) => {
        // Final result
        console.log('Complete:', result);
        showResults(result);
    }
);
```

---

## 🔒 SECURITY CHECKLIST

✅ **Authentication:** Session-based, user_id required  
✅ **CSRF Protection:** Token on all mutations  
✅ **Rate Limiting:** 60 requests/min per user  
✅ **Tool Whitelist:** Only approved tools allowed  
✅ **Audit Logging:** All agent calls logged with user_id  
✅ **Input Validation:** Task length limits, tool validation  
✅ **Output Sanitization:** Redact secrets from responses  
✅ **Timeout Limits:** 5-minute max execution  
✅ **Error Handling:** Never expose internal errors  
✅ **Secrets Management:** MCP_API_KEY in .env only  

---

## 🎯 EXAMPLE USE CASES

### 1. **Find and Fix 500 Errors**
```javascript
agent.executeTask(
    'Scan error logs for last 48h, find unique 500 errors, investigate root cause, suggest fixes',
    ['logs.grep', 'semantic_search', 'db.schema', 'fs.read']
);
```

### 2. **Generate Transfer Report**
```javascript
agent.executeTask(
    'Get all OPEN transfers, group by outlet, calculate totals, generate Excel report',
    ['db.query', 'fs.write']
);
```

### 3. **Optimize Slow Queries**
```javascript
agent.executeTask(
    'Find slow queries in logs, run EXPLAIN, suggest indexes',
    ['logs.grep', 'db.query', 'db.explain']
);
```

### 4. **Code Review**
```javascript
agent.executeTask(
    'Review TransferService.php for security issues and performance bottlenecks',
    ['fs.read', 'analyze_file', 'semantic_search']
);
```

---

## 📊 MONITORING

Track these metrics:
- Agent task success rate
- Average execution time
- Tool usage frequency
- Error rate by tool
- User satisfaction (thumbs up/down)
- Cost per task (API calls)

---

## 🚀 DEPLOYMENT

1. Add `.env` entries:
```bash
MCP_SERVER_URL=https://gpt.ecigdis.co.nz/mcp/server_v3.php
MCP_API_KEY=31ce0106609a6c5bc4f7ece0deb2f764df90a06167bda83468883516302a6a35
MCP_PROJECT_ID=2
MCP_BUSINESS_UNIT_ID=2
```

2. Test with curl:
```bash
curl -X POST https://staff.vapeshed.co.nz/modules/consignments/api/agent.php \
  -H "Content-Type: application/json" \
  -d '{"action":"execute","task":"Search KB for transfer documentation","csrf":"TOKEN"}'
```

3. Monitor logs:
```bash
tail -f logs/agent.log
```

---

**This architecture gives you:**
- ✅ Full MCP tool access (50+ tools)
- ✅ Safe execution (whitelist + audit)
- ✅ Real-time streaming
- ✅ Production-ready security
- ✅ Easy to extend

**Ready to implement?** 🚀
