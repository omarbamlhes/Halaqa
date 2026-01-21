<?php

namespace Drupal\halaqa_custom\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\halaqa_custom\Service\HalaqaStudentService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for recording student attendance.
 */
class AttendanceForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The Halaqa student service.
   *
   * @var \Drupal\halaqa_custom\Service\HalaqaStudentService
   */
  protected HalaqaStudentService $halaqaStudentService;

  /**
   * Constructs an AttendanceForm object.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    HalaqaStudentService $halaqa_student_service
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->halaqaStudentService = $halaqa_student_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('halaqa_custom.student_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'halaqa_attendance_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $halaqa_nid = NULL): array {
    $halaqa_nid = $halaqa_nid ? (int) $halaqa_nid : NULL;

    // Get halaqas for current teacher.
    $halaqas = $this->halaqaStudentService->getHalaqasByCurrentTeacher();

    if (empty($halaqas)) {
      $form['message'] = [
        '#markup' => '<div class="messages messages--warning"><p>' . $this->t('You have no Halaqas assigned. Please create a Halaqa first.') . '</p></div>',
      ];
      return $form;
    }

    // Add CSS library.
    $form['#attached']['library'][] = 'halaqa_theme/attendance';

    $form['#attributes']['class'][] = 'attendance-form';

    // Build halaqa options.
    $halaqa_options = [];
    foreach ($halaqas as $halaqa) {
      $halaqa_options[$halaqa->id()] = $halaqa->label();
    }

    $default_halaqa = $halaqa_nid && isset($halaqa_options[$halaqa_nid]) ? $halaqa_nid : key($halaqa_options);

    $form['halaqa'] = [
      '#type' => 'select',
      '#title' => $this->t('Halaqa'),
      '#options' => $halaqa_options,
      '#default_value' => $default_halaqa,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateStudentsTable',
        'wrapper' => 'students-attendance-wrapper',
      ],
      '#attributes' => ['class' => ['attendance-halaqa-select']],
    ];

    $form['date'] = [
      '#type' => 'date',
      '#title' => $this->t('Date'),
      '#default_value' => date('Y-m-d'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateStudentsTable',
        'wrapper' => 'students-attendance-wrapper',
      ],
      '#attributes' => ['class' => ['attendance-date-input']],
    ];

    // Students attendance table wrapper.
    $form['students_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'students-attendance-wrapper'],
    ];

    // Get selected halaqa and date.
    $selected_halaqa = $form_state->getValue('halaqa', $default_halaqa);
    $selected_date = $form_state->getValue('date', date('Y-m-d'));

    // Get students for selected halaqa.
    $students = $this->halaqaStudentService->getStudentsByHalaqa((int) $selected_halaqa);

    if (empty($students)) {
      $form['students_wrapper']['no_students'] = [
        '#markup' => '<div class="messages messages--warning"><p>' . $this->t('No students found in this Halaqa.') . '</p></div>',
      ];
    }
    else {
      // Get existing attendance for this date.
      $existing_attendance = $this->halaqaStudentService->getAttendanceByHalaqaAndDate((int) $selected_halaqa, $selected_date);

      $form['students_wrapper']['students_table'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['attendance-students-table']],
      ];

      // Table header.
      $form['students_wrapper']['students_table']['header'] = [
        '#markup' => '<div class="attendance-header">
          <div class="attendance-header-student">' . $this->t('Student') . '</div>
          <div class="attendance-header-status">' . $this->t('Status') . '</div>
          <div class="attendance-header-notes">' . $this->t('Notes') . '</div>
        </div>',
      ];

      $status_options = [
        'present' => $this->t('Present'),
        'absent' => $this->t('Absent'),
        'late' => $this->t('Late'),
        'excused' => $this->t('Excused'),
      ];

      foreach ($students as $student) {
        $student_id = $student->id();

        // Get default values from existing attendance.
        $default_status = 'present';
        $default_notes = '';
        if (isset($existing_attendance[$student_id])) {
          $record = $existing_attendance[$student_id];
          if ($record->hasField('field_attendance_status') && !$record->get('field_attendance_status')->isEmpty()) {
            $default_status = $record->get('field_attendance_status')->value;
          }
          if ($record->hasField('field_attendance_notes') && !$record->get('field_attendance_notes')->isEmpty()) {
            $default_notes = $record->get('field_attendance_notes')->value;
          }
        }

        $form['students_wrapper']['students_table']['student_' . $student_id] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['attendance-row']],
        ];

        $form['students_wrapper']['students_table']['student_' . $student_id]['name'] = [
          '#markup' => '<div class="attendance-student-name">' . $student->label() . '</div>',
        ];

        $form['students_wrapper']['students_table']['student_' . $student_id]['status_' . $student_id] = [
          '#type' => 'radios',
          '#options' => $status_options,
          '#default_value' => $default_status,
          '#attributes' => ['class' => ['attendance-status-radios']],
        ];

        $form['students_wrapper']['students_table']['student_' . $student_id]['notes_' . $student_id] = [
          '#type' => 'textfield',
          '#default_value' => $default_notes,
          '#placeholder' => $this->t('Notes (optional)'),
          '#attributes' => ['class' => ['attendance-notes-input']],
          '#size' => 30,
        ];
      }

      // Store student IDs for submit handler.
      $student_ids = array_keys($students);
      $form['student_ids'] = [
        '#type' => 'hidden',
        '#value' => implode(',', $student_ids),
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['attendance-actions']],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Attendance'),
      '#button_type' => 'primary',
      '#attributes' => ['class' => ['attendance-submit-btn']],
    ];

    return $form;
  }

  /**
   * Ajax callback to update students table.
   */
  public function updateStudentsTable(array &$form, FormStateInterface $form_state): array {
    return $form['students_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $halaqa_nid = (int) $values['halaqa'];
    $date = $values['date'];

    // Get student IDs.
    $student_ids_string = $values['student_ids'] ?? '';
    if (empty($student_ids_string)) {
      $this->messenger()->addError($this->t('No students found to record attendance.'));
      return;
    }

    $student_ids = explode(',', $student_ids_string);
    $saved_count = 0;

    foreach ($student_ids as $student_id) {
      $status_key = 'status_' . $student_id;
      $notes_key = 'notes_' . $student_id;

      $status = $values[$status_key] ?? 'present';
      $notes = $values[$notes_key] ?? '';

      $result = $this->halaqaStudentService->saveAttendanceRecord(
        (int) $student_id,
        $halaqa_nid,
        $date,
        $status,
        $notes ?: NULL
      );

      if ($result) {
        $saved_count++;
      }
    }

    $this->messenger()->addStatus($this->t('Attendance recorded for @count students.', ['@count' => $saved_count]));

    // Redirect to halaqa attendance records page.
    $form_state->setRedirect('halaqa_custom.halaqa_attendance', ['nid' => $halaqa_nid]);
  }

}
