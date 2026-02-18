<?php

namespace Drupal\munasabat\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\munasabat\Service\OccasionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an Occasion Greeting block.
 *
 * @Block(
 *   id = "occasion_greeting",
 *   admin_label = @Translation("Occasion Greeting"),
 *   category = @Translation("Munasabat"),
 * )
 */
class OccasionGreetingBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The occasion manager.
   */
  protected OccasionManager $occasionManager;

  /**
   * The file URL generator.
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    OccasionManager $occasion_manager,
    FileUrlGeneratorInterface $file_url_generator,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->occasionManager = $occasion_manager;
    $this->fileUrlGenerator = $file_url_generator;
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
      $container->get('file_url_generator'),
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

    $greeting_text = $occasion->getGreetingText();
    if (empty($greeting_text)) {
      return [];
    }

    $logo_url = NULL;
    if (!$occasion->get('logo')->isEmpty() && $occasion->get('logo')->entity) {
      $logo_url = $this->fileUrlGenerator->generateAbsoluteString($occasion->get('logo')->entity->getFileUri());
    }

    return [
      '#theme' => 'occasion_greeting',
      '#occasion_name' => $occasion->getName(),
      '#greeting_text' => $greeting_text,
      '#logo_url' => $logo_url,
      '#primary_color' => $occasion->getPrimaryColor(),
      '#secondary_color' => $occasion->getSecondaryColor(),
      '#attached' => [
        'library' => ['munasabat/greeting'],
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
