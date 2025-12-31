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
            'separator' => [
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
          'link' => [
            '#type' => 'link',
            '#title' => $this->t('View Students →'),
            '#url' => Url::fromRoute('halaqa_custom.halaqa_students', ['nid' => $halaqa->id()]),
            '#attributes' => ['class' => ['halaqa-link']],
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
          .halaqa-link { color: #2196F3; }
          .dashboard-records h3 { margin-bottom: 15px; }
        ',
      ],
      'halaqa_dashboard_styles',
    ];

    $build['#cache'] = [
      'tags' => ['node_list:halaqa', 'node_list:student', 'node_list:memorization_record'],
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

}
