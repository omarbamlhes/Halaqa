<?php

namespace Drupal\munasabat;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a listing of Occasion entities.
 */
class OccasionListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Name');
    $header['type'] = $this->t('Type');
    $header['date_start'] = $this->t('Start Date');
    $header['date_end'] = $this->t('End Date');
    $header['is_active'] = $this->t('Status');
    $header['css_theme'] = $this->t('Theme');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\munasabat\Entity\OccasionInterface $entity */
    $row['name'] = $entity->toLink();
    $row['type'] = $entity->get('type')->value;
    $row['date_start'] = $entity->getStartDate();
    $row['date_end'] = $entity->getEndDate();
    $row['is_active'] = $entity->isActive() ? $this->t('Active') : $this->t('Inactive');
    $row['css_theme'] = $entity->getCssTheme();
    return $row + parent::buildRow($entity);
  }

}
