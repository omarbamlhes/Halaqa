<?php

namespace Drupal\halaqa_custom\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\halaqa_custom\Service\HalaqaStudentService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for Halaqa Custom module.
 */
class HalaqaCustomController extends ControllerBase {

  /**
   * The Halaqa student service.
   *
   * @var \Drupal\halaqa_custom\Service\HalaqaStudentService
   */
  protected HalaqaStudentService $halaqaStudentService;

  /**
   * Constructs a HalaqaCustomController object.
   *
   * @param \Drupal\halaqa_custom\Service\HalaqaStudentService $halaqa_student_service
   *   The Halaqa student service.
   */
  public function __construct(HalaqaStudentService $halaqa_student_service) {
    $this->halaqaStudentService = $halaqa_student_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('halaqa_custom.student_service')
    );
  }

  /**
   * Example page callback.
   *
   * @return array
   *   A render array.
   */
  public function example(): array {
    return [
      '#markup' => $this->t('Welcome to Halaqa Custom module!'),
    ];
  }

  /**
   * Display students for a specific Halaqa.
   *
   * @param string|int $nid
   *   The Halaqa node ID.
   *
   * @return array
   *   A render array.
   */
  public function students($nid): array {
    $nid = (int) $nid;

    // Load the Halaqa.
    $halaqa = $this->halaqaStudentService->loadHalaqa($nid);

    if (!$halaqa) {
      throw new NotFoundHttpException('Halaqa not found.');
    }

    // Check access - only allow teacher who owns this halaqa or admin.
    $current_user = $this->currentUser();
    $is_admin = in_array('administrator', $current_user->getRoles());
    if (!$is_admin) {
      if (!$this->halaqaStudentService->userHasAccessToHalaqa($nid, (int) $current_user->id())) {
        throw new AccessDeniedHttpException('You do not have access to this Halaqa.');
      }
    }

    // Get students for this Halaqa.
    $students = $this->halaqaStudentService->getStudentsByHalaqa($nid);

    $build = [
      '#theme' => 'halaqa_students_list',
      '#halaqa' => $halaqa,
      '#students' => $students,
      '#cache' => [
        'tags' => ['node:' . $nid, 'node_list:student'],
        'contexts' => ['user'],
      ],
    ];

    // Build the page.
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['halaqa-students-page']],
    ];

    // Add record button.
    $build['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['page-actions']],
      'add_record' => [
        '#type' => 'link',
        '#title' => $this->t('📝 Add Memorization Record'),
        '#url' => Url::fromRoute('halaqa_custom.add_memorization_record_halaqa', ['halaqa_nid' => $nid]),
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
      ],
    ];

    if (empty($students)) {
      $build['content'] = [
        '#markup' => '<p>' . $this->t('No students found in this Halaqa.') . '</p>',
      ];
    }
    else {
      // Build student cards.
      $build['students'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['students-list']],
        'title' => [
          '#markup' => '<h3>' . $this->t('Students (@count)', ['@count' => count($students)]) . '</h3>',
        ],
      ];

      foreach ($students as $index => $student) {
        $build['students']['student_' . $index] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['student-card']],
          'name' => [
            '#markup' => '<div class="student-name">' . $student->label() . '</div>',
          ],
          'actions' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['student-actions']],
            'progress' => [
              '#type' => 'link',
              '#title' => $this->t('📊 Progress'),
              '#url' => Url::fromRoute('halaqa_custom.student_progress', ['student_nid' => $student->id()]),
              '#attributes' => ['class' => ['student-link']],
            ],
            'separator1' => [
              '#markup' => ' | ',
            ],
            'attendance' => [
              '#type' => 'link',
              '#title' => $this->t('📅 Attendance'),
              '#url' => Url::fromRoute('halaqa_custom.student_attendance', ['student_nid' => $student->id()]),
              '#attributes' => ['class' => ['student-link']],
            ],
            'separator2' => [
              '#markup' => ' | ',
            ],
            'report' => [
              '#type' => 'link',
              '#title' => $this->t('📄 Report'),
              '#url' => Url::fromRoute('halaqa_reports.student_report', ['student_nid' => $student->id()]),
              '#attributes' => ['class' => ['student-link']],
            ],
            'separator3' => [
              '#markup' => ' | ',
            ],
            'view' => [
              '#type' => 'link',
              '#title' => $this->t('View'),
              '#url' => $student->toUrl(),
              '#attributes' => ['class' => ['student-link']],
            ],
          ],
        ];
      }
    }

    // Add styles.
    $build['#attached']['html_head'][] = [
      [
        '#tag' => 'style',
        '#value' => '
          .halaqa-students-page { max-width: 800px; margin: 0 auto; padding: 20px; }
          .page-actions { margin-bottom: 20px; }
          .students-list h3 { margin-bottom: 15px; }
          .student-card { background: #fff; border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; }
          .student-name { font-weight: bold; }
          .student-link { color: #2196F3; }
        ',
      ],
      'halaqa_students_styles',
    ];

    $build['#cache'] = [
      'tags' => ['node:' . $nid, 'node_list:student'],
      'contexts' => ['user'],
    ];

    return $build;
  }

  /**
   * Title callback for the students page.
   *
   * @param string|int $nid
   *   The Halaqa node ID.
   *
   * @return string
   *   The page title.
   */
  public function studentsTitle($nid): string {
    $nid = (int) $nid;
    $halaqa = $this->halaqaStudentService->loadHalaqa($nid);

    if ($halaqa) {
      return $this->t('Students - @halaqa', ['@halaqa' => $halaqa->label()]);
    }

    return $this->t('Students');
  }

  /**
   * Display all Halaqas for the current teacher.
   *
   * @return array
   *   A render array.
   */
  public function myHalaqas(): array {
    $halaqas = $this->halaqaStudentService->getHalaqasByCurrentTeacher();

    if (empty($halaqas)) {
      return [
        '#markup' => $this->t('You have no Halaqas assigned.'),
      ];
    }

    $items = [];
    foreach ($halaqas as $halaqa) {
      $items[] = [
        '#type' => 'link',
        '#title' => $halaqa->label(),
        '#url' => $halaqa->toUrl(),
        '#suffix' => ' | ',
        'students_link' => [
          '#type' => 'link',
          '#title' => $this->t('View Students'),
          '#url' => Url::fromRoute('halaqa_custom.halaqa_students', ['nid' => $halaqa->id()]),
        ],
      ];
    }

    return [
      '#theme' => 'item_list',
      '#title' => $this->t('My Halaqas'),
      '#items' => $items,
      '#list_type' => 'ul',
      '#cache' => [
        'tags' => ['node_list:halaqa'],
        'contexts' => ['user'],
      ],
    ];
  }

  /**
   * Teacher Dashboard page.
   *
   * @return array
   *   A render array.
   */
  public function dashboard(): array {
    $stats = $this->halaqaStudentService->getTeacherDashboardStats();

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['teacher-dashboard']],
    ];

    // Statistics cards.
    $build['stats'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['dashboard-stats']],
    ];

    $build['stats']['halaqas'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['stat-card']],
      'count' => [
        '#markup' => '<div class="stat-number">' . $stats['halaqa_count'] . '</div>',
      ],
      'label' => [
        '#markup' => '<div class="stat-label">' . $this->t('Halaqas') . '</div>',
      ],
    ];

    $build['stats']['students'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['stat-card']],
      'count' => [
        '#markup' => '<div class="stat-number">' . $stats['total_students'] . '</div>',
      ],
      'label' => [
        '#markup' => '<div class="stat-label">' . $this->t('Students') . '</div>',
      ],
    ];

    // Quick actions.
    $build['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['dashboard-actions']],
      'title' => [
        '#markup' => '<h3>' . $this->t('Quick Actions') . '</h3>',
      ],
      'links' => [
        '#theme' => 'item_list',
        '#items' => [
          [
            '#type' => 'link',
            '#title' => $this->t('➕ Add New Student'),
            '#url' => Url::fromRoute('node.add', ['node_type' => 'student']),
          ],
          [
            '#type' => 'link',
            '#title' => $this->t('📝 Add Memorization Record'),
            '#url' => Url::fromRoute('halaqa_custom.add_memorization_record'),
          ],
          [
            '#type' => 'link',
            '#title' => $this->t('📋 View My Halaqas'),
            '#url' => Url::fromRoute('halaqa_custom.my_halaqas'),
          ],
        ],
      ],
    ];

    // Today's attendance section.
    $build['attendance_today'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['dashboard-attendance-today']],
      'title' => [
        '#markup' => '<h3>' . $this->t("Today's Attendance") . '</h3>',
      ],
    ];

    if (!empty($stats['halaqas'])) {
      foreach ($stats['halaqas'] as $index => $halaqa_data) {
        $halaqa = $halaqa_data['halaqa'];
        $today_attendance = $this->halaqaStudentService->getTodayAttendance((int) $halaqa->id());

        $attendance_status = $today_attendance['recorded']
          ? $this->t('@present present, @absent absent', [
              '@present' => $today_attendance['present'],
              '@absent' => $today_attendance['absent'],
            ])
          : $this->t('Not recorded yet');

        $build['attendance_today']['halaqa_' . $index] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['attendance-today-item']],
          'info' => [
            '#markup' => '<div class="attendance-today-info">
              <span class="attendance-today-halaqa">' . $halaqa->label() . '</span>
              <span class="attendance-today-status">' . $attendance_status . '</span>
            </div>',
          ],
          'action' => [
            '#type' => 'link',
            '#title' => $today_attendance['recorded'] ? $this->t('Edit') : $this->t('Record'),
            '#url' => Url::fromRoute('halaqa_custom.attendance_form', ['halaqa_nid' => $halaqa->id()]),
            '#attributes' => ['class' => ['attendance-today-btn', $today_attendance['recorded'] ? 'btn-edit' : 'btn-record']],
          ],
        ];
      }
    }

    // Halaqas with students.
    if (!empty($stats['halaqas'])) {
      $build['halaqas'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['dashboard-halaqas']],
        'title' => [
          '#markup' => '<h3>' . $this->t('My Halaqas') . '</h3>',
        ],
      ];

      foreach ($stats['halaqas'] as $index => $halaqa_data) {
        $halaqa = $halaqa_data['halaqa'];
        $student_count = $halaqa_data['student_count'];

        $build['halaqas']['halaqa_' . $index] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['halaqa-card']],
          'name' => [
            '#markup' => '<div class="halaqa-name">' . $halaqa->label() . '</div>',
          ],
          'count' => [
            '#markup' => '<div class="halaqa-students">' . $this->t('@count students', ['@count' => $student_count]) . '</div>',
          ],
          'links' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['halaqa-links']],
            'students' => [
              '#type' => 'link',
              '#title' => $this->t('Students'),
              '#url' => Url::fromRoute('halaqa_custom.halaqa_students', ['nid' => $halaqa->id()]),
              '#attributes' => ['class' => ['halaqa-link']],
            ],
            'separator1' => [
              '#markup' => ' | ',
            ],
            'attendance' => [
              '#type' => 'link',
              '#title' => $this->t('Attendance'),
              '#url' => Url::fromRoute('halaqa_custom.halaqa_attendance', ['nid' => $halaqa->id()]),
              '#attributes' => ['class' => ['halaqa-link']],
            ],
            'separator2' => [
              '#markup' => ' | ',
            ],
            'report' => [
              '#type' => 'link',
              '#title' => $this->t('📄 Report'),
              '#url' => Url::fromRoute('halaqa_reports.halaqa_report', ['halaqa_nid' => $halaqa->id()]),
              '#attributes' => ['class' => ['halaqa-link', 'halaqa-report-btn']],
            ],
            'separator3' => [
              '#markup' => ' | ',
            ],
            'record_attendance' => [
              '#type' => 'link',
              '#title' => $this->t('Record'),
              '#url' => Url::fromRoute('halaqa_custom.attendance_form', ['halaqa_nid' => $halaqa->id()]),
              '#attributes' => ['class' => ['halaqa-link', 'halaqa-record-btn']],
            ],
          ],
        ];
      }
    }

    // Recent memorization records.
    if (!empty($stats['recent_records'])) {
      $build['records'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['dashboard-records']],
        'title' => [
          '#markup' => '<h3>' . $this->t('Recent Memorization Records') . '</h3>',
        ],
      ];

      $record_items = [];
      foreach ($stats['recent_records'] as $record) {
        $student_name = '';
        if ($record->hasField('field_student') && !$record->get('field_student')->isEmpty()) {
          $student = $record->get('field_student')->entity;
          $student_name = $student ? $student->label() : '';
        }

        $date = '';
        if ($record->hasField('field_date') && !$record->get('field_date')->isEmpty()) {
          $date = $record->get('field_date')->value;
        }

        $record_items[] = [
          '#markup' => $student_name . ' - ' . $date,
        ];
      }

      $build['records']['list'] = [
        '#theme' => 'item_list',
        '#items' => $record_items,
      ];
    }

    // Add basic styles.
    $build['#attached']['html_head'][] = [
      [
        '#tag' => 'style',
        '#value' => '
          .teacher-dashboard { max-width: 1200px; margin: 0 auto; padding: 20px; }
          .dashboard-stats { display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap; }
          .stat-card { background: #f5f5f5; padding: 20px; border-radius: 8px; text-align: center; min-width: 150px; }
          .stat-number { font-size: 2.5em; font-weight: bold; color: #2196F3; }
          .stat-label { color: #666; margin-top: 5px; }
          .dashboard-actions { margin-bottom: 30px; }
          .dashboard-actions h3 { margin-bottom: 15px; }
          .dashboard-halaqas { margin-bottom: 30px; }
          .dashboard-halaqas h3 { margin-bottom: 15px; }
          .halaqa-card { background: #fff; border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; border-radius: 8px; }
          .halaqa-name { font-weight: bold; font-size: 1.2em; }
          .halaqa-students { color: #666; margin: 5px 0; }
          .halaqa-links { margin-top: 10px; }
          .halaqa-link { color: #2196F3; }
          .halaqa-record-btn { background: #4CAF50; color: #fff; padding: 4px 10px; border-radius: 4px; text-decoration: none; }
          .halaqa-record-btn:hover { background: #388E3C; color: #fff; }
          .halaqa-report-btn { background: #7b1fa2; color: #fff; padding: 4px 10px; border-radius: 4px; text-decoration: none; }
          .halaqa-report-btn:hover { background: #6a1b9a; color: #fff; }
          .dashboard-records h3 { margin-bottom: 15px; }
          .dashboard-attendance-today { margin-bottom: 30px; background: #e8f5e9; padding: 20px; border-radius: 8px; }
          .dashboard-attendance-today h3 { margin-top: 0; margin-bottom: 15px; color: #2e7d32; }
          .attendance-today-item { display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #fff; border-radius: 6px; margin-bottom: 8px; }
          .attendance-today-info { display: flex; flex-direction: column; }
          .attendance-today-halaqa { font-weight: bold; }
          .attendance-today-status { color: #666; font-size: 0.9em; }
          .attendance-today-btn { padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.9em; }
          .attendance-today-btn.btn-record { background: #4CAF50; color: #fff; }
          .attendance-today-btn.btn-edit { background: #2196F3; color: #fff; }
        ',
      ],
      'halaqa_dashboard_styles',
    ];

    $build['#cache'] = [
      'tags' => ['node_list:halaqa', 'node_list:student', 'node_list:memorization_record', 'node_list:attendance_record'],
      'contexts' => ['user'],
    ];

    return $build;
  }

  /**
   * Display student progress page.
   *
   * @param string|int $student_nid
   *   The student node ID.
   *
   * @return array
   *   A render array.
   */
  public function studentProgress($student_nid): array {
    $student_nid = (int) $student_nid;

    // Load the student.
    $student = $this->halaqaStudentService->loadStudent($student_nid);

    if (!$student) {
      throw new NotFoundHttpException('Student not found.');
    }

    // Check access.
    $current_user = $this->currentUser();
    $is_admin = in_array('administrator', $current_user->getRoles());
    if (!$is_admin && !$this->halaqaStudentService->userHasAccessToStudent($student_nid)) {
      throw new AccessDeniedHttpException('You do not have access to this student.');
    }

    // Get progress statistics.
    $progress = $this->halaqaStudentService->getStudentProgress($student_nid);

    // Surah names for display.
    $surah_names = $this->getSurahNames();

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['student-progress-page']],
    ];

    // Student info header.
    $build['header'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['student-header']],
      'name' => [
        '#markup' => '<h2>' . $student->label() . '</h2>',
      ],
    ];

    // Statistics cards.
    $build['stats'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['progress-stats']],
    ];

    $build['stats']['records'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['stat-card']],
      'count' => [
        '#markup' => '<div class="stat-number">' . $progress['total_records'] . '</div>',
      ],
      'label' => [
        '#markup' => '<div class="stat-label">' . $this->t('Total Records') . '</div>',
      ],
    ];

    $build['stats']['ayahs'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['stat-card']],
      'count' => [
        '#markup' => '<div class="stat-number">' . $progress['total_ayahs'] . '</div>',
      ],
      'label' => [
        '#markup' => '<div class="stat-label">' . $this->t('Total Ayahs') . '</div>',
      ],
    ];

    $build['stats']['surahs'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['stat-card']],
      'count' => [
        '#markup' => '<div class="stat-number">' . count($progress['surahs_touched']) . '</div>',
      ],
      'label' => [
        '#markup' => '<div class="stat-label">' . $this->t('Surahs Covered') . '</div>',
      ],
    ];

    // Evaluation breakdown.
    $build['evaluations'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['evaluation-breakdown']],
      'title' => [
        '#markup' => '<h3>' . $this->t('Evaluation Summary') . '</h3>',
      ],
    ];

    $eval_labels = [
      'excellent' => $this->t('Excellent'),
      'very_good' => $this->t('Very Good'),
      'good' => $this->t('Good'),
      'acceptable' => $this->t('Acceptable'),
      'needs_improvement' => $this->t('Needs Improvement'),
    ];

    $eval_colors = [
      'excellent' => '#4CAF50',
      'very_good' => '#8BC34A',
      'good' => '#FFC107',
      'acceptable' => '#FF9800',
      'needs_improvement' => '#f44336',
    ];

    foreach ($progress['evaluations'] as $key => $count) {
      if ($count > 0) {
        $build['evaluations']['eval_' . $key] = [
          '#markup' => '<div class="eval-bar"><span class="eval-label">' . $eval_labels[$key] . '</span><span class="eval-count" style="background: ' . $eval_colors[$key] . '">' . $count . '</span></div>',
        ];
      }
    }

    // Records list.
    $build['records'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['records-list']],
      'title' => [
        '#markup' => '<h3>' . $this->t('Memorization Records') . '</h3>',
      ],
    ];

    if (empty($progress['records'])) {
      $build['records']['empty'] = [
        '#markup' => '<p>' . $this->t('No memorization records yet.') . '</p>',
      ];
    }
    else {
      foreach ($progress['records'] as $index => $record) {
        $from_surah = '';
        $to_surah = '';
        $from_ayah = '';
        $to_ayah = '';
        $date = '';
        $eval = '';

        if ($record->hasField('field_from_surah') && !$record->get('field_from_surah')->isEmpty()) {
          $surah_num = $record->get('field_from_surah')->value;
          $from_surah = $surah_names[$surah_num] ?? $surah_num;
        }
        if ($record->hasField('field_to_surah') && !$record->get('field_to_surah')->isEmpty()) {
          $surah_num = $record->get('field_to_surah')->value;
          $to_surah = $surah_names[$surah_num] ?? $surah_num;
        }
        if ($record->hasField('field_from_ayah') && !$record->get('field_from_ayah')->isEmpty()) {
          $from_ayah = $record->get('field_from_ayah')->value;
        }
        if ($record->hasField('field_to_ayah') && !$record->get('field_to_ayah')->isEmpty()) {
          $to_ayah = $record->get('field_to_ayah')->value;
        }
        if ($record->hasField('field_date') && !$record->get('field_date')->isEmpty()) {
          $date = $record->get('field_date')->value;
        }
        if ($record->hasField('field_evaluation') && !$record->get('field_evaluation')->isEmpty()) {
          $eval_key = $record->get('field_evaluation')->value;
          $eval = isset($eval_labels[$eval_key]) ? $eval_labels[$eval_key] : $eval_key;
        }

        $range_text = $from_surah . ' (' . $from_ayah . ')';
        if ($from_surah !== $to_surah || $from_ayah !== $to_ayah) {
          $range_text .= ' → ' . $to_surah . ' (' . $to_ayah . ')';
        }

        $build['records']['record_' . $index] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['record-card']],
          'date' => [
            '#markup' => '<div class="record-date">' . $date . '</div>',
          ],
          'range' => [
            '#markup' => '<div class="record-range">' . $range_text . '</div>',
          ],
          'eval' => [
            '#markup' => '<div class="record-eval">' . $eval . '</div>',
          ],
        ];
      }
    }

    // Add styles.
    $build['#attached']['html_head'][] = [
      [
        '#tag' => 'style',
        '#value' => '
          .student-progress-page { max-width: 900px; margin: 0 auto; padding: 20px; }
          .student-header { margin-bottom: 30px; }
          .student-header h2 { margin: 0; color: #333; }
          .progress-stats { display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap; }
          .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 12px; text-align: center; min-width: 120px; color: #fff; }
          .stat-number { font-size: 2.5em; font-weight: bold; }
          .stat-label { opacity: 0.9; margin-top: 5px; }
          .evaluation-breakdown { margin-bottom: 30px; background: #f9f9f9; padding: 20px; border-radius: 12px; }
          .evaluation-breakdown h3 { margin-top: 0; margin-bottom: 15px; }
          .eval-bar { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #eee; }
          .eval-label { font-weight: 500; }
          .eval-count { padding: 4px 12px; border-radius: 20px; color: #fff; font-weight: bold; }
          .records-list { margin-bottom: 30px; }
          .records-list h3 { margin-bottom: 15px; }
          .record-card { background: #fff; border: 1px solid #e0e0e0; padding: 15px; margin-bottom: 10px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
          .record-date { color: #666; font-size: 0.9em; }
          .record-range { font-weight: bold; flex: 1; }
          .record-eval { background: #e3f2fd; padding: 4px 12px; border-radius: 20px; font-size: 0.9em; }
        ',
      ],
      'student_progress_styles',
    ];

    $build['#cache'] = [
      'tags' => ['node:' . $student_nid, 'node_list:memorization_record'],
      'contexts' => ['user'],
    ];

    return $build;
  }

  /**
   * Title callback for student progress page.
   *
   * @param string|int $student_nid
   *   The student node ID.
   *
   * @return string
   *   The page title.
   */
  public function studentProgressTitle($student_nid): string {
    $student = $this->halaqaStudentService->loadStudent((int) $student_nid);

    if ($student) {
      return $this->t('Progress - @name', ['@name' => $student->label()]);
    }

    return $this->t('Student Progress');
  }

  /**
   * Get Surah names array.
   *
   * @return array
   *   Array of surah names keyed by number.
   */
  protected function getSurahNames(): array {
    return [
      1 => 'الفاتحة', 2 => 'البقرة', 3 => 'آل عمران', 4 => 'النساء', 5 => 'المائدة',
      6 => 'الأنعام', 7 => 'الأعراف', 8 => 'الأنفال', 9 => 'التوبة', 10 => 'يونس',
      11 => 'هود', 12 => 'يوسف', 13 => 'الرعد', 14 => 'إبراهيم', 15 => 'الحجر',
      16 => 'النحل', 17 => 'الإسراء', 18 => 'الكهف', 19 => 'مريم', 20 => 'طه',
      21 => 'الأنبياء', 22 => 'الحج', 23 => 'المؤمنون', 24 => 'النور', 25 => 'الفرقان',
      26 => 'الشعراء', 27 => 'النمل', 28 => 'القصص', 29 => 'العنكبوت', 30 => 'الروم',
      31 => 'لقمان', 32 => 'السجدة', 33 => 'الأحزاب', 34 => 'سبأ', 35 => 'فاطر',
      36 => 'يس', 37 => 'الصافات', 38 => 'ص', 39 => 'الزمر', 40 => 'غافر',
      41 => 'فصلت', 42 => 'الشورى', 43 => 'الزخرف', 44 => 'الدخان', 45 => 'الجاثية',
      46 => 'الأحقاف', 47 => 'محمد', 48 => 'الفتح', 49 => 'الحجرات', 50 => 'ق',
      51 => 'الذاريات', 52 => 'الطور', 53 => 'النجم', 54 => 'القمر', 55 => 'الرحمن',
      56 => 'الواقعة', 57 => 'الحديد', 58 => 'المجادلة', 59 => 'الحشر', 60 => 'الممتحنة',
      61 => 'الصف', 62 => 'الجمعة', 63 => 'المنافقون', 64 => 'التغابن', 65 => 'الطلاق',
      66 => 'التحريم', 67 => 'الملك', 68 => 'القلم', 69 => 'الحاقة', 70 => 'المعارج',
      71 => 'نوح', 72 => 'الجن', 73 => 'المزمل', 74 => 'المدثر', 75 => 'القيامة',
      76 => 'الإنسان', 77 => 'المرسلات', 78 => 'النبأ', 79 => 'النازعات', 80 => 'عبس',
      81 => 'التكوير', 82 => 'الانفطار', 83 => 'المطففين', 84 => 'الانشقاق', 85 => 'البروج',
      86 => 'الطارق', 87 => 'الأعلى', 88 => 'الغاشية', 89 => 'الفجر', 90 => 'البلد',
      91 => 'الشمس', 92 => 'الليل', 93 => 'الضحى', 94 => 'الشرح', 95 => 'التين',
      96 => 'العلق', 97 => 'القدر', 98 => 'البينة', 99 => 'الزلزلة', 100 => 'العاديات',
      101 => 'القارعة', 102 => 'التكاثر', 103 => 'العصر', 104 => 'الهمزة', 105 => 'الفيل',
      106 => 'قريش', 107 => 'الماعون', 108 => 'الكوثر', 109 => 'الكافرون', 110 => 'النصر',
      111 => 'المسد', 112 => 'الإخلاص', 113 => 'الفلق', 114 => 'الناس',
    ];
  }

  /**
   * Parent Dashboard page.
   *
   * @return array
   *   A render array.
   */
  public function parentDashboard(): array {
    $stats = $this->halaqaStudentService->getParentDashboardStats();

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['parent-dashboard']],
    ];

    // Welcome message.
    $build['welcome'] = [
      '#markup' => '<h2>' . $this->t('Welcome, @parent!', ['@parent' => $this->currentUser()->getAccountName()]) . '</h2>',
    ];

    // Statistics cards.
    $build['stats'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['dashboard-stats']],
    ];

    $build['stats']['children'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['stat-card', 'stat-children']],
      'count' => [
        '#markup' => '<div class="stat-number">' . $stats['children_count'] . '</div>',
      ],
      'label' => [
        '#markup' => '<div class="stat-label">' . $this->t('My Children') . '</div>',
      ],
    ];

    $build['stats']['records'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['stat-card', 'stat-records']],
      'count' => [
        '#markup' => '<div class="stat-number">' . $stats['total_records'] . '</div>',
      ],
      'label' => [
        '#markup' => '<div class="stat-label">' . $this->t('Total Records') . '</div>',
      ],
    ];

    // Children list.
    if (empty($stats['children'])) {
      $build['no_children'] = [
        '#markup' => '<div class="no-children-message"><p>' . $this->t('You have no registered children yet.') . '</p><p>' . $this->t('Please contact the administrator to register your children.') . '</p></div>',
      ];
    }
    else {
      $build['children'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['children-section']],
        'title' => [
          '#markup' => '<h3>' . $this->t('My Children') . '</h3>',
        ],
      ];

      foreach ($stats['children'] as $index => $child_data) {
        $child = $child_data['student'];
        $progress = $child_data['progress'];
        $halaqa_name = $child_data['halaqa_name'];

        // Calculate evaluation summary.
        $eval_summary = '';
        $total_evals = array_sum($progress['evaluations']);
        if ($total_evals > 0) {
          $excellent_percent = round(($progress['evaluations']['excellent'] / $total_evals) * 100);
          $eval_summary = $this->t('@percent% Excellent', ['@percent' => $excellent_percent]);
        }

        // Get attendance stats for child.
        $attendance_stats = $this->halaqaStudentService->getStudentAttendanceStats((int) $child->id());

        $build['children']['child_' . $index] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['child-card']],
          'header' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['child-header']],
            'name' => [
              '#markup' => '<div class="child-name">' . $child->label() . '</div>',
            ],
            'halaqa' => [
              '#markup' => '<div class="child-halaqa">' . ($halaqa_name ? $this->t('Halaqa: @name', ['@name' => $halaqa_name]) : '') . '</div>',
            ],
          ],
          'stats' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['child-stats']],
            'records' => [
              '#markup' => '<span class="child-stat">' . $this->t('@count records', ['@count' => $progress['total_records']]) . '</span>',
            ],
            'ayahs' => [
              '#markup' => '<span class="child-stat">' . $this->t('@count ayahs', ['@count' => $progress['total_ayahs']]) . '</span>',
            ],
            'eval' => [
              '#markup' => $eval_summary ? '<span class="child-stat child-eval">' . $eval_summary . '</span>' : '',
            ],
            'attendance' => [
              '#markup' => '<span class="child-stat child-attendance">' . $this->t('@rate% attendance', ['@rate' => $attendance_stats['rate']]) . '</span>',
            ],
          ],
          'actions' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['child-actions']],
            'progress' => [
              '#type' => 'link',
              '#title' => $this->t('📊 View Progress'),
              '#url' => Url::fromRoute('halaqa_custom.parent_child_progress', ['student_nid' => $child->id()]),
              '#attributes' => ['class' => ['button', 'button--primary']],
            ],
            'attendance' => [
              '#type' => 'link',
              '#title' => $this->t('📅 Attendance'),
              '#url' => Url::fromRoute('halaqa_custom.student_attendance', ['student_nid' => $child->id()]),
              '#attributes' => ['class' => ['button', 'button--secondary', 'child-attendance-btn']],
            ],
            'report' => [
              '#type' => 'link',
              '#title' => $this->t('📄 Monthly Report'),
              '#url' => Url::fromRoute('halaqa_reports.student_report', ['student_nid' => $child->id()]),
              '#attributes' => ['class' => ['button', 'button--secondary', 'child-report-btn']],
            ],
          ],
        ];

        // Recent records for this child.
        if (!empty($progress['records'])) {
          $recent = array_slice($progress['records'], 0, 3);
          $surah_names = $this->getSurahNames();

          $build['children']['child_' . $index]['recent'] = [
            '#type' => 'container',
            '#attributes' => ['class' => ['child-recent']],
            'title' => [
              '#markup' => '<div class="recent-title">' . $this->t('Recent Records:') . '</div>',
            ],
          ];

          foreach ($recent as $ri => $record) {
            $date = '';
            $from_surah = '';

            if ($record->hasField('field_date') && !$record->get('field_date')->isEmpty()) {
              $date = $record->get('field_date')->value;
            }
            if ($record->hasField('field_from_surah') && !$record->get('field_from_surah')->isEmpty()) {
              $surah_num = $record->get('field_from_surah')->value;
              $from_surah = $surah_names[$surah_num] ?? $surah_num;
            }

            $build['children']['child_' . $index]['recent']['record_' . $ri] = [
              '#markup' => '<div class="recent-record">' . $date . ' - ' . $from_surah . '</div>',
            ];
          }
        }
      }
    }

    // Add styles.
    $build['#attached']['html_head'][] = [
      [
        '#tag' => 'style',
        '#value' => '
          .parent-dashboard { max-width: 900px; margin: 0 auto; padding: 20px; }
          .parent-dashboard h2 { color: #2e7d32; margin-bottom: 30px; }
          .dashboard-stats { display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap; }
          .stat-card { padding: 25px; border-radius: 12px; text-align: center; min-width: 150px; color: #fff; }
          .stat-children { background: linear-gradient(135deg, #43a047 0%, #1b5e20 100%); }
          .stat-records { background: linear-gradient(135deg, #1976d2 0%, #0d47a1 100%); }
          .stat-number { font-size: 2.5em; font-weight: bold; }
          .stat-label { opacity: 0.9; margin-top: 5px; }
          .no-children-message { background: #fff3e0; padding: 20px; border-radius: 8px; text-align: center; }
          .children-section h3 { margin-bottom: 20px; color: #333; }
          .child-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
          .child-header { margin-bottom: 15px; }
          .child-name { font-size: 1.4em; font-weight: bold; color: #2e7d32; }
          .child-halaqa { color: #666; font-size: 0.9em; margin-top: 5px; }
          .child-stats { display: flex; gap: 15px; margin-bottom: 15px; flex-wrap: wrap; }
          .child-stat { background: #e8f5e9; padding: 6px 12px; border-radius: 20px; font-size: 0.9em; }
          .child-eval { background: #fff9c4; }
          .child-attendance { background: #e3f2fd; }
          .child-actions { margin-bottom: 15px; display: flex; gap: 10px; flex-wrap: wrap; }
          .child-attendance-btn { background: #1976d2; color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none; display: inline-block; }
          .child-attendance-btn:hover { background: #1565c0; color: #fff; }
          .child-report-btn { background: #7b1fa2; color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none; display: inline-block; }
          .child-report-btn:hover { background: #6a1b9a; color: #fff; }
          .child-recent { border-top: 1px solid #eee; padding-top: 15px; }
          .recent-title { font-weight: 500; margin-bottom: 10px; color: #666; }
          .recent-record { padding: 5px 0; color: #555; font-size: 0.9em; }
          .button--primary { background: #2e7d32; color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none; display: inline-block; }
          .button--primary:hover { background: #1b5e20; color: #fff; }
        ',
      ],
      'parent_dashboard_styles',
    ];

    $build['#cache'] = [
      'tags' => ['node_list:student', 'node_list:memorization_record', 'node_list:attendance_record'],
      'contexts' => ['user'],
    ];

    return $build;
  }

  /**
   * Parent view of child progress.
   *
   * @param string|int $student_nid
   *   The student node ID.
   *
   * @return array
   *   A render array.
   */
  public function parentChildProgress($student_nid): array {
    $student_nid = (int) $student_nid;

    // Check if user is parent of this student.
    if (!$this->halaqaStudentService->isParentOfStudent($student_nid)) {
      throw new AccessDeniedHttpException('You do not have access to this student.');
    }

    // Reuse the student progress page.
    return $this->studentProgress($student_nid);
  }

  /**
   * Student Dashboard page.
   *
   * @return array
   *   A render array.
   */
  public function studentDashboard(): array {
    $data = $this->halaqaStudentService->getStudentDashboardData();

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['student-dashboard']],
    ];

    // Check if student profile exists.
    if (!$data['student']) {
      $build['no_profile'] = [
        '#markup' => '<div class="no-profile-message">
          <h2>' . $this->t('Welcome!') . '</h2>
          <p>' . $this->t('Your student profile has not been set up yet.') . '</p>
          <p>' . $this->t('Please contact your teacher or administrator.') . '</p>
        </div>',
      ];

      $build['#attached']['html_head'][] = [
        [
          '#tag' => 'style',
          '#value' => '
            .student-dashboard { max-width: 600px; margin: 50px auto; padding: 20px; }
            .no-profile-message { background: #fff3e0; padding: 30px; border-radius: 12px; text-align: center; }
            .no-profile-message h2 { color: #e65100; }
          ',
        ],
        'student_dashboard_no_profile_styles',
      ];

      return $build;
    }

    $student = $data['student'];
    $progress = $data['progress'];
    $halaqa = $data['halaqa'];
    $teacher = $data['teacher'];
    $surah_names = $this->getSurahNames();

    // Welcome header.
    $build['header'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['dashboard-header']],
      'welcome' => [
        '#markup' => '<h2>' . $this->t('Welcome, @name!', ['@name' => $student->label()]) . '</h2>',
      ],
    ];

    // Info cards.
    $build['info'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['info-cards']],
    ];

    if ($halaqa) {
      $build['info']['halaqa'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['info-card']],
        'icon' => ['#markup' => '<div class="info-icon">🕌</div>'],
        'label' => ['#markup' => '<div class="info-label">' . $this->t('My Halaqa') . '</div>'],
        'value' => ['#markup' => '<div class="info-value">' . $halaqa->label() . '</div>'],
      ];
    }

    if ($teacher) {
      $build['info']['teacher'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['info-card']],
        'icon' => ['#markup' => '<div class="info-icon">👨‍🏫</div>'],
        'label' => ['#markup' => '<div class="info-label">' . $this->t('My Teacher') . '</div>'],
        'value' => ['#markup' => '<div class="info-value">' . $teacher->label() . '</div>'],
      ];
    }

    // Get attendance stats for this student.
    $attendance_stats = $this->halaqaStudentService->getStudentAttendanceStats((int) $student->id());

    // Statistics.
    $build['stats'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['dashboard-stats']],
    ];

    $build['stats']['records'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['stat-card', 'stat-blue']],
      'count' => ['#markup' => '<div class="stat-number">' . $progress['total_records'] . '</div>'],
      'label' => ['#markup' => '<div class="stat-label">' . $this->t('Sessions') . '</div>'],
    ];

    $build['stats']['ayahs'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['stat-card', 'stat-green']],
      'count' => ['#markup' => '<div class="stat-number">' . $progress['total_ayahs'] . '</div>'],
      'label' => ['#markup' => '<div class="stat-label">' . $this->t('Ayahs') . '</div>'],
    ];

    $build['stats']['surahs'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['stat-card', 'stat-purple']],
      'count' => ['#markup' => '<div class="stat-number">' . count($progress['surahs_touched']) . '</div>'],
      'label' => ['#markup' => '<div class="stat-label">' . $this->t('Surahs') . '</div>'],
    ];

    $build['stats']['attendance'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['stat-card', 'stat-orange']],
      'count' => ['#markup' => '<div class="stat-number">' . $attendance_stats['rate'] . '%</div>'],
      'label' => ['#markup' => '<div class="stat-label">' . $this->t('Attendance') . '</div>'],
    ];

    // Evaluation summary.
    $total_evals = array_sum($progress['evaluations']);
    if ($total_evals > 0) {
      $build['evaluation'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['evaluation-section']],
        'title' => ['#markup' => '<h3>' . $this->t('My Performance') . '</h3>'],
      ];

      $eval_labels = [
        'excellent' => ['label' => $this->t('Excellent'), 'emoji' => '⭐'],
        'very_good' => ['label' => $this->t('Very Good'), 'emoji' => '👍'],
        'good' => ['label' => $this->t('Good'), 'emoji' => '👌'],
        'acceptable' => ['label' => $this->t('Acceptable'), 'emoji' => '📝'],
        'needs_improvement' => ['label' => $this->t('Needs Work'), 'emoji' => '💪'],
      ];

      foreach ($progress['evaluations'] as $key => $count) {
        if ($count > 0 && isset($eval_labels[$key])) {
          $percent = round(($count / $total_evals) * 100);
          $build['evaluation']['eval_' . $key] = [
            '#markup' => '<div class="eval-item">
              <span class="eval-emoji">' . $eval_labels[$key]['emoji'] . '</span>
              <span class="eval-name">' . $eval_labels[$key]['label'] . '</span>
              <span class="eval-bar-container"><span class="eval-bar" style="width: ' . $percent . '%"></span></span>
              <span class="eval-percent">' . $percent . '%</span>
            </div>',
          ];
        }
      }
    }

    // Recent records.
    if (!empty($progress['records'])) {
      $build['records'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['records-section']],
        'title' => ['#markup' => '<h3>' . $this->t('Recent Sessions') . '</h3>'],
      ];

      $recent = array_slice($progress['records'], 0, 5);
      foreach ($recent as $index => $record) {
        $date = '';
        $from_surah = '';
        $to_surah = '';
        $from_ayah = '';
        $to_ayah = '';
        $eval = '';
        $eval_emoji = '';

        if ($record->hasField('field_date') && !$record->get('field_date')->isEmpty()) {
          $date = $record->get('field_date')->value;
        }
        if ($record->hasField('field_from_surah') && !$record->get('field_from_surah')->isEmpty()) {
          $surah_num = $record->get('field_from_surah')->value;
          $from_surah = $surah_names[$surah_num] ?? $surah_num;
        }
        if ($record->hasField('field_to_surah') && !$record->get('field_to_surah')->isEmpty()) {
          $surah_num = $record->get('field_to_surah')->value;
          $to_surah = $surah_names[$surah_num] ?? $surah_num;
        }
        if ($record->hasField('field_from_ayah') && !$record->get('field_from_ayah')->isEmpty()) {
          $from_ayah = $record->get('field_from_ayah')->value;
        }
        if ($record->hasField('field_to_ayah') && !$record->get('field_to_ayah')->isEmpty()) {
          $to_ayah = $record->get('field_to_ayah')->value;
        }
        if ($record->hasField('field_evaluation') && !$record->get('field_evaluation')->isEmpty()) {
          $eval_key = $record->get('field_evaluation')->value;
          $eval_data = [
            'excellent' => ['⭐', $this->t('Excellent')],
            'very_good' => ['👍', $this->t('Very Good')],
            'good' => ['👌', $this->t('Good')],
            'acceptable' => ['📝', $this->t('Acceptable')],
            'needs_improvement' => ['💪', $this->t('Needs Work')],
          ];
          if (isset($eval_data[$eval_key])) {
            $eval_emoji = $eval_data[$eval_key][0];
            $eval = $eval_data[$eval_key][1];
          }
        }

        $range = $from_surah . ' (' . $from_ayah . ')';
        if ($from_surah !== $to_surah || $from_ayah !== $to_ayah) {
          $range .= ' → ' . $to_surah . ' (' . $to_ayah . ')';
        }

        $build['records']['record_' . $index] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['record-item']],
          'content' => [
            '#markup' => '
              <div class="record-date">' . $date . '</div>
              <div class="record-range">' . $range . '</div>
              <div class="record-eval">' . $eval_emoji . ' ' . $eval . '</div>
            ',
          ],
        ];
      }
    }

    // Recent attendance records.
    $recent_attendance = $this->halaqaStudentService->getRecentStudentAttendance((int) $student->id(), 5);
    if (!empty($recent_attendance)) {
      $build['attendance'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['attendance-section']],
        'title' => ['#markup' => '<h3>' . $this->t('Recent Attendance') . '</h3>'],
      ];

      $attendance_labels = [
        'present' => ['label' => $this->t('Present'), 'class' => 'status-present'],
        'absent' => ['label' => $this->t('Absent'), 'class' => 'status-absent'],
        'late' => ['label' => $this->t('Late'), 'class' => 'status-late'],
        'excused' => ['label' => $this->t('Excused'), 'class' => 'status-excused'],
      ];

      foreach ($recent_attendance as $ai => $att_record) {
        $att_date = '';
        $att_status = '';
        $att_class = '';

        if ($att_record->hasField('field_attendance_date') && !$att_record->get('field_attendance_date')->isEmpty()) {
          $att_date = $att_record->get('field_attendance_date')->value;
        }

        if ($att_record->hasField('field_attendance_status') && !$att_record->get('field_attendance_status')->isEmpty()) {
          $status_key = $att_record->get('field_attendance_status')->value;
          if (isset($attendance_labels[$status_key])) {
            $att_status = $attendance_labels[$status_key]['label'];
            $att_class = $attendance_labels[$status_key]['class'];
          }
        }

        $build['attendance']['record_' . $ai] = [
          '#markup' => '<div class="attendance-item">
            <span class="attendance-date">' . $att_date . '</span>
            <span class="attendance-status ' . $att_class . '">' . $att_status . '</span>
          </div>',
        ];
      }
    }

    // Motivational message.
    $build['motivation'] = [
      '#markup' => '<div class="motivation-message">
        <span class="motivation-icon">📖</span>
        <span class="motivation-text">' . $this->t('Keep up the great work! Every ayah you memorize brings you closer to Allah.') . '</span>
      </div>',
    ];

    // Add styles.
    $build['#attached']['html_head'][] = [
      [
        '#tag' => 'style',
        '#value' => '
          .student-dashboard { max-width: 800px; margin: 0 auto; padding: 20px; }
          .dashboard-header { text-align: center; margin-bottom: 30px; }
          .dashboard-header h2 { color: #1565c0; margin: 0; font-size: 1.8em; }
          .info-cards { display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; justify-content: center; }
          .info-card { background: #f5f5f5; padding: 15px 25px; border-radius: 12px; text-align: center; }
          .info-icon { font-size: 2em; }
          .info-label { color: #666; font-size: 0.85em; margin-top: 5px; }
          .info-value { font-weight: bold; color: #333; margin-top: 3px; }
          .dashboard-stats { display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; justify-content: center; }
          .stat-card { padding: 20px 30px; border-radius: 12px; text-align: center; color: #fff; min-width: 100px; }
          .stat-blue { background: linear-gradient(135deg, #1976d2 0%, #0d47a1 100%); }
          .stat-green { background: linear-gradient(135deg, #43a047 0%, #1b5e20 100%); }
          .stat-purple { background: linear-gradient(135deg, #7b1fa2 0%, #4a148c 100%); }
          .stat-orange { background: linear-gradient(135deg, #ff9800 0%, #e65100 100%); }
          .stat-number { font-size: 2.2em; font-weight: bold; }
          .stat-label { opacity: 0.9; font-size: 0.9em; }
          .evaluation-section { background: #fff; border: 1px solid #e0e0e0; border-radius: 12px; padding: 20px; margin-bottom: 25px; }
          .evaluation-section h3 { margin: 0 0 15px 0; color: #333; }
          .eval-item { display: flex; align-items: center; gap: 10px; padding: 8px 0; }
          .eval-emoji { font-size: 1.3em; }
          .eval-name { width: 100px; }
          .eval-bar-container { flex: 1; background: #e0e0e0; border-radius: 10px; height: 10px; overflow: hidden; }
          .eval-bar { background: linear-gradient(90deg, #4caf50, #8bc34a); height: 100%; border-radius: 10px; }
          .eval-percent { width: 45px; text-align: right; font-weight: bold; }
          .records-section { margin-bottom: 25px; }
          .records-section h3 { margin-bottom: 15px; color: #333; }
          .record-item { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 12px 15px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
          .record-date { color: #666; font-size: 0.9em; }
          .record-range { font-weight: 500; flex: 1; }
          .record-eval { background: #e8f5e9; padding: 4px 10px; border-radius: 15px; font-size: 0.9em; }
          .attendance-section { margin-bottom: 25px; }
          .attendance-section h3 { margin-bottom: 15px; color: #333; }
          .attendance-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 15px; background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: 8px; }
          .attendance-date { color: #666; }
          .attendance-status { padding: 4px 12px; border-radius: 15px; font-size: 0.9em; }
          .status-present { background: #e8f5e9; color: #2e7d32; }
          .status-absent { background: #ffebee; color: #c62828; }
          .status-late { background: #fff3e0; color: #e65100; }
          .status-excused { background: #f5f5f5; color: #616161; }
          .motivation-message { background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%); border-radius: 12px; padding: 20px; text-align: center; display: flex; align-items: center; justify-content: center; gap: 15px; }
          .motivation-icon { font-size: 2em; }
          .motivation-text { color: #5d4037; font-style: italic; }
        ',
      ],
      'student_dashboard_styles',
    ];

    $build['#cache'] = [
      'tags' => ['node_list:memorization_record', 'node_list:attendance_record', 'node:' . $student->id()],
      'contexts' => ['user'],
    ];

    return $build;
  }

}
