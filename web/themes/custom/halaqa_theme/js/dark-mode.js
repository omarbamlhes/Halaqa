/**
 * @file
 * Dark Mode Toggle JavaScript for Halaqa Theme
 */

(function (Drupal) {
  'use strict';

  /**
   * Dark mode behavior.
   */
  Drupal.behaviors.halaqaDarkMode = {
    attach: function (context, settings) {
      // Only run once on document
      if (context !== document) {
        return;
      }

      const STORAGE_KEY = 'halaqa-theme';
      const DARK = 'dark';
      const LIGHT = 'light';
      
      // ==================== INITIALIZE THEME ====================
      const initTheme = function() {
        // Add no-transition class to prevent flash
        document.documentElement.classList.add('no-transition');
        
        // Check saved preference
        const savedTheme = localStorage.getItem(STORAGE_KEY);
        
        if (savedTheme) {
          setTheme(savedTheme);
        } else {
          // Check system preference
          const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
          setTheme(prefersDark ? DARK : LIGHT);
        }
        
        // Remove no-transition class after a short delay
        setTimeout(function() {
          document.documentElement.classList.remove('no-transition');
        }, 100);
      };

      // ==================== SET THEME ====================
      const setTheme = function(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem(STORAGE_KEY, theme);
        
        // Update toggle button state
        const toggleBtns = document.querySelectorAll('.theme-toggle');
        toggleBtns.forEach(function(btn) {
          btn.setAttribute('aria-pressed', theme === DARK);
          btn.setAttribute('aria-label', theme === DARK ? 'تفعيل الوضع الفاتح' : 'تفعيل الوضع الداكن');
        });
        
        // Dispatch custom event
        document.dispatchEvent(new CustomEvent('themeChange', { detail: { theme: theme } }));
      };

      // ==================== TOGGLE THEME ====================
      const toggleTheme = function() {
        const currentTheme = document.documentElement.getAttribute('data-theme') || LIGHT;
        const newTheme = currentTheme === DARK ? LIGHT : DARK;
        setTheme(newTheme);
        
        // Add animation class
        document.body.classList.add('theme-transitioning');
        setTimeout(function() {
          document.body.classList.remove('theme-transitioning');
        }, 300);
      };

      // ==================== CREATE TOGGLE BUTTON ====================
      const createToggleButton = function() {
        // Check if button already exists
        if (document.querySelector('.theme-toggle')) {
          return;
        }
        
        // Find the secondary navigation or header actions
        const headerActions = document.querySelector('.secondary-navigation') || 
                             document.querySelector('.header-actions') ||
                             document.querySelector('.header-inner');
        
        if (!headerActions) {
          return;
        }
        
        // Create the toggle button
        const toggleBtn = document.createElement('button');
        toggleBtn.className = 'theme-toggle';
        toggleBtn.setAttribute('type', 'button');
        toggleBtn.setAttribute('aria-label', 'تبديل الوضع الداكن');
        toggleBtn.setAttribute('title', 'تبديل الوضع الداكن / الفاتح');
        
        // Create the track (circle)
        const track = document.createElement('span');
        track.className = 'theme-toggle-track';
        toggleBtn.appendChild(track);
        
        // Add click handler
        toggleBtn.addEventListener('click', function(e) {
          e.preventDefault();
          toggleTheme();
        });
        
        // Insert the button
        if (document.querySelector('.notification-bell')) {
          // Insert before notification bell
          document.querySelector('.notification-bell').parentNode.insertBefore(
            toggleBtn, 
            document.querySelector('.notification-bell')
          );
        } else {
          // Prepend to header actions
          headerActions.insertBefore(toggleBtn, headerActions.firstChild);
        }
      };

      // ==================== LISTEN FOR SYSTEM THEME CHANGES ====================
      const watchSystemTheme = function() {
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        
        mediaQuery.addEventListener('change', function(e) {
          // Only auto-switch if user hasn't manually set a preference
          const savedTheme = localStorage.getItem(STORAGE_KEY);
          if (!savedTheme) {
            setTheme(e.matches ? DARK : LIGHT);
          }
        });
      };

      // ==================== KEYBOARD SHORTCUT ====================
      const setupKeyboardShortcut = function() {
        document.addEventListener('keydown', function(e) {
          // Ctrl/Cmd + Shift + D to toggle dark mode
          if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'D') {
            e.preventDefault();
            toggleTheme();
          }
        });
      };

      // ==================== INITIALIZE ====================
      initTheme();
      createToggleButton();
      watchSystemTheme();
      setupKeyboardShortcut();

      // ==================== EXPOSE API ====================
      window.HalaqaDarkMode = {
        toggle: toggleTheme,
        setTheme: setTheme,
        getTheme: function() {
          return document.documentElement.getAttribute('data-theme') || LIGHT;
        },
        isDark: function() {
          return this.getTheme() === DARK;
        }
      };

    }
  };

})(Drupal);

