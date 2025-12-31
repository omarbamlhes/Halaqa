/**
 * @file
 * Charts JavaScript for Halaqa Theme
 * Interactive charts using Chart.js
 */

(function (Drupal) {
  'use strict';

  /**
   * Charts behavior.
   */
  Drupal.behaviors.halaqaCharts = {
    attach: function (context, settings) {
      // Only run once on document
      if (context !== document) {
        return;
      }

      // ==================== CHART.JS COLORS ====================
      const colors = {
        primary: '#0d9488',
        primaryLight: '#14b8a6',
        success: '#22c55e',
        successLight: '#4ade80',
        warning: '#f59e0b',
        warningLight: '#fbbf24',
        danger: '#ef4444',
        dangerLight: '#f87171',
        info: '#3b82f6',
        infoLight: '#60a5fa',
        gray: '#9ca3af',
        grayLight: '#e5e7eb',
        white: '#ffffff',
        dark: '#1f2937'
      };

      // Check for dark mode
      const isDarkMode = () => document.documentElement.getAttribute('data-theme') === 'dark';
      
      const getTextColor = () => isDarkMode() ? '#f1f5f9' : '#1f2937';
      const getGridColor = () => isDarkMode() ? '#334155' : '#e5e7eb';

      // ==================== PROGRESS CIRCLE ====================
      window.HalaqaCharts = window.HalaqaCharts || {};

      /**
       * Create an animated progress circle
       */
      HalaqaCharts.progressCircle = function(containerId, percentage, options = {}) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const size = options.size || 150;
        const strokeWidth = options.strokeWidth || 12;
        const radius = (size - strokeWidth) / 2;
        const circumference = radius * 2 * Math.PI;
        const offset = circumference - (percentage / 100) * circumference;
        
        // Determine color class based on percentage
        let colorClass = 'needs-work';
        if (percentage >= 90) colorClass = 'excellent';
        else if (percentage >= 80) colorClass = 'very-good';
        else if (percentage >= 70) colorClass = 'good';
        else if (percentage >= 60) colorClass = 'acceptable';

        container.innerHTML = `
          <div class="progress-circle" style="width: ${size}px; height: ${size}px;">
            <svg width="${size}" height="${size}">
              <circle class="progress-circle-bg"
                cx="${size / 2}" cy="${size / 2}" r="${radius}"></circle>
              <circle class="progress-circle-progress ${colorClass}"
                cx="${size / 2}" cy="${size / 2}" r="${radius}"
                stroke-dasharray="${circumference}"
                stroke-dashoffset="${circumference}"></circle>
            </svg>
            <div class="progress-circle-text">
              <div class="progress-circle-value">0%</div>
              <div class="progress-circle-label">${options.label || 'Progress'}</div>
            </div>
          </div>
        `;

        // Animate
        setTimeout(() => {
          const progressCircle = container.querySelector('.progress-circle-progress');
          const valueText = container.querySelector('.progress-circle-value');
          
          progressCircle.style.strokeDashoffset = offset;
          
          // Animate counter
          animateCounter(valueText, 0, percentage, 1000, '%');
        }, 100);
      };

      /**
       * Create a progress bar
       */
      HalaqaCharts.progressBar = function(containerId, percentage, options = {}) {
        const container = document.getElementById(containerId);
        if (!container) return;

        // Determine color class
        let colorClass = 'needs-work';
        if (percentage >= 90) colorClass = 'excellent';
        else if (percentage >= 80) colorClass = 'very-good';
        else if (percentage >= 70) colorClass = 'good';
        else if (percentage >= 60) colorClass = 'acceptable';

        const subtitleHtml = options.subtitle 
          ? `<div class="progress-bar-subtitle">${options.subtitle}</div>` 
          : '';

        container.innerHTML = `
          <div class="progress-bar-container">
            <div class="progress-bar-header">
              <div>
                <span class="progress-bar-label">${options.label || 'Progress'}</span>
                ${subtitleHtml}
              </div>
              <span class="progress-bar-value">0%</span>
            </div>
            <div class="progress-bar">
              <div class="progress-bar-fill ${colorClass}" style="width: 0%"></div>
            </div>
          </div>
        `;

        // Animate
        setTimeout(() => {
          const fill = container.querySelector('.progress-bar-fill');
          const value = container.querySelector('.progress-bar-value');
          
          fill.style.width = percentage + '%';
          animateCounter(value, 0, percentage, 800, '%');
        }, 100);
      };

      /**
       * Create evaluation chart
       */
      HalaqaCharts.evaluationChart = function(containerId, data) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const evaluations = [
          { key: 'excellent', name: 'ممتاز', emoji: '🌟', class: 'excellent' },
          { key: 'very_good', name: 'جيد جداً', emoji: '⭐', class: 'very-good' },
          { key: 'good', name: 'جيد', emoji: '👍', class: 'good' },
          { key: 'acceptable', name: 'مقبول', emoji: '📖', class: 'acceptable' },
          { key: 'needs_work', name: 'يحتاج تحسين', emoji: '💪', class: 'needs-work' }
        ];

        const total = Object.values(data).reduce((a, b) => a + b, 0) || 1;

        let html = '<div class="evaluation-chart">';
        evaluations.forEach(eval => {
          const count = data[eval.key] || 0;
          const percent = Math.round((count / total) * 100);
          html += `
            <div class="evaluation-item ${eval.class}">
              <span class="evaluation-emoji">${eval.emoji}</span>
              <div class="evaluation-name">${eval.name}</div>
              <div class="evaluation-count" data-target="${count}">0</div>
              <div class="evaluation-percent">${percent}%</div>
            </div>
          `;
        });
        html += '</div>';
        
        container.innerHTML = html;

        // Animate counters
        setTimeout(() => {
          container.querySelectorAll('.evaluation-count').forEach(el => {
            const target = parseInt(el.dataset.target);
            animateCounter(el, 0, target, 1000);
          });
        }, 100);
      };

      /**
       * Create weekly activity chart (pure CSS)
       */
      HalaqaCharts.weeklyChart = function(containerId, data) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const days = ['السبت', 'الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة'];
        const maxValue = Math.max(...data, 1);

        let html = '<div class="weekly-chart-container"><div class="weekly-bars">';
        
        days.forEach((day, index) => {
          const value = data[index] || 0;
          const height = (value / maxValue) * 150;
          html += `
            <div class="weekly-bar-item">
              <div class="weekly-bar" style="height: ${height}px" data-value="${value}">
                <div class="weekly-bar-tooltip">${value} سجل</div>
              </div>
              <span class="weekly-bar-day">${day}</span>
            </div>
          `;
        });
        
        html += '</div></div>';
        container.innerHTML = html;
      };

      /**
       * Create surah progress list
       */
      HalaqaCharts.surahProgress = function(containerId, data) {
        const container = document.getElementById(containerId);
        if (!container) return;

        let html = '<div class="surah-progress-list">';
        
        data.forEach((surah, index) => {
          const percent = Math.round((surah.memorized / surah.total) * 100);
          html += `
            <div class="surah-progress-item">
              <div class="surah-number">${index + 1}</div>
              <div class="surah-info">
                <div class="surah-name">${surah.name}</div>
                <div class="surah-ayahs">${surah.memorized} / ${surah.total} آية</div>
              </div>
              <div class="surah-progress-bar">
                <div class="surah-progress-fill" style="width: ${percent}%"></div>
              </div>
            </div>
          `;
        });
        
        html += '</div>';
        container.innerHTML = html;
      };

      /**
       * Create stats mini cards
       */
      HalaqaCharts.statsMini = function(containerId, stats) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const cardClasses = ['primary', 'success', 'warning', 'info'];
        
        let html = '<div class="stats-mini-grid">';
        
        stats.forEach((stat, index) => {
          const cardClass = cardClasses[index % cardClasses.length];
          html += `
            <div class="stats-mini-card ${cardClass}">
              <div class="stats-mini-icon">${stat.icon}</div>
              <div class="stats-mini-value" data-target="${stat.value}">0</div>
              <div class="stats-mini-label">${stat.label}</div>
            </div>
          `;
        });
        
        html += '</div>';
        container.innerHTML = html;

        // Animate counters
        setTimeout(() => {
          container.querySelectorAll('.stats-mini-value').forEach(el => {
            const target = parseInt(el.dataset.target);
            animateCounter(el, 0, target, 1200);
          });
        }, 100);
      };

      // ==================== CHART.JS CHARTS ====================
      
      /**
       * Create line chart using Chart.js
       */
      HalaqaCharts.lineChart = function(canvasId, config) {
        const canvas = document.getElementById(canvasId);
        if (!canvas || typeof Chart === 'undefined') return null;

        const ctx = canvas.getContext('2d');
        
        // Create gradient
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(13, 148, 136, 0.3)');
        gradient.addColorStop(1, 'rgba(13, 148, 136, 0.0)');

        return new Chart(ctx, {
          type: 'line',
          data: {
            labels: config.labels,
            datasets: [{
              label: config.label || 'Data',
              data: config.data,
              borderColor: colors.primary,
              backgroundColor: gradient,
              borderWidth: 3,
              fill: true,
              tension: 0.4,
              pointBackgroundColor: colors.primary,
              pointBorderColor: colors.white,
              pointBorderWidth: 2,
              pointRadius: 5,
              pointHoverRadius: 7
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                display: false
              },
              tooltip: {
                backgroundColor: isDarkMode() ? '#1e293b' : '#1f2937',
                titleColor: colors.white,
                bodyColor: colors.white,
                borderColor: colors.primary,
                borderWidth: 1,
                cornerRadius: 8,
                padding: 12
              }
            },
            scales: {
              x: {
                grid: {
                  color: getGridColor(),
                  drawBorder: false
                },
                ticks: {
                  color: getTextColor()
                }
              },
              y: {
                grid: {
                  color: getGridColor(),
                  drawBorder: false
                },
                ticks: {
                  color: getTextColor()
                },
                beginAtZero: true
              }
            },
            interaction: {
              intersect: false,
              mode: 'index'
            }
          }
        });
      };

      /**
       * Create doughnut chart using Chart.js
       */
      HalaqaCharts.doughnutChart = function(canvasId, config) {
        const canvas = document.getElementById(canvasId);
        if (!canvas || typeof Chart === 'undefined') return null;

        const ctx = canvas.getContext('2d');

        return new Chart(ctx, {
          type: 'doughnut',
          data: {
            labels: config.labels,
            datasets: [{
              data: config.data,
              backgroundColor: [
                colors.success,
                colors.primary,
                colors.info,
                colors.warning,
                colors.danger
              ],
              borderWidth: 0,
              hoverOffset: 10
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
              legend: {
                position: 'bottom',
                labels: {
                  color: getTextColor(),
                  padding: 15,
                  usePointStyle: true,
                  pointStyle: 'circle'
                }
              },
              tooltip: {
                backgroundColor: isDarkMode() ? '#1e293b' : '#1f2937',
                titleColor: colors.white,
                bodyColor: colors.white,
                borderColor: colors.primary,
                borderWidth: 1,
                cornerRadius: 8
              }
            }
          }
        });
      };

      /**
       * Create bar chart using Chart.js
       */
      HalaqaCharts.barChart = function(canvasId, config) {
        const canvas = document.getElementById(canvasId);
        if (!canvas || typeof Chart === 'undefined') return null;

        const ctx = canvas.getContext('2d');

        return new Chart(ctx, {
          type: 'bar',
          data: {
            labels: config.labels,
            datasets: [{
              label: config.label || 'Data',
              data: config.data,
              backgroundColor: config.colors || colors.primary,
              borderRadius: 8,
              borderSkipped: false,
              barThickness: 30
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                display: false
              },
              tooltip: {
                backgroundColor: isDarkMode() ? '#1e293b' : '#1f2937',
                titleColor: colors.white,
                bodyColor: colors.white,
                cornerRadius: 8
              }
            },
            scales: {
              x: {
                grid: {
                  display: false
                },
                ticks: {
                  color: getTextColor()
                }
              },
              y: {
                grid: {
                  color: getGridColor(),
                  drawBorder: false
                },
                ticks: {
                  color: getTextColor()
                },
                beginAtZero: true
              }
            }
          }
        });
      };

      // ==================== HELPER FUNCTIONS ====================
      
      /**
       * Animate a counter
       */
      function animateCounter(element, start, end, duration, suffix = '') {
        const range = end - start;
        const startTime = performance.now();
        
        function updateCounter(currentTime) {
          const elapsed = currentTime - startTime;
          const progress = Math.min(elapsed / duration, 1);
          
          // Easing function (ease-out)
          const easeOut = 1 - Math.pow(1 - progress, 3);
          const current = Math.round(start + (range * easeOut));
          
          element.textContent = current + suffix;
          
          if (progress < 1) {
            requestAnimationFrame(updateCounter);
          }
        }
        
        requestAnimationFrame(updateCounter);
      }

      // ==================== THEME CHANGE LISTENER ====================
      document.addEventListener('themeChange', function() {
        // Update chart colors on theme change
        if (typeof Chart !== 'undefined') {
          Chart.instances.forEach(chart => {
            if (chart.options.scales) {
              if (chart.options.scales.x) {
                chart.options.scales.x.grid.color = getGridColor();
                chart.options.scales.x.ticks.color = getTextColor();
              }
              if (chart.options.scales.y) {
                chart.options.scales.y.grid.color = getGridColor();
                chart.options.scales.y.ticks.color = getTextColor();
              }
            }
            if (chart.options.plugins && chart.options.plugins.legend) {
              chart.options.plugins.legend.labels.color = getTextColor();
            }
            chart.update();
          });
        }
      });

    }
  };

})(Drupal);

