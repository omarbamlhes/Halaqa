<?php

namespace Drupal\halaqa_custom;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Helper service for Halaqa Custom module.
 */
class HalaqaCustomHelper {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a HalaqaCustomHelper object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Example helper method.
   *
   * @param string $entity_type
   *   The entity type ID.
   *
   * @return array
   *   An array of entity IDs.
   */
  public function getEntityIds(string $entity_type): array {
    $storage = $this->entityTypeManager->getStorage($entity_type);
    return $storage->getQuery()
      ->accessCheck(TRUE)
      ->execute();
  }

}

