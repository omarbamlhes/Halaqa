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

}

