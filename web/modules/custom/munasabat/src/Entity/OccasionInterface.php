<?php

namespace Drupal\munasabat\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface for the Occasion entity.
 */
interface OccasionInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * Gets the occasion name.
   */
  public function getName(): string;

  /**
   * Sets the occasion name.
   */
  public function setName(string $name): self;

  /**
   * Gets the occasion type.
   */
  public function getType(): string;

  /**
   * Gets the start date.
   */
  public function getStartDate(): ?string;

  /**
   * Gets the end date.
   */
  public function getEndDate(): ?string;

  /**
   * Gets whether the occasion is active.
   */
  public function isActive(): bool;

  /**
   * Sets the active status.
   */
  public function setActive(bool $active): self;

  /**
   * Gets the primary color.
   */
  public function getPrimaryColor(): ?string;

  /**
   * Gets the secondary color.
   */
  public function getSecondaryColor(): ?string;

  /**
   * Gets the CSS theme key.
   */
  public function getCssTheme(): ?string;

  /**
   * Gets the effects key.
   */
  public function getEffects(): ?string;

  /**
   * Gets the greeting text.
   */
  public function getGreetingText(): ?string;

  /**
   * Gets the created timestamp.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the created timestamp.
   */
  public function setCreatedTime(int $timestamp): self;

}
