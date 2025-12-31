<?php

declare(strict_types=1);

namespace Drupal\halaqa_custom\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Controller for Landing Page.
 */
class LandingPageController extends ControllerBase {

  /**
   * Front page / Landing page.
   *
   * @return array
   *   A render array.
   */
  public function frontPage(): array {
    $logged_in = $this->currentUser()->isAuthenticated();

    $build = [
      '#theme' => 'halaqa_landing_page',
      '#logged_in' => $logged_in,
      '#attached' => [
        'library' => [
          'halaqa_custom/landing_page',
        ],
      ],
      '#cache' => [
        'contexts' => ['user.roles:authenticated'],
      ],
    ];

    return $build;
  }

}

