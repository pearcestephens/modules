#!/usr/bin/env node

/**
 * Interactive Staff Portal Crawler with Real-Time Control
 * 
 * Features:
 * - Remote control via HTTP API
 * - Pause/Resume/Stop commands
 * - Real-time screenshots on demand
 * - Live status updates
 * - JavaScript debugger integration
 * - Chat-based interaction
 * 
 * Usage:
 *   node scripts/interactive-crawler.js --username=USER --password=PASS --port=3000
 *   
 * Control via HTTP:
 *   curl http://localhost:3000/pause
 *   curl http://localhost:3000/resume
 *   curl http://localhost:3000/screenshot
 *   curl http://localhost:3000/status
 *   curl http://localhost:3000/evaluate -d '{"code":"document.title"}'
 */

const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');
const http = require('http');
const yargs = require('yargs/yargs');
const { hideBin } = require('yargs/helpers');

// Parse arguments
const argv = yargs(hideBin(process.argv))
  .option('username', { alias: 'u', type: 'string', demandOption: true })
  .option('password', { alias: 'p', type: 'string', demandOption: true })
  .option('port', { type: 'number', default: 3000, description: 'Control server port' })
  .option('slow-mo', { type: 'number', default: 0 })
  .option('wait', { type: 'number', default: 3000 })
  .help()
  .argv;

// Global state
const state = {
  browser: null,
  page: null,
  isPaused: false,
  isRunning: true,
  currentStep: 'initializing',
  screenshots: [],
  messages: [],
  errors: [],
  networkLogs: [],
  consoleLogs: []
};

// Output directory
const timestamp = new Date().toISOString().replace(/[:.]/g, '-').split('T')[0] + '_' + 
                  new Date().toTimeString().split(' ')[0].replace(/:/g, '');
const outputDir = path.join(__dirname, '..', 'reports', `interactive_crawl_${timestamp}`);
fs.mkdirSync(outputDir, { recursive: true });
fs.mkdirSync(path.join(outputDir, 'screenshots'), { recursive: true });

/**
 * Log message with timestamp
 */
function log(message, type = 'info') {
  const entry = {
    timestamp: new Date().toISOString(),
    type,
    message
  };
  
  state.messages.push(entry);
  console.log(`[${entry.timestamp}] [${type.toUpperCase()}] ${message}`);
  
  // Save to file
  fs.appendFileSync(
    path.join(outputDir, 'messages.log'),
    JSON.stringify(entry) + '\n'
  );
}

/**
 * Capture screenshot
 */
async function captureScreenshot(description = 'screenshot') {
  if (!state.page) return null;
  
  const timestamp = Date.now();
  const filename = `${description.replace(/[^a-z0-9]/gi, '_')}_${timestamp}.png`;
  const filepath = path.join(outputDir, 'screenshots', filename);
  
  await state.page.screenshot({
    path: filepath,
    fullPage: true
  });
  
  const screenshot = {
    filename,
    filepath,
    description,
    timestamp: new Date().toISOString(),
    url: state.page.url()
  };
  
  state.screenshots.push(screenshot);
  log(`Screenshot captured: ${description}`, 'screenshot');
  
  return screenshot;
}

/**
 * Setup page monitoring
 */
async function setupMonitoring(page) {
  // Console messages
  page.on('console', msg => {
    const entry = {
      type: msg.type(),
      text: msg.text(),
      location: msg.location(),
      timestamp: new Date().toISOString()
    };
    state.consoleLogs.push(entry);
    log(`Console [${entry.type}]: ${entry.text}`, 'console');
  });

  // Page errors
  page.on('pageerror', error => {
    const entry = {
      message: error.message,
      stack: error.stack,
      timestamp: new Date().toISOString()
    };
    state.errors.push(entry);
    log(`JavaScript Error: ${error.message}`, 'error');
  });

  // Network monitoring
  page.on('response', async response => {
    const status = response.status();
    if (status === 404 || status >= 500) {
      const entry = {
        url: response.url(),
        status,
        timestamp: new Date().toISOString()
      };
      state.networkLogs.push(entry);
      log(`HTTP ${status}: ${response.url()}`, 'network');
    }
  });
}

/**
 * Wait for user to resume (if paused)
 */
async function checkPause() {
  while (state.isPaused && state.isRunning) {
    log('Paused... waiting for resume command', 'status');
    await new Promise(resolve => setTimeout(resolve, 1000));
  }
}

/**
 * Apply stealth techniques
 */
async function applyStealth(page) {
  await page.evaluateOnNewDocument(() => {
    Object.defineProperty(navigator, 'webdriver', {
      get: () => false
    });
    window.chrome = { runtime: {} };
    Object.defineProperty(navigator, 'plugins', {
      get: () => [1, 2, 3, 4, 5]
    });
    Object.defineProperty(navigator, 'languages', {
      get: () => ['en-US', 'en']
    });
  });

  await page.setViewport({ width: 1920, height: 1080 });
  await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
}

/**
 * Login to staff portal
 */
async function login(page, username, password) {
  state.currentStep = 'login';
  log('Starting login process', 'status');
  
  await checkPause();
  
  // Navigate to login
  log('Navigating to https://staff.vapeshed.co.nz', 'action');
  await page.goto('https://staff.vapeshed.co.nz', {
    waitUntil: 'networkidle2',
    timeout: 60000
  });
  
  await page.waitForTimeout(argv.wait);
  await captureScreenshot('login_page');
  
  await checkPause();
  
  // Find form elements
  const usernameSelector = 'input[name="username"], input[name="email"], input[type="email"]';
  const passwordSelector = 'input[name="password"], input[type="password"]';
  const submitSelector = 'button[type="submit"], input[type="submit"]';
  
  // Type username
  log('Entering username', 'action');
  await page.waitForSelector(usernameSelector, { timeout: 10000 });
  await page.type(usernameSelector, username, { delay: 100 });
  
  await checkPause();
  
  // Type password
  log('Entering password', 'action');
  await page.type(passwordSelector, password, { delay: 100 });
  
  await captureScreenshot('before_login');
  await checkPause();
  
  // Submit
  log('Submitting login form', 'action');
  await page.click(submitSelector);
  
  try {
    await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 });
  } catch (e) {
    await page.waitForTimeout(5000);
  }
  
  await captureScreenshot('after_login');
  log('Login completed', 'status');
}

/**
 * Main crawler function
 */
async function crawl() {
  log('🕷️  Interactive Crawler Starting...', 'status');
  log(`📁 Output directory: ${outputDir}`, 'info');
  log(`🎮 Control server: http://localhost:${argv.port}`, 'info');
  
  // Launch browser
  state.currentStep = 'launching_browser';
  log('Launching browser', 'status');
  
  state.browser = await puppeteer.launch({
    headless: 'new',
    executablePath: '/usr/bin/chromium',
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-blink-features=AutomationControlled',
      '--disable-gpu',
      '--disable-dev-shm-usage'
    ],
    slowMo: argv['slow-mo']
  });
  
  state.page = await state.browser.newPage();
  await applyStealth(state.page);
  await setupMonitoring(state.page);
  
  // Enable JavaScript debugger
  const client = await state.page.target().createCDPSession();
  await client.send('Debugger.enable');
  log('JavaScript debugger enabled', 'status');
  
  try {
    // Login
    await login(state.page, argv.username, argv.password);
    
    // Get page info
    state.currentStep = 'analyzing_page';
    log('Analyzing page structure', 'action');
    
    const pageInfo = await state.page.evaluate(() => {
      return {
        title: document.title,
        url: window.location.href,
        links: Array.from(document.querySelectorAll('a')).length,
        buttons: Array.from(document.querySelectorAll('button')).length,
        forms: Array.from(document.querySelectorAll('form')).length
      };
    });
    
    log(`Page: ${pageInfo.title}`, 'info');
    log(`Found: ${pageInfo.links} links, ${pageInfo.buttons} buttons, ${pageInfo.forms} forms`, 'info');
    
    await captureScreenshot('page_analyzed');
    
    // Wait for commands
    state.currentStep = 'waiting_for_commands';
    log('Ready for commands! Use the control API to interact.', 'status');
    log(`Example: curl http://localhost:${argv.port}/screenshot`, 'info');
    
    // Keep alive
    while (state.isRunning) {
      await checkPause();
      await new Promise(resolve => setTimeout(resolve, 1000));
    }
    
  } catch (error) {
    log(`Error: ${error.message}`, 'error');
    state.errors.push({
      message: error.message,
      stack: error.stack,
      timestamp: new Date().toISOString()
    });
  } finally {
    if (state.browser) {
      await state.browser.close();
    }
    log('Crawler stopped', 'status');
  }
}

/**
 * HTTP Control Server
 */
const server = http.createServer(async (req, res) => {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Content-Type', 'application/json');
  
  const url = req.url;
  
  try {
    // Status
    if (url === '/status') {
      res.writeHead(200);
      res.end(JSON.stringify({
        success: true,
        state: {
          currentStep: state.currentStep,
          isPaused: state.isPaused,
          isRunning: state.isRunning,
          url: state.page ? state.page.url() : null,
          screenshots: state.screenshots.length,
          messages: state.messages.length,
          errors: state.errors.length
        }
      }, null, 2));
      return;
    }
    
    // Pause
    if (url === '/pause') {
      state.isPaused = true;
      log('⏸️  Paused by user', 'control');
      res.writeHead(200);
      res.end(JSON.stringify({ success: true, message: 'Paused' }));
      return;
    }
    
    // Resume
    if (url === '/resume') {
      state.isPaused = false;
      log('▶️  Resumed by user', 'control');
      res.writeHead(200);
      res.end(JSON.stringify({ success: true, message: 'Resumed' }));
      return;
    }
    
    // Stop
    if (url === '/stop') {
      state.isRunning = false;
      log('⏹️  Stopped by user', 'control');
      res.writeHead(200);
      res.end(JSON.stringify({ success: true, message: 'Stopped' }));
      return;
    }
    
    // Screenshot
    if (url === '/screenshot') {
      if (!state.page) {
        res.writeHead(400);
        res.end(JSON.stringify({ success: false, error: 'No page available' }));
        return;
      }
      
      const screenshot = await captureScreenshot('manual_screenshot');
      res.writeHead(200);
      res.end(JSON.stringify({
        success: true,
        screenshot: screenshot,
        message: 'Screenshot captured'
      }, null, 2));
      return;
    }
    
    // Messages
    if (url === '/messages') {
      res.writeHead(200);
      res.end(JSON.stringify({
        success: true,
        messages: state.messages.slice(-50) // Last 50 messages
      }, null, 2));
      return;
    }
    
    // Errors
    if (url === '/errors') {
      res.writeHead(200);
      res.end(JSON.stringify({
        success: true,
        errors: state.errors
      }, null, 2));
      return;
    }
    
    // Screenshots list
    if (url === '/screenshots') {
      res.writeHead(200);
      res.end(JSON.stringify({
        success: true,
        screenshots: state.screenshots
      }, null, 2));
      return;
    }
    
    // Evaluate JavaScript
    if (url === '/evaluate' && req.method === 'POST') {
      let body = '';
      req.on('data', chunk => body += chunk);
      req.on('end', async () => {
        try {
          const { code } = JSON.parse(body);
          
          if (!state.page) {
            res.writeHead(400);
            res.end(JSON.stringify({ success: false, error: 'No page available' }));
            return;
          }
          
          log(`Evaluating: ${code}`, 'evaluate');
          const result = await state.page.evaluate(code);
          
          res.writeHead(200);
          res.end(JSON.stringify({
            success: true,
            result: result
          }, null, 2));
        } catch (error) {
          res.writeHead(500);
          res.end(JSON.stringify({ success: false, error: error.message }));
        }
      });
      return;
    }
    
    // Navigate
    if (url.startsWith('/navigate?url=')) {
      const targetUrl = decodeURIComponent(url.split('url=')[1]);
      
      if (!state.page) {
        res.writeHead(400);
        res.end(JSON.stringify({ success: false, error: 'No page available' }));
        return;
      }
      
      log(`Navigating to: ${targetUrl}`, 'action');
      await state.page.goto(targetUrl, { waitUntil: 'networkidle2' });
      await captureScreenshot('after_navigation');
      
      res.writeHead(200);
      res.end(JSON.stringify({
        success: true,
        message: `Navigated to ${targetUrl}`
      }));
      return;
    }
    
    // Click element
    if (url === '/click' && req.method === 'POST') {
      let body = '';
      req.on('data', chunk => body += chunk);
      req.on('end', async () => {
        try {
          const { selector } = JSON.parse(body);
          
          if (!state.page) {
            res.writeHead(400);
            res.end(JSON.stringify({ success: false, error: 'No page available' }));
            return;
          }
          
          log(`Clicking: ${selector}`, 'action');
          await state.page.click(selector);
          await state.page.waitForTimeout(argv.wait);
          await captureScreenshot('after_click');
          
          res.writeHead(200);
          res.end(JSON.stringify({
            success: true,
            message: `Clicked ${selector}`
          }));
        } catch (error) {
          res.writeHead(500);
          res.end(JSON.stringify({ success: false, error: error.message }));
        }
      });
      return;
    }
    
    // Help
    if (url === '/' || url === '/help') {
      res.writeHead(200);
      res.end(JSON.stringify({
        success: true,
        endpoints: {
          '/status': 'Get current crawler status',
          '/pause': 'Pause the crawler',
          '/resume': 'Resume the crawler',
          '/stop': 'Stop the crawler',
          '/screenshot': 'Capture screenshot',
          '/messages': 'Get recent messages',
          '/errors': 'Get all errors',
          '/screenshots': 'List all screenshots',
          '/evaluate': 'POST JSON: {"code": "document.title"}',
          '/navigate?url=URL': 'Navigate to URL',
          '/click': 'POST JSON: {"selector": "button.save"}'
        }
      }, null, 2));
      return;
    }
    
    res.writeHead(404);
    res.end(JSON.stringify({ success: false, error: 'Not found' }));
    
  } catch (error) {
    res.writeHead(500);
    res.end(JSON.stringify({ success: false, error: error.message }));
  }
});

// Start server and crawler
server.listen(argv.port, () => {
  console.log(`\n🎮 Control Server: http://localhost:${argv.port}`);
  console.log(`📖 Help: http://localhost:${argv.port}/help\n`);
  
  // Start crawler
  crawl().catch(console.error);
});
