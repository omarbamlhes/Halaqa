<?php

namespace Drupal\halaqa_custom\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for Halaqa Custom module.
 */
class HalaqaCustomController extends ControllerBase {

  /**
   * Example page callback.
   *
   * @return array
   *   A render array.
   */
  public function example(): array {
    return [
      '#markup' => $this->t('Welcome to Halaqa Custom module!'),
    ];
  }

}

