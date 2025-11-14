#!/usr/bin/env node
/**
 * Deep Web Crawler & Debugger
 * 
 * Comprehensive analysis tool that captures EVERYTHING:
 * - Full page HTML source
 * - All network requests (HAR format)
 * - Console logs (all levels)
 * - JavaScript errors
 * - Performance metrics
 * - Screenshots at every interaction
 * - Button clicks and navigation
 * - Form interactions
 * - Resource loading
 * - DOM structure
 * - And more!
 * 
 * Usage:
 *   node deep-crawler.js --url=https://example.com
 *   node deep-crawler.js --url=https://example.com --click-all-buttons
 *   node deep-crawler.js --url=https://example.com --crawl-links --max-depth=2
 */

const puppeteer = require('puppeteer');
const fs = require('fs').promises;
const path = require('path');
const yargs = require('yargs/yargs');
const { hideBin } = require('yargs/helpers');
const colors = require('colors');
const authManager = require('./auth-manager');

// Parse command line arguments
const argv = yargs(hideBin(process.argv))
  .option('url', {
    alias: 'u',
    description: 'Starting URL to crawl',
    type: 'string',
    demandOption: true
  })
  .option('output', {
    alias: 'o',
    description: 'Output directory',
    type: 'string',
    default: '../reports'
  })
  .option('click-all-buttons', {
    description: 'Click all buttons and capture state',
    type: 'boolean',
    default: false
  })
  .option('click-all-links', {
    description: 'Click all links and capture navigation',
    type: 'boolean',
    default: false
  })
  .option('fill-forms', {
    description: 'Attempt to fill and submit forms',
    type: 'boolean',
    default: false
  })
  .option('crawl-links', {
    description: 'Follow links and crawl entire site',
    type: 'boolean',
    default: false
  })
  .option('max-depth', {
    description: 'Maximum crawl depth',
    type: 'number',
    default: 2
  })
  .option('viewport', {
    alias: 'v',
    description: 'Viewport size (desktop, mobile, tablet, WIDTHxHEIGHT)',
    type: 'string',
    default: 'desktop'
  })
  .option('wait', {
    alias: 'w',
    description: 'Wait time after page load (ms)',
    type: 'number',
    default: 2000
  })
  .option('slow-mo', {
    description: 'Slow down operations by N ms (for debugging)',
    type: 'number',
    default: 0
  })
  .option('auth', {
    description: 'Enable authentication using profiles',
    type: 'boolean',
    default: false
  })
  .option('profile', {
    description: 'Authentication profile to use (cis-robot, gpt-hub, retail-sites)',
    type: 'string'
  })
  .help()
  .alias('help', 'h')
  .argv;

// Viewport presets
const VIEWPORTS = {
  desktop: { width: 1920, height: 1080 },
  laptop: { width: 1366, height: 768 },
  tablet: { width: 768, height: 1024 },
  mobile: { width: 375, height: 667 }
};

// Global state
const crawlData = {
  startUrl: argv.url,
  startTime: new Date(),
  pages: [],
  totalRequests: 0,
  totalErrors: 0,
  visitedUrls: new Set(),
  screenshots: [],
  interactions: []
};

/**
 * Parse viewport argument
 */
function parseViewport(viewportArg) {
  if (VIEWPORTS[viewportArg]) {
    return VIEWPORTS[viewportArg];
  }
  const match = viewportArg.match(/^(\d+)x(\d+)$/);
  if (match) {
    return { width: parseInt(match[1]), height: parseInt(match[2]) };
  }
  console.warn(colors.yellow(`⚠️  Invalid viewport "${viewportArg}", using desktop`));
  return VIEWPORTS.desktop;
}

/**
 * Setup page monitoring - captures EVERYTHING
 */
async function setupPageMonitoring(page, pageData) {
  // Capture console messages
  page.on('console', msg => {
    const entry = {
      type: msg.type(),
      text: msg.text(),
      location: msg.location(),
      timestamp: new Date().toISOString()
    };
    pageData.console.push(entry);
    
    const icon = msg.type() === 'error' ? '❌' : msg.type() === 'warning' ? '⚠️' : 'ℹ️';
    console.log(colors.gray(`${icon} Console ${msg.type()}: ${msg.text()}`));
  });

  // Capture page errors
  page.on('pageerror', error => {
    const entry = {
      message: error.message,
      stack: error.stack,
      timestamp: new Date().toISOString()
    };
    pageData.errors.push(entry);
    crawlData.totalErrors++;
    console.log(colors.red(`❌ Page Error: ${error.message}`));
  });

  // Capture failed requests
  page.on('requestfailed', request => {
    const entry = {
      url: request.url(),
      method: request.method(),
      failure: request.failure().errorText,
      timestamp: new Date().toISOString()
    };
    pageData.failedRequests.push(entry);
    console.log(colors.red(`❌ Request Failed: ${request.url()}`));
  });

  // Capture all network requests
  page.on('request', request => {
    const entry = {
      url: request.url(),
      method: request.method(),
      resourceType: request.resourceType(),
      headers: request.headers(),
      postData: request.postData(),
      timestamp: new Date().toISOString()
    };
    pageData.requests.push(entry);
    crawlData.totalRequests++;
  });

  // Capture all responses
  page.on('response', async response => {
    const entry = {
      url: response.url(),
      status: response.status(),
      statusText: response.statusText(),
      headers: response.headers(),
      fromCache: response.fromCache(),
      timing: response.timing(),
      timestamp: new Date().toISOString()
    };
    
    // Try to capture response body for non-binary types
    try {
      const contentType = response.headers()['content-type'] || '';
      if (contentType.includes('json') || contentType.includes('text') || contentType.includes('xml')) {
        entry.body = await response.text();
      }
    } catch (e) {
      // Binary or protected content, skip
    }
    
    pageData.responses.push(entry);
  });

  // Capture dialog boxes (alerts, confirms, prompts)
  page.on('dialog', async dialog => {
    const entry = {
      type: dialog.type(),
      message: dialog.message(),
      defaultValue: dialog.defaultValue(),
      timestamp: new Date().toISOString()
    };
    pageData.dialogs.push(entry);
    console.log(colors.yellow(`💬 Dialog: ${dialog.type()} - ${dialog.message()}`));
    await dialog.accept(); // Auto-accept dialogs
  });
}

/**
 * Capture screenshot with metadata
 */
async function captureScreenshot(page, name, pageData, outputDir) {
  const timestamp = Date.now();
  const filename = `${timestamp}_${name.replace(/[^a-z0-9]/gi, '_')}.png`;
  const filepath = path.join(outputDir, 'screenshots', filename);
  
  await fs.mkdir(path.dirname(filepath), { recursive: true });
  await page.screenshot({ path: filepath, fullPage: true });
  
  const screenshotData = {
    name,
    filename,
    filepath,
    timestamp: new Date().toISOString(),
    viewport: await page.viewport(),
    url: page.url()
  };
  
  pageData.screenshots.push(screenshotData);
  crawlData.screenshots.push(screenshotData);
  console.log(colors.green(`📸 Screenshot: ${name}`));
  
  return screenshotData;
}

/**
 * Get full page analysis
 */
async function getPageAnalysis(page) {
  return await page.evaluate(() => {
    // Get all interactive elements
    const buttons = Array.from(document.querySelectorAll('button, input[type="button"], input[type="submit"]'));
    const links = Array.from(document.querySelectorAll('a[href]'));
    const forms = Array.from(document.querySelectorAll('form'));
    const inputs = Array.from(document.querySelectorAll('input, textarea, select'));
    
    // Get page structure
    const structure = {
      title: document.title,
      url: window.location.href,
      html: document.documentElement.outerHTML,
      bodyHTML: document.body.innerHTML,
      headHTML: document.head.innerHTML,
      
      // Meta information
      meta: {
        description: document.querySelector('meta[name="description"]')?.content || '',
        keywords: document.querySelector('meta[name="keywords"]')?.content || '',
        viewport: document.querySelector('meta[name="viewport"]')?.content || '',
        charset: document.characterSet,
        lang: document.documentElement.lang
      },
      
      // DOM metrics
      metrics: {
        totalElements: document.querySelectorAll('*').length,
        totalScripts: document.querySelectorAll('script').length,
        totalStyles: document.querySelectorAll('style, link[rel="stylesheet"]').length,
        totalImages: document.querySelectorAll('img').length,
        totalLinks: links.length,
        totalButtons: buttons.length,
        totalForms: forms.length,
        totalInputs: inputs.length
      },
      
      // Interactive elements
      interactiveElements: {
        buttons: buttons.map((btn, idx) => ({
          index: idx,
          text: btn.textContent.trim(),
          type: btn.type,
          id: btn.id,
          classes: btn.className,
          disabled: btn.disabled,
          visible: btn.offsetParent !== null,
          selector: generateSelector(btn)
        })),
        
        links: links.map((link, idx) => ({
          index: idx,
          text: link.textContent.trim(),
          href: link.href,
          target: link.target,
          id: link.id,
          classes: link.className,
          visible: link.offsetParent !== null,
          selector: generateSelector(link)
        })),
        
        forms: forms.map((form, idx) => ({
          index: idx,
          action: form.action,
          method: form.method,
          id: form.id,
          classes: form.className,
          inputs: Array.from(form.querySelectorAll('input, textarea, select')).map(input => ({
            name: input.name,
            type: input.type,
            id: input.id,
            required: input.required,
            placeholder: input.placeholder
          })),
          selector: generateSelector(form)
        })),
        
        inputs: inputs.map((input, idx) => ({
          index: idx,
          name: input.name,
          type: input.type,
          id: input.id,
          value: input.value,
          placeholder: input.placeholder,
          required: input.required,
          disabled: input.disabled,
          selector: generateSelector(input)
        }))
      },
      
      // Accessibility check
      accessibility: {
        imagesWithoutAlt: Array.from(document.querySelectorAll('img:not([alt])')).length,
        inputsWithoutLabels: Array.from(document.querySelectorAll('input:not([aria-label]):not([id])')).length,
        headings: Array.from(document.querySelectorAll('h1, h2, h3, h4, h5, h6')).map(h => ({
          level: h.tagName,
          text: h.textContent.trim()
        })),
        landmarks: Array.from(document.querySelectorAll('[role], header, nav, main, footer, aside')).map(el => ({
          tag: el.tagName,
          role: el.getAttribute('role') || el.tagName.toLowerCase()
        }))
      },
      
      // Performance hints
      performance: {
        largeImages: Array.from(document.querySelectorAll('img')).filter(img => {
          return img.naturalWidth > 2000 || img.naturalHeight > 2000;
        }).length,
        inlineScripts: document.querySelectorAll('script:not([src])').length,
        inlineStyles: document.querySelectorAll('style').length
      },
      
      // Local storage and cookies
      storage: {
        localStorageKeys: Object.keys(localStorage),
        sessionStorageKeys: Object.keys(sessionStorage),
        cookies: document.cookie
      }
    };
    
    // Helper function to generate unique selector
    function generateSelector(element) {
      if (element.id) return `#${element.id}`;
      if (element.name) return `[name="${element.name}"]`;
      
      let path = [];
      let current = element;
      while (current.parentElement) {
        let selector = current.tagName.toLowerCase();
        if (current.className) {
          selector += '.' + current.className.trim().split(/\s+/).join('.');
        }
        path.unshift(selector);
        current = current.parentElement;
        if (path.length > 5) break; // Limit depth
      }
      return path.join(' > ');
    }
    
    return structure;
  });
}

/**
 * Click all buttons and capture results
 */
async function clickAllButtons(page, pageData, outputDir) {
  console.log(colors.cyan('🖱️  Clicking all buttons...'));
  
  const buttons = await page.$$('button, input[type="button"], input[type="submit"], a.btn, .button');
  console.log(colors.gray(`Found ${buttons.length} buttons to click`));
  
  for (let i = 0; i < buttons.length; i++) {
    try {
      const button = buttons[i];
      
      // Get button info
      const buttonInfo = await button.evaluate(el => ({
        text: el.textContent.trim(),
        id: el.id,
        classes: el.className,
        type: el.type,
        disabled: el.disabled
      }));
      
      if (buttonInfo.disabled) {
        console.log(colors.gray(`⊘ Skipping disabled button: ${buttonInfo.text}`));
        continue;
      }
      
      console.log(colors.cyan(`🖱️  Clicking button ${i + 1}/${buttons.length}: "${buttonInfo.text}"`));
      
      // Capture before click
      await captureScreenshot(page, `before_button_${i}_${buttonInfo.text}`, pageData, outputDir);
      
      // Click and wait for potential navigation/changes
      await Promise.all([
        button.click(),
        page.waitForTimeout(1000) // Wait for any animations/changes
      ]).catch(() => {}); // Ignore errors if button causes navigation
      
      // Capture after click
      await captureScreenshot(page, `after_button_${i}_${buttonInfo.text}`, pageData, outputDir);
      
      // Record interaction
      pageData.interactions.push({
        type: 'button_click',
        element: buttonInfo,
        timestamp: new Date().toISOString()
      });
      
      // Go back if we navigated
      if (page.url() !== pageData.url) {
        await page.goBack({ waitUntil: 'networkidle2' });
        await page.waitForTimeout(1000);
      }
      
    } catch (error) {
      console.log(colors.red(`❌ Error clicking button ${i}: ${error.message}`));
    }
  }
}

/**
 * Click all links and capture navigation
 */
async function clickAllLinks(page, pageData, outputDir) {
  console.log(colors.cyan('🔗 Clicking all links...'));
  
  const links = await page.$$('a[href]');
  console.log(colors.gray(`Found ${links.length} links to click`));
  
  for (let i = 0; i < links.length; i++) {
    try {
      const link = links[i];
      
      // Get link info
      const linkInfo = await link.evaluate(el => ({
        text: el.textContent.trim(),
        href: el.href,
        target: el.target,
        id: el.id,
        classes: el.className
      }));
      
      // Skip external links, javascript:, mailto:, tel:
      if (!linkInfo.href.startsWith(pageData.url.split('/').slice(0, 3).join('/')) ||
          linkInfo.href.includes('javascript:') ||
          linkInfo.href.includes('mailto:') ||
          linkInfo.href.includes('tel:')) {
        console.log(colors.gray(`⊘ Skipping external/special link: ${linkInfo.href}`));
        continue;
      }
      
      console.log(colors.cyan(`🔗 Clicking link ${i + 1}/${links.length}: "${linkInfo.text}"`));
      
      // Capture before click
      await captureScreenshot(page, `before_link_${i}_${linkInfo.text}`, pageData, outputDir);
      
      // Click and wait for navigation
      await Promise.all([
        link.click(),
        page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 10000 }).catch(() => {})
      ]).catch(() => {});
      
      // Wait for page to settle
      await page.waitForTimeout(argv.wait);
      
      // Capture after navigation
      await captureScreenshot(page, `after_link_${i}_${linkInfo.text}`, pageData, outputDir);
      
      // Record interaction
      pageData.interactions.push({
        type: 'link_click',
        element: linkInfo,
        navigatedTo: page.url(),
        timestamp: new Date().toISOString()
      });
      
      // Go back to original page
      await page.goBack({ waitUntil: 'networkidle2' });
      await page.waitForTimeout(1000);
      
    } catch (error) {
      console.log(colors.red(`❌ Error clicking link ${i}: ${error.message}`));
      // Try to go back anyway
      try {
        await page.goBack({ waitUntil: 'networkidle2' });
      } catch (e) {}
    }
  }
}

/**
 * Crawl a single page
 */
async function crawlPage(browser, url, depth, outputDir, authProfile = null) {
  if (depth > argv['max-depth'] || crawlData.visitedUrls.has(url)) {
    return;
  }
  
  crawlData.visitedUrls.add(url);
  
  console.log(colors.bold.cyan(`\n${'='.repeat(80)}`));
  console.log(colors.bold.cyan(`🌐 Crawling: ${url} (Depth: ${depth})`));
  if (authProfile) {
    console.log(colors.bold.magenta(`🔐 Authentication: ${authProfile.name}`));
  }
  console.log(colors.bold.cyan(`${'='.repeat(80)}\n`));
  
  const page = await browser.newPage();
  const viewport = parseViewport(argv.viewport);
  await page.setViewport(viewport);
  
  // Initialize page data
  const pageData = {
    url,
    depth,
    startTime: new Date(),
    console: [],
    errors: [],
    requests: [],
    responses: [],
    failedRequests: [],
    dialogs: [],
    screenshots: [],
    interactions: [],
    metrics: null,
    structure: null,
    authentication: authProfile ? {
      profile: authProfile.name,
      status: 'pending'
    } : null
  };
  
  try {
    // Setup monitoring
    await setupPageMonitoring(page, pageData);
    
    // Handle authentication first
    if (authProfile && depth === 0) {  // Only authenticate on first page
      console.log(colors.magenta('🔐 Authenticating...'));
      const authResult = await authManager.authenticateBot(authProfile, page);
      
      if (authResult.success) {
        console.log(colors.green(`✅ Authentication successful as ${authProfile.name}`));
        pageData.authentication.status = 'success';
        pageData.authentication.mode = authResult.mode;
      } else {
        console.log(colors.red(`❌ Authentication failed: ${authResult.error}`));
        pageData.authentication.status = 'failed';
        pageData.authentication.error = authResult.error;
        
        // Continue anyway for debugging
        console.log(colors.yellow('⚠️ Continuing without authentication...'));
      }
    }
    
    // Navigate to page (or continue if already authenticated and on dashboard)
    if (!authProfile || page.url() !== url) {
      console.log(colors.cyan('📡 Loading page...'));
      await page.goto(url, { waitUntil: 'networkidle2', timeout: 30000 });
    }
    
    // Wait for page to settle
    await page.waitForTimeout(argv.wait);
    
    // Capture initial screenshot
    await captureScreenshot(page, 'initial_load', pageData, outputDir);
    
    // Get performance metrics
    console.log(colors.cyan('📊 Collecting performance metrics...'));
    pageData.metrics = await page.metrics();
    
    // Get full page analysis
    console.log(colors.cyan('🔍 Analyzing page structure...'));
    pageData.structure = await getPageAnalysis(page);
    
    // Get performance timing
    const performanceTiming = await page.evaluate(() => {
      const timing = performance.timing;
      return {
        navigationStart: timing.navigationStart,
        domContentLoadedEventEnd: timing.domContentLoadedEventEnd,
        loadEventEnd: timing.loadEventEnd,
        domComplete: timing.domComplete,
        domInteractive: timing.domInteractive,
        responseEnd: timing.responseEnd,
        requestStart: timing.requestStart,
        calculated: {
          domContentLoaded: timing.domContentLoadedEventEnd - timing.navigationStart,
          pageLoad: timing.loadEventEnd - timing.navigationStart,
          domReady: timing.domComplete - timing.navigationStart,
          timeToInteractive: timing.domInteractive - timing.navigationStart,
          serverResponse: timing.responseEnd - timing.requestStart
        }
      };
    });
    pageData.performanceTiming = performanceTiming;
    
    // Click all buttons if requested
    if (argv['click-all-buttons']) {
      await clickAllButtons(page, pageData, outputDir);
    }
    
    // Click all links if requested
    if (argv['click-all-links']) {
      await clickAllLinks(page, pageData, outputDir);
    }
    
    // Capture final screenshot
    await captureScreenshot(page, 'final_state', pageData, outputDir);
    
    pageData.endTime = new Date();
    pageData.duration = pageData.endTime - pageData.startTime;
    
    crawlData.pages.push(pageData);
    
    console.log(colors.green(`✅ Page crawled successfully`));
    console.log(colors.gray(`   - ${pageData.console.length} console messages`));
    console.log(colors.gray(`   - ${pageData.errors.length} errors`));
    console.log(colors.gray(`   - ${pageData.requests.length} requests`));
    console.log(colors.gray(`   - ${pageData.screenshots.length} screenshots`));
    console.log(colors.gray(`   - ${pageData.interactions.length} interactions`));
    
    // Crawl links if enabled
    if (argv['crawl-links'] && depth < argv['max-depth']) {
      const links = pageData.structure.interactiveElements.links
        .filter(link => link.href.startsWith(url.split('/').slice(0, 3).join('/')))
        .map(link => link.href);
      
      for (const link of links) {
        await crawlPage(browser, link, depth + 1, outputDir, authProfile);
      }
    }
    
  } catch (error) {
    console.error(colors.red(`❌ Error crawling page: ${error.message}`));
    pageData.error = error.message;
    pageData.errorStack = error.stack;
    crawlData.pages.push(pageData);
  } finally {
    await page.close();
  }
}

/**
 * Generate comprehensive report
 */
async function generateReport(outputDir) {
  console.log(colors.cyan('\n📝 Generating comprehensive report...'));
  
  const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
  const reportDir = path.join(outputDir, `crawl_${timestamp}`);
  await fs.mkdir(reportDir, { recursive: true });
  
  crawlData.endTime = new Date();
  crawlData.totalDuration = crawlData.endTime - crawlData.startTime;
  
  // Save full JSON data
  const jsonPath = path.join(reportDir, 'full_crawl_data.json');
  await fs.writeFile(jsonPath, JSON.stringify(crawlData, null, 2));
  console.log(colors.green(`📊 JSON report: ${jsonPath}`));
  
  // Generate HTML report
  const htmlReport = generateHTMLReport(crawlData, reportDir);
  const htmlPath = path.join(reportDir, 'index.html');
  await fs.writeFile(htmlPath, htmlReport);
  console.log(colors.green(`📄 HTML report: ${htmlPath}`));
  
  // Generate markdown summary
  const mdReport = generateMarkdownReport(crawlData);
  const mdPath = path.join(reportDir, 'SUMMARY.md');
  await fs.writeFile(mdPath, mdReport);
  console.log(colors.green(`📝 Markdown report: ${mdPath}`));
  
  // Save each page's HTML source
  for (let i = 0; i < crawlData.pages.length; i++) {
    const pageData = crawlData.pages[i];
    if (pageData.structure && pageData.structure.html) {
      const htmlSourcePath = path.join(reportDir, `page_${i}_source.html`);
      await fs.writeFile(htmlSourcePath, pageData.structure.html);
    }
  }
  
  return reportDir;
}

/**
 * Generate HTML report
 */
function generateHTMLReport(data, reportDir) {
  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Deep Crawl Report - ${data.startUrl}</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; background: #f5f5f5; }
    .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
    header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 20px; border-radius: 10px; margin-bottom: 30px; }
    h1 { font-size: 2.5em; margin-bottom: 10px; }
    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0; }
    .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .stat-value { font-size: 2.5em; font-weight: bold; color: #667eea; }
    .stat-label { color: #666; font-size: 0.9em; }
    .section { background: white; padding: 30px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    h2 { color: #667eea; margin-bottom: 20px; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
    .page-card { background: #f9f9f9; padding: 20px; margin: 15px 0; border-radius: 8px; border-left: 4px solid #667eea; }
    .page-title { font-size: 1.3em; font-weight: bold; margin-bottom: 10px; }
    .page-url { color: #666; font-size: 0.9em; margin-bottom: 15px; word-break: break-all; }
    .metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin: 15px 0; }
    .metric { background: white; padding: 10px; border-radius: 5px; text-align: center; }
    .metric-value { font-size: 1.5em; font-weight: bold; color: #667eea; }
    .metric-label { font-size: 0.8em; color: #666; }
    .error { background: #fee; border-left-color: #f00; padding: 10px; margin: 5px 0; border-radius: 4px; }
    .warning { background: #ffc; border-left-color: #fa0; padding: 10px; margin: 5px 0; border-radius: 4px; }
    .success { background: #efe; border-left-color: #0a0; padding: 10px; margin: 5px 0; border-radius: 4px; }
    .console-log { background: #f5f5f5; padding: 10px; margin: 5px 0; border-radius: 4px; font-family: monospace; font-size: 0.9em; }
    .screenshot-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; margin: 20px 0; }
    .screenshot { border: 1px solid #ddd; border-radius: 8px; overflow: hidden; }
    .screenshot img { width: 100%; height: auto; display: block; }
    .screenshot-label { padding: 10px; background: #f9f9f9; font-size: 0.9em; }
    code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
    pre { background: #f5f5f5; padding: 15px; border-radius: 8px; overflow-x: auto; }
    .tab-container { margin: 20px 0; }
    .tab-buttons { display: flex; gap: 10px; border-bottom: 2px solid #ddd; }
    .tab-button { padding: 10px 20px; border: none; background: none; cursor: pointer; color: #666; font-size: 1em; }
    .tab-button.active { color: #667eea; border-bottom: 2px solid #667eea; margin-bottom: -2px; }
    .tab-content { display: none; padding: 20px 0; }
    .tab-content.active { display: block; }
  </style>
</head>
<body>
  <div class="container">
    <header>
      <h1>🕷️ Deep Crawl Report</h1>
      <p>Complete analysis of <strong>${data.startUrl}</strong></p>
      <p>Generated: ${data.startTime.toLocaleString()}</p>
    </header>

    <div class="stats">
      <div class="stat-card">
        <div class="stat-value">${data.pages.length}</div>
        <div class="stat-label">Pages Crawled</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">${data.totalRequests}</div>
        <div class="stat-label">Total Requests</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">${data.totalErrors}</div>
        <div class="stat-label">Errors Found</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">${data.screenshots.length}</div>
        <div class="stat-label">Screenshots</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">${Math.round(data.totalDuration / 1000)}s</div>
        <div class="stat-label">Total Duration</div>
      </div>
    </div>

    ${data.pages.map((page, idx) => `
      <div class="section">
        <div class="page-card">
          <div class="page-title">Page ${idx + 1}: ${page.structure?.title || 'Untitled'}</div>
          <div class="page-url">${page.url}</div>
          
          <div class="metrics">
            <div class="metric">
              <div class="metric-value">${page.requests.length}</div>
              <div class="metric-label">Requests</div>
            </div>
            <div class="metric">
              <div class="metric-value">${page.console.length}</div>
              <div class="metric-label">Console Logs</div>
            </div>
            <div class="metric">
              <div class="metric-value">${page.errors.length}</div>
              <div class="metric-label">Errors</div>
            </div>
            <div class="metric">
              <div class="metric-value">${page.screenshots.length}</div>
              <div class="metric-label">Screenshots</div>
            </div>
            <div class="metric">
              <div class="metric-value">${page.interactions.length}</div>
              <div class="metric-label">Interactions</div>
            </div>
            <div class="metric">
              <div class="metric-value">${Math.round(page.duration / 1000)}s</div>
              <div class="metric-label">Duration</div>
            </div>
          </div>

          <div class="tab-container">
            <div class="tab-buttons">
              <button class="tab-button active" onclick="showTab(${idx}, 0)">Overview</button>
              <button class="tab-button" onclick="showTab(${idx}, 1)">Console</button>
              <button class="tab-button" onclick="showTab(${idx}, 2)">Network</button>
              <button class="tab-button" onclick="showTab(${idx}, 3)">Screenshots</button>
              <button class="tab-button" onclick="showTab(${idx}, 4)">Structure</button>
              <button class="tab-button" onclick="showTab(${idx}, 5)">Performance</button>
            </div>

            <div class="tab-content active" id="tab-${idx}-0">
              <h3>Page Overview</h3>
              ${page.structure ? `
                <p><strong>Total Elements:</strong> ${page.structure.metrics.totalElements}</p>
                <p><strong>Buttons:</strong> ${page.structure.metrics.totalButtons}</p>
                <p><strong>Links:</strong> ${page.structure.metrics.totalLinks}</p>
                <p><strong>Forms:</strong> ${page.structure.metrics.totalForms}</p>
                <p><strong>Images:</strong> ${page.structure.metrics.totalImages}</p>
                ${page.errors.length > 0 ? `<div class="error"><strong>⚠️ ${page.errors.length} errors found!</strong></div>` : ''}
              ` : '<p>No structure data available</p>'}
            </div>

            <div class="tab-content" id="tab-${idx}-1">
              <h3>Console Messages (${page.console.length})</h3>
              ${page.console.map(log => `
                <div class="console-log ${log.type}">
                  <strong>[${log.type}]</strong> ${log.text}
                </div>
              `).join('')}
            </div>

            <div class="tab-content" id="tab-${idx}-2">
              <h3>Network Requests (${page.requests.length})</h3>
              <p><strong>Failed:</strong> ${page.failedRequests.length}</p>
              ${page.failedRequests.map(req => `
                <div class="error">
                  <strong>${req.method}</strong> ${req.url}<br>
                  <em>${req.failure}</em>
                </div>
              `).join('')}
            </div>

            <div class="tab-content" id="tab-${idx}-3">
              <h3>Screenshots (${page.screenshots.length})</h3>
              <div class="screenshot-grid">
                ${page.screenshots.map(ss => `
                  <div class="screenshot">
                    <img src="../${ss.filepath.split('/').pop()}" alt="${ss.name}">
                    <div class="screenshot-label">${ss.name}</div>
                  </div>
                `).join('')}
              </div>
            </div>

            <div class="tab-content" id="tab-${idx}-4">
              <h3>Page Structure</h3>
              ${page.structure ? `
                <h4>Meta Information</h4>
                <pre>${JSON.stringify(page.structure.meta, null, 2)}</pre>
                
                <h4>Accessibility Issues</h4>
                <p>Images without alt: ${page.structure.accessibility.imagesWithoutAlt}</p>
                <p>Inputs without labels: ${page.structure.accessibility.inputsWithoutLabels}</p>
              ` : '<p>No structure data</p>'}
            </div>

            <div class="tab-content" id="tab-${idx}-5">
              <h3>Performance Metrics</h3>
              ${page.performanceTiming ? `
                <div class="metrics">
                  <div class="metric">
                    <div class="metric-value">${page.performanceTiming.calculated.pageLoad}ms</div>
                    <div class="metric-label">Page Load</div>
                  </div>
                  <div class="metric">
                    <div class="metric-value">${page.performanceTiming.calculated.domContentLoaded}ms</div>
                    <div class="metric-label">DOM Content Loaded</div>
                  </div>
                  <div class="metric">
                    <div class="metric-value">${page.performanceTiming.calculated.timeToInteractive}ms</div>
                    <div class="metric-label">Time to Interactive</div>
                  </div>
                  <div class="metric">
                    <div class="metric-value">${page.performanceTiming.calculated.serverResponse}ms</div>
                    <div class="metric-label">Server Response</div>
                  </div>
                </div>
              ` : '<p>No performance data</p>'}
            </div>
          </div>
        </div>
      </div>
    `).join('')}
  </div>

  <script>
    function showTab(pageIdx, tabIdx) {
      const buttons = document.querySelectorAll('.tab-buttons')[pageIdx].querySelectorAll('.tab-button');
      const contents = document.querySelectorAll('.tab-container')[pageIdx].querySelectorAll('.tab-content');
      
      buttons.forEach((btn, idx) => {
        btn.classList.toggle('active', idx === tabIdx);
      });
      
      contents.forEach((content, idx) => {
        content.classList.toggle('active', idx === tabIdx);
      });
    }
  </script>
</body>
</html>`;
}

/**
 * Generate markdown summary
 */
function generateMarkdownReport(data) {
  return `# Deep Crawl Report

**URL:** ${data.startUrl}  
**Started:** ${data.startTime.toLocaleString()}  
**Duration:** ${Math.round(data.totalDuration / 1000)} seconds  

---

## Summary

- **Pages Crawled:** ${data.pages.length}
- **Total Requests:** ${data.totalRequests}
- **Total Errors:** ${data.totalErrors}
- **Screenshots Captured:** ${data.screenshots.length}
- **Total Interactions:** ${data.pages.reduce((sum, p) => sum + p.interactions.length, 0)}

---

${data.pages.map((page, idx) => `
## Page ${idx + 1}: ${page.structure?.title || 'Untitled'}

**URL:** ${page.url}  
**Duration:** ${Math.round(page.duration / 1000)}s  

### Metrics
- Requests: ${page.requests.length}
- Console logs: ${page.console.length}
- Errors: ${page.errors.length}
- Screenshots: ${page.screenshots.length}
- Interactions: ${page.interactions.length}

${page.structure ? `
### Page Structure
- Total elements: ${page.structure.metrics.totalElements}
- Buttons: ${page.structure.metrics.totalButtons}
- Links: ${page.structure.metrics.totalLinks}
- Forms: ${page.structure.metrics.totalForms}
- Images: ${page.structure.metrics.totalImages}

### Accessibility
- Images without alt: ${page.structure.accessibility.imagesWithoutAlt}
- Inputs without labels: ${page.structure.accessibility.inputsWithoutLabels}
` : ''}

${page.errors.length > 0 ? `
### Errors
${page.errors.map(err => `- ${err.message}`).join('\n')}
` : ''}

${page.performanceTiming ? `
### Performance
- Page Load: ${page.performanceTiming.calculated.pageLoad}ms
- DOM Content Loaded: ${page.performanceTiming.calculated.domContentLoaded}ms
- Time to Interactive: ${page.performanceTiming.calculated.timeToInteractive}ms
- Server Response: ${page.performanceTiming.calculated.serverResponse}ms
` : ''}

---
`).join('\n')}

**Report generated by Deep Crawler**
`;
}

/**
 * Main execution
 */
async function main() {
  console.log(colors.bold.cyan('\n🕷️  Deep Web Crawler & Debugger\n'));
  console.log(colors.gray(`Starting URL: ${argv.url}`));
  console.log(colors.gray(`Max Depth: ${argv['max-depth']}`));
  console.log(colors.gray(`Viewport: ${argv.viewport}`));
  console.log(colors.gray(`Click Buttons: ${argv['click-all-buttons']}`));
  console.log(colors.gray(`Click Links: ${argv['click-all-links']}`));
  console.log(colors.gray(`Crawl Links: ${argv['crawl-links']}\n`));
  
  try {
    // Launch browser
    console.log(colors.cyan('🚀 Launching headless Chrome...'));
    const browser = await puppeteer.launch({
      headless: 'new',
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-gpu'
      ],
      slowMo: argv['slow-mo']
    });
    
    try {
      // Handle authentication if requested
      let authProfile = null;
      if (argv.auth) {
        console.log(colors.magenta('🔐 Setting up authentication...'));
        
        // Auto-detect profile if not specified
        if (!argv.profile) {
          argv.profile = await authManager.getProfileForUrl(argv.url);
          if (argv.profile) {
            console.log(colors.yellow(`🤖 Auto-detected profile: ${argv.profile}`));
          }
        }
        
        if (argv.profile) {
          authProfile = await authManager.loadProfile(argv.profile);
          if (!authProfile) {
            console.log(colors.red(`❌ Failed to load profile: ${argv.profile}`));
            console.log(colors.yellow('Available profiles:'));
            await authManager.displayProfiles();
            process.exit(1);
          }
          console.log(colors.green(`✅ Loaded authentication profile: ${authProfile.name}`));
        } else {
          console.log(colors.yellow('⚠️ No suitable authentication profile found'));
        }
      }
      
      // Start crawling with authentication
      await crawlPage(browser, argv.url, 0, argv.output, authProfile);
      
      // Generate reports
      const reportDir = await generateReport(argv.output);
      
      // Display summary
      console.log(colors.bold.green('\n✅ Crawl Complete!\n'));
      console.log(colors.white('Summary:'));
      console.log(colors.gray(`  - Pages crawled: ${crawlData.pages.length}`));
      console.log(colors.gray(`  - Total requests: ${crawlData.totalRequests}`));
      console.log(colors.gray(`  - Total errors: ${crawlData.totalErrors}`));
      console.log(colors.gray(`  - Screenshots: ${crawlData.screenshots.length}`));
      console.log(colors.gray(`  - Duration: ${Math.round(crawlData.totalDuration / 1000)}s`));
      
      console.log(colors.bold.cyan('\n📁 Reports saved to:'));
      console.log(colors.white(`  ${reportDir}`));
      
    } finally {
      await browser.close();
    }
    
  } catch (error) {
    console.error(colors.bold.red('\n❌ Fatal Error:'), error.message);
    console.error(error.stack);
    process.exit(1);
  }
}

// Run
if (require.main === module) {
  main();
}

module.exports = { crawlPage, generateReport };
