# 🚀 CIS JavaScript Stack - Complete Guide

## Overview

The CIS JavaScript stack provides enterprise-grade features for building modern web applications with professional error handling, real-time communication, and advanced Web APIs.

## Architecture

```
┌─────────────────────────────────────────┐
│     CIS Error Handler                   │
│  - Beautiful error popups               │
│  - Multiple severity levels             │
│  - Copy to clipboard                    │
│  - Stack trace viewing                  │
│  - Auto AJAX/fetch interception         │
└─────────────────────────────────────────┘
                    ▼
┌─────────────────────────────────────────┐
│     CIS Core Utilities                  │
│  - AJAX/Fetch helpers                   │
│  - Format utilities                     │
│  - Toast notifications                  │
│  - LocalStorage helpers                 │
│  - Form utilities                       │
│  - Validation helpers                   │
│  - WebSocket manager                    │
│  - Modern Web APIs                      │
└─────────────────────────────────────────┘
                    ▼
┌─────────────────────────────────────────┐
│     Module Extensions                   │
│  (e.g., CIS.Consignments)              │
│  - Module-specific utilities            │
│  - Inherits all BASE features           │
└─────────────────────────────────────────┘
```

---

## 🎯 1. Error Handler (CIS.ErrorHandler)

### Beautiful Error Popups

```javascript
// Show error (red)
CIS.ErrorHandler.error('Failed to save data', 'Details about the error...');

// Show warning (yellow)
CIS.ErrorHandler.warning('Data might be incomplete', 'Some fields are missing');

// Show info (blue)
CIS.ErrorHandler.info('Loading complete', '1,234 records loaded');

// Show success (green)
CIS.ErrorHandler.success('Transfer saved!', 'ID: 12345');
```

### Features

✅ **Auto-dismiss** (except errors)
✅ **Copy to clipboard** button
✅ **Stack trace toggle**
✅ **Responsive design**
✅ **Multiple severity levels**
✅ **Timestamp display**
✅ **Auto-intercepts AJAX errors**

### Configuration

```javascript
CIS.ErrorHandler.configure({
    position: 'top-right', // top-right, top-left, bottom-right, bottom-left, center
    showStackTrace: true,
    autoShow: true,
    debugMode: false
});
```

### Manual Error Handling

```javascript
// Custom error with details
CIS.ErrorHandler.show({
    message: 'Database connection failed',
    details: 'Host: localhost\nPort: 3306\nError: Connection refused',
    severity: 'error',
    stack: new Error().stack
});

// Get all errors
const errors = CIS.ErrorHandler.getErrors();

// Clear all errors
CIS.ErrorHandler.clearAll();

// Dismiss specific error
CIS.ErrorHandler.dismissAlert('error_id_here');
```

---

## 🛠️ 2. Core Utilities (CIS.Core or CIS.$)

### AJAX Helpers

```javascript
// GET request
CIS.$.get('/api/users', { role: 'admin' })
    .then(users => console.log(users))
    .catch(error => console.error(error)); // Auto-handled by ErrorHandler

// POST request
CIS.$.post('/api/users', { name: 'John', email: 'john@example.com' })
    .then(response => console.log(response));

// PUT request
CIS.$.put('/api/users/123', { name: 'Jane' });

// DELETE request
CIS.$.delete('/api/users/123');

// Custom AJAX
CIS.$.ajax('/api/endpoint', {
    method: 'PATCH',
    body: { status: 'active' },
    headers: { 'X-Custom': 'value' }
});
```

**Features:**
- ✅ Automatic CSRF token injection
- ✅ Auto error handling with ErrorHandler
- ✅ JSON body serialization
- ✅ Credential management

### Format Utilities

```javascript
// Currency
CIS.$.formatCurrency(1234.56);           // "$1234.56"
CIS.$.formatCurrency(99.9, false);       // "99.90" (no symbol)

// Date
CIS.$.formatDate('2025-11-04');          // "Nov 4, 2025"
CIS.$.formatDateTime('2025-11-04 14:30'); // "Nov 4, 2025, 2:30 PM"

// Number
CIS.$.formatNumber(1234567.89, 2);       // "1,234,567.89"

// File size
CIS.$.formatFileSize(1536000);           // "1.46 MB"

// Phone (NZ)
CIS.$.formatPhone('0211234567');         // "021 123 4567"
```

### User Feedback

```javascript
// Toast notifications (uses ErrorHandler)
CIS.$.toast('Saved successfully!', 'success');
CIS.$.toast('Something went wrong', 'error');
CIS.$.toast('Please review', 'warning');
CIS.$.toast('Processing...', 'info');

// Confirmation dialog
CIS.$.confirm('Delete this item?',
    () => console.log('Confirmed'),
    () => console.log('Cancelled')
);

// Loading overlay
CIS.$.showLoading('Please wait...');
// ... do work ...
CIS.$.hideLoading();
```

### LocalStorage Helpers

```javascript
// Store data
CIS.$.store('userSettings', { theme: 'dark', lang: 'en' });

// Retrieve data
const settings = CIS.$.retrieve('userSettings', { theme: 'light' });

// Remove data
CIS.$.forget('userSettings');

// Clear all CIS storage
CIS.$.clearStorage();
```

### Form Utilities

```javascript
// Serialize form to object
const formData = CIS.$.serializeForm(document.getElementById('myForm'));
// { name: 'John', email: 'john@example.com' }

// Populate form with data
CIS.$.populateForm(form, { name: 'Jane', email: 'jane@example.com' });

// Reset form and clear validation
CIS.$.resetForm(form);
```

### Validation Helpers

```javascript
CIS.$.isEmail('test@example.com');       // true
CIS.$.isPhone('021 123 4567');           // true
CIS.$.isUrl('https://example.com');      // true
CIS.$.isEmpty('');                        // true
CIS.$.isEmpty([]);                        // true
CIS.$.isEmpty({});                        // true
```

---

## 🌐 3. WebSocket Manager

```javascript
const ws = CIS.$.connectWebSocket('wss://example.com/socket', {
    reconnect: true,
    reconnectInterval: 5000,
    maxReconnectAttempts: 10,

    onOpen: (event) => {
        console.log('Connected!');
        ws.send({ type: 'subscribe', channel: 'updates' });
    },

    onMessage: (data, event) => {
        console.log('Received:', data);
    },

    onClose: (event) => {
        console.log('Connection closed');
    },

    onError: (event) => {
        console.error('WebSocket error');
    }
});

// Send message
ws.send({ action: 'ping' });

// Check connection
if (ws.isConnected()) {
    ws.send({ data: 'test' });
}

// Get state
console.log(ws.getState()); // CONNECTING, OPEN, CLOSING, CLOSED

// Close connection
ws.close();

// Reconnect
ws.reconnect();
```

---

## 📡 4. Server-Sent Events (SSE)

```javascript
const sse = CIS.$.connectSSE('/api/events', {
    reconnect: true,
    reconnectInterval: 3000,

    onMessage: (data) => {
        console.log('Event received:', data);
    },

    onError: (event) => {
        console.error('SSE error');
    }
});

// Close connection
sse.close();
```

---

## 📝 5. Advanced Logging

```javascript
// Create logger
const logger = CIS.$.createLogger('MyModule');

// Log messages
logger.debug('Debug info', { data: 123 });
logger.info('User logged in', { userId: 456 });
logger.warn('Deprecation warning');
logger.error('Failed to load', new Error('Network error'));

// Grouped logging
logger.group('Database Operations');
logger.info('Query executed');
logger.info('Results fetched');
logger.groupEnd();

// Table display
logger.table([
    { id: 1, name: 'John' },
    { id: 2, name: 'Jane' }
]);

// Performance timing
logger.time('API Call');
// ... do work ...
logger.timeEnd('API Call');

// Set log level
logger.setLevel('warn'); // Only warn and error

// Remote logging
logger.setRemoteEndpoint('/api/logs');
```

---

## 🔔 6. Browser Notifications

```javascript
// Request permission
const granted = await CIS.$.requestNotificationPermission();

if (granted) {
    // Show notification
    const notification = CIS.$.notify('New Message', {
        body: 'You have a new message from John',
        icon: '/assets/images/icon.png',
        badge: '/assets/images/badge.png',
        vibrate: [200, 100, 200],
        requireInteraction: false,
        data: { messageId: 123 }
    });

    notification.onclick = () => {
        console.log('Notification clicked');
        window.focus();
    };
}
```

---

## 👷 7. Web Workers

```javascript
// Create worker
const worker = CIS.$.createWorker(function() {
    // This runs in worker thread
    self.onmessage = function(e) {
        const result = e.data * 2;
        self.postMessage(result);
    };
});

// Send data to worker
worker.postMessage(42);

// Receive data from worker
worker.onMessage((result) => {
    console.log('Worker result:', result); // 84
});

// Handle errors
worker.onError((error) => {
    console.error('Worker error:', error);
});

// Terminate worker
worker.terminate();
```

---

## 💾 8. IndexedDB

```javascript
// Open database
const db = await CIS.$.openDB('MyApp', 1, (db, oldVersion, newVersion) => {
    // Upgrade database
    if (!db.objectStoreNames.contains('users')) {
        const store = db.createObjectStore('users', { keyPath: 'id', autoIncrement: true });
        store.createIndex('email', 'email', { unique: true });
    }
});

// Use database
const transaction = db.transaction(['users'], 'readwrite');
const store = transaction.objectStore('users');
store.add({ name: 'John', email: 'john@example.com' });
```

---

## 📍 9. Geolocation

```javascript
// Get current position
try {
    const position = await CIS.$.getCurrentPosition();
    console.log('Location:', position.latitude, position.longitude);
} catch (error) {
    console.error('Geolocation error:', error);
}

// Watch position changes
const watcher = CIS.$.watchPosition(
    (position) => {
        console.log('Position updated:', position);
    },
    (error) => {
        console.error('Position error:', error);
    },
    { enableHighAccuracy: true }
);

// Stop watching
watcher.clear();
```

---

## 📋 10. Clipboard API

```javascript
// Copy to clipboard (with toast)
CIS.$.copyToClipboard('Hello World');

// Advanced clipboard operations
await CIS.$.writeClipboard('Text content', 'text');
await CIS.$.writeClipboard('<strong>HTML</strong>', 'html');
await CIS.$.writeClipboard(imageBlob, 'image');

// Read from clipboard
const text = await CIS.$.readClipboard();
```

---

## 📤 11. Web Share API

```javascript
// Share content
const shared = await CIS.$.share({
    title: 'Check this out!',
    text: 'Amazing content',
    url: 'https://example.com'
});

if (shared) {
    console.log('Shared successfully');
}
```

---

## 📳 12. Vibration API

```javascript
// Vibrate once
CIS.$.vibrate(200);

// Vibrate pattern (vibrate, pause, vibrate...)
CIS.$.vibrate([100, 50, 100, 50, 100]);
```

---

## 🔋 13. Battery API

```javascript
const battery = await CIS.$.getBatteryInfo();
console.log('Battery level:', battery.level * 100 + '%');
console.log('Charging:', battery.charging);
```

---

## 🌐 14. Network Information

```javascript
// Get network info
const network = CIS.$.getNetworkInfo();
console.log('Connection type:', network.effectiveType); // 4g, 3g, 2g, slow-2g
console.log('Online:', network.online);
console.log('Downlink:', network.downlink, 'Mbps');

// Watch network changes
const watcher = CIS.$.watchNetworkStatus((info) => {
    if (!info.online) {
        CIS.$.toast('You are offline', 'warning');
    }
});

// Stop watching
watcher.stop();
```

---

## ⚡ 15. Performance API

```javascript
// Mark performance points
CIS.$.mark('start-operation');
// ... do work ...
CIS.$.mark('end-operation');

// Measure duration
const measure = CIS.$.measure('operation-duration', 'start-operation', 'end-operation');
console.log('Operation took:', measure.duration, 'ms');

// Get performance metrics
const metrics = CIS.$.getPerformanceMetrics();
console.log('Page load:', metrics.loadTime, 'ms');
console.log('First paint:', metrics.firstPaint, 'ms');
console.log('Memory used:', metrics.memory.used);
```

---

## 👁️ 16. Intersection Observer (Lazy Loading)

```javascript
// Lazy load images
const images = document.querySelectorAll('img[data-src]');
const observer = CIS.$.observeIntersection(images, (img) => {
    img.src = img.dataset.src;
    img.removeAttribute('data-src');
}, { threshold: 0.1 });

// Infinite scroll
const sentinel = document.querySelector('.sentinel');
CIS.$.observeIntersection(sentinel, () => {
    loadMoreItems();
});

// Stop observing
observer.disconnect();
```

---

## 🔄 17. Mutation Observer

```javascript
// Watch DOM changes
const observer = CIS.$.observeMutations(document.body, (mutations) => {
    mutations.forEach(mutation => {
        console.log('DOM changed:', mutation.type);
    });
}, {
    childList: true,
    attributes: true,
    subtree: true
});

// Stop observing
observer.disconnect();
```

---

## 📏 18. Resize Observer

```javascript
// Watch element size changes
const elements = document.querySelectorAll('.resizable');
const observer = CIS.$.observeResize(elements, (element, rect) => {
    console.log('Element resized:', rect.width, 'x', rect.height);
});

// Stop observing
observer.disconnect();
```

---

## 🎤 19. Speech Recognition

```javascript
// Start voice recognition
const recognition = CIS.$.startVoiceRecognition({
    continuous: true,
    interimResults: true,
    lang: 'en-US',

    onResult: (results) => {
        results.forEach(result => {
            console.log('Transcript:', result.transcript);
            console.log('Confidence:', result.confidence);
        });
    },

    onError: (error) => {
        console.error('Recognition error:', error);
    }
});

// Stop recognition
recognition.stop();
```

---

## 🔊 20. Text-to-Speech

```javascript
// Speak text
const speaker = CIS.$.speak('Hello, welcome to CIS!', {
    lang: 'en-US',
    rate: 1.0,
    pitch: 1.0,
    volume: 1.0,
    onEnd: () => console.log('Speech finished')
});

// Control playback
speaker.pause();
speaker.resume();
speaker.cancel();
```

---

## 📁 21. File API

```javascript
// Read file as text
const text = await CIS.$.readFileAsText(file);

// Read file as data URL
const dataURL = await CIS.$.readFileAsDataURL(file);

// Download file
CIS.$.downloadFile('Hello World', 'hello.txt', 'text/plain');
CIS.$.downloadFile(jsonData, 'data.json', 'application/json');
```

---

## 🛠️ 22. Utility Functions

```javascript
// Debounce
const debouncedSearch = CIS.$.debounce((query) => {
    console.log('Searching for:', query);
}, 300);

// Throttle
const throttledScroll = CIS.$.throttle(() => {
    console.log('Scroll event');
}, 100);

// Generate unique ID
const id = CIS.$.uniqueId('user'); // "user_1699123456789_a1b2c3"

// Get URL parameter
const userId = CIS.$.getParam('user_id'); // from ?user_id=123

// Check if element is in viewport
if (CIS.$.isInViewport(element)) {
    console.log('Element is visible');
}
```

---

## 🎯 Module Extension Example

```javascript
// Extend CIS for your module
window.CIS.MyModule = {
    // Inherit core features
    ajax: CIS.$.ajax,
    toast: CIS.$.toast,
    logger: CIS.$.createLogger('MyModule'),

    // Module-specific features
    doSomething() {
        this.logger.info('Doing something...');
        return this.ajax('/api/my-endpoint');
    }
};
```

---

## 🔧 Configuration

```javascript
// Configure CIS Core
CIS.$.configure({
    apiBase: '/api/v1',
    dateFormat: 'en-NZ',
    currency: 'NZD',
    currencySymbol: '$',
    debug: true
});

// Get configuration
const debug = CIS.$.getConfig('debug', false);
```

---

## 📦 What's Included

### Automatic Features

✅ **Global error catching**
✅ **AJAX error interception**
✅ **CSRF token injection**
✅ **Beautiful error UI**
✅ **Copy to clipboard**
✅ **Stack trace viewing**
✅ **Auto-reconnect for WebSocket/SSE**
✅ **Loading overlays**
✅ **Toast notifications**

### Supported Web APIs

✅ WebSocket
✅ Server-Sent Events (SSE)
✅ Notifications
✅ Geolocation
✅ Web Share
✅ Clipboard
✅ IndexedDB
✅ Web Workers
✅ Speech Recognition
✅ Speech Synthesis
✅ Battery Status
✅ Network Information
✅ Intersection Observer
✅ Mutation Observer
✅ Resize Observer
✅ Performance API
✅ Page Visibility
✅ Vibration

---

## 🚀 Quick Start

1. **Include libraries** (already in CIS Classic theme):
```html
<script src="/modules/base/_assets/js/cis-error-handler.js"></script>
<script src="/modules/base/_assets/js/cis-core.js"></script>
```

2. **Use immediately**:
```javascript
// All features available at CIS.$ or CIS.Core
CIS.$.toast('Ready to go!', 'success');
```

3. **Check available features**:
```javascript
CIS.$.configure({ debug: true }); // See feature detection in console
```

---

## 📚 Browser Support

| Feature | Chrome | Firefox | Safari | Edge |
|---------|--------|---------|--------|------|
| Error Handler | ✅ | ✅ | ✅ | ✅ |
| Core Utils | ✅ | ✅ | ✅ | ✅ |
| WebSocket | ✅ | ✅ | ✅ | ✅ |
| SSE | ✅ | ✅ | ✅ | ✅ |
| Notifications | ✅ | ✅ | ✅ | ✅ |
| Web Workers | ✅ | ✅ | ✅ | ✅ |
| Speech API | ✅ | ❌ | ✅ | ✅ |
| Web Share | ✅ | ❌ | ✅ | ✅ |

---

## 🎓 Best Practices

1. **Always use CIS.$ for common operations**
2. **Let ErrorHandler handle errors automatically**
3. **Use debounce/throttle for frequent events**
4. **Create module-specific loggers**
5. **Use WebSocket for real-time features**
6. **Leverage Intersection Observer for performance**
7. **Store user preferences with LocalStorage helpers**

---

**Version:** 1.0.0
**Created:** November 4, 2025
**Status:** ✅ Production Ready
