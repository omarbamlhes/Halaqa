<?php

namespace Drupal\halaqa_custom\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for charts and analytics.
 */
class ChartsController extends ControllerBase {

  /**
   * Renders the charts demo page with REAL data.
   *
   * @return array
   *   A render array.
   */
  public function chartsDemo(): array {
    // Initialize default stats.
    $stats = [
      'total_records' => 0,
      'total_students' => 0,
      'total_halaqas' => 0,
      'total_teachers' => 0,
      'total_ayahs' => 0,
      'surahs_count' => 0,
      'evaluations' => [
        'excellent' => 0,
        'very_good' => 0,
        'good' => 0,
        'acceptable' => 0,
        'needs_improvement' => 0,
      ],
      'excellent_count' => 0,
    ];

    // Try to get real data.
    try {
      /** @var \Drupal\halaqa_custom\Service\HalaqaStudentService $service */
      $service = \Drupal::service('halaqa_custom.student_service');
      $stats = $service->getGlobalChartStats();
    }
    catch (\Exception $e) {
      // Use default stats if service fails.
      \Drupal::logger('halaqa_custom')->error('Charts: ' . $e->getMessage());
    }

    // Prepare data for JavaScript.
    $chart_data = [
      'stats' => [
        ['icon' => '📚', 'value' => $stats['total_records'], 'label' => $this->t('Total Records')->__toString()],
        ['icon' => '📖', 'value' => $stats['total_ayahs'], 'label' => $this->t('Ayahs Memorized')->__toString()],
        ['icon' => '🕌', 'value' => $stats['surahs_count'], 'label' => $this->t('Surahs Covered')->__toString()],
        ['icon' => '⭐', 'value' => $stats['excellent_count'], 'label' => $this->t('Excellent Sessions')->__toString()],
      ],
      'overall_progress' => 0,
      'evaluations' => $stats['evaluations'],
      'weekly' => [0, 0, 0, 0, 0, 0, 0],
      'juz_progress' => [
        ['name' => $this->t('Juz Amma')->__toString(), 'value' => 0, 'ayahs' => 0, 'total' => 564],
        ['name' => $this->t('Juz Tabarak')->__toString(), 'value' => 0, 'ayahs' => 0, 'total' => 431],
        ['name' => $this->t('Juz Qad Samia')->__toString(), 'value' => 0, 'ayahs' => 0, 'total' => 451],
      ],
      'platform_stats' => [
        'students' => $stats['total_students'],
        'teachers' => $stats['total_teachers'],
        'halaqas' => $stats['total_halaqas'],
      ],
    ];

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['charts-demo-page']],
      '#attached' => [
        'library' => [
          'halaqa_theme/charts',
          'halaqa_custom/charts_demo_scripts',
        ],
        'drupalSettings' => [
          'halaqaCharts' => $chart_data,
        ],
      ],
    ];

    // Page Header.
    $build['header'] = [
      '#markup' => '
        <div class="charts-header">
          <h1>📊 ' . $this->t('Analytics Dashboard') . '</h1>
          <p class="charts-subtitle">' . $this->t('Real-time progress tracking for Halaqa platform') . '</p>
          <div class="data-badge">
            <span class="live-indicator"></span>
            ' . $this->t('Live Data') . '
          </div>
        </div>
      ',
    ];

    // Platform Overview.
    $build['platform_overview'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['platform-overview-section']],
      'content' => [
        '#markup' => '
          <h2>🏫 ' . $this->t('Platform Overview') . '</h2>
          <div class="platform-stats-grid">
            <div class="platform-stat-card">
              <div class="stat-icon">👨‍🎓</div>
              <div class="stat-value">' . $stats['total_students'] . '</div>
              <div class="stat-label">' . $this->t('Students') . '</div>
            </div>
            <div class="platform-stat-card">
              <div class="stat-icon">👨‍🏫</div>
              <div class="stat-value">' . $stats['total_teachers'] . '</div>
              <div class="stat-label">' . $this->t('Teachers') . '</div>
            </div>
            <div class="platform-stat-card">
              <div class="stat-icon">🕌</div>
              <div class="stat-value">' . $stats['total_halaqas'] . '</div>
              <div class="stat-label">' . $this->t('Halaqas') . '</div>
            </div>
            <div class="platform-stat-card">
              <div class="stat-icon">📝</div>
              <div class="stat-value">' . $stats['total_records'] . '</div>
              <div class="stat-label">' . $this->t('Total Sessions') . '</div>
            </div>
          </div>
        ',
      ],
    ];

    // Quick Stats Mini Cards.
    $build['quick_stats'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['quick-stats-section']],
      'title' => ['#markup' => '<h2>📈 ' . $this->t('Memorization Statistics') . '</h2>'],
      'stats_mini_container' => [
        '#markup' => '<div id="stats-mini-container" class="stats-mini-grid"></div>',
      ],
    ];

    // Overall Progress Circle & Evaluation Summary.
    $build['progress_eval'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['progress-eval-grid']],
      'overall_progress' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['chart-card']],
        'title' => ['#markup' => '<h3>🎯 ' . $this->t('Overall Progress') . '</h3>'],
        'subtitle' => ['#markup' => '<p class="card-subtitle">' . $stats['total_ayahs'] . ' / 6236 ' . $this->t('Ayahs') . '</p>'],
        'chart' => ['#markup' => '<div id="progress-circle-container" class="progress-circle-wrapper"></div>'],
      ],
      'evaluation_summary' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['chart-card']],
        'title' => ['#markup' => '<h3>⭐ ' . $this->t('Evaluation Summary') . '</h3>'],
        'subtitle' => ['#markup' => '<p class="card-subtitle">' . $this->t('Performance distribution') . '</p>'],
        'chart' => ['#markup' => '<div id="evaluation-chart-container" class="evaluation-summary-wrapper"></div>'],
      ],
    ];

    // Weekly Activity.
    $build['activity_monthly'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['activity-monthly-grid']],
      'weekly_activity' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['chart-card']],
        'title' => ['#markup' => '<h3>📅 ' . $this->t('Weekly Activity') . '</h3>'],
        'subtitle' => ['#markup' => '<p class="card-subtitle">' . $this->t('Sessions this week') . '</p>'],
        'chart' => ['#markup' => '<canvas id="weekly-chart-container"></canvas>'],
      ],
      'evaluation_distribution' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['chart-card']],
        'title' => ['#markup' => '<h3>🥧 ' . $this->t('Evaluation Distribution') . '</h3>'],
        'subtitle' => ['#markup' => '<p class="card-subtitle">' . $this->t('All sessions breakdown') . '</p>'],
        'chart' => ['#markup' => '<canvas id="doughnut-chart"></canvas>'],
      ],
    ];

    // Juz Progress.
    $build['juz_progress'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['juz-progress-section']],
      'title' => ['#markup' => '<h2>📖 ' . $this->t('Juz Progress') . '</h2>'],
      'progress_bars' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['juz-progress-grid']],
        'bar_1' => ['#markup' => '<div id="progress-bar-1" class="progress-bar-wrapper"></div>'],
        'bar_2' => ['#markup' => '<div id="progress-bar-2" class="progress-bar-wrapper"></div>'],
        'bar_3' => ['#markup' => '<div id="progress-bar-3" class="progress-bar-wrapper"></div>'],
      ],
    ];

    // Summary Footer.
    $total_eval = array_sum($stats['evaluations']);
    $excellent_percent = $total_eval > 0 ? round(($stats['evaluations']['excellent'] / $total_eval) * 100) : 0;

    $build['summary'] = [
      '#markup' => '
        <div class="charts-summary">
          <div class="summary-card">
            <h3>📊 ' . $this->t('Summary') . '</h3>
            <ul class="summary-list">
              <li><strong>' . $stats['total_records'] . '</strong> ' . $this->t('total memorization sessions') . '</li>
              <li><strong>' . $stats['total_ayahs'] . '</strong> ' . $this->t('ayahs memorized') . '</li>
              <li><strong>' . $stats['surahs_count'] . '</strong> ' . $this->t('surahs covered') . '</li>
              <li><strong>' . $excellent_percent . '%</strong> ' . $this->t('excellent performance rate') . '</li>
            </ul>
          </div>
        </div>
      ',
    ];

    return $build;
  }

}
