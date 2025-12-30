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
    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    if ($user && $user->hasRole('administrator')) {
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

}

