/**
 * @file
 * Halaqa Login Page JavaScript
 * Adds interactivity and animations to the login page.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.halaqaLogin = {
    attach: function (context, settings) {
      // Initialize login page enhancements
      once('halaqaLogin', '.login-page-wrapper', context).forEach(function (wrapper) {
        new HalaqaLoginPage(wrapper);
      });
    }
  };

  /**
   * HalaqaLoginPage Class
   * Handles all login page interactions and animations.
   */
  class HalaqaLoginPage {
    constructor(wrapper) {
      this.wrapper = wrapper;
      this.form = wrapper.querySelector('.login-form-wrapper form');
      this.inputs = wrapper.querySelectorAll('input[type="text"], input[type="password"], input[type="email"]');
      this.submitBtn = wrapper.querySelector('input[type="submit"], button[type="submit"]');
      this.featureItems = wrapper.querySelectorAll('.feature-item');
      this.floatingIcons = wrapper.querySelectorAll('.float-icon');
      
      this.init();
    }

    init() {
      this.animateEntrance();
      this.setupInputEffects();
      this.setupFormValidation();
      this.setupParallaxIcons();
      this.setupFeatureHover();
      this.addPasswordToggle();
      this.setupLoadingState();
    }

    /**
     * Entrance animations
     */
    animateEntrance() {
      const elements = [
        { el: this.wrapper.querySelector('.logo-section'), delay: 100 },
        { el: this.wrapper.querySelector('.features-list'), delay: 200 },
        { el: this.wrapper.querySelector('.quranic-verse'), delay: 300 },
        { el: this.wrapper.querySelector('.form-container'), delay: 150 },
      ];

      elements.forEach(item => {
        if (item.el) {
          item.el.style.opacity = '0';
          item.el.style.transform = 'translateY(20px)';
          
          setTimeout(() => {
            item.el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            item.el.style.opacity = '1';
            item.el.style.transform = 'translateY(0)';
          }, item.delay);
        }
      });

      // Animate feature items individually
      this.featureItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateX(-20px)';
        
        setTimeout(() => {
          item.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
          item.style.opacity = '1';
          item.style.transform = 'translateX(0)';
        }, 400 + (index * 150));
      });
    }

    /**
     * Input focus effects
     */
    setupInputEffects() {
      this.inputs.forEach(input => {
        const wrapper = input.closest('.form-item');
        
        // Add focus effects
        input.addEventListener('focus', () => {
          if (wrapper) {
            wrapper.classList.add('is-focused');
          }
          this.createRipple(input);
        });

        input.addEventListener('blur', () => {
          if (wrapper) {
            wrapper.classList.remove('is-focused');
            if (input.value) {
              wrapper.classList.add('has-value');
            } else {
              wrapper.classList.remove('has-value');
            }
          }
        });

        // Add typing animation
        input.addEventListener('input', () => {
          if (wrapper && input.value) {
            wrapper.classList.add('is-typing');
            clearTimeout(input.typingTimeout);
            input.typingTimeout = setTimeout(() => {
              wrapper.classList.remove('is-typing');
            }, 500);
          }
        });
      });
    }

    /**
     * Create ripple effect
     */
    createRipple(element) {
      const ripple = document.createElement('span');
      ripple.classList.add('input-ripple');
      ripple.style.cssText = `
        position: absolute;
        background: rgba(255, 215, 0, 0.3);
        border-radius: 50%;
        transform: scale(0);
        animation: ripple 0.6s ease-out;
        pointer-events: none;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
      `;

      const wrapper = element.closest('.form-item');
      if (wrapper) {
        wrapper.style.position = 'relative';
        wrapper.style.overflow = 'hidden';
        wrapper.appendChild(ripple);
        
        setTimeout(() => ripple.remove(), 600);
      }
    }

    /**
     * Form validation
     */
    setupFormValidation() {
      if (!this.form) return;

      this.form.addEventListener('submit', (e) => {
        let isValid = true;
        
        this.inputs.forEach(input => {
          if (input.required && !input.value.trim()) {
            isValid = false;
            this.showError(input, Drupal.t('This field is required'));
          } else {
            this.clearError(input);
          }
        });

        if (!isValid) {
          e.preventDefault();
          this.shakeForm();
        }
      });
    }

    /**
     * Show error message
     */
    showError(input, message) {
      const wrapper = input.closest('.form-item');
      if (!wrapper) return;

      wrapper.classList.add('has-error');
      input.style.borderColor = '#ef4444';
      input.style.animation = 'shake 0.5s ease';

      let errorEl = wrapper.querySelector('.error-message');
      if (!errorEl) {
        errorEl = document.createElement('span');
        errorEl.classList.add('error-message');
        errorEl.style.cssText = `
          display: block;
          color: #ef4444;
          font-size: 0.85rem;
          margin-top: 5px;
          animation: fadeIn 0.3s ease;
        `;
        wrapper.appendChild(errorEl);
      }
      errorEl.textContent = message;

      setTimeout(() => {
        input.style.animation = '';
      }, 500);
    }

    /**
     * Clear error message
     */
    clearError(input) {
      const wrapper = input.closest('.form-item');
      if (!wrapper) return;

      wrapper.classList.remove('has-error');
      input.style.borderColor = '';
      
      const errorEl = wrapper.querySelector('.error-message');
      if (errorEl) {
        errorEl.remove();
      }
    }

    /**
     * Shake form animation
     */
    shakeForm() {
      const formContainer = this.wrapper.querySelector('.form-container');
      if (formContainer) {
        formContainer.style.animation = 'shake 0.5s ease';
        setTimeout(() => {
          formContainer.style.animation = '';
        }, 500);
      }
    }

    /**
     * Parallax effect on floating icons
     */
    setupParallaxIcons() {
      if (this.floatingIcons.length === 0) return;

      document.addEventListener('mousemove', (e) => {
        const xPos = (e.clientX / window.innerWidth - 0.5) * 20;
        const yPos = (e.clientY / window.innerHeight - 0.5) * 20;

        this.floatingIcons.forEach((icon, index) => {
          const depth = (index + 1) * 0.5;
          icon.style.transform = `translate(${xPos * depth}px, ${yPos * depth}px)`;
        });
      });
    }

    /**
     * Feature items hover effects
     */
    setupFeatureHover() {
      this.featureItems.forEach(item => {
        item.addEventListener('mouseenter', () => {
          const icon = item.querySelector('.feature-icon');
          if (icon) {
            icon.style.transform = 'scale(1.2) rotate(10deg)';
            icon.style.transition = 'transform 0.3s ease';
          }
        });

        item.addEventListener('mouseleave', () => {
          const icon = item.querySelector('.feature-icon');
          if (icon) {
            icon.style.transform = '';
          }
        });
      });
    }

    /**
     * Add password visibility toggle
     */
    addPasswordToggle() {
      const passwordInputs = this.wrapper.querySelectorAll('input[type="password"]');
      
      passwordInputs.forEach(input => {
        const wrapper = input.closest('.form-item');
        if (!wrapper) return;

        wrapper.style.position = 'relative';

        const toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.classList.add('password-toggle');
        toggleBtn.innerHTML = '👁️';
        toggleBtn.setAttribute('aria-label', Drupal.t('Toggle password visibility'));
        toggleBtn.style.cssText = `
          position: absolute;
          right: 15px;
          top: 50%;
          transform: translateY(-50%);
          background: none;
          border: none;
          cursor: pointer;
          font-size: 1.2rem;
          opacity: 0.6;
          transition: opacity 0.3s ease;
          padding: 5px;
          z-index: 2;
        `;

        // RTL support
        if (document.dir === 'rtl') {
          toggleBtn.style.right = 'auto';
          toggleBtn.style.left = '15px';
        }

        toggleBtn.addEventListener('click', () => {
          if (input.type === 'password') {
            input.type = 'text';
            toggleBtn.innerHTML = '🙈';
          } else {
            input.type = 'password';
            toggleBtn.innerHTML = '👁️';
          }
        });

        toggleBtn.addEventListener('mouseenter', () => {
          toggleBtn.style.opacity = '1';
        });

        toggleBtn.addEventListener('mouseleave', () => {
          toggleBtn.style.opacity = '0.6';
        });

        // Adjust input padding for toggle button
        input.style.paddingRight = document.dir === 'rtl' ? input.style.paddingRight : '50px';
        input.style.paddingLeft = document.dir === 'rtl' ? '50px' : input.style.paddingLeft;

        wrapper.appendChild(toggleBtn);
      });
    }

    /**
     * Loading state for form submission
     */
    setupLoadingState() {
      if (!this.form || !this.submitBtn) return;

      this.form.addEventListener('submit', () => {
        // Only add loading if form is valid
        const isValid = Array.from(this.inputs).every(input => {
          return !input.required || input.value.trim();
        });

        if (isValid) {
          this.submitBtn.disabled = true;
          this.submitBtn.style.opacity = '0.7';
          this.submitBtn.style.cursor = 'wait';
          
          const originalText = this.submitBtn.value || this.submitBtn.textContent;
          if (this.submitBtn.tagName === 'INPUT') {
            this.submitBtn.value = Drupal.t('Signing in...');
          } else {
            this.submitBtn.innerHTML = `
              <span class="loading-spinner"></span>
              ${Drupal.t('Signing in...')}
            `;
          }

          // Add spinner styles
          const style = document.createElement('style');
          style.textContent = `
            .loading-spinner {
              display: inline-block;
              width: 18px;
              height: 18px;
              border: 2px solid rgba(26, 47, 74, 0.3);
              border-radius: 50%;
              border-top-color: #1a2f4a;
              animation: spin 1s ease-in-out infinite;
              margin-right: 10px;
              vertical-align: middle;
            }
            @keyframes spin {
              to { transform: rotate(360deg); }
            }
          `;
          document.head.appendChild(style);
        }
      });
    }
  }

  // Add CSS keyframes for animations
  const style = document.createElement('style');
  style.textContent = `
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
      20%, 40%, 60%, 80% { transform: translateX(5px); }
    }
    
    @keyframes ripple {
      to {
        transform: scale(4);
        opacity: 0;
      }
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-5px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .form-item.is-focused label {
      color: #ffd700;
      transform: translateY(-2px);
      transition: all 0.3s ease;
    }
    
    .form-item.is-typing input {
      box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.1);
    }
  `;
  document.head.appendChild(style);

})(Drupal, once);

