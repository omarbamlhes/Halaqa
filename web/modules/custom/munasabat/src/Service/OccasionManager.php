<?php

namespace Drupal\munasabat\Service;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\munasabat\Entity\OccasionInterface;

/**
 * Service for managing occasions.
 */
class OccasionManager {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs an OccasionManager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Gets the currently active occasion.
   *
   * @return \Drupal\munasabat\Entity\OccasionInterface|null
   *   The active occasion, or NULL if none is active.
   */
  public function getActiveOccasion(): ?OccasionInterface {
    $storage = $this->entityTypeManager->getStorage('occasion');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('is_active', 1)
      ->sort('date_start', 'ASC')
      ->range(0, 1)
      ->execute();

    if (empty($ids)) {
      return NULL;
    }

    return $storage->load(reset($ids));
  }

  /**
   * Checks if any occasion is currently active.
   *
   * @return bool
   *   TRUE if an occasion is active.
   */
  public function isOccasionActive(): bool {
    return $this->getActiveOccasion() !== NULL;
  }

  /**
   * Gets upcoming occasions.
   *
   * @param int $limit
   *   Maximum number of occasions to return.
   *
   * @return \Drupal\munasabat\Entity\OccasionInterface[]
   *   Array of upcoming occasions.
   */
  public function getUpcomingOccasions(int $limit = 5): array {
    $today = new DrupalDateTime('now');
    $today_str = $today->format('Y-m-d');

    $storage = $this->entityTypeManager->getStorage('occasion');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('date_start', $today_str, '>')
      ->sort('date_start', 'ASC')
      ->range(0, $limit)
      ->execute();

    if (empty($ids)) {
      return [];
    }

    return $storage->loadMultiple($ids);
  }

  /**
   * Gets the next upcoming occasion.
   *
   * @return \Drupal\munasabat\Entity\OccasionInterface|null
   *   The next occasion, or NULL if none upcoming.
   */
  public function getNextOccasion(): ?OccasionInterface {
    $occasions = $this->getUpcomingOccasions(1);
    return !empty($occasions) ? reset($occasions) : NULL;
  }

  /**
   * Activates/deactivates occasions based on their date range.
   *
   * Called by cron to auto-toggle the is_active field.
   */
  public function activateBySchedule(): void {
    $today = new DrupalDateTime('now');
    $today_str = $today->format('Y-m-d');

    $storage = $this->entityTypeManager->getStorage('occasion');

    // Activate occasions whose date range includes today.
    $to_activate = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('is_active', 0)
      ->condition('date_start', $today_str, '<=')
      ->condition('date_end', $today_str, '>=')
      ->execute();

    foreach ($storage->loadMultiple($to_activate) as $occasion) {
      /** @var \Drupal\munasabat\Entity\OccasionInterface $occasion */
      $occasion->setActive(TRUE);
      $occasion->save();
    }

    // Deactivate occasions whose date range has passed.
    $to_deactivate = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('is_active', 1)
      ->condition('date_end', $today_str, '<')
      ->execute();

    foreach ($storage->loadMultiple($to_deactivate) as $occasion) {
      /** @var \Drupal\munasabat\Entity\OccasionInterface $occasion */
      $occasion->setActive(FALSE);
      $occasion->save();
    }
  }

}
