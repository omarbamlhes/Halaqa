<?php

namespace Drupal\halaqa_custom\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\halaqa_custom\Service\NotificationService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for notification pages.
 */
class NotificationController extends ControllerBase {

  /**
   * The notification service.
   *
   * @var \Drupal\halaqa_custom\Service\NotificationService
   */
  protected NotificationService $notificationService;

  /**
   * Constructs a NotificationController object.
   *
   * @param \Drupal\halaqa_custom\Service\NotificationService $notification_service
   *   The notification service.
   */
  public function __construct(NotificationService $notification_service) {
    $this->notificationService = $notification_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('halaqa_custom.notification_service')
    );
  }

  /**
   * Display all notifications for the current user.
   *
   * @return array
   *   A render array.
   */
  public function notificationsPage(): array {
    $notifications = $this->notificationService->getUserNotifications(NULL, FALSE, 50);
    $unread_count = $this->notificationService->getUnreadCount();

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['notifications-page']],
      '#attached' => [
        'library' => ['halaqa_theme/notifications'],
      ],
    ];

    // Page header.
    $build['header'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['notifications-page-header']],
      'title' => [
        '#markup' => '<h2>' . $this->t('Notifications') . '</h2>',
      ],
    ];

    if ($unread_count > 0) {
      $build['header']['mark_all'] = [
        '#type' => 'link',
        '#title' => $this->t('Mark all as read'),
        '#url' => Url::fromRoute('halaqa_custom.notifications_mark_all_read'),
        '#attributes' => ['class' => ['mark-all-read-btn']],
      ];
    }

    // Notifications list.
    $build['notifications'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['notifications-list']],
    ];

    if (empty($notifications)) {
      $build['notifications']['empty'] = [
        '#markup' => '<div class="no-notifications">
          <span class="no-notifications-icon">🔔</span>
          <p>' . $this->t('No notifications yet.') . '</p>
        </div>',
      ];
    }
    else {
      foreach ($notifications as $index => $notification) {
        $type = '';
        $message = '';
        $link = NULL;
        $is_read = FALSE;
        $created = $notification->getCreatedTime();

        if ($notification->hasField('field_notification_type') && !$notification->get('field_notification_type')->isEmpty()) {
          $type = $notification->get('field_notification_type')->value;
        }

        if ($notification->hasField('field_notification_message') && !$notification->get('field_notification_message')->isEmpty()) {
          $message = $notification->get('field_notification_message')->value;
        }

        if ($notification->hasField('field_notification_link') && !$notification->get('field_notification_link')->isEmpty()) {
          $link = $notification->get('field_notification_link')->uri;
        }

        if ($notification->hasField('field_notification_read') && !$notification->get('field_notification_read')->isEmpty()) {
          $is_read = (bool) $notification->get('field_notification_read')->value;
        }

        $icon = $this->notificationService->getNotificationIcon($type);
        $color_class = $this->notificationService->getNotificationColor($type);
        $read_class = $is_read ? 'notification-read' : 'notification-unread';
        $time_ago = $this->timeAgo($created);

        $notification_content = '<div class="notification-icon">' . $icon . '</div>
          <div class="notification-content">
            <div class="notification-message">' . $message . '</div>
            <div class="notification-time">' . $time_ago . '</div>
          </div>';

        if (!$is_read) {
          $notification_content .= '<div class="notification-actions">
            <a href="' . Url::fromRoute('halaqa_custom.notification_mark_read', ['nid' => $notification->id()])->toString() . '" class="mark-read-btn" title="' . $this->t('Mark as read') . '">✓</a>
          </div>';
        }

        if ($link) {
          $internal_link = str_replace('internal:', '', $link);
          $build['notifications']['notification_' . $index] = [
            '#markup' => '<a href="' . $internal_link . '" class="notification-item ' . $color_class . ' ' . $read_class . '">' . $notification_content . '</a>',
          ];
        }
        else {
          $build['notifications']['notification_' . $index] = [
            '#markup' => '<div class="notification-item ' . $color_class . ' ' . $read_class . '">' . $notification_content . '</div>',
          ];
        }
      }
    }

    // Cache settings.
    $build['#cache'] = [
      'tags' => ['node_list:notification'],
      'contexts' => ['user'],
      'max-age' => 0,
    ];

    return $build;
  }

  /**
   * Get notifications dropdown content (AJAX).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with notifications.
   */
  public function getNotificationsAjax(): JsonResponse {
    $notifications = $this->notificationService->getUserNotifications(NULL, FALSE, 10);
    $unread_count = $this->notificationService->getUnreadCount();

    $items = [];
    foreach ($notifications as $notification) {
      $type = '';
      $message = '';
      $link = NULL;
      $is_read = FALSE;
      $created = $notification->getCreatedTime();

      if ($notification->hasField('field_notification_type') && !$notification->get('field_notification_type')->isEmpty()) {
        $type = $notification->get('field_notification_type')->value;
      }

      if ($notification->hasField('field_notification_message') && !$notification->get('field_notification_message')->isEmpty()) {
        $message = $notification->get('field_notification_message')->value;
      }

      if ($notification->hasField('field_notification_link') && !$notification->get('field_notification_link')->isEmpty()) {
        $link = $notification->get('field_notification_link')->uri;
        $link = str_replace('internal:', '', $link);
      }

      if ($notification->hasField('field_notification_read') && !$notification->get('field_notification_read')->isEmpty()) {
        $is_read = (bool) $notification->get('field_notification_read')->value;
      }

      $items[] = [
        'id' => $notification->id(),
        'type' => $type,
        'message' => $message,
        'link' => $link,
        'is_read' => $is_read,
        'icon' => $this->notificationService->getNotificationIcon($type),
        'color' => $this->notificationService->getNotificationColor($type),
        'time_ago' => $this->timeAgo($created),
      ];
    }

    return new JsonResponse([
      'unread_count' => $unread_count,
      'notifications' => $items,
    ]);
  }

  /**
   * Get unread count (AJAX).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with unread count.
   */
  public function getUnreadCountAjax(): JsonResponse {
    $unread_count = $this->notificationService->getUnreadCount();

    return new JsonResponse([
      'unread_count' => $unread_count,
    ]);
  }

  /**
   * Mark a notification as read.
   *
   * @param int $nid
   *   The notification node ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse|array
   *   JSON response or redirect.
   */
  public function markAsRead(int $nid, Request $request) {
    $result = $this->notificationService->markAsRead($nid);

    if ($request->isXmlHttpRequest()) {
      return new JsonResponse(['success' => $result]);
    }

    // Redirect back to notifications page.
    return $this->redirect('halaqa_custom.notifications');
  }

  /**
   * Mark all notifications as read.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse|array
   *   JSON response or redirect.
   */
  public function markAllAsRead(Request $request) {
    $count = $this->notificationService->markAllAsRead();

    if ($request->isXmlHttpRequest()) {
      return new JsonResponse(['success' => TRUE, 'count' => $count]);
    }

    $this->messenger()->addStatus($this->t('@count notifications marked as read.', ['@count' => $count]));

    return $this->redirect('halaqa_custom.notifications');
  }

  /**
   * Calculate time ago string.
   *
   * @param int $timestamp
   *   The Unix timestamp.
   *
   * @return string
   *   Human-readable time ago string.
   */
  protected function timeAgo(int $timestamp): string {
    $diff = time() - $timestamp;

    if ($diff < 60) {
      return $this->t('Just now');
    }
    elseif ($diff < 3600) {
      $minutes = floor($diff / 60);
      return $this->t('@count min ago', ['@count' => $minutes]);
    }
    elseif ($diff < 86400) {
      $hours = floor($diff / 3600);
      return $this->t('@count hour ago', ['@count' => $hours]);
    }
    elseif ($diff < 604800) {
      $days = floor($diff / 86400);
      return $this->t('@count day ago', ['@count' => $days]);
    }
    else {
      return date('Y-m-d', $timestamp);
    }
  }

}
