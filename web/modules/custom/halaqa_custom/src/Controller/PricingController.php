<?php

declare(strict_types=1);

namespace Drupal\halaqa_custom\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for Pricing page.
 */
class PricingController extends ControllerBase {

  /**
   * Pricing page.
   *
   * @return array
   *   A render array.
   */
  public function pricingPage(): array {
    return [
      '#theme' => 'halaqa_pricing_page',
      '#attached' => [
        'library' => [
          'halaqa_custom/pricing_page',
        ],
      ],
      '#cache' => [
        'contexts' => ['languages'],
      ],
    ];
  }

}
