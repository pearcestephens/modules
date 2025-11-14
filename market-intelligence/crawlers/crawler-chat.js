#!/usr/bin/env node

/**
 * Interactive Chat Interface for Crawler Control
 * 
 * Control the crawler through a simple chat interface
 * 
 * Usage:
 *   node scripts/crawler-chat.js --port=3000
 */

const readline = require('readline');
const axios = require('axios');
const yargs = require('yargs/yargs');
const { hideBin } = require('yargs/helpers');
const colors = require('colors');

const argv = yargs(hideBin(process.argv))
  .option('port', { type: 'number', default: 3000 })
  .option('host', { type: 'string', default: 'localhost' })
  .help()
  .argv;

const baseUrl = `http://${argv.host}:${argv.port}`;

const rl = readline.createInterface({
  input: process.stdin,
  output: process.stdout,
  prompt: '🤖 You: '.cyan
});

console.log('\n' + '='.repeat(60).cyan);
console.log('🕷️  Interactive Crawler Chat Interface'.bold.white);
console.log('='.repeat(60).cyan);
console.log(`📡 Connected to: ${baseUrl}`.gray);
console.log('\n💡 Type commands or ask questions:'.yellow);
console.log('  - "status" - Get current status');
console.log('  - "pause" - Pause the crawler');
console.log('  - "resume" - Resume the crawler');
console.log('  - "screenshot" - Capture screenshot');
console.log('  - "messages" - Get recent messages');
console.log('  - "errors" - Show errors');
console.log('  - "eval <code>" - Run JavaScript');
console.log('  - "go <url>" - Navigate to URL');
console.log('  - "click <selector>" - Click element');
console.log('  - "help" - Show all commands');
console.log('  - "quit" - Exit chat\n'.gray);

rl.prompt();

rl.on('line', async (input) => {
  const line = input.trim();
  
  if (!line) {
    rl.prompt();
    return;
  }
  
  if (line === 'quit' || line === 'exit') {
    console.log('👋 Goodbye!'.cyan);
    process.exit(0);
  }
  
  try {
    // Status
    if (line === 'status') {
      const { data } = await axios.get(`${baseUrl}/status`);
      console.log('\n📊 Status:'.green.bold);
      console.log(`  Step: ${data.state.currentStep}`.white);
      console.log(`  Paused: ${data.state.isPaused ? '⏸️  Yes' : '▶️  No'}`.white);
      console.log(`  Running: ${data.state.isRunning ? '✅ Yes' : '⏹️  No'}`.white);
      console.log(`  URL: ${data.state.url}`.gray);
      console.log(`  Screenshots: ${data.state.screenshots}`.gray);
      console.log(`  Messages: ${data.state.messages}`.gray);
      console.log(`  Errors: ${data.state.errors}`.gray);
      console.log('');
    }
    
    // Pause
    else if (line === 'pause') {
      const { data } = await axios.get(`${baseUrl}/pause`);
      console.log(`\n⏸️  ${data.message}`.yellow);
      console.log('');
    }
    
    // Resume
    else if (line === 'resume') {
      const { data } = await axios.get(`${baseUrl}/resume`);
      console.log(`\n▶️  ${data.message}`.green);
      console.log('');
    }
    
    // Stop
    else if (line === 'stop') {
      const { data } = await axios.get(`${baseUrl}/stop`);
      console.log(`\n⏹️  ${data.message}`.red);
      console.log('');
    }
    
    // Screenshot
    else if (line === 'screenshot' || line === 'ss') {
      console.log('\n📸 Capturing screenshot...'.cyan);
      const { data } = await axios.get(`${baseUrl}/screenshot`);
      console.log(`✅ Screenshot saved: ${data.screenshot.filepath}`.green);
      console.log(`   Description: ${data.screenshot.description}`.gray);
      console.log('');
    }
    
    // Messages
    else if (line === 'messages' || line === 'msgs') {
      const { data } = await axios.get(`${baseUrl}/messages`);
      console.log('\n📝 Recent Messages:'.cyan.bold);
      data.messages.slice(-10).forEach(msg => {
        const time = new Date(msg.timestamp).toLocaleTimeString();
        const typeColor = {
          info: 'white',
          status: 'cyan',
          action: 'yellow',
          error: 'red',
          screenshot: 'magenta',
          control: 'green'
        }[msg.type] || 'white';
        
        console.log(`  [${time}] [${msg.type.toUpperCase()}] ${msg.message}`[typeColor]);
      });
      console.log('');
    }
    
    // Errors
    else if (line === 'errors') {
      const { data } = await axios.get(`${baseUrl}/errors`);
      if (data.errors.length === 0) {
        console.log('\n✅ No errors!'.green);
      } else {
        console.log(`\n❌ Errors (${data.errors.length}):`.red.bold);
        data.errors.forEach((err, i) => {
          console.log(`\n${i + 1}. ${err.message}`.red);
          if (err.stack) {
            console.log(`   ${err.stack.split('\n')[0]}`.gray);
          }
        });
      }
      console.log('');
    }
    
    // Screenshots list
    else if (line === 'screenshots' || line === 'ss list') {
      const { data } = await axios.get(`${baseUrl}/screenshots`);
      console.log(`\n📸 Screenshots (${data.screenshots.length}):`.magenta.bold);
      data.screenshots.slice(-10).forEach((ss, i) => {
        console.log(`  ${i + 1}. ${ss.description} - ${ss.filename}`.white);
        console.log(`     ${ss.filepath}`.gray);
      });
      console.log('');
    }
    
    // Evaluate JavaScript
    else if (line.startsWith('eval ')) {
      const code = line.substring(5);
      console.log(`\n⚙️  Evaluating: ${code}`.yellow);
      const { data } = await axios.post(`${baseUrl}/evaluate`, { code });
      console.log('Result:'.green);
      console.log(JSON.stringify(data.result, null, 2).white);
      console.log('');
    }
    
    // Navigate
    else if (line.startsWith('go ')) {
      const url = line.substring(3);
      console.log(`\n🌐 Navigating to: ${url}`.cyan);
      const { data } = await axios.get(`${baseUrl}/navigate?url=${encodeURIComponent(url)}`);
      console.log(`✅ ${data.message}`.green);
      console.log('');
    }
    
    // Click
    else if (line.startsWith('click ')) {
      const selector = line.substring(6);
      console.log(`\n🖱️  Clicking: ${selector}`.cyan);
      const { data } = await axios.post(`${baseUrl}/click`, { selector });
      console.log(`✅ ${data.message}`.green);
      console.log('');
    }
    
    // Help
    else if (line === 'help') {
      const { data } = await axios.get(`${baseUrl}/help`);
      console.log('\n📖 Available Commands:'.cyan.bold);
      Object.entries(data.endpoints).forEach(([endpoint, description]) => {
        console.log(`  ${endpoint.padEnd(30)} - ${description}`.white);
      });
      console.log('');
    }
    
    // Unknown
    else {
      console.log(`\n❓ Unknown command: "${line}"`.red);
      console.log('💡 Type "help" to see available commands\n'.yellow);
    }
    
  } catch (error) {
    if (error.code === 'ECONNREFUSED') {
      console.log('\n❌ Cannot connect to crawler!'.red);
      console.log('💡 Make sure the crawler is running:'.yellow);
      console.log(`   node scripts/interactive-crawler.js -u USER -p PASS\n`.gray);
    } else {
      console.log(`\n❌ Error: ${error.message}`.red);
      if (error.response && error.response.data) {
        console.log(`   ${JSON.stringify(error.response.data)}`.gray);
      }
      console.log('');
    }
  }
  
  rl.prompt();
});

rl.on('close', () => {
  console.log('\n👋 Goodbye!'.cyan);
  process.exit(0);
});
