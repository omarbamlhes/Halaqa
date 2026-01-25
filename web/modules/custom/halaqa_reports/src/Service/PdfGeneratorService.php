<?php

namespace Drupal\halaqa_reports\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Render\RendererInterface;
use Mpdf\Mpdf;

/**
 * Service for generating PDF reports.
 */
class PdfGeneratorService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * List of Quran Surahs.
   *
   * @var array
   */
  protected $surahs = [
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

  /**
   * Constructs a new PdfGeneratorService.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    DateFormatterInterface $date_formatter,
    RendererInterface $renderer
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->dateFormatter = $date_formatter;
    $this->renderer = $renderer;
  }

  /**
   * Get Surah name by number.
   */
  public function getSurahName($number) {
    return $this->surahs[$number] ?? $number;
  }

  /**
   * Generate student report data.
   */
  public function getStudentReportData($student_nid, $month = NULL, $year = NULL) {
    $node_storage = $this->entityTypeManager->getStorage('node');
    $student = $node_storage->load($student_nid);

    if (!$student || $student->bundle() !== 'student') {
      return NULL;
    }

    // Default to current month/year
    $month = $month ?: date('n');
    $year = $year ?: date('Y');

    // Get start and end timestamps for the month
    $start_date = strtotime("$year-$month-01");
    $end_date = strtotime("+1 month", $start_date) - 1;

    // Get Halaqa info
    $halaqa = NULL;
    $teacher = NULL;
    if ($student->hasField('field_halaqa') && !$student->get('field_halaqa')->isEmpty()) {
      $halaqa = $student->get('field_halaqa')->entity;
      if ($halaqa && $halaqa->hasField('field_teacher') && !$halaqa->get('field_teacher')->isEmpty()) {
        $teacher = $halaqa->get('field_teacher')->entity;
      }
    }

    // Get attendance records for this month
    $attendance_query = $node_storage->getQuery()
      ->condition('type', 'attendance_record')
      ->condition('field_student', $student_nid)
      ->condition('field_date', date('Y-m-d', $start_date), '>=')
      ->condition('field_date', date('Y-m-d', $end_date), '<=')
      ->accessCheck(FALSE)
      ->sort('field_date', 'ASC');

    $attendance_nids = $attendance_query->execute();
    $attendance_records = $node_storage->loadMultiple($attendance_nids);

    $attendance_stats = [
      'total' => count($attendance_records),
      'present' => 0,
      'absent' => 0,
      'late' => 0,
      'excused' => 0,
    ];

    $attendance_list = [];
    foreach ($attendance_records as $record) {
      $status = $record->get('field_status')->value;
      $attendance_stats[$status]++;
      $attendance_list[] = [
        'date' => $record->get('field_date')->value,
        'status' => $status,
        'notes' => $record->hasField('field_notes') ? $record->get('field_notes')->value : '',
      ];
    }

    $attendance_rate = $attendance_stats['total'] > 0
      ? round(($attendance_stats['present'] + $attendance_stats['late']) / $attendance_stats['total'] * 100)
      : 0;

    // Get memorization records for this month
    $memorization_query = $node_storage->getQuery()
      ->condition('type', 'memorization_record')
      ->condition('field_student', $student_nid)
      ->condition('field_date', date('Y-m-d', $start_date), '>=')
      ->condition('field_date', date('Y-m-d', $end_date), '<=')
      ->accessCheck(FALSE)
      ->sort('field_date', 'ASC');

    $memorization_nids = $memorization_query->execute();
    $memorization_records = $node_storage->loadMultiple($memorization_nids);

    $memorization_stats = [
      'total_records' => count($memorization_records),
      'total_ayahs' => 0,
      'excellent' => 0,
      'very_good' => 0,
      'good' => 0,
      'acceptable' => 0,
      'needs_improvement' => 0,
    ];

    $memorization_list = [];
    foreach ($memorization_records as $record) {
      $from_surah = $record->get('field_from_surah')->value;
      $to_surah = $record->get('field_to_surah')->value;
      $from_ayah = $record->get('field_from_ayah')->value;
      $to_ayah = $record->get('field_to_ayah')->value;
      $evaluation = $record->get('field_evaluation')->value;

      // Simple ayah count (same surah only)
      if ($from_surah == $to_surah) {
        $memorization_stats['total_ayahs'] += ($to_ayah - $from_ayah + 1);
      }

      $memorization_stats[$evaluation]++;

      $memorization_list[] = [
        'date' => $record->get('field_date')->value,
        'from_surah' => $this->getSurahName($from_surah),
        'to_surah' => $this->getSurahName($to_surah),
        'from_ayah' => $from_ayah,
        'to_ayah' => $to_ayah,
        'evaluation' => $evaluation,
      ];
    }

    return [
      'student' => [
        'name' => $student->getTitle(),
        'nid' => $student_nid,
      ],
      'halaqa' => $halaqa ? [
        'name' => $halaqa->getTitle(),
        'nid' => $halaqa->id(),
      ] : NULL,
      'teacher' => $teacher ? [
        'name' => $teacher->getTitle(),
      ] : NULL,
      'period' => [
        'month' => $month,
        'year' => $year,
        'month_name' => $this->getArabicMonthName($month),
      ],
      'attendance' => [
        'stats' => $attendance_stats,
        'rate' => $attendance_rate,
        'records' => $attendance_list,
      ],
      'memorization' => [
        'stats' => $memorization_stats,
        'records' => $memorization_list,
      ],
      'generated_date' => date('Y-m-d H:i'),
    ];
  }

  /**
   * Get Arabic month name.
   */
  protected function getArabicMonthName($month) {
    $months = [
      1 => 'يناير',
      2 => 'فبراير',
      3 => 'مارس',
      4 => 'أبريل',
      5 => 'مايو',
      6 => 'يونيو',
      7 => 'يوليو',
      8 => 'أغسطس',
      9 => 'سبتمبر',
      10 => 'أكتوبر',
      11 => 'نوفمبر',
      12 => 'ديسمبر',
    ];
    return $months[$month] ?? $month;
  }

  /**
   * Generate PDF from HTML content using mPDF.
   */
  public function generatePdf($html, $filename = 'report.pdf') {
    $mpdf = new Mpdf([
      'mode' => 'utf-8',
      'format' => 'A4',
      'default_font' => 'xbriyaz',
      'default_font_size' => 12,
      'margin_left' => 15,
      'margin_right' => 15,
      'margin_top' => 15,
      'margin_bottom' => 15,
      'tempDir' => sys_get_temp_dir() . '/mpdf',
    ]);

    // Enable RTL for Arabic
    $mpdf->SetDirectionality('rtl');
    $mpdf->autoScriptToLang = true;
    $mpdf->autoLangToFont = true;

    $mpdf->WriteHTML($html);

    return $mpdf->Output('', 'S');
  }

  /**
   * Get evaluation label in Arabic.
   */
  public function getEvaluationLabel($evaluation) {
    $labels = [
      'excellent' => 'ممتاز',
      'very_good' => 'جيد جداً',
      'good' => 'جيد',
      'acceptable' => 'مقبول',
      'needs_improvement' => 'يحتاج تحسين',
    ];
    return $labels[$evaluation] ?? $evaluation;
  }

  /**
   * Get status label in Arabic.
   */
  public function getStatusLabel($status) {
    $labels = [
      'present' => 'حاضر',
      'absent' => 'غائب',
      'late' => 'متأخر',
      'excused' => 'معذور',
    ];
    return $labels[$status] ?? $status;
  }

}
