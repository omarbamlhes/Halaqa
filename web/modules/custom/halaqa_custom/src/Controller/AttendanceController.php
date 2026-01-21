<?php

namespace Drupal\halaqa_custom\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\halaqa_custom\Service\HalaqaStudentService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for attendance pages.
 */
class AttendanceController extends ControllerBase {

  /**
   * The Halaqa student service.
   *
   * @var \Drupal\halaqa_custom\Service\HalaqaStudentService
   */
  protected HalaqaStudentService $halaqaStudentService;

  /**
   * Constructs an AttendanceController object.
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
   * Display attendance records for a Halaqa.
   *
   * @param string|int $nid
   *   The Halaqa node ID.
   *
   * @return array
   *   A render array.
   */
  public function halaqaAttendance($nid): array {
    $nid = (int) $nid;

    // Load the Halaqa.
    $halaqa = $this->halaqaStudentService->loadHalaqa($nid);

    if (!$halaqa) {
      throw new NotFoundHttpException('Halaqa not found.');
    }

    // Check access.
    $current_user = $this->currentUser();
    $is_admin = in_array('administrator', $current_user->getRoles());
    if (!$is_admin && !$this->halaqaStudentService->userHasAccessToHalaqa($nid, (int) $current_user->id())) {
      throw new AccessDeniedHttpException('You do not have access to this Halaqa.');
    }

    // Get attendance stats.
    $stats = $this->halaqaStudentService->getHalaqaAttendanceStats($nid);
    $recent_attendance = $this->halaqaStudentService->getRecentHalaqaAttendance($nid, 10);

    // Get students for reference.
    $students = $this->halaqaStudentService->getStudentsByHalaqa($nid);
    $student_names = [];
    foreach ($students as $student) {
      $student_names[$student->id()] = $student->label();
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['halaqa-attendance-page']],
      '#attached' => [
        'library' => ['halaqa_theme/attendance'],
      ],
    ];

    // Page header with action button.
    $build['header'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['attendance-page-header']],
      'title' => [
        '#markup' => '<h2>' . $this->t('Attendance Records - @halaqa', ['@halaqa' => $halaqa->label()]) . '</h2>',
      ],
      'action' => [
        '#type' => 'link',
        '#title' => $this->t('Record Attendance'),
        '#url' => Url::fromRoute('halaqa_custom.attendance_form', ['halaqa_nid' => $nid]),
        '#attributes' => ['class' => ['button', 'button--primary', 'attendance-record-btn']],
      ],
    ];

    // Statistics cards.
    $build['stats'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['attendance-stats-grid']],
    ];

    $build['stats']['sessions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['attendance-stat-card', 'stat-sessions']],
      'count' => [
        '#markup' => '<div class="stat-number">' . $stats['session_count'] . '</div>',
      ],
      'label' => [
        '#markup' => '<div class="stat-label">' . $this->t('Sessions') . '</div>',
      ],
    ];

    $build['stats']['rate'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['attendance-stat-card', 'stat-rate']],
      'count' => [
        '#markup' => '<div class="stat-number">' . $stats['rate'] . '%</div>',
      ],
      'label' => [
        '#markup' => '<div class="stat-label">' . $this->t('Attendance Rate') . '</div>',
      ],
    ];

    $build['stats']['present'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['attendance-stat-card', 'stat-present']],
      'count' => [
        '#markup' => '<div class="stat-number">' . $stats['present'] . '</div>',
      ],
      'label' => [
        '#markup' => '<div class="stat-label">' . $this->t('Present') . '</div>',
      ],
    ];

    $build['stats']['absent'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['attendance-stat-card', 'stat-absent']],
      'count' => [
        '#markup' => '<div class="stat-number">' . $stats['absent'] . '</div>',
      ],
      'label' => [
        '#markup' => '<div class="stat-label">' . $this->t('Absent') . '</div>',
      ],
    ];

    // Recent attendance records.
    $build['records'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['attendance-records-section']],
      'title' => [
        '#markup' => '<h3>' . $this->t('Recent Sessions') . '</h3>',
      ],
    ];

    if (empty($recent_attendance)) {
      $build['records']['empty'] = [
        '#markup' => '<p class="no-records">' . $this->t('No attendance records yet. Click "Record Attendance" to start.') . '</p>',
      ];
    }
    else {
      $status_labels = [
        'present' => $this->t('Present'),
        'absent' => $this->t('Absent'),
        'late' => $this->t('Late'),
        'excused' => $this->t('Excused'),
      ];

      foreach ($recent_attendance as $date => $day_data) {
        $build['records']['date_' . str_replace('-', '', $date)] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['attendance-day-card']],
        ];

        $day_card = &$build['records']['date_' . str_replace('-', '', $date)];

        $day_card['header'] = [
          '#markup' => '<div class="day-header">
            <span class="day-date">' . $date . '</span>
            <span class="day-summary">
              <span class="present-badge">' . $day_data['present'] . ' ' . $this->t('present') . '</span>
              <span class="absent-badge">' . $day_data['absent'] . ' ' . $this->t('absent') . '</span>
              <span class="late-badge">' . $day_data['late'] . ' ' . $this->t('late') . '</span>
            </span>
          </div>',
        ];

        $day_card['students'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['day-students']],
        ];

        foreach ($day_data['records'] as $index => $record) {
          $student_id = NULL;
          $student_name = '';
          $status = '';
          $status_class = '';

          if ($record->hasField('field_attendance_student') && !$record->get('field_attendance_student')->isEmpty()) {
            $student_id = $record->get('field_attendance_student')->target_id;
            $student_name = $student_names[$student_id] ?? $this->t('Unknown Student');
          }

          if ($record->hasField('field_attendance_status') && !$record->get('field_attendance_status')->isEmpty()) {
            $status_key = $record->get('field_attendance_status')->value;
            $status = $status_labels[$status_key] ?? $status_key;
            $status_class = 'status-' . $status_key;
          }

          $day_card['students']['student_' . $index] = [
            '#markup' => '<div class="student-attendance-row">
              <span class="student-name">' . $student_name . '</span>
              <span class="student-status ' . $status_class . '">' . $status . '</span>
            </div>',
          ];
        }
      }
    }

    // Cache settings.
    $build['#cache'] = [
      'tags' => ['node:' . $nid, 'node_list:attendance_record'],
      'contexts' => ['user'],
    ];

    return $build;
  }

  /**
   * Title callback for Halaqa attendance page.
   *
   * @param string|int $nid
   *   The Halaqa node ID.
   *
   * @return string
   *   The page title.
   */
  public function halaqaAttendanceTitle($nid): string {
    $halaqa = $this->halaqaStudentService->loadHalaqa((int) $nid);

    if ($halaqa) {
      return $this->t('Attendance - @halaqa', ['@halaqa' => $halaqa->label()]);
    }

    return $this->t('Attendance Records');
  }

  /**
   * Display attendance records for a student.
   *
   * @param string|int $student_nid
   *   The student node ID.
   *
   * @return array
   *   A render array.
   */
  public function studentAttendance($student_nid): array {
    $student_nid = (int) $student_nid;

    // Load the student.
    $student = $this->halaqaStudentService->loadStudent($student_nid);

    if (!$student) {
      throw new NotFoundHttpException('Student not found.');
    }

    // Check access.
    $current_user = $this->currentUser();
    $is_admin = in_array('administrator', $current_user->getRoles());
    $is_teacher = in_array('teacher', $current_user->getRoles());
    $is_parent = $this->halaqaStudentService->isParentOfStudent($student_nid);

    if (!$is_admin && !$is_teacher && !$is_parent) {
      throw new AccessDeniedHttpException('You do not have access to this student.');
    }

    // If teacher, verify access to student's halaqa.
    if ($is_teacher && !$is_admin && !$is_parent) {
      if (!$this->halaqaStudentService->userHasAccessToStudent($student_nid)) {
        throw new AccessDeniedHttpException('You do not have access to this student.');
      }
    }

    // Get attendance stats.
    $stats = $this->halaqaStudentService->getStudentAttendanceStats($student_nid);
    $records = $this->halaqaStudentService->getStudentAttendance($student_nid);

    // Get halaqa name.
    $halaqa_name = '';
    if ($student->hasField('field_halaqa') && !$student->get('field_halaqa')->isEmpty()) {
      $halaqa = $student->get('field_halaqa')->entity;
      if ($halaqa) {
        $halaqa_name = $halaqa->label();
      }
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['student-attendance-page']],
      '#attached' => [
        'library' => ['halaqa_theme/attendance'],
      ],
    ];

    // Page header.
    $build['header'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['attendance-page-header']],
      'title' => [
        '#markup' => '<h2>' . $this->t('Attendance Record') . '</h2>',
      ],
      'student_info' => [
        '#markup' => '<div class="student-info">
          <span class="student-name-large">' . $student->label() . '</span>
          ' . ($halaqa_name ? '<span class="student-halaqa">' . $this->t('Halaqa: @name', ['@name' => $halaqa_name]) . '</span>' : '') . '
        </div>',
      ],
    ];

    // Statistics cards.
    $build['stats'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['attendance-stats-grid']],
    ];

    $build['stats']['total'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['attendance-stat-card', 'stat-total']],
      'count' => [
        '#markup' => '<div class="stat-number">' . $stats['total'] . '</div>',
      ],
      'label' => [
        '#markup' => '<div class="stat-label">' . $this->t('Total Sessions') . '</div>',
      ],
    ];

    $build['stats']['rate'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['attendance-stat-card', 'stat-rate']],
      'count' => [
        '#markup' => '<div class="stat-number">' . $stats['rate'] . '%</div>',
      ],
      'label' => [
        '#markup' => '<div class="stat-label">' . $this->t('Attendance Rate') . '</div>',
      ],
    ];

    $build['stats']['present'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['attendance-stat-card', 'stat-present']],
      'count' => [
        '#markup' => '<div class="stat-number">' . $stats['present'] . '</div>',
      ],
      'label' => [
        '#markup' => '<div class="stat-label">' . $this->t('Present') . '</div>',
      ],
    ];

    $build['stats']['absent'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['attendance-stat-card', 'stat-absent']],
      'count' => [
        '#markup' => '<div class="stat-number">' . $stats['absent'] . '</div>',
      ],
      'label' => [
        '#markup' => '<div class="stat-label">' . $this->t('Absent') . '</div>',
      ],
    ];

    // Attendance breakdown.
    $build['breakdown'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['attendance-breakdown']],
      'title' => [
        '#markup' => '<h3>' . $this->t('Status Breakdown') . '</h3>',
      ],
    ];

    $status_colors = [
      'present' => '#4CAF50',
      'absent' => '#f44336',
      'late' => '#FF9800',
      'excused' => '#9E9E9E',
    ];

    $status_labels = [
      'present' => $this->t('Present'),
      'absent' => $this->t('Absent'),
      'late' => $this->t('Late'),
      'excused' => $this->t('Excused'),
    ];

    foreach (['present', 'absent', 'late', 'excused'] as $status_key) {
      $count = $stats[$status_key];
      $percent = $stats['total'] > 0 ? round(($count / $stats['total']) * 100) : 0;

      $build['breakdown']['status_' . $status_key] = [
        '#markup' => '<div class="breakdown-row">
          <span class="breakdown-label">' . $status_labels[$status_key] . '</span>
          <div class="breakdown-bar-container">
            <div class="breakdown-bar" style="width: ' . $percent . '%; background-color: ' . $status_colors[$status_key] . '"></div>
          </div>
          <span class="breakdown-count">' . $count . ' (' . $percent . '%)</span>
        </div>',
      ];
    }

    // Attendance history.
    $build['history'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['attendance-history']],
      'title' => [
        '#markup' => '<h3>' . $this->t('Attendance History') . '</h3>',
      ],
    ];

    if (empty($records)) {
      $build['history']['empty'] = [
        '#markup' => '<p class="no-records">' . $this->t('No attendance records yet.') . '</p>',
      ];
    }
    else {
      foreach ($records as $index => $record) {
        $date = '';
        $status = '';
        $status_key = '';
        $notes = '';

        if ($record->hasField('field_attendance_date') && !$record->get('field_attendance_date')->isEmpty()) {
          $date = $record->get('field_attendance_date')->value;
        }

        if ($record->hasField('field_attendance_status') && !$record->get('field_attendance_status')->isEmpty()) {
          $status_key = $record->get('field_attendance_status')->value;
          $status = $status_labels[$status_key] ?? $status_key;
        }

        if ($record->hasField('field_attendance_notes') && !$record->get('field_attendance_notes')->isEmpty()) {
          $notes = $record->get('field_attendance_notes')->value;
        }

        $build['history']['record_' . $index] = [
          '#markup' => '<div class="history-row">
            <span class="history-date">' . $date . '</span>
            <span class="history-status status-' . $status_key . '">' . $status . '</span>
            ' . ($notes ? '<span class="history-notes">' . $notes . '</span>' : '') . '
          </div>',
        ];
      }
    }

    // Cache settings.
    $build['#cache'] = [
      'tags' => ['node:' . $student_nid, 'node_list:attendance_record'],
      'contexts' => ['user'],
    ];

    return $build;
  }

  /**
   * Title callback for student attendance page.
   *
   * @param string|int $student_nid
   *   The student node ID.
   *
   * @return string
   *   The page title.
   */
  public function studentAttendanceTitle($student_nid): string {
    $student = $this->halaqaStudentService->loadStudent((int) $student_nid);

    if ($student) {
      return $this->t('Attendance - @name', ['@name' => $student->label()]);
    }

    return $this->t('Student Attendance');
  }

}
