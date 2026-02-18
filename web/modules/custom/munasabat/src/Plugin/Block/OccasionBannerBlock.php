<?php

namespace Drupal\munasabat\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\munasabat\Service\OccasionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an Occasion Banner block.
 *
 * @Block(
 *   id = "occasion_banner",
 *   admin_label = @Translation("Occasion Banner"),
 *   category = @Translation("Munasabat"),
 * )
 */
class OccasionBannerBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
    $occasion = $this->occasionManager->getActiveOccasion();

    if (!$occasion) {
      return [];
    }

    $banners = [];
    foreach ($occasion->get('banner_images') as $item) {
      if ($item->entity) {
        $banners[] = [
          'url' => \Drupal::service('file_url_generator')->generateAbsoluteString($item->entity->getFileUri()),
          'alt' => $item->alt ?? $occasion->getName(),
        ];
      }
    }

    $logo_url = NULL;
    if (!$occasion->get('logo')->isEmpty() && $occasion->get('logo')->entity) {
      $logo_url = \Drupal::service('file_url_generator')->generateAbsoluteString($occasion->get('logo')->entity->getFileUri());
    }

    return [
      '#theme' => 'occasion_banner',
      '#occasion_name' => $occasion->getName(),
      '#banners' => $banners,
      '#logo_url' => $logo_url,
      '#primary_color' => $occasion->getPrimaryColor(),
      '#secondary_color' => $occasion->getSecondaryColor(),
      '#attached' => [
        'library' => ['munasabat/banner'],
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
    $occasion = $this->occasionManager->getActiveOccasion();
    if ($occasion) {
      $tags = Cache::mergeTags($tags, $occasion->getCacheTags());
    }
    return $tags;
  }

}
