<?php

namespace Drupal\munasabat\Service;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\munasabat\Entity\OccasionInterface;

/**
 * Service for managing occasions.
 */
class OccasionManager {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The file URL generator.
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * Constructs an OccasionManager.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    FileUrlGeneratorInterface $file_url_generator,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fileUrlGenerator = $file_url_generator;
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

  /**
   * Builds render data for the active occasion.
   *
   * Used by hook_page_top() and blocks to avoid static \Drupal:: calls.
   *
   * @return array|null
   *   Render data array or NULL if no active occasion.
   */
  public function buildActiveOccasionRenderData(): ?array {
    $occasion = $this->getActiveOccasion();

    if (!$occasion) {
      return NULL;
    }

    $banners = [];
    foreach ($occasion->get('banner_images') as $item) {
      if ($item->entity) {
        $banners[] = [
          'url' => $this->fileUrlGenerator->generateAbsoluteString($item->entity->getFileUri()),
          'alt' => $item->alt ?? $occasion->getName(),
        ];
      }
    }

    $logo_url = NULL;
    if (!$occasion->get('logo')->isEmpty() && $occasion->get('logo')->entity) {
      $logo_url = $this->fileUrlGenerator->generateAbsoluteString($occasion->get('logo')->entity->getFileUri());
    }

    return [
      'occasion' => $occasion,
      'name' => $occasion->getName(),
      'type' => $occasion->getType(),
      'greeting_text' => $occasion->getGreetingText(),
      'logo_url' => $logo_url,
      'banners' => $banners,
      'primary_color' => $occasion->getPrimaryColor(),
      'secondary_color' => $occasion->getSecondaryColor(),
      'css_theme' => $occasion->getCssTheme(),
      'effects' => $occasion->getEffects(),
      'cache_tags' => $occasion->getCacheTags(),
    ];
  }

  /**
   * Validates a hex color code.
   *
   * @param string|null $color
   *   The color string to validate.
   *
   * @return bool
   *   TRUE if valid hex color.
   */
  public static function isValidHexColor(?string $color): bool {
    if (empty($color)) {
      return TRUE;
    }
    return (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $color);
  }

}
