<?php

namespace Drupal\halaqa_custom\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;

/**
 * Service for managing Halaqa students data.
 */
class HalaqaStudentService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Constructs a HalaqaStudentService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $current_user
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  /**
   * Get students by Halaqa node ID.
   *
   * @param int $halaqa_nid
   *   The Halaqa node ID.
   *
   * @return \Drupal\node\NodeInterface[]
   *   An array of student nodes.
   */
  public function getStudentsByHalaqa(int $halaqa_nid): array {
    $storage = $this->entityTypeManager->getStorage('node');

    $query = $storage->getQuery()
      ->condition('type', 'student')
      ->condition('status', 1)
      ->condition('field_halaqa', $halaqa_nid)
      ->accessCheck(TRUE)
      ->sort('title', 'ASC');

    $nids = $query->execute();

    if (empty($nids)) {
      return [];
    }

    return $storage->loadMultiple($nids);
  }

  /**
   * Get Halaqas managed by the current teacher.
   *
   * @return \Drupal\node\NodeInterface[]
   *   An array of Halaqa nodes.
   */
  public function getHalaqasByCurrentTeacher(): array {
    // First, find teacher node(s) created by the current user.
    $teacher_nodes = $this->getTeacherNodesByUser($this->currentUser->id());

    if (empty($teacher_nodes)) {
      return [];
    }

    $teacher_nids = array_keys($teacher_nodes);

    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->condition('type', 'halaqa')
      ->condition('status', 1)
      ->condition('field_teacher', $teacher_nids, 'IN')
      ->accessCheck(TRUE);

    $nids = $query->execute();

    if (empty($nids)) {
      return [];
    }

    return $storage->loadMultiple($nids);
  }

  /**
   * Get teacher nodes by user ID.
   *
   * @param int $uid
   *   The user ID.
   *
   * @return \Drupal\node\NodeInterface[]
   *   An array of teacher nodes.
   */
  public function getTeacherNodesByUser(int $uid): array {
    $storage = $this->entityTypeManager->getStorage('node');

    $query = $storage->getQuery()
      ->condition('type', 'teacher')
      ->condition('uid', $uid)
      ->condition('status', 1)
      ->accessCheck(TRUE);

    $nids = $query->execute();

    if (empty($nids)) {
      return [];
    }

    return $storage->loadMultiple($nids);
  }

  /**
   * Check if user has access to a specific Halaqa.
   *
   * @param int $halaqa_nid
   *   The Halaqa node ID.
   * @param int|null $uid
   *   The user ID. Defaults to current user.
   *
   * @return bool
   *   TRUE if user has access, FALSE otherwise.
   */
  public function userHasAccessToHalaqa(int $halaqa_nid, ?int $uid = NULL): bool {
    $uid = $uid ?? $this->currentUser->id();

    // Administrators always have access.
    /** @var \Drupal\user\UserInterface|null $user */
    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    if ($user && in_array('administrator', $user->getRoles())) {
      return TRUE;
    }

    // Load the Halaqa node.
    $halaqa = $this->entityTypeManager->getStorage('node')->load($halaqa_nid);
    if (!$halaqa || $halaqa->bundle() !== 'halaqa') {
      return FALSE;
    }

    // Get teacher nodes for this user.
    $teacher_nodes = $this->getTeacherNodesByUser($uid);
    if (empty($teacher_nodes)) {
      return FALSE;
    }

    // Check if the Halaqa's teacher matches any of user's teacher nodes.
    if ($halaqa->hasField('field_teacher') && !$halaqa->get('field_teacher')->isEmpty()) {
      $halaqa_teacher_id = $halaqa->get('field_teacher')->target_id;
      return isset($teacher_nodes[$halaqa_teacher_id]);
    }

    return FALSE;
  }

  /**
   * Load a Halaqa node.
   *
   * @param int $nid
   *   The node ID.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The Halaqa node or NULL if not found.
   */
  public function loadHalaqa(int $nid): ?NodeInterface {
    $node = $this->entityTypeManager->getStorage('node')->load($nid);

    if ($node && $node->bundle() === 'halaqa') {
      return $node;
    }

    return NULL;
  }

  /**
   * Get statistics for current teacher's dashboard.
   *
   * @return array
   *   Array with statistics.
   */
  public function getTeacherDashboardStats(): array {
    $halaqas = $this->getHalaqasByCurrentTeacher();
    $halaqa_count = count($halaqas);

    $total_students = 0;
    $halaqas_with_students = [];

    foreach ($halaqas as $halaqa) {
      $students = $this->getStudentsByHalaqa((int) $halaqa->id());
      $student_count = count($students);
      $total_students += $student_count;

      $halaqas_with_students[] = [
        'halaqa' => $halaqa,
        'students' => $students,
        'student_count' => $student_count,
      ];
    }

    // Get recent memorization records.
    $recent_records = $this->getRecentMemorizationRecords();

    return [
      'halaqa_count' => $halaqa_count,
      'total_students' => $total_students,
      'halaqas' => $halaqas_with_students,
      'recent_records' => $recent_records,
    ];
  }

  /**
   * Get recent memorization records for current teacher.
   *
   * @param int $limit
   *   Number of records to return.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Array of memorization record nodes.
   */
  public function getRecentMemorizationRecords(int $limit = 5): array {
    $teacher_nodes = $this->getTeacherNodesByUser($this->currentUser->id());

    if (empty($teacher_nodes)) {
      return [];
    }

    $teacher_nids = array_keys($teacher_nodes);

    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->condition('type', 'memorization_record')
      ->condition('status', 1)
      ->condition('field_teacher', $teacher_nids, 'IN')
      ->accessCheck(TRUE)
      ->sort('created', 'DESC')
      ->range(0, $limit);

    $nids = $query->execute();

    if (empty($nids)) {
      return [];
    }

    return $storage->loadMultiple($nids);
  }

  /**
   * Load a student node.
   *
   * @param int $nid
   *   The node ID.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The student node or NULL.
   */
  public function loadStudent(int $nid): ?NodeInterface {
    $node = $this->entityTypeManager->getStorage('node')->load($nid);

    if ($node && $node->bundle() === 'student') {
      return $node;
    }

    return NULL;
  }

  /**
   * Get memorization records for a specific student.
   *
   * @param int $student_nid
   *   The student node ID.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Array of memorization record nodes.
   */
  public function getStudentMemorizationRecords(int $student_nid): array {
    $storage = $this->entityTypeManager->getStorage('node');

    $query = $storage->getQuery()
      ->condition('type', 'memorization_record')
      ->condition('status', 1)
      ->condition('field_student', $student_nid)
      ->accessCheck(TRUE)
      ->sort('field_date', 'DESC');

    $nids = $query->execute();

    if (empty($nids)) {
      return [];
    }

    return $storage->loadMultiple($nids);
  }

  /**
   * Get student progress statistics.
   *
   * @param int $student_nid
   *   The student node ID.
   *
   * @return array
   *   Array with progress statistics.
   */
  public function getStudentProgress(int $student_nid): array {
    $records = $this->getStudentMemorizationRecords($student_nid);

    $stats = [
      'total_records' => count($records),
      'records' => $records,
      'evaluations' => [
        'excellent' => 0,
        'very_good' => 0,
        'good' => 0,
        'acceptable' => 0,
        'needs_improvement' => 0,
      ],
      'total_ayahs' => 0,
      'surahs_touched' => [],
    ];

    foreach ($records as $record) {
      // Count evaluations.
      if ($record->hasField('field_evaluation') && !$record->get('field_evaluation')->isEmpty()) {
        $eval = $record->get('field_evaluation')->value;
        if (isset($stats['evaluations'][$eval])) {
          $stats['evaluations'][$eval]++;
        }
      }

      // Calculate ayahs (approximate).
      $from_ayah = 0;
      $to_ayah = 0;
      if ($record->hasField('field_from_ayah') && !$record->get('field_from_ayah')->isEmpty()) {
        $from_ayah = (int) $record->get('field_from_ayah')->value;
      }
      if ($record->hasField('field_to_ayah') && !$record->get('field_to_ayah')->isEmpty()) {
        $to_ayah = (int) $record->get('field_to_ayah')->value;
      }
      if ($to_ayah >= $from_ayah) {
        $stats['total_ayahs'] += ($to_ayah - $from_ayah + 1);
      }

      // Track surahs.
      if ($record->hasField('field_from_surah') && !$record->get('field_from_surah')->isEmpty()) {
        $surah = $record->get('field_from_surah')->value;
        if (!in_array($surah, $stats['surahs_touched'])) {
          $stats['surahs_touched'][] = $surah;
        }
      }
      if ($record->hasField('field_to_surah') && !$record->get('field_to_surah')->isEmpty()) {
        $surah = $record->get('field_to_surah')->value;
        if (!in_array($surah, $stats['surahs_touched'])) {
          $stats['surahs_touched'][] = $surah;
        }
      }
    }

    return $stats;
  }

  /**
   * Check if current user has access to view a student.
   *
   * @param int $student_nid
   *   The student node ID.
   *
   * @return bool
   *   TRUE if user has access.
   */
  public function userHasAccessToStudent(int $student_nid): bool {
    $uid = $this->currentUser->id();

    // Administrators always have access.
    /** @var \Drupal\user\UserInterface|null $user */
    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    if ($user && in_array('administrator', $user->getRoles())) {
      return TRUE;
    }

    // Load the student.
    /** @var \Drupal\node\NodeInterface|null $student */
    $student = $this->loadStudent($student_nid);
    if (!$student) {
      return FALSE;
    }

    // Get the student's halaqa.
    if (!$student->hasField('field_halaqa') || $student->get('field_halaqa')->isEmpty()) {
      return FALSE;
    }

    $halaqa_id = (int) $student->get('field_halaqa')->target_id;

    // Check if teacher has access to this halaqa.
    return $this->userHasAccessToHalaqa($halaqa_id, $uid);
  }

  /**
   * Get children (students) for a parent user.
   *
   * @param int|null $uid
   *   The parent user ID. Defaults to current user.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Array of student nodes.
   */
  public function getChildrenByParent(?int $uid = NULL): array {
    $uid = $uid ?? $this->currentUser->id();

    $storage = $this->entityTypeManager->getStorage('node');

    // First try to find students with field_parent (if exists).
    // Otherwise, find students created by this user.
    $query = $storage->getQuery()
      ->condition('type', 'student')
      ->condition('status', 1)
      ->condition('uid', $uid)
      ->accessCheck(TRUE)
      ->sort('title', 'ASC');

    $nids = $query->execute();

    if (empty($nids)) {
      return [];
    }

    return $storage->loadMultiple($nids);
  }

  /**
   * Get parent dashboard statistics.
   *
   * @return array
   *   Array with statistics.
   */
  public function getParentDashboardStats(): array {
    $children = $this->getChildrenByParent();

    $stats = [
      'children_count' => count($children),
      'children' => [],
      'total_records' => 0,
    ];

    foreach ($children as $child) {
      $progress = $this->getStudentProgress((int) $child->id());

      // Get halaqa name.
      $halaqa_name = '';
      if ($child->hasField('field_halaqa') && !$child->get('field_halaqa')->isEmpty()) {
        $halaqa = $child->get('field_halaqa')->entity;
        $halaqa_name = $halaqa ? $halaqa->label() : '';
      }

      $stats['children'][] = [
        'student' => $child,
        'halaqa_name' => $halaqa_name,
        'progress' => $progress,
      ];

      $stats['total_records'] += $progress['total_records'];
    }

    return $stats;
  }

  /**
   * Check if current user is parent of a student.
   *
   * @param int $student_nid
   *   The student node ID.
   *
   * @return bool
   *   TRUE if user is parent.
   */
  public function isParentOfStudent(int $student_nid): bool {
    $uid = $this->currentUser->id();

    // Check if user is admin.
    /** @var \Drupal\user\UserInterface|null $user */
    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    if ($user && in_array('administrator', $user->getRoles())) {
      return TRUE;
    }

    $student = $this->loadStudent($student_nid);
    if (!$student) {
      return FALSE;
    }

    // Check if student was created by this user (parent).
    return (int) $student->getOwnerId() === $uid;
  }

  /**
   * Get student node for current user.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The student node or NULL.
   */
  public function getStudentForCurrentUser(): ?NodeInterface {
    $uid = $this->currentUser->id();

    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->condition('type', 'student')
      ->condition('status', 1)
      ->condition('uid', $uid)
      ->accessCheck(TRUE)
      ->range(0, 1);

    $nids = $query->execute();

    if (empty($nids)) {
      return NULL;
    }

    return $storage->load(reset($nids));
  }

  /**
   * Get student nodes by user ID.
   *
   * @param int $uid
   *   The user ID.
   *
   * @return array
   *   Array of student node IDs.
   */
  public function getStudentNodesByUser(int $uid): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->condition('type', 'student')
      ->condition('status', 1)
      ->condition('uid', $uid)
      ->accessCheck(TRUE);

    return $query->execute();
  }

  /**
   * Get halaqa and teacher info for a student.
   *
   * @param int $student_nid
   *   The student node ID.
   *
   * @return array|null
   *   Array with halaqa and teacher info, or NULL.
   */
  public function getStudentHalaqaAndTeacher(int $student_nid): ?array {
    $storage = $this->entityTypeManager->getStorage('node');
    $student = $storage->load($student_nid);

    if (!$student || $student->bundle() !== 'student') {
      return NULL;
    }

    $result = [
      'halaqa_name' => '',
      'halaqa_nid' => NULL,
      'teacher_name' => '',
      'teacher_nid' => NULL,
    ];

    // Get halaqa.
    if ($student->hasField('field_halaqa') && !$student->get('field_halaqa')->isEmpty()) {
      $halaqa = $student->get('field_halaqa')->entity;
      if ($halaqa) {
        $result['halaqa_name'] = $halaqa->label();
        $result['halaqa_nid'] = $halaqa->id();

        // Get teacher from halaqa.
        if ($halaqa->hasField('field_teacher') && !$halaqa->get('field_teacher')->isEmpty()) {
          $teacher = $halaqa->get('field_teacher')->entity;
          if ($teacher) {
            $result['teacher_name'] = $teacher->label();
            $result['teacher_nid'] = $teacher->id();
          }
        }
      }
    }

    return $result;
  }

  /**
   * Get student dashboard data.
   *
   * @return array
   *   Array with dashboard data.
   */
  public function getStudentDashboardData(): array {
    $student = $this->getStudentForCurrentUser();

    if (!$student) {
      return [
        'student' => NULL,
        'halaqa' => NULL,
        'teacher' => NULL,
        'progress' => NULL,
      ];
    }

    // Get progress.
    $progress = $this->getStudentProgress((int) $student->id());

    // Get halaqa.
    $halaqa = NULL;
    $teacher = NULL;
    if ($student->hasField('field_halaqa') && !$student->get('field_halaqa')->isEmpty()) {
      $halaqa = $student->get('field_halaqa')->entity;

      // Get teacher from halaqa.
      if ($halaqa && $halaqa->hasField('field_teacher') && !$halaqa->get('field_teacher')->isEmpty()) {
        $teacher = $halaqa->get('field_teacher')->entity;
      }
    }

    return [
      'student' => $student,
      'halaqa' => $halaqa,
      'teacher' => $teacher,
      'progress' => $progress,
    ];
  }

  /**
   * Get global statistics for charts display.
   *
   * @return array
   *   Array with global statistics.
   */
  public function getGlobalChartStats(): array {
    $storage = $this->entityTypeManager->getStorage('node');

    $total_records = 0;
    $total_students = 0;
    $total_halaqas = 0;
    $total_teachers = 0;

    try {
      // Count total memorization records.
      $records_query = $storage->getQuery()
        ->condition('type', 'memorization_record')
        ->condition('status', 1)
        ->accessCheck(TRUE);
      $total_records = count($records_query->execute());
    }
    catch (\Exception $e) {
      // Content type may not exist.
    }

    try {
      // Count total students.
      $students_query = $storage->getQuery()
        ->condition('type', 'student')
        ->condition('status', 1)
        ->accessCheck(TRUE);
      $total_students = count($students_query->execute());
    }
    catch (\Exception $e) {
      // Content type may not exist.
    }

    try {
      // Count total halaqas.
      $halaqas_query = $storage->getQuery()
        ->condition('type', 'halaqa')
        ->condition('status', 1)
        ->accessCheck(TRUE);
      $total_halaqas = count($halaqas_query->execute());
    }
    catch (\Exception $e) {
      // Content type may not exist.
    }

    try {
      // Count total teachers.
      $teachers_query = $storage->getQuery()
        ->condition('type', 'teacher')
        ->condition('status', 1)
        ->accessCheck(TRUE);
      $total_teachers = count($teachers_query->execute());
    }
    catch (\Exception $e) {
      // Content type may not exist.
    }

    $evaluations = [
      'excellent' => 0,
      'very_good' => 0,
      'good' => 0,
      'acceptable' => 0,
      'needs_improvement' => 0,
    ];

    $total_ayahs = 0;
    $surahs_touched = [];

    try {
      // Get all evaluations and calculate ayahs.
      $all_records_nids = $storage->getQuery()
        ->condition('type', 'memorization_record')
        ->condition('status', 1)
        ->accessCheck(TRUE)
        ->execute();

      if (!empty($all_records_nids)) {
        $records = $storage->loadMultiple($all_records_nids);

        foreach ($records as $record) {
          // Count evaluations.
          if ($record->hasField('field_evaluation') && !$record->get('field_evaluation')->isEmpty()) {
            $eval = $record->get('field_evaluation')->value;
            if (isset($evaluations[$eval])) {
              $evaluations[$eval]++;
            }
          }

          // Calculate ayahs.
          $from_ayah = 0;
          $to_ayah = 0;
          if ($record->hasField('field_from_ayah') && !$record->get('field_from_ayah')->isEmpty()) {
            $from_ayah = (int) $record->get('field_from_ayah')->value;
          }
          if ($record->hasField('field_to_ayah') && !$record->get('field_to_ayah')->isEmpty()) {
            $to_ayah = (int) $record->get('field_to_ayah')->value;
          }
          if ($to_ayah >= $from_ayah && $from_ayah > 0) {
            $total_ayahs += ($to_ayah - $from_ayah + 1);
          }

          // Track surahs.
          if ($record->hasField('field_from_surah') && !$record->get('field_from_surah')->isEmpty()) {
            $surah = $record->get('field_from_surah')->value;
            if (!in_array($surah, $surahs_touched)) {
              $surahs_touched[] = $surah;
            }
          }
        }
      }
    }
    catch (\Exception $e) {
      // Content type or fields may not exist.
    }

    return [
      'total_records' => $total_records,
      'total_students' => $total_students,
      'total_halaqas' => $total_halaqas,
      'total_teachers' => $total_teachers,
      'total_ayahs' => $total_ayahs,
      'surahs_count' => count($surahs_touched),
      'evaluations' => $evaluations,
      'excellent_count' => $evaluations['excellent'],
    ];
  }

  /**
   * Get weekly activity (records per day for the current week).
   *
   * @return array
   *   Array with daily record counts (Sat-Fri).
   */
  public function getWeeklyActivity(): array {
    $storage = $this->entityTypeManager->getStorage('node');

    // Get start of week (Saturday).
    $now = new \DateTime();
    $day_of_week = (int) $now->format('w'); // 0 = Sunday, 6 = Saturday
    // Adjust to make Saturday = 0.
    $adjusted_day = ($day_of_week + 1) % 7;
    $start_of_week = (clone $now)->modify("-{$adjusted_day} days")->setTime(0, 0, 0);

    $weekly_data = [0, 0, 0, 0, 0, 0, 0]; // Sat, Sun, Mon, Tue, Wed, Thu, Fri

    for ($i = 0; $i < 7; $i++) {
      $day_start = (clone $start_of_week)->modify("+{$i} days");
      $day_end = (clone $day_start)->modify('+1 day');

      $query = $storage->getQuery()
        ->condition('type', 'memorization_record')
        ->condition('status', 1)
        ->condition('created', $day_start->getTimestamp(), '>=')
        ->condition('created', $day_end->getTimestamp(), '<')
        ->accessCheck(TRUE);

      $weekly_data[$i] = count($query->execute());
    }

    return $weekly_data;
  }

  /**
   * Get Juz progress statistics.
   *
   * @return array
   *   Array with Juz progress data.
   */
  public function getJuzProgress(): array {
    // Juz boundaries (approximate ayah numbers).
    $juz_info = [
      'juz_amma' => ['start' => 78, 'end' => 114, 'name' => 'Juz Amma', 'total_ayahs' => 564],
      'juz_tabarak' => ['start' => 67, 'end' => 77, 'name' => 'Juz Tabarak', 'total_ayahs' => 431],
      'juz_qad_samia' => ['start' => 58, 'end' => 66, 'name' => 'Juz Qad Samia', 'total_ayahs' => 451],
    ];

    $storage = $this->entityTypeManager->getStorage('node');

    // Get all memorization records.
    $records_nids = $storage->getQuery()
      ->condition('type', 'memorization_record')
      ->condition('status', 1)
      ->accessCheck(TRUE)
      ->execute();

    $juz_ayahs = [
      'juz_amma' => 0,
      'juz_tabarak' => 0,
      'juz_qad_samia' => 0,
    ];

    if (!empty($records_nids)) {
      $records = $storage->loadMultiple($records_nids);

      foreach ($records as $record) {
        $from_surah = 0;
        $from_ayah = 0;
        $to_ayah = 0;

        if ($record->hasField('field_from_surah') && !$record->get('field_from_surah')->isEmpty()) {
          $from_surah = (int) $record->get('field_from_surah')->value;
        }
        if ($record->hasField('field_from_ayah') && !$record->get('field_from_ayah')->isEmpty()) {
          $from_ayah = (int) $record->get('field_from_ayah')->value;
        }
        if ($record->hasField('field_to_ayah') && !$record->get('field_to_ayah')->isEmpty()) {
          $to_ayah = (int) $record->get('field_to_ayah')->value;
        }

        $ayahs_count = ($to_ayah >= $from_ayah && $from_ayah > 0) ? ($to_ayah - $from_ayah + 1) : 0;

        // Categorize by Juz.
        if ($from_surah >= 78 && $from_surah <= 114) {
          $juz_ayahs['juz_amma'] += $ayahs_count;
        }
        elseif ($from_surah >= 67 && $from_surah <= 77) {
          $juz_ayahs['juz_tabarak'] += $ayahs_count;
        }
        elseif ($from_surah >= 58 && $from_surah <= 66) {
          $juz_ayahs['juz_qad_samia'] += $ayahs_count;
        }
      }
    }

    // Calculate percentages.
    return [
      [
        'name' => 'Juz Amma',
        'value' => min(100, round(($juz_ayahs['juz_amma'] / $juz_info['juz_amma']['total_ayahs']) * 100)),
        'ayahs' => $juz_ayahs['juz_amma'],
        'total' => $juz_info['juz_amma']['total_ayahs'],
      ],
      [
        'name' => 'Juz Tabarak',
        'value' => min(100, round(($juz_ayahs['juz_tabarak'] / $juz_info['juz_tabarak']['total_ayahs']) * 100)),
        'ayahs' => $juz_ayahs['juz_tabarak'],
        'total' => $juz_info['juz_tabarak']['total_ayahs'],
      ],
      [
        'name' => 'Juz Qad Samia',
        'value' => min(100, round(($juz_ayahs['juz_qad_samia'] / $juz_info['juz_qad_samia']['total_ayahs']) * 100)),
        'ayahs' => $juz_ayahs['juz_qad_samia'],
        'total' => $juz_info['juz_qad_samia']['total_ayahs'],
      ],
    ];
  }

  /**
   * Calculate overall progress percentage.
   *
   * @return int
   *   Progress percentage (0-100).
   */
  public function getOverallProgress(): int {
    $stats = $this->getGlobalChartStats();

    // Total Quran ayahs = 6236.
    $total_quran_ayahs = 6236;

    if ($stats['total_ayahs'] <= 0) {
      return 0;
    }

    return min(100, round(($stats['total_ayahs'] / $total_quran_ayahs) * 100));
  }

  /**
   * Get attendance records for a Halaqa on a specific date.
   *
   * @param int $halaqa_nid
   *   The Halaqa node ID.
   * @param string $date
   *   The date in Y-m-d format.
   *
   * @return array
   *   Array of attendance records keyed by student NID.
   */
  public function getAttendanceByHalaqaAndDate(int $halaqa_nid, string $date): array {
    $storage = $this->entityTypeManager->getStorage('node');

    $query = $storage->getQuery()
      ->condition('type', 'attendance_record')
      ->condition('status', 1)
      ->condition('field_attendance_halaqa', $halaqa_nid)
      ->condition('field_attendance_date', $date)
      ->accessCheck(TRUE);

    $nids = $query->execute();

    if (empty($nids)) {
      return [];
    }

    $records = $storage->loadMultiple($nids);
    $result = [];

    foreach ($records as $record) {
      if ($record->hasField('field_attendance_student') && !$record->get('field_attendance_student')->isEmpty()) {
        $student_nid = $record->get('field_attendance_student')->target_id;
        $result[$student_nid] = $record;
      }
    }

    return $result;
  }

  /**
   * Get student attendance records.
   *
   * @param int $student_nid
   *   The student node ID.
   * @param string|null $from_date
   *   Optional start date filter (Y-m-d format).
   * @param string|null $to_date
   *   Optional end date filter (Y-m-d format).
   *
   * @return \Drupal\node\NodeInterface[]
   *   Array of attendance record nodes.
   */
  public function getStudentAttendance(int $student_nid, ?string $from_date = NULL, ?string $to_date = NULL): array {
    $storage = $this->entityTypeManager->getStorage('node');

    $query = $storage->getQuery()
      ->condition('type', 'attendance_record')
      ->condition('status', 1)
      ->condition('field_attendance_student', $student_nid)
      ->accessCheck(TRUE)
      ->sort('field_attendance_date', 'DESC');

    if ($from_date) {
      $query->condition('field_attendance_date', $from_date, '>=');
    }

    if ($to_date) {
      $query->condition('field_attendance_date', $to_date, '<=');
    }

    $nids = $query->execute();

    if (empty($nids)) {
      return [];
    }

    return $storage->loadMultiple($nids);
  }

  /**
   * Get student attendance statistics.
   *
   * @param int $student_nid
   *   The student node ID.
   *
   * @return array
   *   Array with attendance statistics.
   */
  public function getStudentAttendanceStats(int $student_nid): array {
    $records = $this->getStudentAttendance($student_nid);

    $stats = [
      'total' => count($records),
      'present' => 0,
      'absent' => 0,
      'late' => 0,
      'excused' => 0,
      'rate' => 0,
    ];

    foreach ($records as $record) {
      if ($record->hasField('field_attendance_status') && !$record->get('field_attendance_status')->isEmpty()) {
        $status = $record->get('field_attendance_status')->value;
        if (isset($stats[$status])) {
          $stats[$status]++;
        }
      }
    }

    // Calculate attendance rate (present + late counted as attended).
    if ($stats['total'] > 0) {
      $attended = $stats['present'] + $stats['late'];
      $stats['rate'] = round(($attended / $stats['total']) * 100);
    }

    return $stats;
  }

  /**
   * Get Halaqa attendance statistics.
   *
   * @param int $halaqa_nid
   *   The Halaqa node ID.
   *
   * @return array
   *   Array with attendance statistics.
   */
  public function getHalaqaAttendanceStats(int $halaqa_nid): array {
    $storage = $this->entityTypeManager->getStorage('node');

    $query = $storage->getQuery()
      ->condition('type', 'attendance_record')
      ->condition('status', 1)
      ->condition('field_attendance_halaqa', $halaqa_nid)
      ->accessCheck(TRUE);

    $nids = $query->execute();

    $stats = [
      'total' => count($nids),
      'present' => 0,
      'absent' => 0,
      'late' => 0,
      'excused' => 0,
      'rate' => 0,
      'unique_dates' => [],
    ];

    if (empty($nids)) {
      return $stats;
    }

    $records = $storage->loadMultiple($nids);

    foreach ($records as $record) {
      if ($record->hasField('field_attendance_status') && !$record->get('field_attendance_status')->isEmpty()) {
        $status = $record->get('field_attendance_status')->value;
        if (isset($stats[$status])) {
          $stats[$status]++;
        }
      }

      if ($record->hasField('field_attendance_date') && !$record->get('field_attendance_date')->isEmpty()) {
        $date = $record->get('field_attendance_date')->value;
        if (!in_array($date, $stats['unique_dates'])) {
          $stats['unique_dates'][] = $date;
        }
      }
    }

    // Calculate attendance rate.
    if ($stats['total'] > 0) {
      $attended = $stats['present'] + $stats['late'];
      $stats['rate'] = round(($attended / $stats['total']) * 100);
    }

    $stats['session_count'] = count($stats['unique_dates']);

    return $stats;
  }

  /**
   * Save an attendance record.
   *
   * @param int $student_nid
   *   The student node ID.
   * @param int $halaqa_nid
   *   The Halaqa node ID.
   * @param string $date
   *   The date in Y-m-d format.
   * @param string $status
   *   The attendance status (present, absent, late, excused).
   * @param string|null $notes
   *   Optional notes.
   *
   * @return bool
   *   TRUE if saved successfully, FALSE otherwise.
   */
  public function saveAttendanceRecord(int $student_nid, int $halaqa_nid, string $date, string $status, ?string $notes = NULL): bool {
    $storage = $this->entityTypeManager->getStorage('node');

    // Check if record already exists.
    $existing = $this->getExistingAttendanceRecord($student_nid, $halaqa_nid, $date);

    if ($existing) {
      // Update existing record.
      $existing->set('field_attendance_status', $status);
      if ($notes !== NULL) {
        $existing->set('field_attendance_notes', $notes);
      }
      $existing->save();
      return TRUE;
    }

    // Create new record.
    $student = $storage->load($student_nid);
    $student_name = $student ? $student->label() : 'Student';

    $node = $storage->create([
      'type' => 'attendance_record',
      'title' => $this->t('Attendance - @student - @date', [
        '@student' => $student_name,
        '@date' => $date,
      ]),
      'field_attendance_student' => $student_nid,
      'field_attendance_halaqa' => $halaqa_nid,
      'field_attendance_date' => $date,
      'field_attendance_status' => $status,
      'field_attendance_notes' => $notes,
    ]);

    $node->save();

    return TRUE;
  }

  /**
   * Get existing attendance record.
   *
   * @param int $student_nid
   *   The student node ID.
   * @param int $halaqa_nid
   *   The Halaqa node ID.
   * @param string $date
   *   The date in Y-m-d format.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The attendance record node or NULL.
   */
  protected function getExistingAttendanceRecord(int $student_nid, int $halaqa_nid, string $date): ?NodeInterface {
    $storage = $this->entityTypeManager->getStorage('node');

    $query = $storage->getQuery()
      ->condition('type', 'attendance_record')
      ->condition('field_attendance_student', $student_nid)
      ->condition('field_attendance_halaqa', $halaqa_nid)
      ->condition('field_attendance_date', $date)
      ->accessCheck(TRUE)
      ->range(0, 1);

    $nids = $query->execute();

    if (empty($nids)) {
      return NULL;
    }

    return $storage->load(reset($nids));
  }

  /**
   * Check if attendance record exists for a student on a date.
   *
   * @param int $student_nid
   *   The student node ID.
   * @param string $date
   *   The date in Y-m-d format.
   *
   * @return bool
   *   TRUE if record exists, FALSE otherwise.
   */
  public function attendanceRecordExists(int $student_nid, string $date): bool {
    $storage = $this->entityTypeManager->getStorage('node');

    $query = $storage->getQuery()
      ->condition('type', 'attendance_record')
      ->condition('field_attendance_student', $student_nid)
      ->condition('field_attendance_date', $date)
      ->accessCheck(TRUE)
      ->count();

    return $query->execute() > 0;
  }

  /**
   * Get today's attendance count for a Halaqa.
   *
   * @param int $halaqa_nid
   *   The Halaqa node ID.
   *
   * @return array
   *   Array with today's attendance counts.
   */
  public function getTodayAttendance(int $halaqa_nid): array {
    $today = date('Y-m-d');
    $records = $this->getAttendanceByHalaqaAndDate($halaqa_nid, $today);

    $counts = [
      'total' => count($records),
      'present' => 0,
      'absent' => 0,
      'late' => 0,
      'excused' => 0,
      'recorded' => !empty($records),
    ];

    foreach ($records as $record) {
      if ($record->hasField('field_attendance_status') && !$record->get('field_attendance_status')->isEmpty()) {
        $status = $record->get('field_attendance_status')->value;
        if (isset($counts[$status])) {
          $counts[$status]++;
        }
      }
    }

    return $counts;
  }

  /**
   * Get recent attendance records for a Halaqa.
   *
   * @param int $halaqa_nid
   *   The Halaqa node ID.
   * @param int $limit
   *   Number of unique dates to return.
   *
   * @return array
   *   Array of attendance records grouped by date.
   */
  public function getRecentHalaqaAttendance(int $halaqa_nid, int $limit = 7): array {
    $storage = $this->entityTypeManager->getStorage('node');

    $query = $storage->getQuery()
      ->condition('type', 'attendance_record')
      ->condition('status', 1)
      ->condition('field_attendance_halaqa', $halaqa_nid)
      ->accessCheck(TRUE)
      ->sort('field_attendance_date', 'DESC');

    $nids = $query->execute();

    if (empty($nids)) {
      return [];
    }

    $records = $storage->loadMultiple($nids);
    $grouped = [];

    foreach ($records as $record) {
      if ($record->hasField('field_attendance_date') && !$record->get('field_attendance_date')->isEmpty()) {
        $date = $record->get('field_attendance_date')->value;

        if (!isset($grouped[$date])) {
          $grouped[$date] = [
            'date' => $date,
            'records' => [],
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'excused' => 0,
          ];
        }

        $grouped[$date]['records'][] = $record;

        if ($record->hasField('field_attendance_status') && !$record->get('field_attendance_status')->isEmpty()) {
          $status = $record->get('field_attendance_status')->value;
          if (isset($grouped[$date][$status])) {
            $grouped[$date][$status]++;
          }
        }
      }
    }

    // Return only the requested number of dates.
    return array_slice($grouped, 0, $limit, TRUE);
  }

  /**
   * Get recent attendance records for a student (for dashboard display).
   *
   * @param int $student_nid
   *   The student node ID.
   * @param int $limit
   *   Number of records to return.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Array of attendance record nodes.
   */
  public function getRecentStudentAttendance(int $student_nid, int $limit = 5): array {
    $storage = $this->entityTypeManager->getStorage('node');

    $query = $storage->getQuery()
      ->condition('type', 'attendance_record')
      ->condition('status', 1)
      ->condition('field_attendance_student', $student_nid)
      ->accessCheck(TRUE)
      ->sort('field_attendance_date', 'DESC')
      ->range(0, $limit);

    $nids = $query->execute();

    if (empty($nids)) {
      return [];
    }

    return $storage->loadMultiple($nids);
  }

  /**
   * Translate method for service (since services don't extend ControllerBase).
   *
   * @param string $string
   *   The string to translate.
   * @param array $args
   *   Replacement arguments.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The translated string.
   */
  protected function t($string, array $args = []) {
    return new \Drupal\Core\StringTranslation\TranslatableMarkup($string, $args);
  }

}

