<?php

namespace Drupal\munasabat\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\munasabat\Service\OccasionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an Occasion Countdown block.
 *
 * @Block(
 *   id = "occasion_countdown",
 *   admin_label = @Translation("Occasion Countdown"),
 *   category = @Translation("Munasabat"),
 * )
 */
class OccasionCountdownBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The occasion manager.
   */
  protected OccasionManager $occasionManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, OccasionManager $occasion_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->occasionManager = $occasion_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('munasabat.occasion_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $occasion = $this->occasionManager->getNextOccasion();

    if (!$occasion) {
      return [];
    }

    return [
      '#theme' => 'occasion_countdown',
      '#occasion_name' => $occasion->getName(),
      '#target_date' => $occasion->getStartDate(),
      '#attached' => [
        'library' => ['munasabat/countdown'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 3600;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $tags = parent::getCacheTags();
    $occasion = $this->occasionManager->getNextOccasion();
    if ($occasion) {
      $tags = Cache::mergeTags($tags, $occasion->getCacheTags());
    }
    return $tags;
  }

}
