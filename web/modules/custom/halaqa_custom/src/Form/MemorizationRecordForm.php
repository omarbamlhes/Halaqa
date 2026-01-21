<?php

namespace Drupal\halaqa_custom\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\halaqa_custom\Service\HalaqaStudentService;
use Drupal\halaqa_custom\Service\NotificationService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Quick form for adding memorization records.
 */
class MemorizationRecordForm extends FormBase {

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
   * The notification service.
   *
   * @var \Drupal\halaqa_custom\Service\NotificationService
   */
  protected NotificationService $notificationService;

  /**
   * Constructs a MemorizationRecordForm object.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    HalaqaStudentService $halaqa_student_service,
    NotificationService $notification_service
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->halaqaStudentService = $halaqa_student_service;
    $this->notificationService = $notification_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('halaqa_custom.student_service'),
      $container->get('halaqa_custom.notification_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'halaqa_memorization_record_form';
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
        '#markup' => '<p>' . $this->t('You have no Halaqas assigned. Please create a Halaqa first.') . '</p>',
      ];
      return $form;
    }

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
        'callback' => '::updateStudentOptions',
        'wrapper' => 'student-wrapper',
      ],
    ];

    // Get students for selected halaqa.
    $selected_halaqa = $form_state->getValue('halaqa', $default_halaqa);
    $students = $this->halaqaStudentService->getStudentsByHalaqa((int) $selected_halaqa);

    $student_options = [];
    foreach ($students as $student) {
      $student_options[$student->id()] = $student->label();
    }

    $form['student_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'student-wrapper'],
    ];

    $form['student_wrapper']['student'] = [
      '#type' => 'select',
      '#title' => $this->t('Student'),
      '#options' => $student_options,
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select Student -'),
    ];

    if (empty($student_options)) {
      $form['student_wrapper']['student']['#empty_option'] = $this->t('No students in this Halaqa');
      $form['student_wrapper']['student']['#disabled'] = TRUE;
    }

    // Surah list.
    $surahs = $this->getSurahList();

    $form['from_details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('From'),
    ];

    $form['from_details']['from_surah'] = [
      '#type' => 'select',
      '#title' => $this->t('Surah'),
      '#options' => $surahs,
      '#required' => TRUE,
    ];

    $form['from_details']['from_ayah'] = [
      '#type' => 'number',
      '#title' => $this->t('Ayah'),
      '#min' => 1,
      '#max' => 286,
      '#required' => TRUE,
    ];

    $form['to_details'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('To'),
    ];

    $form['to_details']['to_surah'] = [
      '#type' => 'select',
      '#title' => $this->t('Surah'),
      '#options' => $surahs,
      '#required' => TRUE,
    ];

    $form['to_details']['to_ayah'] = [
      '#type' => 'number',
      '#title' => $this->t('Ayah'),
      '#min' => 1,
      '#max' => 286,
      '#required' => TRUE,
    ];

    $form['evaluation'] = [
      '#type' => 'select',
      '#title' => $this->t('Evaluation'),
      '#options' => [
        'excellent' => $this->t('Excellent'),
        'very_good' => $this->t('Very Good'),
        'good' => $this->t('Good'),
        'acceptable' => $this->t('Acceptable'),
        'needs_improvement' => $this->t('Needs Improvement'),
      ],
      '#required' => TRUE,
    ];

    $form['date'] = [
      '#type' => 'date',
      '#title' => $this->t('Date'),
      '#default_value' => date('Y-m-d'),
      '#required' => TRUE,
    ];

    $form['notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notes'),
      '#rows' => 3,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Record'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * Ajax callback to update student options.
   */
  public function updateStudentOptions(array &$form, FormStateInterface $form_state): array {
    return $form['student_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();

    // Get teacher node for current user.
    $teacher_nodes = $this->halaqaStudentService->getTeacherNodesByUser((int) $this->currentUser()->id());
    $teacher_nid = !empty($teacher_nodes) ? key($teacher_nodes) : NULL;

    // Create memorization record node.
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'memorization_record',
      'title' => $this->t('Record - @date', ['@date' => $values['date']]),
      'field_student' => $values['student'],
      'field_teacher' => $teacher_nid,
      'field_from_surah' => $values['from_surah'],
      'field_from_ayah' => $values['from_ayah'],
      'field_to_surah' => $values['to_surah'],
      'field_to_ayah' => $values['to_ayah'],
      'field_evaluation' => $values['evaluation'],
      'field_date' => $values['date'],
      'body' => [
        'value' => $values['notes'],
        'format' => 'basic_html',
      ],
    ]);

    $node->save();

    // Send notification to parent about new memorization record.
    $student_id = (int) $values['student'];
    $evaluation = $values['evaluation'];
    $this->notificationService->notifyParentOfNewRecord($student_id, $evaluation, (int) $node->id());

    $this->messenger()->addStatus($this->t('Memorization record saved successfully.'));

    // Redirect back to dashboard.
    $form_state->setRedirect('halaqa_custom.teacher_dashboard');
  }

  /**
   * Get list of Quran Surahs.
   *
   * @return array
   *   Array of surah names.
   */
  protected function getSurahList(): array {
    return [
      1 => 'الفاتحة',
      2 => 'البقرة',
      3 => 'آل عمران',
      4 => 'النساء',
      5 => 'المائدة',
      6 => 'الأنعام',
      7 => 'الأعراف',
      8 => 'الأنفال',
      9 => 'التوبة',
      10 => 'يونس',
      11 => 'هود',
      12 => 'يوسف',
      13 => 'الرعد',
      14 => 'إبراهيم',
      15 => 'الحجر',
      16 => 'النحل',
      17 => 'الإسراء',
      18 => 'الكهف',
      19 => 'مريم',
      20 => 'طه',
      21 => 'الأنبياء',
      22 => 'الحج',
      23 => 'المؤمنون',
      24 => 'النور',
      25 => 'الفرقان',
      26 => 'الشعراء',
      27 => 'النمل',
      28 => 'القصص',
      29 => 'العنكبوت',
      30 => 'الروم',
      31 => 'لقمان',
      32 => 'السجدة',
      33 => 'الأحزاب',
      34 => 'سبأ',
      35 => 'فاطر',
      36 => 'يس',
      37 => 'الصافات',
      38 => 'ص',
      39 => 'الزمر',
      40 => 'غافر',
      41 => 'فصلت',
      42 => 'الشورى',
      43 => 'الزخرف',
      44 => 'الدخان',
      45 => 'الجاثية',
      46 => 'الأحقاف',
      47 => 'محمد',
      48 => 'الفتح',
      49 => 'الحجرات',
      50 => 'ق',
      51 => 'الذاريات',
      52 => 'الطور',
      53 => 'النجم',
      54 => 'القمر',
      55 => 'الرحمن',
      56 => 'الواقعة',
      57 => 'الحديد',
      58 => 'المجادلة',
      59 => 'الحشر',
      60 => 'الممتحنة',
      61 => 'الصف',
      62 => 'الجمعة',
      63 => 'المنافقون',
      64 => 'التغابن',
      65 => 'الطلاق',
      66 => 'التحريم',
      67 => 'الملك',
      68 => 'القلم',
      69 => 'الحاقة',
      70 => 'المعارج',
      71 => 'نوح',
      72 => 'الجن',
      73 => 'المزمل',
      74 => 'المدثر',
      75 => 'القيامة',
      76 => 'الإنسان',
      77 => 'المرسلات',
      78 => 'النبأ',
      79 => 'النازعات',
      80 => 'عبس',
      81 => 'التكوير',
      82 => 'الانفطار',
      83 => 'المطففين',
      84 => 'الانشقاق',
      85 => 'البروج',
      86 => 'الطارق',
      87 => 'الأعلى',
      88 => 'الغاشية',
      89 => 'الفجر',
      90 => 'البلد',
      91 => 'الشمس',
      92 => 'الليل',
      93 => 'الضحى',
      94 => 'الشرح',
      95 => 'التين',
      96 => 'العلق',
      97 => 'القدر',
      98 => 'البينة',
      99 => 'الزلزلة',
      100 => 'العاديات',
      101 => 'القارعة',
      102 => 'التكاثر',
      103 => 'العصر',
      104 => 'الهمزة',
      105 => 'الفيل',
      106 => 'قريش',
      107 => 'الماعون',
      108 => 'الكوثر',
      109 => 'الكافرون',
      110 => 'النصر',
      111 => 'المسد',
      112 => 'الإخلاص',
      113 => 'الفلق',
      114 => 'الناس',
    ];
  }

}

