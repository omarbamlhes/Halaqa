<?php

namespace Drupal\munasabat\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\munasabat\Service\OccasionManager;

/**
 * Form controller for the Occasion entity add/edit forms.
 */
class OccasionForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Validate hex color codes to prevent CSS injection.
    foreach (['primary_color', 'secondary_color'] as $field) {
      $value = $form_state->getValue([$field, 0, 'value']);
      if (!empty($value) && !OccasionManager::isValidHexColor($value)) {
        $form_state->setErrorByName($field, $this->t('Invalid color format. Use hex format like #006B3F.'));
      }
    }

    // Validate that end date is after start date.
    $start = $form_state->getValue(['date_start', 0, 'value']);
    $end = $form_state->getValue(['date_end', 0, 'value']);
    if ($start && $end && $start > $end) {
      $form_state->setErrorByName('date_end', $this->t('End date must be after start date.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['basic'] = [
      '#type' => 'details',
      '#title' => $this->t('Basic Information'),
      '#open' => TRUE,
      '#weight' => 0,
    ];

    if (isset($form['name'])) {
      $form['name']['#group'] = 'basic';
    }
    if (isset($form['type'])) {
      $form['type']['#group'] = 'basic';
    }

    $form['scheduling'] = [
      '#type' => 'details',
      '#title' => $this->t('Scheduling'),
      '#open' => TRUE,
      '#weight' => 1,
    ];

    if (isset($form['date_start'])) {
      $form['date_start']['#group'] = 'scheduling';
    }
    if (isset($form['date_end'])) {
      $form['date_end']['#group'] = 'scheduling';
    }
    if (isset($form['is_active'])) {
      $form['is_active']['#group'] = 'scheduling';
    }

    $form['media'] = [
      '#type' => 'details',
      '#title' => $this->t('Media'),
      '#open' => TRUE,
      '#weight' => 2,
    ];

    if (isset($form['logo'])) {
      $form['logo']['#group'] = 'media';
    }
    if (isset($form['banner_images'])) {
      $form['banner_images']['#group'] = 'media';
    }

    $form['appearance'] = [
      '#type' => 'details',
      '#title' => $this->t('Appearance'),
      '#open' => TRUE,
      '#weight' => 3,
    ];

    // Override color fields to use HTML5 color picker.
    if (isset($form['primary_color'])) {
      $form['primary_color']['#group'] = 'appearance';
      $form['primary_color']['widget'][0]['value']['#type'] = 'color';
      $form['primary_color']['widget'][0]['value']['#size'] = NULL;
      $form['primary_color']['widget'][0]['value']['#maxlength'] = NULL;
    }
    if (isset($form['secondary_color'])) {
      $form['secondary_color']['#group'] = 'appearance';
      $form['secondary_color']['widget'][0]['value']['#type'] = 'color';
      $form['secondary_color']['widget'][0]['value']['#size'] = NULL;
      $form['secondary_color']['widget'][0]['value']['#maxlength'] = NULL;
    }
    if (isset($form['css_theme'])) {
      $form['css_theme']['#group'] = 'appearance';
    }
    if (isset($form['effects'])) {
      $form['effects']['#group'] = 'appearance';
    }

    $form['content'] = [
      '#type' => 'details',
      '#title' => $this->t('Content'),
      '#open' => TRUE,
      '#weight' => 4,
    ];

    if (isset($form['greeting_text'])) {
      $form['greeting_text']['#group'] = 'content';
    }

    $form['#attached']['library'][] = 'munasabat/admin';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = parent::save($form, $form_state);

    if ($status === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('Occasion %name has been created.', [
        '%name' => $entity->label(),
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('Occasion %name has been updated.', [
        '%name' => $entity->label(),
      ]));
    }

    $form_state->setRedirectUrl($entity->toUrl('collection'));
    return $status;
  }

}
