/**
 * @file
 * Charts demo page with REAL data from Drupal.
 */

(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.halaqaChartsDemo = {
    attach: function (context, settings) {
      // Ensure Chart.js and HalaqaCharts are loaded.
      if (typeof Chart === 'undefined' || typeof HalaqaCharts === 'undefined') {
        console.warn('Chart.js or HalaqaCharts not loaded');
        return;
      }

      // Get real data from Drupal settings.
      const chartData = drupalSettings.halaqaCharts;

      if (!chartData) {
        console.warn('No chart data found in drupalSettings');
        return;
      }

      // Use once to ensure the script runs only once per element.
      once('halaqaChartsDemo', '.charts-demo-page', context).forEach(function (element) {
        console.log('🎯 Initializing charts with REAL data:', chartData);

        // Stats Mini Cards - Real data.
        if (chartData.stats && document.getElementById('stats-mini-container')) {
          HalaqaCharts.statsMini('stats-mini-container', chartData.stats);
        }

        // Progress Circle - Real data.
        if (document.getElementById('progress-circle-container')) {
          HalaqaCharts.progressCircle('progress-circle-container', chartData.overall_progress, {
            label: Drupal.t('Completion')
          });
        }

        // Evaluation Chart - Real data.
        if (chartData.evaluations && document.getElementById('evaluation-chart-container')) {
          HalaqaCharts.evaluationChart('evaluation-chart-container', chartData.evaluations);
        }

        // Weekly Chart - Real data.
        if (chartData.weekly && document.getElementById('weekly-chart-container')) {
          HalaqaCharts.weeklyChart('weekly-chart-container', chartData.weekly);
        }

        // Juz Progress Bars - Real data.
        if (chartData.juz_progress && chartData.juz_progress.length >= 3) {
          if (document.getElementById('progress-bar-1')) {
            HalaqaCharts.progressBar('progress-bar-1', chartData.juz_progress[0].value, {
              label: chartData.juz_progress[0].name,
              subtitle: chartData.juz_progress[0].ayahs + ' / ' + chartData.juz_progress[0].total + ' ' + Drupal.t('ayahs')
            });
          }
          if (document.getElementById('progress-bar-2')) {
            HalaqaCharts.progressBar('progress-bar-2', chartData.juz_progress[1].value, {
              label: chartData.juz_progress[1].name,
              subtitle: chartData.juz_progress[1].ayahs + ' / ' + chartData.juz_progress[1].total + ' ' + Drupal.t('ayahs')
            });
          }
          if (document.getElementById('progress-bar-3')) {
            HalaqaCharts.progressBar('progress-bar-3', chartData.juz_progress[2].value, {
              label: chartData.juz_progress[2].name,
              subtitle: chartData.juz_progress[2].ayahs + ' / ' + chartData.juz_progress[2].total + ' ' + Drupal.t('ayahs')
            });
          }
        }

        // Doughnut Chart for Evaluation Distribution - Real data.
        if (chartData.evaluations && document.getElementById('doughnut-chart')) {
          const evalLabels = [
            Drupal.t('Excellent'),
            Drupal.t('Very Good'),
            Drupal.t('Good'),
            Drupal.t('Acceptable'),
            Drupal.t('Needs Work')
          ];
          const evalData = [
            chartData.evaluations.excellent || 0,
            chartData.evaluations.very_good || 0,
            chartData.evaluations.good || 0,
            chartData.evaluations.acceptable || 0,
            chartData.evaluations.needs_improvement || 0
          ];

          HalaqaCharts.doughnutChart('doughnut-chart', {
            labels: evalLabels,
            data: evalData
          });
        }

        console.log('✅ Charts initialized with real data!');
      });
    }
  };

})(Drupal, drupalSettings);
