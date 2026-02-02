<?php

declare(strict_types=1);

namespace Drupal\halaqa_custom\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for About Us page.
 */
class AboutController extends ControllerBase {

  /**
   * About Us page.
   *
   * @return array
   *   A render array.
   */
  public function aboutPage(): array {
    return [
      '#theme' => 'halaqa_about_page',
      '#attached' => [
        'library' => [
          'halaqa_custom/about_page',
        ],
      ],
      '#cache' => [
        'contexts' => ['languages'],
      ],
    ];
  }

}
