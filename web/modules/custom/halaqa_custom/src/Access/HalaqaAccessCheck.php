<?php

namespace Drupal\halaqa_custom\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\halaqa_custom\Service\HalaqaStudentService;
use Symfony\Component\Routing\Route;

/**
 * Access check for Halaqa routes.
 */
class HalaqaAccessCheck implements AccessInterface {

  /**
   * The Halaqa student service.
   *
   * @var \Drupal\halaqa_custom\Service\HalaqaStudentService
   */
  protected HalaqaStudentService $halaqaStudentService;

  /**
   * Constructs a HalaqaAccessCheck object.
   *
   * @param \Drupal\halaqa_custom\Service\HalaqaStudentService $halaqa_student_service
   *   The Halaqa student service.
   */
  public function __construct(HalaqaStudentService $halaqa_student_service) {
    $this->halaqaStudentService = $halaqa_student_service;
  }

  /**
   * Checks access for the Halaqa students page.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param int|null $nid
   *   The Halaqa node ID.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, AccountInterface $account, ?int $nid = NULL): AccessResultInterface {
    // User must be logged in.
    if ($account->isAnonymous()) {
      return AccessResult::forbidden('User must be logged in.');
    }

    // Must have teacher role or administrator.
    if (!$account->hasPermission('view halaqa students') &&
        !in_array('teacher', $account->getRoles()) &&
        !in_array('administrator', $account->getRoles())) {
      return AccessResult::forbidden('User does not have teacher role.');
    }

    // If no nid provided, deny access.
    if ($nid === NULL) {
      return AccessResult::forbidden('No Halaqa ID provided.');
    }

    // Check if user has access to this specific Halaqa.
    if ($this->halaqaStudentService->userHasAccessToHalaqa($nid, $account->id())) {
      return AccessResult::allowed()
        ->addCacheContexts(['user'])
        ->addCacheTags(['node:' . $nid]);
    }

    return AccessResult::forbidden('User does not have access to this Halaqa.')
      ->addCacheContexts(['user'])
      ->addCacheTags(['node:' . $nid]);
  }

}

