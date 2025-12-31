/**
 * @file
 * Landing page JavaScript.
 */

(function (Drupal) {
  'use strict';

  /**
   * Animate numbers on scroll.
   */
  Drupal.behaviors.halaqaLandingNumbers = {
    attach: function (context, settings) {
      const numbers = context.querySelectorAll('.stat-number');
      if (!numbers.length) return;
      
      const animateNumber = (element) => {
        const text = element.textContent;
        const target = parseInt(text.replace(/[^0-9]/g, ''), 10);
        if (isNaN(target) || target === 0) return;
        
        let current = 0;
        const duration = 2000;
        const stepTime = 30;
        const steps = duration / stepTime;
        const increment = target / steps;
        const suffix = text.includes('+') ? '+' : '';
        
        element.textContent = '0' + suffix;
        
        const timer = setInterval(function () {
          current += increment;
          if (current >= target) {
            element.textContent = target + suffix;
            clearInterval(timer);
          } else {
            element.textContent = Math.floor(current) + suffix;
          }
        }, stepTime);
      };
      
      const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            animateNumber(entry.target);
            observer.unobserve(entry.target);
          }
        });
      }, { threshold: 0.5 });
      
      numbers.forEach(function (num) {
        observer.observe(num);
      });
    }
  };

  /**
   * Smooth scroll for anchor links.
   */
  Drupal.behaviors.halaqaLandingSmoothScroll = {
    attach: function (context, settings) {
      const links = context.querySelectorAll('a[href^="#"]');
      
      links.forEach(function (link) {
        link.addEventListener('click', function (e) {
          const targetId = this.getAttribute('href');
          if (targetId === '#') return;
          
          const target = document.querySelector(targetId);
          if (target) {
            e.preventDefault();
            target.scrollIntoView({
              behavior: 'smooth',
              block: 'start'
            });
          }
        });
      });
    }
  };

  /**
   * Fade in elements on scroll.
   */
  Drupal.behaviors.halaqaLandingFadeIn = {
    attach: function (context, settings) {
      const elements = context.querySelectorAll('.feature-card, .step-card, .testimonial-card');
      
      if (!elements.length) return;
      
      const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry, index) {
          if (entry.isIntersecting) {
            setTimeout(function () {
              entry.target.style.opacity = '1';
              entry.target.style.transform = 'translateY(0)';
            }, index * 100);
            observer.unobserve(entry.target);
          }
        });
      }, { threshold: 0.1 });
      
      elements.forEach(function (el) {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
      });
    }
  };

})(Drupal);

