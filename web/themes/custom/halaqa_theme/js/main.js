/**
 * @file
 * Main JavaScript for Halaqa Theme
 * Professional interactions and animations
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Header scroll behavior
   */
  Drupal.behaviors.halaqaHeader = {
    attach: function (context, settings) {
      once('header-scroll', '.header', context).forEach(function (header) {
        let lastScrollTop = 0;
        let ticking = false;
        
        window.addEventListener('scroll', function () {
          if (!ticking) {
            window.requestAnimationFrame(function () {
              const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
              
              // Add scrolled class
              if (scrollTop > 50) {
                header.classList.add('scrolled');
              } else {
                header.classList.remove('scrolled');
              }
              
              // Hide/show on scroll direction
              if (scrollTop > lastScrollTop && scrollTop > 200) {
                header.classList.add('header-hidden');
                header.classList.remove('header-visible');
              } else {
                header.classList.remove('header-hidden');
                header.classList.add('header-visible');
              }
              
              lastScrollTop = scrollTop;
              ticking = false;
            });
            ticking = true;
          }
        });
      });
    }
  };

  /**
   * Mobile menu toggle
   */
  Drupal.behaviors.halaqaMobileMenu = {
    attach: function (context, settings) {
      once('mobile-menu', '.mobile-menu-toggle', context).forEach(function (toggle) {
        toggle.addEventListener('click', function () {
          const nav = document.querySelector('.main-navigation');
          if (nav) {
            nav.classList.toggle('is-open');
            toggle.classList.toggle('is-active');
            document.body.classList.toggle('menu-open');
          }
        });
      });
    }
  };

  /**
   * Animate numbers counting up
   */
  Drupal.behaviors.halaqaAnimateNumbers = {
    attach: function (context, settings) {
      once('animate-numbers', '.stat-number', context).forEach(function (element) {
        const target = parseInt(element.textContent.replace(/[^0-9]/g, ''), 10);
        if (isNaN(target) || target === 0) return;
        
        const observer = new IntersectionObserver(function (entries) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) {
              let current = 0;
              const duration = 1500;
              const stepTime = 30;
              const steps = duration / stepTime;
              const increment = target / steps;
              
              element.textContent = '0';
              
              const timer = setInterval(function () {
                current += increment;
                if (current >= target) {
                  element.textContent = target.toLocaleString('ar-EG');
                  clearInterval(timer);
                } else {
                  element.textContent = Math.floor(current).toLocaleString('ar-EG');
                }
              }, stepTime);
              
              observer.unobserve(entry.target);
            }
          });
        }, { threshold: 0.5 });
        
        observer.observe(element);
      });
    }
  };

  /**
   * Cards fade-in animation on scroll
   */
  Drupal.behaviors.halaqaFadeInCards = {
    attach: function (context, settings) {
      const cards = once('fade-in-cards', '.card, .stat-card, .halaqa-card, .child-card, .record-card, .student-card, .feature-card', context);
      
      if (cards.length === 0) return;
      
      const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry, index) {
          if (entry.isIntersecting) {
            setTimeout(function () {
              entry.target.classList.add('animate-in');
            }, index * 100);
            observer.unobserve(entry.target);
          }
        });
      }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });
      
      cards.forEach(function (card) {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(card);
      });
      
      // Add animate-in styles
      const style = document.createElement('style');
      style.textContent = '.animate-in { opacity: 1 !important; transform: translateY(0) !important; }';
      document.head.appendChild(style);
    }
  };

  /**
   * Smooth scroll for anchor links
   */
  Drupal.behaviors.halaqaSmoothScroll = {
    attach: function (context, settings) {
      once('smooth-scroll', 'a[href^="#"]', context).forEach(function (element) {
        element.addEventListener('click', function (e) {
          const targetId = this.getAttribute('href');
          if (targetId === '#') return;
          
          const targetElement = document.querySelector(targetId);
          if (targetElement) {
            e.preventDefault();
            const headerHeight = document.querySelector('.header')?.offsetHeight || 0;
            const targetPosition = targetElement.getBoundingClientRect().top + window.pageYOffset - headerHeight - 20;
            
            window.scrollTo({
              top: targetPosition,
              behavior: 'smooth'
            });
          }
        });
      });
    }
  };

  /**
   * Progress bar animation
   */
  Drupal.behaviors.halaqaProgressBars = {
    attach: function (context, settings) {
      once('progress-bars', '.eval-bar-container .eval-bar, .progress-bar, [style*="width"]', context).forEach(function (element) {
        if (!element.style.width) return;
        
        const targetWidth = element.style.width;
        element.style.width = '0';
        
        const observer = new IntersectionObserver(function (entries) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) {
              setTimeout(function () {
                entry.target.style.transition = 'width 1.2s ease-out';
                entry.target.style.width = targetWidth;
              }, 200);
              observer.unobserve(entry.target);
            }
          });
        }, { threshold: 0.5 });
        
        observer.observe(element);
      });
    }
  };

  /**
   * Notification badge pulse
   */
  Drupal.behaviors.halaqaNotifications = {
    attach: function (context, settings) {
      once('notification-pulse', '.notification-badge', context).forEach(function (badge) {
        // Add pulse animation
        badge.style.animation = 'pulse-badge 2s infinite';
      });
    }
  };

  /**
   * User dropdown behavior
   */
  Drupal.behaviors.halaqaUserDropdown = {
    attach: function (context, settings) {
      once('user-dropdown', '.user-dropdown', context).forEach(function (dropdown) {
        const avatar = dropdown.querySelector('.user-avatar');
        const menu = dropdown.querySelector('.user-dropdown-menu');
        
        if (!avatar || !menu) return;
        
        // Click to toggle on mobile
        avatar.addEventListener('click', function (e) {
          if (window.innerWidth <= 768) {
            e.stopPropagation();
            menu.classList.toggle('is-open');
          }
        });
        
        // Close on outside click
        document.addEventListener('click', function (e) {
          if (!dropdown.contains(e.target)) {
            menu.classList.remove('is-open');
          }
        });
      });
    }
  };

  /**
   * Search box expand
   */
  Drupal.behaviors.halaqaSearch = {
    attach: function (context, settings) {
      once('search-expand', '.header-search input', context).forEach(function (input) {
        input.addEventListener('focus', function () {
          this.parentElement.classList.add('is-expanded');
        });
        
        input.addEventListener('blur', function () {
          if (!this.value) {
            this.parentElement.classList.remove('is-expanded');
          }
        });
      });
    }
  };

  /**
   * Tooltip initialization
   */
  Drupal.behaviors.halaqaTooltips = {
    attach: function (context, settings) {
      once('tooltips', '[data-tooltip]', context).forEach(function (element) {
        element.addEventListener('mouseenter', function () {
          const tooltip = document.createElement('div');
          tooltip.className = 'tooltip';
          tooltip.textContent = this.getAttribute('data-tooltip');
          document.body.appendChild(tooltip);
          
          const rect = this.getBoundingClientRect();
          tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';
          tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
          tooltip.classList.add('visible');
          
          this._tooltip = tooltip;
        });
        
        element.addEventListener('mouseleave', function () {
          if (this._tooltip) {
            this._tooltip.remove();
            this._tooltip = null;
          }
        });
      });
    }
  };

  /**
   * Form validation enhancement
   */
  Drupal.behaviors.halaqaFormValidation = {
    attach: function (context, settings) {
      once('form-validation', 'form input, form select, form textarea', context).forEach(function (field) {
        field.addEventListener('invalid', function (e) {
          this.classList.add('is-invalid');
          // Add shake animation
          this.classList.add('shake');
          setTimeout(() => this.classList.remove('shake'), 500);
        });
        
        field.addEventListener('input', function () {
          if (this.validity.valid) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
          }
        });
      });
    }
  };

  /**
   * Lazy load images
   */
  Drupal.behaviors.halaqaLazyLoad = {
    attach: function (context, settings) {
      if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver(function (entries) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) {
              const img = entry.target;
              if (img.dataset.src) {
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
              }
              img.classList.add('loaded');
              imageObserver.unobserve(img);
            }
          });
        });
        
        once('lazy-load', 'img[data-src]', context).forEach(function (img) {
          imageObserver.observe(img);
        });
      }
    }
  };

  /**
   * Confirmation dialogs
   */
  Drupal.behaviors.halaqaConfirm = {
    attach: function (context, settings) {
      once('confirm-action', '[data-confirm]', context).forEach(function (element) {
        element.addEventListener('click', function (e) {
          const message = this.getAttribute('data-confirm');
          if (!confirm(message)) {
            e.preventDefault();
          }
        });
      });
    }
  };

  /**
   * Print functionality
   */
  Drupal.behaviors.halaqaPrint = {
    attach: function (context, settings) {
      once('print-button', '.btn-print, [data-print]', context).forEach(function (element) {
        element.addEventListener('click', function (e) {
          e.preventDefault();
          window.print();
        });
      });
    }
  };

  /**
   * Copy to clipboard
   */
  Drupal.behaviors.halaqaCopyClipboard = {
    attach: function (context, settings) {
      once('copy-clipboard', '[data-copy]', context).forEach(function (element) {
        element.addEventListener('click', function () {
          const text = this.getAttribute('data-copy');
          navigator.clipboard.writeText(text).then(function () {
            // Show success feedback
            const originalText = element.textContent;
            element.textContent = '✓ Copied!';
            element.classList.add('copied');
            
            setTimeout(function () {
              element.textContent = originalText;
              element.classList.remove('copied');
            }, 2000);
          });
        });
      });
    }
  };

  /**
   * Back to top button
   */
  Drupal.behaviors.halaqaBackToTop = {
    attach: function (context, settings) {
      once('back-to-top', 'body', context).forEach(function () {
        // Create button
        const btn = document.createElement('button');
        btn.className = 'back-to-top';
        btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>';
        btn.setAttribute('aria-label', 'Back to top');
        document.body.appendChild(btn);
        
        // Show/hide on scroll
        window.addEventListener('scroll', function () {
          if (window.pageYOffset > 500) {
            btn.classList.add('visible');
          } else {
            btn.classList.remove('visible');
          }
        });
        
        // Scroll to top
        btn.addEventListener('click', function () {
          window.scrollTo({
            top: 0,
            behavior: 'smooth'
          });
        });
        
        // Add styles
        const style = document.createElement('style');
        style.textContent = `
          .back-to-top {
            position: fixed;
            bottom: 30px;
            left: 30px;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transform: translateY(20px);
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(13, 148, 136, 0.4);
            z-index: 999;
          }
          [dir="rtl"] .back-to-top {
            left: auto;
            right: 30px;
          }
          .back-to-top.visible {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
          }
          .back-to-top:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(13, 148, 136, 0.5);
          }
        `;
        document.head.appendChild(style);
      });
    }
  };

})(Drupal, once);
