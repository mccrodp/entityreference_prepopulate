<?php

class EntityReferencePrepopulateInstanceBehavior extends EntityReference_BehaviorHandler_Abstract {

  /**
   * Generate a settings form for this handler.
   */
  public function settingsForm($field, $instance) {
    $form['action'] = array(
      '#type' => 'select',
      '#title' => t('Action'),
      '#options' => array(
        'none' => t('Do nothing'),
        'hide' => t('Hide field'),
        'disable' => t('Disable field'),
      ),
      '#description' => t('Action to take when prepopulating field with values via URL.'),
    );
    $form['action_on_edit'] = array(
      '#type' => 'checkbox',
      '#title' => t('Apply action on edit'),
      '#description' => t('Apply action when editing an existing entity.'),
      '#states' => array(
        'invisible' => array(
          ':input[name="instance[settings][behaviors][prepopulate][action]"]' => array('value' => 'none'),
        ),
      ),
    );
    $form['fallback'] = array(
      '#type' => 'select',
      '#title' => t('Fallback behaviour'),
      '#description' => t('Determine what should happen if no values are provided via URL.'),
      '#options' => array(
        'none' => t('Do nothing'),
        'hide' => t('Hide field'),
        'form_error' => t('Set form error'),
        'redirect' => t('Redirect'),
      ),
    );

    // Get list of permissions.
    $perms = array();
    $perms[0] = t('- None -');
    foreach (module_list(FALSE, FALSE, TRUE) as $module) {
      // By keeping them keyed by module we can use optgroups with the
      // 'select' type.
      if ($permissions = \Drupal::moduleHandler()->invoke($module, 'permission')) {
        foreach ($permissions as $id => $permission) {
          $perms[$module][$id] = strip_tags($permission['title']);
        }
      }
    }

    $form['skip_perm'] = array(
      '#type' => 'select',
      '#title' => t('Skip access permission'),
      '#description' => t('Set a permission that will not be affected by the fallback behavior.'),
      '#options' => $perms,
    );

    $form['providers'] = array(
      '#type' => 'container',
      '#theme' => 'entityreference_prepopulate_providers_table',
      '#element_validate' => array('entityreference_prepopulate_providers_validate'),
    );

    $providers = entityreference_prepopulate_providers_info();

    // Sort providers by weight.
    $providers_names = !empty($instance['settings']['behaviors']['prepopulate']['providers']) ? array_keys($instance['settings']['behaviors']['prepopulate']['providers']) : array();
    $providers_names = \Drupal\Component\Utility\NestedArray::mergeDeep($providers_names, array_keys($providers));

    $weight = 0;
    foreach ($providers_names as $name) {
      // Validate that the provider exists.
      if (!isset($providers[$name])) {
        continue;
      }

      $provider = $providers[$name];

      // Set default values.
      $provider += array(
        'disabled' => FALSE,
      );

      $form['providers']['title'][$name] = array(
        '#type' => 'item',
        '#markup' => \Drupal\Component\Utility\Xss::filter($provider['title']),
        '#description' => \Drupal\Component\Utility\Xss::filter($provider['description']),
      );

      if (!isset($instance['settings']['behaviors']['prepopulate']['providers'][$name])) {
        // backwards compatibility with version 1.4.
        if ($name == 'url') {
          // Enable the URL provider is it is not set in the instance yet.
          $default_value = TRUE;
        }
        elseif ($name == 'og_context') {
          $default_value = !empty($instance['settings']['behaviors']['prepopulate']['og_context']);
        }
      }
      else {
        $default_value = !empty($instance['settings']['behaviors']['prepopulate']['providers'][$name]);
      }

      $form['providers']['enabled'][$name] = array(
        '#type' => 'checkbox',
        '#disabled' => $provider['disabled'],
        '#default_value' => $default_value,
      );

      $form['providers']['weight'][$name] = array(
        '#type' => 'weight',
        '#default_value' => $weight,
        '#attributes' => array('class' => array('provider-weight')),
      );

      ++$weight;
    }

    return $form;
  }
}

/**
 * Theme the providers table.
 *
 * @ingroup themeable
 */
function theme_entityreference_prepopulate_providers_table($variables) {
  $form = $variables['form'];

  $provider_names = \Drupal\Core\Render\Element::children($form['enabled']);

  foreach ($provider_names as $provider_name) {
    $row = array(
      'data' => array(
        \Drupal::service("renderer")->render($form['title'][$provider_name]),
        \Drupal::service("renderer")->render($form['enabled'][$provider_name]),
        \Drupal::service("renderer")->render($form['weight'][$provider_name]),
      ),
      'class' => array('draggable'),
    );
    $rows[] = $row;
  }

  $header = array(
    array('data' => t('Provider')),
    array('data' => t('Enabled')),
    array('data' => t('Weight')),
  );

  $table_variables = array(
    'header' => $header,
    'rows' => $rows,
    'attributes' => array('id' => 'table-providers'),
  );

  // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
// 
// 
// @see https://www.drupal.org/node/2195739
// $output = theme('table', $table_variables);


  // @FIXME
// TableDrag is now attached with the #tabledrag property of certain render
// arrays. drupal_add_tabledrag() is now internal and should never be called directly.
// 
// 
// @see https://www.drupal.org/node/2160571
// drupal_add_tabledrag('table-providers', 'order', 'sibling', 'provider-weight');

  return $output;
}

/**
 * Element validate; Set the value of the providers.
 */
function entityreference_prepopulate_providers_validate($element, &$form_state) {
  $value = $form_state['values']['instance']['settings']['behaviors']['prepopulate']['providers']['enabled'];

  // Sort the value by the weight.
  uasort($value, 'drupal_sort_weight');

  $form_state->setValueForElement($element, $value);
}
