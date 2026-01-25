<?php

namespace Drupal\halaqa_reports\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\halaqa_reports\Service\PdfGeneratorService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for generating reports.
 */
class ReportController extends ControllerBase {

  /**
   * The PDF generator service.
   *
   * @var \Drupal\halaqa_reports\Service\PdfGeneratorService
   */
  protected $pdfGenerator;

  /**
   * Constructs a ReportController object.
   */
  public function __construct(PdfGeneratorService $pdf_generator) {
    $this->pdfGenerator = $pdf_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('halaqa_reports.pdf_generator')
    );
  }

  /**
   * Display student report page (preview).
   */
  public function studentReport($student_nid, Request $request) {
    $month = $request->query->get('month', date('n'));
    $year = $request->query->get('year', date('Y'));

    $data = $this->pdfGenerator->getStudentReportData($student_nid, $month, $year);

    if (!$data) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    return [
      '#theme' => 'halaqa_student_report',
      '#data' => $data,
      '#pdf_generator' => $this->pdfGenerator,
      '#attached' => [
        'library' => [
          'halaqa_reports/report-styles',
        ],
      ],
    ];
  }

  /**
   * Download student report as PDF.
   */
  public function downloadStudentReport($student_nid, Request $request) {
    $month = $request->query->get('month', date('n'));
    $year = $request->query->get('year', date('Y'));

    $data = $this->pdfGenerator->getStudentReportData($student_nid, $month, $year);

    if (!$data) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $html = $this->renderReportHtml($data);
    $pdf_content = $this->pdfGenerator->generatePdf($html);

    $filename = 'student-report-' . $student_nid . '-' . $year . '-' . $month . '.pdf';

    $response = new Response($pdf_content);
    $response->headers->set('Content-Type', 'application/pdf');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
    $response->headers->set('Content-Length', strlen($pdf_content));

    return $response;
  }

  /**
   * Display Halaqa report page.
   */
  public function halaqaReport($halaqa_nid, Request $request) {
    $month = $request->query->get('month', date('n'));
    $year = $request->query->get('year', date('Y'));

    $node_storage = $this->entityTypeManager()->getStorage('node');
    $halaqa = $node_storage->load($halaqa_nid);

    if (!$halaqa || $halaqa->bundle() !== 'halaqa') {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    // Get all students in this Halaqa
    $student_query = $node_storage->getQuery()
      ->condition('type', 'student')
      ->condition('field_halaqa', $halaqa_nid)
      ->accessCheck(FALSE);

    $student_nids = $student_query->execute();
    $students_data = [];

    foreach ($student_nids as $student_nid) {
      $student_data = $this->pdfGenerator->getStudentReportData($student_nid, $month, $year);
      if ($student_data) {
        $students_data[] = $student_data;
      }
    }

    return [
      '#theme' => 'halaqa_report',
      '#halaqa' => $halaqa,
      '#students_data' => $students_data,
      '#month' => $month,
      '#year' => $year,
      '#month_name' => $this->pdfGenerator->getStudentReportData($halaqa_nid)['period']['month_name'] ?? '',
      '#pdf_generator' => $this->pdfGenerator,
      '#attached' => [
        'library' => [
          'halaqa_reports/report-styles',
        ],
      ],
    ];
  }

  /**
   * Download Halaqa report as PDF.
   */
  public function downloadHalaqaReport($halaqa_nid, Request $request) {
    $month = $request->query->get('month', date('n'));
    $year = $request->query->get('year', date('Y'));

    $node_storage = $this->entityTypeManager()->getStorage('node');
    $halaqa = $node_storage->load($halaqa_nid);

    if (!$halaqa || $halaqa->bundle() !== 'halaqa') {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    // Get all students in this Halaqa
    $student_query = $node_storage->getQuery()
      ->condition('type', 'student')
      ->condition('field_halaqa', $halaqa_nid)
      ->accessCheck(FALSE);

    $student_nids = $student_query->execute();
    $students_data = [];

    foreach ($student_nids as $student_nid) {
      $student_data = $this->pdfGenerator->getStudentReportData($student_nid, $month, $year);
      if ($student_data) {
        $students_data[] = $student_data;
      }
    }

    $html = $this->renderHalaqaReportHtml($halaqa, $students_data, $month, $year);
    $pdf_content = $this->pdfGenerator->generatePdf($html);

    $filename = 'halaqa-report-' . $halaqa_nid . '-' . $year . '-' . $month . '.pdf';

    $response = new Response($pdf_content);
    $response->headers->set('Content-Type', 'application/pdf');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
    $response->headers->set('Content-Length', strlen($pdf_content));

    return $response;
  }

  /**
   * Render student report HTML for PDF.
   */
  protected function renderReportHtml($data) {
    // Get logo path
    $logo_path = DRUPAL_ROOT . '/themes/custom/halaqa_theme/images/logo.svg';
    $logo_svg = '';
    if (file_exists($logo_path)) {
      $logo_svg = file_get_contents($logo_path);
    }

    $html = '
    <!DOCTYPE html>
    <html dir="rtl" lang="ar">
    <head>
      <meta charset="UTF-8">
      <style>
        @page { margin: 20mm; }
        * { font-family: XB Riyaz, Tahoma, Arial, sans-serif; }
        body {
          direction: rtl;
          text-align: right;
          font-size: 12px;
          line-height: 1.6;
          color: #333;
        }
        .header {
          text-align: center;
          border-bottom: 3px solid #0d9488;
          padding-bottom: 15px;
          margin-bottom: 20px;
        }
        .logo {
          width: 180px;
          height: 50px;
          margin: 0 auto 15px auto;
        }
        .header h1 {
          color: #0d9488;
          margin: 0;
          font-size: 24px;
        }
        .header .subtitle {
          color: #666;
          font-size: 14px;
          margin-top: 5px;
        }
        .section {
          margin-bottom: 20px;
          border: 1px solid #ddd;
          border-radius: 8px;
          overflow: hidden;
        }
        .section-title {
          background: #2e7d32;
          color: white;
          padding: 10px 15px;
          font-size: 16px;
          font-weight: bold;
        }
        .section-content {
          padding: 15px;
        }
        .info-grid {
          display: table;
          width: 100%;
        }
        .info-row {
          display: table-row;
        }
        .info-label {
          display: table-cell;
          padding: 8px;
          font-weight: bold;
          width: 30%;
          background: #f5f5f5;
        }
        .info-value {
          display: table-cell;
          padding: 8px;
        }
        .stats-grid {
          display: table;
          width: 100%;
          margin-top: 10px;
        }
        .stat-box {
          display: table-cell;
          text-align: center;
          padding: 10px;
          border: 1px solid #ddd;
        }
        .stat-number {
          font-size: 24px;
          font-weight: bold;
          color: #2e7d32;
        }
        .stat-label {
          font-size: 11px;
          color: #666;
        }
        table.records {
          width: 100%;
          border-collapse: collapse;
          margin-top: 10px;
        }
        table.records th {
          background: #f5f5f5;
          padding: 10px;
          text-align: right;
          border: 1px solid #ddd;
        }
        table.records td {
          padding: 8px 10px;
          border: 1px solid #ddd;
        }
        .status-present { color: #2e7d32; }
        .status-absent { color: #c62828; }
        .status-late { color: #f57c00; }
        .status-excused { color: #1976d2; }
        .eval-excellent { color: #2e7d32; }
        .eval-very_good { color: #43a047; }
        .eval-good { color: #1976d2; }
        .eval-acceptable { color: #f57c00; }
        .eval-needs_improvement { color: #c62828; }
        .footer {
          text-align: center;
          margin-top: 30px;
          padding-top: 15px;
          border-top: 1px solid #ddd;
          font-size: 10px;
          color: #999;
        }
        .rate-box {
          text-align: center;
          padding: 15px;
          background: #e8f5e9;
          border-radius: 8px;
          margin-bottom: 15px;
        }
        .rate-number {
          font-size: 36px;
          font-weight: bold;
          color: #2e7d32;
        }
        .rate-label {
          color: #666;
        }
      </style>
        .logo-container {
          margin-bottom: 15px;
        }
        .logo-icon {
          display: inline-block;
          width: 50px;
          height: 50px;
          background: linear-gradient(135deg, #0d9488 0%, #0891b2 100%);
          border-radius: 50%;
          text-align: center;
          line-height: 50px;
          color: white;
          font-size: 24px;
          vertical-align: middle;
        }
        .logo-text {
          display: inline-block;
          vertical-align: middle;
          margin-right: 10px;
        }
        .logo-title {
          font-size: 26px;
          font-weight: bold;
          color: #0d9488;
        }
        .logo-tagline {
          font-size: 11px;
          color: #64748b;
        }
      </style>
    </head>
    <body>
      <div class="header">
        <div class="logo-container">
          <span class="logo-icon">📖</span>
          <span class="logo-text">
            <span class="logo-title">حلقة</span><br>
            <span class="logo-tagline">منصة تحفيظ القرآن الكريم</span>
          </span>
        </div>
        <h1>تقرير الطالب الشهري</h1>
        <div class="subtitle">حلقة تحفيظ القرآن الكريم</div>
      </div>

      <div class="section">
        <div class="section-title">معلومات الطالب</div>
        <div class="section-content">
          <div class="info-grid">
            <div class="info-row">
              <div class="info-label">اسم الطالب:</div>
              <div class="info-value">' . htmlspecialchars($data['student']['name']) . '</div>
            </div>
            <div class="info-row">
              <div class="info-label">الحلقة:</div>
              <div class="info-value">' . ($data['halaqa'] ? htmlspecialchars($data['halaqa']['name']) : '-') . '</div>
            </div>
            <div class="info-row">
              <div class="info-label">المعلم:</div>
              <div class="info-value">' . ($data['teacher'] ? htmlspecialchars($data['teacher']['name']) : '-') . '</div>
            </div>
            <div class="info-row">
              <div class="info-label">الفترة:</div>
              <div class="info-value">' . $data['period']['month_name'] . ' ' . $data['period']['year'] . '</div>
            </div>
          </div>
        </div>
      </div>

      <div class="section">
        <div class="section-title">الحضور والغياب</div>
        <div class="section-content">
          <div class="rate-box">
            <div class="rate-number">' . $data['attendance']['rate'] . '%</div>
            <div class="rate-label">نسبة الحضور</div>
          </div>
          <div class="stats-grid">
            <div class="stat-box">
              <div class="stat-number">' . $data['attendance']['stats']['total'] . '</div>
              <div class="stat-label">إجمالي الحصص</div>
            </div>
            <div class="stat-box">
              <div class="stat-number status-present">' . $data['attendance']['stats']['present'] . '</div>
              <div class="stat-label">حاضر</div>
            </div>
            <div class="stat-box">
              <div class="stat-number status-absent">' . $data['attendance']['stats']['absent'] . '</div>
              <div class="stat-label">غائب</div>
            </div>
            <div class="stat-box">
              <div class="stat-number status-late">' . $data['attendance']['stats']['late'] . '</div>
              <div class="stat-label">متأخر</div>
            </div>
          </div>
        </div>
      </div>

      <div class="section">
        <div class="section-title">الحفظ والمراجعة</div>
        <div class="section-content">
          <div class="stats-grid">
            <div class="stat-box">
              <div class="stat-number">' . $data['memorization']['stats']['total_records'] . '</div>
              <div class="stat-label">عدد الحصص</div>
            </div>
            <div class="stat-box">
              <div class="stat-number">' . $data['memorization']['stats']['total_ayahs'] . '</div>
              <div class="stat-label">الآيات المحفوظة</div>
            </div>
            <div class="stat-box">
              <div class="stat-number eval-excellent">' . $data['memorization']['stats']['excellent'] . '</div>
              <div class="stat-label">ممتاز</div>
            </div>
            <div class="stat-box">
              <div class="stat-number eval-good">' . $data['memorization']['stats']['good'] . '</div>
              <div class="stat-label">جيد</div>
            </div>
          </div>';

    if (!empty($data['memorization']['records'])) {
      $html .= '
          <table class="records">
            <thead>
              <tr>
                <th>التاريخ</th>
                <th>من</th>
                <th>إلى</th>
                <th>التقييم</th>
              </tr>
            </thead>
            <tbody>';

      foreach ($data['memorization']['records'] as $record) {
        $evalClass = 'eval-' . $record['evaluation'];
        $evalLabel = $this->pdfGenerator->getEvaluationLabel($record['evaluation']);
        $html .= '
              <tr>
                <td>' . $record['date'] . '</td>
                <td>' . $record['from_surah'] . ' - آية ' . $record['from_ayah'] . '</td>
                <td>' . $record['to_surah'] . ' - آية ' . $record['to_ayah'] . '</td>
                <td class="' . $evalClass . '">' . $evalLabel . '</td>
              </tr>';
      }

      $html .= '
            </tbody>
          </table>';
    }

    $html .= '
        </div>
      </div>

      <div class="footer">
        تم إنشاء هذا التقرير بتاريخ: ' . $data['generated_date'] . '
      </div>
    </body>
    </html>';

    return $html;
  }

  /**
   * Render Halaqa report HTML for PDF.
   */
  protected function renderHalaqaReportHtml($halaqa, $students_data, $month, $year) {
    $months = [
      1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
      5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
      9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
    ];
    $month_name = $months[$month] ?? $month;

    $html = '
    <!DOCTYPE html>
    <html dir="rtl" lang="ar">
    <head>
      <meta charset="UTF-8">
      <style>
        @page { margin: 15mm; }
        * { font-family: XB Riyaz, Tahoma, Arial, sans-serif; }
        body {
          direction: rtl;
          text-align: right;
          font-size: 11px;
          line-height: 1.5;
          color: #333;
        }
        .header {
          text-align: center;
          border-bottom: 3px solid #0d9488;
          padding-bottom: 15px;
          margin-bottom: 20px;
        }
        .logo-container {
          margin-bottom: 15px;
        }
        .logo-icon {
          display: inline-block;
          width: 50px;
          height: 50px;
          background: linear-gradient(135deg, #0d9488 0%, #0891b2 100%);
          border-radius: 50%;
          text-align: center;
          line-height: 50px;
          color: white;
          font-size: 24px;
          vertical-align: middle;
        }
        .logo-text {
          display: inline-block;
          vertical-align: middle;
          margin-right: 10px;
        }
        .logo-title {
          font-size: 26px;
          font-weight: bold;
          color: #0d9488;
        }
        .logo-tagline {
          font-size: 11px;
          color: #64748b;
        }
        .header h1 {
          color: #0d9488;
          margin: 0;
          font-size: 22px;
        }
        .header .subtitle {
          color: #666;
          font-size: 14px;
          margin-top: 5px;
        }
        table.summary {
          width: 100%;
          border-collapse: collapse;
          margin-top: 15px;
        }
        table.summary th {
          background: #0d9488;
          color: white;
          padding: 10px;
          text-align: right;
        }
        table.summary td {
          padding: 8px 10px;
          border: 1px solid #ddd;
        }
        table.summary tr:nth-child(even) {
          background: #f5f5f5;
        }
        .footer {
          text-align: center;
          margin-top: 20px;
          padding-top: 10px;
          border-top: 1px solid #ddd;
          font-size: 10px;
          color: #999;
        }
      </style>
    </head>
    <body>
      <div class="header">
        <div class="logo-container">
          <span class="logo-icon">📖</span>
          <span class="logo-text">
            <span class="logo-title">حلقة</span><br>
            <span class="logo-tagline">منصة تحفيظ القرآن الكريم</span>
          </span>
        </div>
        <h1>تقرير الحلقة الشهري</h1>
        <div class="subtitle">' . htmlspecialchars($halaqa->getTitle()) . ' - ' . $month_name . ' ' . $year . '</div>
      </div>

      <table class="summary">
        <thead>
          <tr>
            <th>اسم الطالب</th>
            <th>نسبة الحضور</th>
            <th>الحصص</th>
            <th>الآيات</th>
            <th>ممتاز</th>
            <th>جيد جداً</th>
            <th>جيد</th>
          </tr>
        </thead>
        <tbody>';

    foreach ($students_data as $student) {
      $html .= '
          <tr>
            <td>' . htmlspecialchars($student['student']['name']) . '</td>
            <td>' . $student['attendance']['rate'] . '%</td>
            <td>' . $student['attendance']['stats']['total'] . '</td>
            <td>' . $student['memorization']['stats']['total_ayahs'] . '</td>
            <td>' . $student['memorization']['stats']['excellent'] . '</td>
            <td>' . $student['memorization']['stats']['very_good'] . '</td>
            <td>' . $student['memorization']['stats']['good'] . '</td>
          </tr>';
    }

    $html .= '
        </tbody>
      </table>

      <div class="footer">
        تم إنشاء هذا التقرير بتاريخ: ' . date('Y-m-d H:i') . '
      </div>
    </body>
    </html>';

    return $html;
  }

}
