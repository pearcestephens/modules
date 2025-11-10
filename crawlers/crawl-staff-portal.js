#!/usr/bin/env node

/**
 * Staff Portal Crawler with Bot Detection Bypass
 * 
 * Crawls staff.vapeshed.co.nz with stealth mode to bypass bot detection
 * 
 * Usage:
 *   node scripts/crawl-staff-portal.js --username=USER --password=PASS
 *   node scripts/crawl-staff-portal.js --username=USER --password=PASS --click-all-buttons
 */

const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');
const yargs = require('yargs/yargs');
const { hideBin } = require('yargs/helpers');

// Parse command line arguments
const argv = yargs(hideBin(process.argv))
  .option('username', {
    alias: 'u',
    type: 'string',
    description: 'Staff portal username',
    demandOption: true
  })
  .option('password', {
    alias: 'p',
    type: 'string',
    description: 'Staff portal password',
    demandOption: true
  })
  .option('click-all-buttons', {
    type: 'boolean',
    description: 'Click all buttons after login',
    default: false
  })
  .option('click-all-links', {
    type: 'boolean',
    description: 'Click all links after login',
    default: false
  })
  .option('wait', {
    type: 'number',
    description: 'Wait time after page load (ms)',
    default: 3000
  })
  .option('slow-mo', {
    type: 'number',
    description: 'Slow motion delay (ms)',
    default: 0
  })
  .option('output', {
    type: 'string',
    description: 'Output directory',
    default: '../reports'
  })
  .option('debug', {
    type: 'boolean',
    description: 'Save page HTML for debugging',
    default: false
  })
  .help()
  .argv;

// Stealth configuration to bypass bot detection
const STEALTH_CONFIG = {
  args: [
    '--no-sandbox',
    '--disable-setuid-sandbox',
    '--disable-blink-features=AutomationControlled',
    '--disable-features=IsolateOrigins,site-per-process',
    '--disable-web-security',
    '--disable-features=VizDisplayCompositor',
    '--disable-gpu',
    '--disable-dev-shm-usage'
  ],
  ignoreHTTPSErrors: true,
  headless: 'new', // Use new headless mode (no display needed)
};

// User agent string (real Chrome)
const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

// Global crawl data
const crawlData = {
  url: 'https://staff.vapeshed.co.nz',
  startTime: new Date().toISOString(),
  pages: [],
  screenshots: [],
  interactions: [],
  networkRequests: [],
  networkResponses: [],
  consoleMessages: [],
  errors: [],
  http404s: [],
  http500s: [],
  brokenLinks: [],
  javascriptErrors: []
};

/**
 * Setup page monitoring (console, errors, network)
 */
async function setupPageMonitoring(page) {
  // Console messages
  page.on('console', msg => {
    crawlData.consoleMessages.push({
      type: msg.type(),
      text: msg.text(),
      location: msg.location(),
      timestamp: new Date().toISOString()
    });
  });

  // Page errors
  page.on('pageerror', error => {
    console.log(`❌ JAVASCRIPT ERROR: ${error.message}`);
    
    const errorData = {
      type: 'page_error',
      message: error.message,
      stack: error.stack,
      timestamp: new Date().toISOString()
    };
    
    crawlData.errors.push(errorData);
    crawlData.javascriptErrors.push(errorData);
  });

  // Failed requests
  page.on('requestfailed', request => {
    crawlData.errors.push({
      type: 'request_failed',
      url: request.url(),
      method: request.method(),
      failure: request.failure().errorText,
      timestamp: new Date().toISOString()
    });
  });

  // Network requests
  page.on('request', request => {
    crawlData.networkRequests.push({
      url: request.url(),
      method: request.method(),
      resourceType: request.resourceType(),
      headers: request.headers(),
      timestamp: new Date().toISOString()
    });
  });

  // Network responses
  page.on('response', async response => {
    const responseData = {
      url: response.url(),
      status: response.status(),
      headers: response.headers(),
      timestamp: new Date().toISOString()
    };

    // Capture response body for text-based responses
    try {
      const contentType = response.headers()['content-type'] || '';
      if (contentType.includes('json') || contentType.includes('text') || contentType.includes('html')) {
        responseData.body = await response.text();
      }
    } catch (e) {
      // Body might not be available
    }

    crawlData.networkResponses.push(responseData);

    // 🔍 Detect 404 errors
    if (response.status() === 404) {
      console.log(`❌ 404 NOT FOUND: ${response.url()}`);
      crawlData.http404s.push({
        url: response.url(),
        status: 404,
        referrer: response.request().headers()['referer'] || 'unknown',
        resourceType: response.request().resourceType(),
        timestamp: new Date().toISOString()
      });
    }

    // 🔍 Detect 500 errors
    if (response.status() >= 500 && response.status() < 600) {
      console.log(`❌ ${response.status()} SERVER ERROR: ${response.url()}`);
      crawlData.http500s.push({
        url: response.url(),
        status: response.status(),
        statusText: response.statusText(),
        referrer: response.request().headers()['referer'] || 'unknown',
        resourceType: response.request().resourceType(),
        body: responseData.body || null,
        timestamp: new Date().toISOString()
      });
    }
  });

  // Dialogs (alerts, confirms, prompts)
  page.on('dialog', async dialog => {
    console.log(`Dialog detected: ${dialog.type()} - ${dialog.message()}`);
    await dialog.accept();
  });
}

/**
 * Apply stealth techniques to bypass bot detection
 */
async function applyStealth(page) {
  // Override navigator.webdriver
  await page.evaluateOnNewDocument(() => {
    Object.defineProperty(navigator, 'webdriver', {
      get: () => false
    });
  });

  // Override navigator properties
  await page.evaluateOnNewDocument(() => {
    // Chrome object
    window.chrome = {
      runtime: {}
    };

    // Permissions
    const originalQuery = window.navigator.permissions.query;
    window.navigator.permissions.query = (parameters) => (
      parameters.name === 'notifications' ?
        Promise.resolve({ state: Notification.permission }) :
        originalQuery(parameters)
    );

    // Plugins
    Object.defineProperty(navigator, 'plugins', {
      get: () => [1, 2, 3, 4, 5]
    });

    // Languages
    Object.defineProperty(navigator, 'languages', {
      get: () => ['en-US', 'en']
    });
  });

  // Set realistic viewport
  await page.setViewport({
    width: 1920,
    height: 1080,
    deviceScaleFactor: 1
  });

  // Set user agent
  await page.setUserAgent(USER_AGENT);

  // Set extra headers
  await page.setExtraHTTPHeaders({
    'Accept-Language': 'en-US,en;q=0.9',
    'Accept-Encoding': 'gzip, deflate, br',
    'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
    'Connection': 'keep-alive',
    'Upgrade-Insecure-Requests': '1'
  });
}

/**
 * Capture screenshot
 */
async function captureScreenshot(page, description, outputDir) {
  const timestamp = Date.now();
  const filename = `${description.replace(/[^a-z0-9]/gi, '_')}_${timestamp}.png`;
  const filepath = path.join(outputDir, 'screenshots', filename);

  await page.screenshot({
    path: filepath,
    fullPage: true
  });

  crawlData.screenshots.push({
    filename,
    description,
    timestamp: new Date().toISOString()
  });

  console.log(`📸 Screenshot: ${description}`);
  return filepath;
}

/**
 * Login to staff portal
 */
async function login(page, username, password, outputDir) {
  console.log('🔐 Logging in to staff portal...');

  // Navigate to login page
  await page.goto('https://staff.vapeshed.co.nz', {
    waitUntil: 'networkidle2',
    timeout: 60000
  });

  await page.waitForTimeout(argv.wait);

  // Capture login page
  await captureScreenshot(page, 'login_page', outputDir);

  // Debug: Save HTML if requested
  if (argv.debug) {
    const html = await page.content();
    fs.writeFileSync(path.join(outputDir, 'login_page.html'), html);
    console.log('🐛 DEBUG: Saved login page HTML');
  }

  // Find and fill login form
  // Adjust selectors based on actual login form
  try {
    // Wait a bit for page to fully load
    await page.waitForTimeout(2000);

    // Common login form selectors - try multiple patterns
    const possibleUsernameSelectors = [
      'input[name="username"]',
      'input[name="email"]', 
      'input[type="email"]',
      'input[id*="user"]',
      'input[id*="email"]',
      'input[id*="login"]',
      '#username',
      '#email',
      '#user',
      'input[placeholder*="username" i]',
      'input[placeholder*="email" i]'
    ];

    const possiblePasswordSelectors = [
      'input[name="password"]',
      'input[type="password"]',
      'input[id*="pass"]',
      '#password',
      '#pass'
    ];

    const possibleSubmitSelectors = [
      'button[type="submit"]',
      'input[type="submit"]',
      'button:has-text("Login")',
      'button:has-text("Sign In")',
      'button:has-text("Log In")',
      'button.btn-primary',
      'button.login-btn',
      '#login-button',
      '#submit'
    ];

    console.log('🔍 Searching for login form fields...');

    // Find username field
    let usernameSelector = null;
    for (const selector of possibleUsernameSelectors) {
      try {
        const field = await page.$(selector);
        if (field) {
          usernameSelector = selector;
          console.log(`✓ Found username field: ${selector}`);
          break;
        }
      } catch (e) {}
    }

    if (!usernameSelector) {
      throw new Error('Could not find username/email field');
    }

    // Find password field
    let passwordSelector = null;
    for (const selector of possiblePasswordSelectors) {
      try {
        const field = await page.$(selector);
        if (field) {
          passwordSelector = selector;
          console.log(`✓ Found password field: ${selector}`);
          break;
        }
      } catch (e) {}
    }

    if (!passwordSelector) {
      throw new Error('Could not find password field');
    }

    // Find submit button
    let submitSelector = null;
    for (const selector of possibleSubmitSelectors) {
      try {
        const button = await page.$(selector);
        if (button) {
          submitSelector = selector;
          console.log(`✓ Found submit button: ${selector}`);
          break;
        }
      } catch (e) {}
    }

    if (!submitSelector) {
      throw new Error('Could not find submit button');
    }

    // Type username
    await page.waitForSelector(usernameSelector, { timeout: 10000 });
    await page.click(usernameSelector); // Focus first
    await page.type(usernameSelector, username, { delay: 100 });
    console.log('✓ Username entered');

    await page.waitForTimeout(500);

    // Type password
    await page.click(passwordSelector); // Focus first
    await page.type(passwordSelector, password, { delay: 100 });
    console.log('✓ Password entered');

    // Capture before login
    await captureScreenshot(page, 'before_login_submit', outputDir);

    // Click submit and wait for navigation
    console.log('🔐 Submitting login...');
    await page.click(submitSelector);
    
    // Wait for navigation (page will redirect after login)
    try {
      await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 });
      console.log('✓ Login submitted - page redirected');
    } catch (e) {
      console.log('⚠️  Navigation timeout - checking if login succeeded anyway...');
    }

    // Wait for page to load
    await page.waitForTimeout(argv.wait);

    // Capture after login
    await captureScreenshot(page, 'after_login', outputDir);

    // Check for error indicators
    await scanForErrorIndicators(page);

    // Check if login was successful
    const currentUrl = page.url();
    if (currentUrl.includes('login') || currentUrl === 'https://staff.vapeshed.co.nz/') {
      console.log('⚠️  Warning: Still on login page. Login might have failed.');
    } else {
      console.log('✅ Login successful!');
    }

  } catch (error) {
    console.error('❌ Login failed:', error.message);
    await captureScreenshot(page, 'login_error', outputDir);
    throw error;
  }
}

/**
 * Check for broken links on current page
 */
async function checkBrokenLinks(page) {
  console.log('🔗 Checking all links for 404s...');
  
  const links = await page.$$eval('a[href]', anchors => 
    anchors.map(a => ({
      href: a.href,
      text: a.innerText.trim(),
      id: a.id,
      class: a.className
    }))
  );

  console.log(`  Found ${links.length} links to check`);

  for (let i = 0; i < links.length; i++) {
    const link = links[i];
    
    // Skip javascript:, mailto:, tel:, #anchors
    if (!link.href || 
        link.href.startsWith('javascript:') || 
        link.href.startsWith('mailto:') || 
        link.href.startsWith('tel:') ||
        link.href.includes('#')) {
      continue;
    }

    try {
      // Check link status without navigating
      const response = await page.goto(link.href, {
        waitUntil: 'domcontentloaded',
        timeout: 10000
      });

      const status = response.status();
      
      if (status === 404) {
        console.log(`  ❌ BROKEN LINK (404): ${link.href}`);
        crawlData.brokenLinks.push({
          url: link.href,
          linkText: link.text,
          status: 404,
          timestamp: new Date().toISOString()
        });
      } else if (status >= 500) {
        console.log(`  ❌ SERVER ERROR (${status}): ${link.href}`);
        crawlData.brokenLinks.push({
          url: link.href,
          linkText: link.text,
          status: status,
          timestamp: new Date().toISOString()
        });
      } else if (status >= 200 && status < 300) {
        console.log(`  ✓ Link OK (${status}): ${link.href}`);
      }

      // Go back to original page
      await page.goBack({ waitUntil: 'domcontentloaded' });

    } catch (error) {
      console.log(`  ⚠️  Could not check link: ${link.href} - ${error.message}`);
      crawlData.brokenLinks.push({
        url: link.href,
        linkText: link.text,
        status: 'error',
        error: error.message,
        timestamp: new Date().toISOString()
      });
    }
  }
}

/**
 * Check for JavaScript errors in console
 */
async function checkJavaScriptErrors(page) {
  console.log('🔍 Checking for JavaScript errors...');
  
  const jsErrors = await page.evaluate(() => {
    const errors = [];
    
    // Check for global error handlers
    const originalError = window.onerror;
    window.onerror = function(msg, url, line, col, error) {
      errors.push({
        message: msg,
        url: url,
        line: line,
        column: col,
        stack: error ? error.stack : null
      });
      if (originalError) originalError.apply(this, arguments);
    };

    // Check for unhandled promise rejections
    const originalRejection = window.onunhandledrejection;
    window.onunhandledrejection = function(event) {
      errors.push({
        message: event.reason ? event.reason.message : 'Unhandled Promise Rejection',
        type: 'promise_rejection',
        reason: event.reason
      });
      if (originalRejection) originalRejection.apply(this, arguments);
    };

    return errors;
  });

  if (jsErrors.length > 0) {
    console.log(`  ❌ Found ${jsErrors.length} JavaScript errors`);
    crawlData.javascriptErrors.push(...jsErrors);
  } else {
    console.log(`  ✓ No JavaScript errors detected`);
  }
}

/**
 * Scan page for error indicators
 */
async function scanForErrorIndicators(page) {
  console.log('🔍 Scanning page for error indicators...');
  
  const errorIndicators = await page.evaluate(() => {
    const indicators = [];
    
    // Look for common error text
    const errorTexts = [
      '404', 'not found', 'page not found',
      '500', 'server error', 'internal error',
      'error occurred', 'something went wrong',
      'oops', 'uh oh'
    ];

    const bodyText = document.body.innerText.toLowerCase();
    
    errorTexts.forEach(errorText => {
      if (bodyText.includes(errorText.toLowerCase())) {
        indicators.push({
          type: 'text',
          indicator: errorText,
          found: true
        });
      }
    });

    // Look for error classes/IDs
    const errorElements = document.querySelectorAll(
      '[class*="error"], [id*="error"], ' +
      '[class*="404"], [id*="404"], ' +
      '[class*="500"], [id*="500"], ' +
      '.alert-danger, .error-message'
    );

    errorElements.forEach(el => {
      indicators.push({
        type: 'element',
        tag: el.tagName,
        class: el.className,
        id: el.id,
        text: el.innerText.trim().substring(0, 100)
      });
    });

    return indicators;
  });

  if (errorIndicators.length > 0) {
    console.log(`  ⚠️  Found ${errorIndicators.length} error indicators on page`);
    crawlData.errors.push({
      type: 'page_error_indicators',
      indicators: errorIndicators,
      url: page.url(),
      timestamp: new Date().toISOString()
    });
  } else {
    console.log(`  ✓ No error indicators found`);
  }

  return errorIndicators;
}

/**
 * Click all buttons on page
 */
async function clickAllButtons(page, outputDir) {
  console.log('🖱️  Clicking all buttons...');

  const buttons = await page.$$('button:not([disabled]), input[type="button"]:not([disabled]), input[type="submit"]:not([disabled])');

  for (let i = 0; i < buttons.length; i++) {
    try {
      const button = buttons[i];

      // Get button info
      const buttonInfo = await page.evaluate(el => {
        return {
          text: el.innerText || el.value || '',
          id: el.id,
          class: el.className,
          type: el.type || 'button'
        };
      }, button);

      console.log(`  Clicking button ${i + 1}/${buttons.length}: "${buttonInfo.text}"`);

      // Screenshot before click
      await captureScreenshot(page, `button_${i}_before_${buttonInfo.text}`, outputDir);

      // Scroll into view
      await button.evaluate(el => el.scrollIntoView({ behavior: 'smooth', block: 'center' }));
      await page.waitForTimeout(500);

      // Click
      await button.click();
      await page.waitForTimeout(argv.wait);

      // Check for errors after click
      await scanForErrorIndicators(page);

      // Screenshot after click
      await captureScreenshot(page, `button_${i}_after_${buttonInfo.text}`, outputDir);

      crawlData.interactions.push({
        type: 'button_click',
        index: i,
        buttonInfo,
        timestamp: new Date().toISOString()
      });

    } catch (error) {
      console.log(`  ⚠️  Error clicking button ${i}: ${error.message}`);
    }
  }
}

/**
 * Generate comprehensive error report
 */
function generateErrorReport() {
  let report = '# Error Report - Staff Portal Crawl\n\n';
  report += `**Generated:** ${new Date().toISOString()}\n`;
  report += `**URL:** ${crawlData.url}\n\n`;
  report += '---\n\n';

  // Summary
  report += '## 📊 Summary\n\n';
  report += `- **404 Errors:** ${crawlData.http404s.length}\n`;
  report += `- **500 Errors:** ${crawlData.http500s.length}\n`;
  report += `- **Broken Links:** ${crawlData.brokenLinks.length}\n`;
  report += `- **JavaScript Errors:** ${crawlData.javascriptErrors.length}\n`;
  report += `- **Total Errors:** ${crawlData.errors.length}\n\n`;

  // 404 Errors
  if (crawlData.http404s.length > 0) {
    report += '## ❌ 404 Not Found Errors\n\n';
    report += '| URL | Resource Type | Referrer |\n';
    report += '|-----|---------------|----------|\n';
    crawlData.http404s.forEach(err => {
      report += `| ${err.url} | ${err.resourceType} | ${err.referrer} |\n`;
    });
    report += '\n';
  }

  // 500 Errors
  if (crawlData.http500s.length > 0) {
    report += '## ❌ 500 Server Errors\n\n';
    report += '| URL | Status | Resource Type | Referrer |\n';
    report += '|-----|--------|---------------|----------|\n';
    crawlData.http500s.forEach(err => {
      report += `| ${err.url} | ${err.status} ${err.statusText} | ${err.resourceType} | ${err.referrer} |\n`;
    });
    report += '\n';

    // Show response bodies for 500 errors
    report += '### Response Bodies\n\n';
    crawlData.http500s.forEach((err, i) => {
      if (err.body) {
        report += `#### Error ${i + 1}: ${err.url}\n\n`;
        report += '```\n' + err.body.substring(0, 500) + '...\n```\n\n';
      }
    });
  }

  // Broken Links
  if (crawlData.brokenLinks.length > 0) {
    report += '## 🔗 Broken Links\n\n';
    report += '| Link Text | URL | Status |\n';
    report += '|-----------|-----|--------|\n';
    crawlData.brokenLinks.forEach(link => {
      report += `| ${link.linkText} | ${link.url} | ${link.status} |\n`;
    });
    report += '\n';
  }

  // JavaScript Errors
  if (crawlData.javascriptErrors.length > 0) {
    report += '## ⚠️ JavaScript Errors\n\n';
    crawlData.javascriptErrors.forEach((err, i) => {
      report += `### Error ${i + 1}\n\n`;
      report += `**Message:** ${err.message}\n\n`;
      if (err.stack) {
        report += '**Stack Trace:**\n```\n' + err.stack + '\n```\n\n';
      }
    });
  }

  // Failed Requests
  const failedRequests = crawlData.errors.filter(e => e.type === 'request_failed');
  if (failedRequests.length > 0) {
    report += '## 🚫 Failed Requests\n\n';
    report += '| URL | Method | Failure Reason |\n';
    report += '|-----|--------|----------------|\n';
    failedRequests.forEach(err => {
      report += `| ${err.url} | ${err.method} | ${err.failure} |\n`;
    });
    report += '\n';
  }

  report += '---\n\n';
  report += '## 🔍 Recommendations\n\n';

  if (crawlData.http404s.length > 0) {
    report += '### Fix 404 Errors\n';
    report += '- Check if resources have been moved or deleted\n';
    report += '- Update links to correct URLs\n';
    report += '- Add redirects for moved resources\n\n';
  }

  if (crawlData.http500s.length > 0) {
    report += '### Fix 500 Errors\n';
    report += '- Check server logs for detailed error messages\n';
    report += '- Review database connections\n';
    report += '- Check for syntax errors in server-side code\n\n';
  }

  if (crawlData.javascriptErrors.length > 0) {
    report += '### Fix JavaScript Errors\n';
    report += '- Review browser console for details\n';
    report += '- Check for undefined variables or functions\n';
    report += '- Ensure all required scripts are loaded\n\n';
  }

  return report;
}

/**
 * Main crawl function
 */
async function crawlStaffPortal() {
  console.log('🕷️  Starting Staff Portal Crawl with Bot Bypass...\n');

  // Create output directory
  const timestamp = new Date().toISOString().replace(/[:.]/g, '-').split('T')[0] + '_' + 
                    new Date().toTimeString().split(' ')[0].replace(/:/g, '');
  const outputDir = path.join(__dirname, '..', argv.output, `staff_crawl_${timestamp}`);
  fs.mkdirSync(outputDir, { recursive: true });
  fs.mkdirSync(path.join(outputDir, 'screenshots'), { recursive: true });

  console.log(`📁 Output directory: ${outputDir}\n`);

  // Launch browser with stealth config
  const browser = await puppeteer.launch({
    ...STEALTH_CONFIG,
    slowMo: argv['slow-mo'],
    executablePath: '/usr/bin/chromium' // Use system Chromium
  });

  const page = await browser.newPage();

  // Apply stealth techniques
  await applyStealth(page);

  // Setup monitoring
  await setupPageMonitoring(page);

  try {
    // Login
    await login(page, argv.username, argv.password, outputDir);

    // Get page analysis after login
    const pageAnalysis = await page.evaluate(() => {
      return {
        title: document.title,
        url: window.location.href,
        links: Array.from(document.querySelectorAll('a')).map(a => ({
          text: a.innerText,
          href: a.href
        })),
        buttons: Array.from(document.querySelectorAll('button, input[type="button"], input[type="submit"]')).map(b => ({
          text: b.innerText || b.value,
          id: b.id,
          class: b.className
        })),
        forms: Array.from(document.querySelectorAll('form')).map(f => ({
          action: f.action,
          method: f.method,
          inputs: Array.from(f.querySelectorAll('input')).length
        }))
      };
    });

    crawlData.pages.push({
      url: page.url(),
      title: await page.title(),
      analysis: pageAnalysis,
      timestamp: new Date().toISOString()
    });

    // Check for JavaScript errors
    await checkJavaScriptErrors(page);

    // Scan for error indicators on main page
    await scanForErrorIndicators(page);

    // Click all buttons if requested
    if (argv['click-all-buttons']) {
      await clickAllButtons(page, outputDir);
    }

    // Check for broken links
    console.log('\n🔗 Testing all links for 404s and 500s...');
    await checkBrokenLinks(page);

    // Final screenshot
    await captureScreenshot(page, 'final_state', outputDir);

    // Save crawl data
    crawlData.endTime = new Date().toISOString();
    const dataPath = path.join(outputDir, 'crawl_data.json');
    fs.writeFileSync(dataPath, JSON.stringify(crawlData, null, 2));

    // Generate error report
    const errorReportPath = path.join(outputDir, 'ERROR_REPORT.md');
    const errorReport = generateErrorReport();
    fs.writeFileSync(errorReportPath, errorReport);

    console.log('\n✅ Crawl complete!');
    console.log(`📊 Data saved to: ${dataPath}`);
    console.log(`📄 Error report: ${errorReportPath}`);
    console.log(`📸 Screenshots: ${crawlData.screenshots.length}`);
    console.log(`🌐 Network requests: ${crawlData.networkRequests.length}`);
    console.log(`📝 Console messages: ${crawlData.consoleMessages.length}`);
    console.log(`❌ Total errors: ${crawlData.errors.length}`);
    console.log(`❌ 404 errors: ${crawlData.http404s.length}`);
    console.log(`❌ 500 errors: ${crawlData.http500s.length}`);
    console.log(`❌ Broken links: ${crawlData.brokenLinks.length}`);
    console.log(`❌ JavaScript errors: ${crawlData.javascriptErrors.length}`);

  } catch (error) {
    console.error('\n❌ Crawl failed:', error);
    await captureScreenshot(page, 'error_final', outputDir);
  } finally {
    await browser.close();
  }
}

// Run crawler
crawlStaffPortal().catch(console.error);
