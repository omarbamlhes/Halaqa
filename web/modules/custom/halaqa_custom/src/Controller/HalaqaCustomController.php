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
          'link' => [
            '#type' => 'link',
            '#title' => $this->t('View Details'),
            '#url' => $student->toUrl(),
            '#attributes' => ['class' => ['student-link']],
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

}
