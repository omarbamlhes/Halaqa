/**
 * @file
 * Halaqa User Profile JavaScript
 * Adds interactivity and animations to the profile page.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.halaqaProfile = {
    attach: function (context, settings) {
      // Initialize profile page enhancements
      once('halaqaProfile', '.profile-page', context).forEach(function (wrapper) {
        new HalaqaProfilePage(wrapper);
      });
    }
  };

  /**
   * HalaqaProfilePage Class
   */
  class HalaqaProfilePage {
    constructor(wrapper) {
      this.wrapper = wrapper;
      this.statValues = wrapper.querySelectorAll('.stat-value[data-count]');
      this.progressRing = wrapper.querySelector('.progress-ring-fill');
      this.achievementBadges = wrapper.querySelectorAll('.achievement-badge');
      
      this.init();
    }

    init() {
      this.animateCounters();
      this.animateProgressRing();
      this.setupAchievementHovers();
      this.setupParallaxEffect();
      this.addSVGGradient();
    }

    /**
     * Animate stat counters
     */
    animateCounters() {
      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            const el = entry.target;
            const target = parseInt(el.getAttribute('data-count'), 10);
            this.countUp(el, target);
            observer.unobserve(el);
          }
        });
      }, { threshold: 0.5 });

      this.statValues.forEach(el => observer.observe(el));
    }

    /**
     * Count up animation
     */
    countUp(element, target) {
      const duration = 1500;
      const frameDuration = 1000 / 60;
      const totalFrames = Math.round(duration / frameDuration);
      const easeOutQuad = t => t * (2 - t);
      
      let frame = 0;
      const counter = setInterval(() => {
        frame++;
        const progress = easeOutQuad(frame / totalFrames);
        const current = Math.round(target * progress);
        
        element.textContent = current.toLocaleString();
        
        if (frame === totalFrames) {
          clearInterval(counter);
          element.textContent = target.toLocaleString();
        }
      }, frameDuration);
    }

    /**
     * Animate progress ring
     */
    animateProgressRing() {
      if (!this.progressRing) return;

      const style = this.progressRing.style;
      const dashoffset = style.strokeDashoffset;
      
      // Set initial state
      style.strokeDashoffset = '408';
      
      // Animate after a delay
      setTimeout(() => {
        style.transition = 'stroke-dashoffset 1.5s ease-out';
        style.strokeDashoffset = dashoffset;
      }, 500);
    }

    /**
     * Achievement badge hover effects
     */
    setupAchievementHovers() {
      this.achievementBadges.forEach(badge => {
        badge.addEventListener('mouseenter', () => {
          if (badge.classList.contains('earned')) {
            const icon = badge.querySelector('.badge-icon');
            if (icon) {
              icon.style.transform = 'scale(1.3) rotate(10deg)';
              icon.style.transition = 'transform 0.3s ease';
            }
          }
        });

        badge.addEventListener('mouseleave', () => {
          const icon = badge.querySelector('.badge-icon');
          if (icon) {
            icon.style.transform = '';
          }
        });

        // Add click effect for locked badges
        badge.addEventListener('click', () => {
          if (badge.classList.contains('locked')) {
            badge.style.animation = 'shake 0.5s ease';
            setTimeout(() => {
              badge.style.animation = '';
            }, 500);
          }
        });
      });
    }

    /**
     * Parallax effect on header
     */
    setupParallaxEffect() {
      const header = this.wrapper.querySelector('.profile-header');
      if (!header) return;

      const pattern = header.querySelector('.profile-pattern');
      if (!pattern) return;

      header.addEventListener('mousemove', (e) => {
        const rect = header.getBoundingClientRect();
        const x = (e.clientX - rect.left) / rect.width - 0.5;
        const y = (e.clientY - rect.top) / rect.height - 0.5;
        
        pattern.style.transform = `translate(${x * 20}px, ${y * 20}px)`;
      });

      header.addEventListener('mouseleave', () => {
        pattern.style.transform = '';
        pattern.style.transition = 'transform 0.5s ease';
      });
    }

    /**
     * Add SVG gradient for progress ring
     */
    addSVGGradient() {
      const svg = this.wrapper.querySelector('.progress-ring');
      if (!svg) return;

      // Check if gradient already exists
      if (svg.querySelector('#progressGradient')) return;

      const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
      defs.innerHTML = `
        <linearGradient id="progressGradient" x1="0%" y1="0%" x2="100%" y2="0%">
          <stop offset="0%" style="stop-color:#ffd700"/>
          <stop offset="50%" style="stop-color:#00d4aa"/>
          <stop offset="100%" style="stop-color:#667eea"/>
        </linearGradient>
      `;
      svg.insertBefore(defs, svg.firstChild);

      // Apply gradient to fill
      const fill = svg.querySelector('.progress-ring-fill');
      if (fill) {
        fill.style.stroke = 'url(#progressGradient)';
      }
    }
  }

  // Add shake animation CSS
  const style = document.createElement('style');
  style.textContent = `
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      10%, 30%, 50%, 70%, 90% { transform: translateX(-3px); }
      20%, 40%, 60%, 80% { transform: translateX(3px); }
    }
  `;
  document.head.appendChild(style);

})(Drupal, once);

