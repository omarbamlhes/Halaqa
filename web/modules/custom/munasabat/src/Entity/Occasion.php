<?php

namespace Drupal\munasabat\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Occasion entity.
 *
 * @ContentEntityType(
 *   id = "occasion",
 *   label = @Translation("Occasion"),
 *   label_collection = @Translation("Occasions"),
 *   label_singular = @Translation("occasion"),
 *   label_plural = @Translation("occasions"),
 *   handlers = {
 *     "view_builder" = "Drupal\munasabat\OccasionViewBuilder",
 *     "list_builder" = "Drupal\munasabat\OccasionListBuilder",
 *     "form" = {
 *       "default" = "Drupal\munasabat\Form\OccasionForm",
 *       "add" = "Drupal\munasabat\Form\OccasionForm",
 *       "edit" = "Drupal\munasabat\Form\OccasionForm",
 *       "delete" = "Drupal\munasabat\Form\OccasionDeleteForm",
 *     },
 *     "access" = "Drupal\munasabat\OccasionAccessControlHandler",
 *   },
 *   base_table = "occasion",
 *   admin_permission = "administer munasabat",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "langcode" = "langcode",
 *     "label" = "name",
 *   },
 *   links = {
 *     "canonical" = "/admin/munasabat/{occasion}",
 *     "add-form" = "/admin/munasabat/add",
 *     "edit-form" = "/admin/munasabat/{occasion}/edit",
 *     "delete-form" = "/admin/munasabat/{occasion}/delete",
 *     "collection" = "/admin/munasabat",
 *   },
 * )
 */
class Occasion extends ContentEntityBase implements OccasionInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return $this->get('name')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setName(string $name): self {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return $this->get('type')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getStartDate(): ?string {
    return $this->get('date_start')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getEndDate(): ?string {
    return $this->get('date_end')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function isActive(): bool {
    return (bool) $this->get('is_active')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setActive(bool $active): self {
    $this->set('is_active', $active);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPrimaryColor(): ?string {
    return $this->get('primary_color')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getSecondaryColor(): ?string {
    return $this->get('secondary_color')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getCssTheme(): ?string {
    return $this->get('css_theme')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getEffects(): ?string {
    return $this->get('effects')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getGreetingText(): ?string {
    return $this->get('greeting_text')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return (int) $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime(int $timestamp): self {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the occasion.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => 0,
      ]);

    $fields['type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Type'))
      ->setDescription(t('The type of occasion.'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values', [
        'national_day' => t('National Day'),
        'founding_day' => t('Founding Day'),
        'ramadan' => t('Ramadan'),
        'eid_fitr' => t('Eid Al-Fitr'),
        'eid_adha' => t('Eid Al-Adha'),
        'hajj' => t('Hajj Season'),
        'custom' => t('Custom'),
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 1,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'list_default',
        'weight' => 1,
      ]);

    $fields['date_start'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Start Date'))
      ->setDescription(t('The start date of the occasion.'))
      ->setRequired(TRUE)
      ->setSetting('datetime_type', 'date')
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
        'weight' => 2,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'datetime_default',
        'weight' => 2,
      ]);

    $fields['date_end'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('End Date'))
      ->setDescription(t('The end date of the occasion.'))
      ->setRequired(TRUE)
      ->setSetting('datetime_type', 'date')
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
        'weight' => 3,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'datetime_default',
        'weight' => 3,
      ]);

    $fields['is_active'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Active'))
      ->setDescription(t('Whether this occasion is currently active.'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 4,
      ]);

    $fields['logo'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Logo'))
      ->setDescription(t('The occasion logo.'))
      ->setSetting('file_directory', 'munasabat/logos')
      ->setSetting('file_extensions', 'png jpg jpeg svg webp')
      ->setSetting('alt_field', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'image_image',
        'weight' => 5,
      ])
      ->setDisplayOptions('view', [
        'type' => 'image',
        'weight' => 5,
        'label' => 'hidden',
      ]);

    $fields['banner_images'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Banner Images'))
      ->setDescription(t('Banner images for the occasion.'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setSetting('file_directory', 'munasabat/banners')
      ->setSetting('file_extensions', 'png jpg jpeg svg webp')
      ->setSetting('alt_field', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'image_image',
        'weight' => 6,
      ])
      ->setDisplayOptions('view', [
        'type' => 'image',
        'weight' => 6,
        'label' => 'hidden',
      ]);

    $fields['primary_color'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Primary Color'))
      ->setDescription(t('Primary color hex code (e.g. #006B3F).'))
      ->setSetting('max_length', 7)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 7,
      ]);

    $fields['secondary_color'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Secondary Color'))
      ->setDescription(t('Secondary color hex code (e.g. #FFFFFF).'))
      ->setSetting('max_length', 7)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 8,
      ]);

    $fields['css_theme'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('CSS Theme'))
      ->setDescription(t('Pre-built CSS theme to apply.'))
      ->setSetting('allowed_values', [
        'national_day' => t('National Day'),
        'founding_day' => t('Founding Day'),
        'ramadan' => t('Ramadan'),
        'eid' => t('Eid'),
        'none' => t('None (custom colors only)'),
      ])
      ->setDefaultValue('none')
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 9,
      ]);

    $fields['effects'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Visual Effects'))
      ->setDescription(t('Visual effect to display.'))
      ->setSetting('allowed_values', [
        'none' => t('None'),
        'confetti' => t('Confetti'),
        'crescent' => t('Crescent & Stars'),
      ])
      ->setDefaultValue('none')
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 10,
      ]);

    $fields['greeting_text'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Greeting Text'))
      ->setDescription(t('Greeting message to display during the occasion.'))
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 11,
      ])
      ->setDisplayOptions('view', [
        'type' => 'basic_string',
        'weight' => 11,
        'label' => 'above',
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the occasion was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the occasion was last edited.'));

    return $fields;
  }

}
