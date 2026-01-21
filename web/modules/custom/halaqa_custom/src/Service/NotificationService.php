<?php

namespace Drupal\halaqa_custom\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Service for managing notifications.
 */
class NotificationService {

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
   * Constructs a NotificationService object.
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
   * Create a notification.
   *
   * @param int $user_id
   *   The recipient user ID.
   * @param string $type
   *   The notification type.
   * @param string $message
   *   The notification message.
   * @param string|null $link
   *   Optional link URL.
   * @param int|null $related_nid
   *   Optional related node ID.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The created notification node or NULL on failure.
   */
  public function createNotification(int $user_id, string $type, string $message, ?string $link = NULL, ?int $related_nid = NULL): ?NodeInterface {
    try {
      $storage = $this->entityTypeManager->getStorage('node');

      $data = [
        'type' => 'notification',
        'title' => $this->truncateMessage($message, 100),
        'field_notification_user' => $user_id,
        'field_notification_type' => $type,
        'field_notification_message' => $message,
        'field_notification_read' => FALSE,
        'status' => 1,
      ];

      if ($link) {
        $data['field_notification_link'] = ['uri' => $link];
      }

      if ($related_nid) {
        $data['field_notification_related'] = $related_nid;
      }

      $node = $storage->create($data);
      $node->save();

      return $node;
    }
    catch (\Exception $e) {
      \Drupal::logger('halaqa_custom')->error('Failed to create notification: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Get notifications for a user.
   *
   * @param int|null $user_id
   *   The user ID. Defaults to current user.
   * @param bool $unread_only
   *   Whether to get only unread notifications.
   * @param int $limit
   *   Maximum number of notifications to return.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Array of notification nodes.
   */
  public function getUserNotifications(?int $user_id = NULL, bool $unread_only = FALSE, int $limit = 20): array {
    $user_id = $user_id ?? $this->currentUser->id();

    $storage = $this->entityTypeManager->getStorage('node');

    $query = $storage->getQuery()
      ->condition('type', 'notification')
      ->condition('status', 1)
      ->condition('field_notification_user', $user_id)
      ->accessCheck(TRUE)
      ->sort('created', 'DESC')
      ->range(0, $limit);

    if ($unread_only) {
      $query->condition('field_notification_read', 0);
    }

    $nids = $query->execute();

    if (empty($nids)) {
      return [];
    }

    return $storage->loadMultiple($nids);
  }

  /**
   * Get unread notification count for a user.
   *
   * @param int|null $user_id
   *   The user ID. Defaults to current user.
   *
   * @return int
   *   Number of unread notifications.
   */
  public function getUnreadCount(?int $user_id = NULL): int {
    $user_id = $user_id ?? $this->currentUser->id();

    $storage = $this->entityTypeManager->getStorage('node');

    $query = $storage->getQuery()
      ->condition('type', 'notification')
      ->condition('status', 1)
      ->condition('field_notification_user', $user_id)
      ->condition('field_notification_read', 0)
      ->accessCheck(TRUE)
      ->count();

    return (int) $query->execute();
  }

  /**
   * Mark a notification as read.
   *
   * @param int $notification_nid
   *   The notification node ID.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  public function markAsRead(int $notification_nid): bool {
    $storage = $this->entityTypeManager->getStorage('node');
    $notification = $storage->load($notification_nid);

    if (!$notification || $notification->bundle() !== 'notification') {
      return FALSE;
    }

    // Check if user owns this notification.
    if ($notification->get('field_notification_user')->target_id != $this->currentUser->id()) {
      return FALSE;
    }

    $notification->set('field_notification_read', TRUE);
    $notification->save();

    return TRUE;
  }

  /**
   * Mark all notifications as read for a user.
   *
   * @param int|null $user_id
   *   The user ID. Defaults to current user.
   *
   * @return int
   *   Number of notifications marked as read.
   */
  public function markAllAsRead(?int $user_id = NULL): int {
    $user_id = $user_id ?? $this->currentUser->id();

    $notifications = $this->getUserNotifications($user_id, TRUE, 100);
    $count = 0;

    foreach ($notifications as $notification) {
      $notification->set('field_notification_read', TRUE);
      $notification->save();
      $count++;
    }

    return $count;
  }

  /**
   * Delete old notifications.
   *
   * @param int $days
   *   Delete notifications older than this many days.
   *
   * @return int
   *   Number of notifications deleted.
   */
  public function deleteOldNotifications(int $days = 30): int {
    $storage = $this->entityTypeManager->getStorage('node');

    $cutoff = strtotime("-{$days} days");

    $query = $storage->getQuery()
      ->condition('type', 'notification')
      ->condition('created', $cutoff, '<')
      ->accessCheck(FALSE);

    $nids = $query->execute();

    if (empty($nids)) {
      return 0;
    }

    $nodes = $storage->loadMultiple($nids);
    $count = count($nodes);
    $storage->delete($nodes);

    return $count;
  }

  /**
   * Create absence notification for parent.
   *
   * @param int $student_nid
   *   The student node ID.
   * @param string $date
   *   The absence date.
   *
   * @return bool
   *   TRUE if notification was created.
   */
  public function notifyParentOfAbsence(int $student_nid, string $date): bool {
    $student = $this->entityTypeManager->getStorage('node')->load($student_nid);

    if (!$student || $student->bundle() !== 'student') {
      return FALSE;
    }

    // Get parent (student owner).
    $parent_id = $student->getOwnerId();

    if (!$parent_id || $parent_id == 0) {
      return FALSE;
    }

    $student_name = $student->label();
    $message = $this->t('Your child @name was absent on @date', [
      '@name' => $student_name,
      '@date' => $date,
    ]);

    $link = 'internal:/student/' . $student_nid . '/attendance';

    $notification = $this->createNotification(
      $parent_id,
      'absence',
      (string) $message,
      $link,
      $student_nid
    );

    return $notification !== NULL;
  }

  /**
   * Create late notification for parent.
   *
   * @param int $student_nid
   *   The student node ID.
   * @param string $date
   *   The date.
   *
   * @return bool
   *   TRUE if notification was created.
   */
  public function notifyParentOfLate(int $student_nid, string $date): bool {
    $student = $this->entityTypeManager->getStorage('node')->load($student_nid);

    if (!$student || $student->bundle() !== 'student') {
      return FALSE;
    }

    $parent_id = $student->getOwnerId();

    if (!$parent_id || $parent_id == 0) {
      return FALSE;
    }

    $student_name = $student->label();
    $message = $this->t('Your child @name was late on @date', [
      '@name' => $student_name,
      '@date' => $date,
    ]);

    $link = 'internal:/student/' . $student_nid . '/attendance';

    $notification = $this->createNotification(
      $parent_id,
      'late',
      (string) $message,
      $link,
      $student_nid
    );

    return $notification !== NULL;
  }

  /**
   * Create new memorization record notification.
   *
   * @param int $student_nid
   *   The student node ID.
   * @param string $evaluation
   *   The evaluation result.
   * @param int|null $record_nid
   *   The memorization record node ID.
   *
   * @return bool
   *   TRUE if notification was created.
   */
  public function notifyParentOfNewRecord(int $student_nid, string $evaluation, ?int $record_nid = NULL): bool {
    $student = $this->entityTypeManager->getStorage('node')->load($student_nid);

    if (!$student || $student->bundle() !== 'student') {
      return FALSE;
    }

    $parent_id = $student->getOwnerId();

    if (!$parent_id || $parent_id == 0) {
      return FALSE;
    }

    $student_name = $student->label();
    $eval_labels = [
      'excellent' => $this->t('Excellent'),
      'very_good' => $this->t('Very Good'),
      'good' => $this->t('Good'),
      'acceptable' => $this->t('Acceptable'),
      'needs_improvement' => $this->t('Needs Improvement'),
    ];

    $eval_text = $eval_labels[$evaluation] ?? $evaluation;

    $message = $this->t('New memorization record for @name: @eval', [
      '@name' => $student_name,
      '@eval' => $eval_text,
    ]);

    $link = 'internal:/student/' . $student_nid . '/progress';

    $type = ($evaluation === 'excellent') ? 'excellent' : 'new_record';

    $notification = $this->createNotification(
      $parent_id,
      $type,
      (string) $message,
      $link,
      $record_nid ?? $student_nid
    );

    return $notification !== NULL;
  }

  /**
   * Check and notify for repeated absence.
   *
   * @param int $student_nid
   *   The student node ID.
   * @param int $threshold
   *   Number of absences to trigger warning.
   *
   * @return bool
   *   TRUE if warning notification was created.
   */
  public function checkAndNotifyRepeatedAbsence(int $student_nid, int $threshold = 3): bool {
    $storage = $this->entityTypeManager->getStorage('node');

    // Count recent absences (last 30 days).
    $cutoff = date('Y-m-d', strtotime('-30 days'));

    $query = $storage->getQuery()
      ->condition('type', 'attendance_record')
      ->condition('status', 1)
      ->condition('field_attendance_student', $student_nid)
      ->condition('field_attendance_status', 'absent')
      ->condition('field_attendance_date', $cutoff, '>=')
      ->accessCheck(TRUE)
      ->count();

    $absence_count = (int) $query->execute();

    if ($absence_count < $threshold) {
      return FALSE;
    }

    $student = $storage->load($student_nid);
    if (!$student || $student->bundle() !== 'student') {
      return FALSE;
    }

    $parent_id = $student->getOwnerId();
    if (!$parent_id || $parent_id == 0) {
      return FALSE;
    }

    // Check if we already sent a warning recently.
    $recent_warning = $storage->getQuery()
      ->condition('type', 'notification')
      ->condition('field_notification_user', $parent_id)
      ->condition('field_notification_type', 'repeated_absence')
      ->condition('field_notification_related', $student_nid)
      ->condition('created', strtotime('-7 days'), '>=')
      ->accessCheck(TRUE)
      ->count()
      ->execute();

    if ($recent_warning > 0) {
      return FALSE;
    }

    $student_name = $student->label();
    $message = $this->t('Warning: @name has been absent @count times in the last 30 days', [
      '@name' => $student_name,
      '@count' => $absence_count,
    ]);

    $link = 'internal:/student/' . $student_nid . '/attendance';

    $notification = $this->createNotification(
      $parent_id,
      'repeated_absence',
      (string) $message,
      $link,
      $student_nid
    );

    // Also notify teacher.
    if ($student->hasField('field_halaqa') && !$student->get('field_halaqa')->isEmpty()) {
      $halaqa = $student->get('field_halaqa')->entity;
      if ($halaqa && $halaqa->hasField('field_teacher') && !$halaqa->get('field_teacher')->isEmpty()) {
        $teacher = $halaqa->get('field_teacher')->entity;
        if ($teacher) {
          $teacher_user_id = $teacher->getOwnerId();
          if ($teacher_user_id) {
            $this->createNotification(
              $teacher_user_id,
              'repeated_absence',
              (string) $message,
              $link,
              $student_nid
            );
          }
        }
      }
    }

    return $notification !== NULL;
  }

  /**
   * Get notification icon by type.
   *
   * @param string $type
   *   The notification type.
   *
   * @return string
   *   The icon emoji.
   */
  public function getNotificationIcon(string $type): string {
    $icons = [
      'absence' => '❌',
      'late' => '⏰',
      'new_record' => '📝',
      'excellent' => '⭐',
      'repeated_absence' => '⚠️',
      'general' => '🔔',
    ];

    return $icons[$type] ?? '🔔';
  }

  /**
   * Get notification color by type.
   *
   * @param string $type
   *   The notification type.
   *
   * @return string
   *   The CSS color class.
   */
  public function getNotificationColor(string $type): string {
    $colors = [
      'absence' => 'notification-danger',
      'late' => 'notification-warning',
      'new_record' => 'notification-info',
      'excellent' => 'notification-success',
      'repeated_absence' => 'notification-danger',
      'general' => 'notification-default',
    ];

    return $colors[$type] ?? 'notification-default';
  }

  /**
   * Truncate message to a maximum length.
   *
   * @param string $message
   *   The message to truncate.
   * @param int $max_length
   *   Maximum length.
   *
   * @return string
   *   Truncated message.
   */
  protected function truncateMessage(string $message, int $max_length): string {
    if (mb_strlen($message) <= $max_length) {
      return $message;
    }

    return mb_substr($message, 0, $max_length - 3) . '...';
  }

  /**
   * Translate helper.
   *
   * @param string $string
   *   The string to translate.
   * @param array $args
   *   Replacement arguments.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The translated string.
   */
  protected function t(string $string, array $args = []): TranslatableMarkup {
    return new TranslatableMarkup($string, $args);
  }

}
