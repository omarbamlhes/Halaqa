/**
 * @file
 * Mobile Menu JavaScript for Halaqa Theme
 */

(function (Drupal) {
  'use strict';

  /**
   * Mobile menu behavior.
   */
  Drupal.behaviors.halaqaMobileMenu = {
    attach: function (context, settings) {
      // Only run once
      if (context !== document) {
        return;
      }

      const mobileToggle = document.querySelector('.mobile-menu-toggle');
      const mainNav = document.querySelector('.main-navigation');
      const body = document.body;
      const header = document.querySelector('.header');
      let lastScrollY = window.scrollY;
      
      // ==================== MOBILE MENU TOGGLE ====================
      if (mobileToggle && mainNav) {
        mobileToggle.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          
          const isOpen = mainNav.classList.contains('is-open');
          
          if (isOpen) {
            closeMenu();
          } else {
            openMenu();
          }
        });

        // Close menu when clicking outside
        document.addEventListener('click', function (e) {
          if (mainNav.classList.contains('is-open')) {
            if (!mainNav.contains(e.target) && !mobileToggle.contains(e.target)) {
              closeMenu();
            }
          }
        });

        // Close menu on escape key
        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape' && mainNav.classList.contains('is-open')) {
            closeMenu();
          }
        });

        // Close menu when clicking on a link
        const menuLinks = mainNav.querySelectorAll('a');
        menuLinks.forEach(function (link) {
          link.addEventListener('click', function () {
            closeMenu();
          });
        });
      }

      function openMenu() {
        mainNav.classList.add('is-open');
        mobileToggle.classList.add('is-active');
        mobileToggle.setAttribute('aria-expanded', 'true');
        body.style.overflow = 'hidden';
        
        // Add overlay
        const overlay = document.createElement('div');
        overlay.className = 'mobile-menu-overlay';
        overlay.style.cssText = `
          position: fixed;
          top: 65px;
          left: 0;
          right: 0;
          bottom: 0;
          background: rgba(0, 0, 0, 0.5);
          z-index: 9997;
          animation: fadeIn 0.3s ease;
        `;
        document.body.appendChild(overlay);
        
        overlay.addEventListener('click', closeMenu);
      }

      function closeMenu() {
        mainNav.classList.remove('is-open');
        mobileToggle.classList.remove('is-active');
        mobileToggle.setAttribute('aria-expanded', 'false');
        body.style.overflow = '';
        
        // Remove overlay
        const overlay = document.querySelector('.mobile-menu-overlay');
        if (overlay) {
          overlay.remove();
        }
      }

      // ==================== STICKY HEADER ON SCROLL ====================
      if (header) {
        let ticking = false;
        
        window.addEventListener('scroll', function () {
          if (!ticking) {
            window.requestAnimationFrame(function () {
              handleScroll();
              ticking = false;
            });
            ticking = true;
          }
        });

        function handleScroll() {
          const currentScrollY = window.scrollY;
          
          // Add scrolled class for shadow effect
          if (currentScrollY > 10) {
            header.classList.add('scrolled');
          } else {
            header.classList.remove('scrolled');
          }
          
          // Hide/show header on scroll (only on mobile)
          if (window.innerWidth <= 768) {
            if (currentScrollY > lastScrollY && currentScrollY > 100) {
              // Scrolling down
              header.classList.add('header-hidden');
              header.classList.remove('header-visible');
            } else {
              // Scrolling up
              header.classList.remove('header-hidden');
              header.classList.add('header-visible');
            }
          }
          
          lastScrollY = currentScrollY;
        }
      }

      // ==================== USER DROPDOWN ON MOBILE ====================
      const userDropdown = document.querySelector('.user-dropdown');
      const userAvatar = document.querySelector('.user-avatar');
      
      if (userDropdown && userAvatar && window.innerWidth <= 768) {
        userAvatar.addEventListener('click', function (e) {
          e.stopPropagation();
          userDropdown.classList.toggle('is-open');
        });

        document.addEventListener('click', function (e) {
          if (!userDropdown.contains(e.target)) {
            userDropdown.classList.remove('is-open');
          }
        });
      }

      // ==================== SMOOTH SCROLL FOR ANCHOR LINKS ====================
      const anchorLinks = document.querySelectorAll('a[href^="#"]');
      anchorLinks.forEach(function (link) {
        link.addEventListener('click', function (e) {
          const targetId = this.getAttribute('href');
          if (targetId === '#') return;
          
          const target = document.querySelector(targetId);
          if (target) {
            e.preventDefault();
            closeMenu();
            
            const headerHeight = header ? header.offsetHeight : 0;
            const targetPosition = target.getBoundingClientRect().top + window.scrollY - headerHeight - 20;
            
            window.scrollTo({
              top: targetPosition,
              behavior: 'smooth'
            });
          }
        });
      });

      // ==================== TOUCH SWIPE TO CLOSE MENU ====================
      let touchStartX = 0;
      let touchEndX = 0;

      if (mainNav) {
        mainNav.addEventListener('touchstart', function (e) {
          touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });

        mainNav.addEventListener('touchend', function (e) {
          touchEndX = e.changedTouches[0].screenX;
          handleSwipe();
        }, { passive: true });
      }

      function handleSwipe() {
        const swipeThreshold = 100;
        const isRTL = document.dir === 'rtl';
        
        if (isRTL) {
          // RTL: swipe right to close
          if (touchEndX - touchStartX > swipeThreshold) {
            closeMenu();
          }
        } else {
          // LTR: swipe left to close
          if (touchStartX - touchEndX > swipeThreshold) {
            closeMenu();
          }
        }
      }

      // ==================== RESIZE HANDLER ====================
      let resizeTimeout;
      window.addEventListener('resize', function () {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function () {
          if (window.innerWidth > 768 && mainNav && mainNav.classList.contains('is-open')) {
            closeMenu();
          }
        }, 250);
      });

      // ==================== PREVENT BODY SCROLL WHEN MODAL OPEN ====================
      const preventScroll = function (e) {
        if (body.style.overflow === 'hidden') {
          e.preventDefault();
        }
      };
      
      document.addEventListener('touchmove', preventScroll, { passive: false });

    }
  };

  // ==================== FADE IN ANIMATION KEYFRAMES ====================
  const style = document.createElement('style');
  style.textContent = `
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    
    .user-dropdown.is-open .user-dropdown-menu {
      opacity: 1 !important;
      visibility: visible !important;
      transform: translateY(5px) !important;
    }
  `;
  document.head.appendChild(style);

})(Drupal);

