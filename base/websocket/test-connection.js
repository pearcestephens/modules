#!/usr/bin/env node

/**
 * WebSocket Server Connection Tester
 * Test connectivity and basic functionality
 *
 * Usage: node test-connection.js
 */

import { io } from 'socket.io-client';
import dotenv from 'dotenv';

dotenv.config();

const WS_URL = process.env.WS_URL || 'http://localhost:8080';
const TEST_TOKEN = process.env.TEST_TOKEN || 'test-token';

let testsPassed = 0;
let testsFailed = 0;

const colors = {
  reset: '\x1b[0m',
  green: '\x1b[32m',
  red: '\x1b[31m',
  yellow: '\x1b[33m',
  blue: '\x1b[34m',
  gray: '\x1b[90m'
};

function log(color, label, message) {
  console.log(`${color}[${label}]${colors.reset} ${message}`);
}

function pass(label, message) {
  log(colors.green, '✅ PASS', `${label}: ${message}`);
  testsPassed++;
}

function fail(label, message) {
  log(colors.red, '❌ FAIL', `${label}: ${message}`);
  testsFailed++;
}

function info(message) {
  log(colors.blue, 'ℹ️  INFO', message);
}

function warn(message) {
  log(colors.yellow, '⚠️  WARN', message);
}

async function testConnection() {
  info('Starting WebSocket connection tests...\n');

  return new Promise((resolve) => {
    const socket = io(WS_URL, {
      reconnection: false,
      transports: ['websocket', 'polling'],
      timeout: 10000
    });

    let connectionTest = false;
    let welcomeTest = false;
    let authTest = false;

    // Test: Connection established
    socket.on('connect', () => {
      connectionTest = true;
      pass('Connection', `Connected to ${WS_URL}`);
    });

    // Test: Welcome message received
    socket.on('connected', (data) => {
      welcomeTest = true;
      pass('Welcome Message', `Server identified as: ${data.serverId}`);
    });

    // Test: Authentication
    socket.on('authenticated', (data) => {
      authTest = true;
      pass('Authentication', `User ${data.userId} authenticated successfully`);

      // Test message broadcasting
      setTimeout(() => {
        testMessageBroadcast(socket);
      }, 500);
    });

    // Test: Error handling
    socket.on('error', (error) => {
      fail('Socket Error', error.message || error);
    });

    // Test: Timeout
    setTimeout(() => {
      if (!connectionTest) {
        fail('Connection', `Could not connect to ${WS_URL} within 10 seconds`);
      }
      if (!welcomeTest) {
        warn('Connection', 'Welcome message not received (server may be starting)');
      }

      // Authenticate
      socket.emit('authenticate', { token: TEST_TOKEN });
    }, 2000);

    // Test: Full timeout - cleanup
    setTimeout(() => {
      socket.disconnect();
      printResults();
      resolve();
    }, 15000);
  });
}

function testMessageBroadcast(socket) {
  info('Testing message broadcast...\n');

  // Join a conversation
  socket.emit('conversation:join', { conversationId: 1 });
  pass('Conversation Join', 'Emitted conversation:join event');

  // Test typing indicator
  socket.emit('typing:start', { conversationId: 1 });
  pass('Typing Indicator', 'Emitted typing:start event');

  setTimeout(() => {
    socket.emit('typing:stop', { conversationId: 1 });
    pass('Typing Stop', 'Emitted typing:stop event');
  }, 500);

  // Test message send
  const messageId = `test_${Date.now()}`;
  socket.emit('message:send', {
    conversationId: 1,
    messageId,
    text: 'Test message from connection tester',
    mentions: []
  });
  pass('Message Send', `Emitted message:send event (ID: ${messageId})`);

  // Listen for acknowledgement
  socket.on('message:ack', (data) => {
    if (data.messageId === messageId) {
      pass('Message Acknowledgement', `Received ACK for message ${messageId}`);
    }
  });

  // Test presence request
  setTimeout(() => {
    socket.emit('presence:request');
    pass('Presence Request', 'Emitted presence:request event');
  }, 1000);

  socket.on('presence:list', (data) => {
    pass('Presence Response', `Received ${data.count} online users`);
  });
}

function printResults() {
  console.log('\n' + '='.repeat(60));
  console.log('TEST RESULTS');
  console.log('='.repeat(60) + '\n');

  const total = testsPassed + testsFailed;
  const percentage = total > 0 ? Math.round((testsPassed / total) * 100) : 0;

  if (testsFailed === 0 && testsPassed > 0) {
    console.log(`${colors.green}✅ ALL TESTS PASSED${colors.reset}`);
  } else if (testsFailed > 0) {
    console.log(`${colors.red}❌ SOME TESTS FAILED${colors.reset}`);
  } else {
    console.log(`${colors.yellow}⚠️  NO TESTS COMPLETED${colors.reset}`);
  }

  console.log(`\nPassed: ${colors.green}${testsPassed}${colors.reset}`);
  console.log(`Failed: ${colors.red}${testsFailed}${colors.reset}`);
  console.log(`Total:  ${total}`);
  console.log(`Success Rate: ${percentage}%\n`);

  console.log('='.repeat(60));
}

// Run tests
testConnection().catch(err => {
  fail('Uncaught Error', err.message);
  printResults();
  process.exit(1);
});
