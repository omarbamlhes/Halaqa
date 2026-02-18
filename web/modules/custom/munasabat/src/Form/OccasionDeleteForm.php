<?php

namespace Drupal\munasabat\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;

/**
 * Provides a confirmation form for deleting an Occasion entity.
 */
class OccasionDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete occasion %name?', [
      '%name' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->entity->toUrl('collection');
  }

}
