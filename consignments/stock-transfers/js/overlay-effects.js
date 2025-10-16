/**
 * 🎆 Epic Overlay Effects - Particles, Confetti & Celebrations
 * 
 * Provides stunning visual effects for the submission overlay:
 * - Animated particle background
 * - Confetti celebrations on success
 * - Dynamic progress animations
 * - SSE real-time updates
 */

(function() {
  'use strict';

  // ============================================================================
  // PARTICLE SYSTEM
  // ============================================================================

  class ParticleSystem {
    constructor(canvasId) {
      this.canvas = document.getElementById(canvasId);
      if (!this.canvas) return;
      
      this.ctx = this.canvas.getContext('2d');
      this.particles = [];
      this.animationId = null;
      
      this.resize();
      window.addEventListener('resize', () => this.resize());
      
      this.createParticles();
      this.animate();
    }
    
    resize() {
      this.canvas.width = window.innerWidth;
      this.canvas.height = window.innerHeight;
    }
    
    createParticles() {
      const count = Math.floor((this.canvas.width * this.canvas.height) / 15000);
      
      for (let i = 0; i < count; i++) {
        this.particles.push({
          x: Math.random() * this.canvas.width,
          y: Math.random() * this.canvas.height,
          radius: Math.random() * 2 + 0.5,
          vx: (Math.random() - 0.5) * 0.5,
          vy: (Math.random() - 0.5) * 0.5,
          opacity: Math.random() * 0.5 + 0.2
        });
      }
    }
    
    animate() {
      this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
      
      this.particles.forEach(particle => {
        // Update position
        particle.x += particle.vx;
        particle.y += particle.vy;
        
        // Wrap around edges
        if (particle.x < 0) particle.x = this.canvas.width;
        if (particle.x > this.canvas.width) particle.x = 0;
        if (particle.y < 0) particle.y = this.canvas.height;
        if (particle.y > this.canvas.height) particle.y = 0;
        
        // Draw particle
        this.ctx.beginPath();
        this.ctx.arc(particle.x, particle.y, particle.radius, 0, Math.PI * 2);
        this.ctx.fillStyle = `rgba(0, 212, 255, ${particle.opacity})`;
        this.ctx.fill();
      });
      
      this.animationId = requestAnimationFrame(() => this.animate());
    }
    
    destroy() {
      if (this.animationId) {
        cancelAnimationFrame(this.animationId);
      }
    }
  }

  // ============================================================================
  // CONFETTI SYSTEM
  // ============================================================================

  class ConfettiSystem {
    constructor(canvasId) {
      this.canvas = document.getElementById(canvasId);
      if (!this.canvas) return;
      
      this.ctx = this.canvas.getContext('2d');
      this.confetti = [];
      this.animationId = null;
      this.colors = ['#00d4ff', '#0099ff', '#4cd964', '#ffcc00', '#ff3b30', '#af52de'];
      
      this.resize();
      window.addEventListener('resize', () => this.resize());
    }
    
    resize() {
      this.canvas.width = window.innerWidth;
      this.canvas.height = window.innerHeight;
    }
    
    burst() {
      const count = 150;
      const centerX = this.canvas.width / 2;
      const centerY = this.canvas.height / 2;
      
      for (let i = 0; i < count; i++) {
        const angle = (Math.PI * 2 * i) / count;
        const velocity = Math.random() * 10 + 5;
        
        this.confetti.push({
          x: centerX,
          y: centerY,
          vx: Math.cos(angle) * velocity,
          vy: Math.sin(angle) * velocity - 5,
          rotation: Math.random() * 360,
          rotationSpeed: Math.random() * 10 - 5,
          color: this.colors[Math.floor(Math.random() * this.colors.length)],
          size: Math.random() * 8 + 4,
          gravity: 0.3 + Math.random() * 0.2,
          opacity: 1
        });
      }
      
      this.animate();
    }
    
    animate() {
      this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
      
      this.confetti = this.confetti.filter(particle => {
        // Update physics
        particle.x += particle.vx;
        particle.y += particle.vy;
        particle.vy += particle.gravity;
        particle.vx *= 0.99;
        particle.rotation += particle.rotationSpeed;
        particle.opacity -= 0.01;
        
        // Draw confetti
        if (particle.opacity > 0) {
          this.ctx.save();
          this.ctx.translate(particle.x, particle.y);
          this.ctx.rotate((particle.rotation * Math.PI) / 180);
          this.ctx.globalAlpha = particle.opacity;
          this.ctx.fillStyle = particle.color;
          this.ctx.fillRect(-particle.size / 2, -particle.size / 2, particle.size, particle.size);
          this.ctx.restore();
          
          return true;
        }
        
        return false;
      });
      
      if (this.confetti.length > 0) {
        this.animationId = requestAnimationFrame(() => this.animate());
      }
    }
    
    destroy() {
      if (this.animationId) {
        cancelAnimationFrame(this.animationId);
      }
      this.confetti = [];
    }
  }

  // ============================================================================
  // OVERLAY CONTROLLER
  // ============================================================================

  window.OverlayEffects = {
    particles: null,
    confetti: null,
    sseConnection: null,
    
    initialize() {
      this.particles = new ParticleSystem('particle-canvas');
      this.confetti = new ConfettiSystem('confetti-canvas');
    },
    
    destroy() {
      if (this.particles) this.particles.destroy();
      if (this.confetti) this.confetti.destroy();
      if (this.sseConnection) this.sseConnection.close();
    },
    
    celebrate() {
      // Trigger confetti burst
      this.confetti.burst();
      
      // Update header for celebration
      const icon = document.getElementById('header-icon');
      const title = document.getElementById('overlay-title');
      const subtitle = document.getElementById('overlay-subtitle');
      
      if (icon) {
        icon.className = 'fa fa-check-circle';
        icon.style.color = '#4cd964';
        icon.style.animation = 'bounce 0.6s ease-out';
      }
      
      if (title) {
        title.textContent = '🎉 Success!';
        title.style.color = '#4cd964';
      }
      
      if (subtitle) {
        subtitle.textContent = 'Transfer created and ready for delivery!';
        subtitle.style.color = '#a8e6b7';
      }
      
      // Play success sound (if available)
      this.playSound('success');
    },
    
    showError(message) {
      const errorState = document.getElementById('error-state');
      const errorMessage = document.getElementById('error-message');
      
      if (errorState) errorState.style.display = 'block';
      if (errorMessage) errorMessage.textContent = message;
      
      // Update header for error
      const icon = document.getElementById('header-icon');
      const title = document.getElementById('overlay-title');
      const subtitle = document.getElementById('overlay-subtitle');
      
      if (icon) {
        icon.className = 'fa fa-shield-alt';
        icon.style.color = '#ff3b30';
      }
      
      if (title) {
        title.textContent = 'Oops! Something Went Wrong';
        title.style.color = '#ff6b6b';
      }
      
      if (subtitle) {
        subtitle.textContent = 'But don\'t worry - your data is 100% safe!';
        subtitle.style.color = '#b8bcc8';
      }
      
      // Play error sound (gentle, not alarming)
      this.playSound('error');
    },
    
    updateOverallProgress(percentage) {
      const bar = document.getElementById('overall-progress-bar');
      const text = document.getElementById('overall-percentage');
      
      if (bar) bar.style.width = percentage + '%';
      if (text) text.textContent = Math.round(percentage) + '%';
    },
    
    updateStepProgress(stepName, state, details = '', progress = 0) {
      const step = document.querySelector(`.progress-step[data-step="${stepName}"]`);
      if (!step) return;
      
      // Remove all state classes
      step.classList.remove('active', 'complete', 'error');
      
      // Add new state
      step.classList.add(state);
      
      // Update details text
      const detailsEl = step.querySelector('.step-details');
      if (detailsEl && details) {
        detailsEl.textContent = details;
      }
      
      // Update progress bar
      const progressBar = step.querySelector('.step-progress-fill');
      if (progressBar) {
        progressBar.style.width = progress + '%';
      }
    },
    
    addLiveFeedback(message, type = 'info', icon = null) {
      const container = document.getElementById('live-feedback');
      if (!container) return;
      
      const timestamp = new Date().toLocaleTimeString('en-NZ', { 
        hour: '2-digit', 
        minute: '2-digit',
        second: '2-digit',
        hour12: false 
      });
      
      const icons = {
        info: 'fa-info-circle',
        success: 'fa-check-circle',
        warning: 'fa-exclamation-triangle',
        error: 'fa-times-circle'
      };
      
      const messageEl = document.createElement('div');
      messageEl.className = `feedback-message ${type}`;
      messageEl.innerHTML = `
        <span class="timestamp">[${timestamp}]</span>
        <span class="icon"><i class="fa ${icon || icons[type]}"></i></span>
        <span class="text">${message}</span>
      `;
      
      container.appendChild(messageEl);
      
      // Auto-scroll to bottom
      const feedbackContainer = document.getElementById('live-feedback-container');
      if (feedbackContainer) {
        feedbackContainer.scrollTop = feedbackContainer.scrollHeight;
      }
      
      // Limit to last 50 messages
      const messages = container.querySelectorAll('.feedback-message');
      if (messages.length > 50) {
        messages[0].remove();
      }
    },
    
    connectSSE(transferId, sessionId) {
      const url = `/modules/consignments/api/consignment-upload-progress.php?transfer_id=${transferId}&session_id=${sessionId}`;
      
      this.addLiveFeedback('Connecting to real-time server...', 'info', 'fa-satellite-dish');
      
      this.sseConnection = new EventSource(url);
      
      this.sseConnection.onopen = () => {
        this.addLiveFeedback('✓ Connected to real-time server', 'success', 'fa-link');
        document.querySelector('.connection-dot')?.classList.add('connected');
      };
      
      this.sseConnection.onmessage = (event) => {
        try {
          const data = JSON.parse(event.data);
          this.handleSSEMessage(data);
        } catch (e) {
          console.error('Failed to parse SSE message:', e);
        }
      };
      
      this.sseConnection.onerror = (error) => {
        console.error('SSE connection error:', error);
        this.addLiveFeedback('Connection interrupted - retrying...', 'warning', 'fa-wifi');
        document.querySelector('.connection-dot')?.classList.remove('connected');
      };
    },
    
    handleSSEMessage(data) {
      console.log('SSE Message:', data);
      
      // Update overall progress
      if (data.progress !== undefined) {
        this.updateOverallProgress(data.progress);
      }
      
      // Update step progress
      if (data.step) {
        this.updateStepProgress(data.step, data.state || 'active', data.message, data.stepProgress || 0);
      }
      
      // Add to live feed
      if (data.message) {
        this.addLiveFeedback(data.message, data.type || 'info', data.icon);
      }
      
      // Handle completion
      if (data.completed) {
        this.celebrate();
        
        // Close SSE connection
        if (this.sseConnection) {
          this.sseConnection.close();
          this.sseConnection = null;
        }
        
        // Redirect after celebration
        setTimeout(() => {
          window.location.href = data.redirect || `/modules/consignments/transfers/view.php?id=${data.transfer_id}`;
        }, 3000);
      }
      
      // Handle errors
      if (data.error) {
        this.showError(data.error);
      }
    },
    
    playSound(type) {
      // TODO: Add sound effects if desired
      // Can use Web Audio API or HTML5 Audio
    }
  };

})();
