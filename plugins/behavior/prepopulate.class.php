<?php

class EntityReferencePrepopulateInstanceBehavior extends EntityReference_BehaviorHandler_Abstract {

  /**
   * Generate a settings form for this handler.
   */
  public function settingsForm() {
    $form['hide'] = array(
      '#type' => 'checkbox',
      '#title' => t('Hide field'),
      '#description' => t('Hide the field when prepopulating field with values via URL.'),
    );
    $form['fallback'] = array(
      '#type' => 'select',
      '#title' => t('Fallback behaviour'),
      '#description' => t('Determine what should happen if no values are provided via URL.'),
      '#options' => array(
        'none' => t('Do nothing'),
        'hide' => t('Hide field'),
        'error' => t('Set form error'),
        'widget' => t('Replace widget'),
        'redirect' => t('Redirect'),
      ),
    );
    $form['widget'] = array(
      '#type' => 'select',
      '#title' => t('Widget'),
      '#description' => t('Determine what widget is used when using fallback.'),
      '#options' => array(),
      '#default_value' => isset($field['settings']['handler_settings']['widget']) ? $field['settings']['handler_settings']['widget'] : 'none',
    );
    return $form;
  }
}