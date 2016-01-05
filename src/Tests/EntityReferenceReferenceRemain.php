<?php
namespace Drupal\entityreference_prepopulate;

class EntityReferenceReferenceRemain extends DrupalWebTestCase {
  public $user;
  public $node1;
  public $node2;

  public static function getInfo() {
    return array(
      'name' => 'Prepopulate settings',
      'description' => 'Verify the reference between entity is remaining when saving the node.',
      'group' => 'Entity reference prepopulate',
    );
  }

  public function setUp() {
    parent::setUp(array('entityreference', 'entityreference_prepopulate'));

    $content_type = $this->drupalCreateContentType();
    $field = array(
      'field_name' => 'node_ref',
      'type' => 'entityreference',
      'cardinality' => 1,
    );
    // @FIXME
// Fields and field instances are now exportable configuration entities, and
// the Field Info API has been removed.
// 
// 
// @see https://www.drupal.org/node/2012896
// field_create_field($field);


    $instance = array(
      'field_name' => 'node_ref',
      'entity_type' => 'node',
      'label' => 'Node ref',
      'bundle' => $content_type->type,
      'settings' => array(
        'behaviors' => array(
          'prepopulate' => array(
            'status' => TRUE,
            'action' => 'none',
            'fallback' => 'none',
          )
        ),
      ),
    );
    // @FIXME
// Fields and field instances are now exportable configuration entities, and
// the Field Info API has been removed.
// 
// 
// @see https://www.drupal.org/node/2012896
// field_create_instance($instance);


    $this->user = $this->drupalCreateUser(array('bypass node access'));

    // Creating two nodes - referencing node and referenced node.
    $this->node1 = $this->drupalCreateNode(array(
      'title' => 'Referencing node',
      'type' => $content_type->type,
    ));

    $this->node2 = $this->drupalCreateNode(array(
      'title' => 'Referenced node',
      'type' => $content_type->type,
    ));

    // Reference the first node to the second node.
    $wrapper = entity_metadata_wrapper('node', $this->node1->nid);
    $wrapper->node_ref->set($this->node2->nid);
    $wrapper->save();
  }

  /**
   * Set various settings of the entity reference prepopulate settings.
   */
  function testScenarioReference() {
    $this->drupalLogin($this->user);

    // Verify the basic prepopulate option.
    $options = array('query' => array('node_ref' => $this->node2->nid));
    $edit = array('title' => 'Referencing node');

    $this->drupalPost('node/add/' . $this->node1->type, $edit, t('Save'), $options);
    $this->assertText('Referenced node', 'The reference has been created');

    // Disable the field.
    $this->changeInstanceSettings(array('action' => 'disable'));

    $this->drupalGet('node/add/' . $this->node1->type, $options);

    $xpath = $this->xpath('//input[@id="edit-node-ref-und-0-target-id" and @disabled="disabled" and @value="Referenced node (' . $this->node2->nid . ')"]');
    $this->assertTrue(!empty($xpath), 'The field is disabled and default value is set to the node 2.');

    $this->drupalPost('node/add/' . $this->node1->type, $edit, t('Save'), $options);
    $this->assertText('Referenced node', 'The reference has been created');

    // Hide the field.
    $this->changeInstanceSettings(array('action' => 'hide'));

    $xpath = $this->xpath('//input[@id="edit-node-ref-und-0-target-id"]');
    $this->assertTrue(empty($xpath), 'The field is not visible to the user.');

    $this->drupalPost('node/add/' . $this->node1->type, $edit, t('Save'), $options);
    $this->assertText('Referenced node', 'The reference has been created');

    // Set an error when the prepopulated value is missing.
    $this->changeInstanceSettings(array('fallback' => 'form_error'));

    $this->drupalGet('node/add/' . $this->node1->type);
    $this->assertText('Field Node ref must be populated via URL.', 'The error of the missing  prepopulated value has been set.');

    // Redirect when there is the prepopulated field is missing.
    $this->changeInstanceSettings(array('fallback' => 'redirect'));

    $this->drupalGet('node/add/' . $this->node1->type);
    // @FIXME
// url() expects a route name or an external URI.
// $this->assertTrue($this->getUrl() == url('<front>', array('absolute' => TRUE)), 'The redirect of due to non prepoulated value.');


    // Verify the basic edit.
    $this->changeInstanceSettings(array(
      'action' => 'hide',
      'action_on_edit' => FALSE,
    ));
    $this->drupalGet('node/' . $this->node1->nid . '/edit');

    $xpath = $this->xpath('//input[@id="edit-node-ref-und-0-target-id"]');
    $this->assertTrue(!empty($xpath), "The node reference is visible to the user.");

    $this->drupalPost('node/' . $this->node1->nid . '/edit', array('title' => 'Referencing node'), t('Save'));
    $this->verifyReferenceRemain();

    // Hide the field when editing.
    $this->changeInstanceSettings(array(
      'action' => 'hide',
      'action_on_edit' => TRUE,
    ));

    $this->drupalGet('node/' . $this->node1->nid . '/edit');
    $xpath = $this->xpath('//input[@id="edit-node-ref-und-0-target-id"]');
    $this->assertTrue(empty($xpath), "The node reference is invisible to the user.");

    $this->drupalPost('node/' . $this->node1->nid . '/edit', array('title' => 'Referencing node'), t('Save'), $options);
    $this->verifyReferenceRemain();

    // Disable the field.
    $this->changeInstanceSettings(array('action' => 'disable'));

    $this->drupalGet('node/' . $this->node1->nid . '/edit');
    $xpath = $this->xpath('//input[@id="edit-node-ref-und-0-target-id" and @disabled="disabled" and @value="Referenced node (' . $this->node2->nid . ')"]');
    $this->assertTrue(!empty($xpath), 'The field is disabled and default value is set to the node 2.');

    $this->drupalPost('node/' . $this->node1->nid . '/edit', array('title' => 'Referencing node'), t('Save'));
    $this->verifyReferenceRemain();
  }

  /**
   * Change the settings of the instance.
   */
  private function changeInstanceSettings($settings) {
    $instance = field_info_instance('node', 'node_ref', $this->node1->type);
    $old_settings = $instance['settings']['behaviors']['prepopulate'];
    $instance['settings']['behaviors']['prepopulate'] = $settings + $old_settings;
    $instance->save();
  }

  /**
   * Verify the node reference remained.
   */
  private function verifyReferenceRemain() {
    // Loading a fresh node object from the DB.
    $node = // @FIXME
// To reset the node cache, use EntityStorageInterface::resetCache().
\Drupal::entityManager()->getStorage('node')->loadRevision(NULL);
    $wrapper = entity_metadata_wrapper('node', $node);
    $this->assertTrue($wrapper->node_ref->getIdentifier() == $this->node2->nid, 'The reference from node 1 to node 2 remained.');
  }
}
