<?php
namespace Drupal\entityreference_prepopulate;

class EntityReferenceProvidersTestCase extends DrupalWebTestCase {

  public static function getInfo() {
    return array(
      'name' => 'Providers',
      'description' => 'Test the providers functionality.',
      'group' => 'Entity reference prepopulate',
      'dependencies' => array('og'),
    );
  }

  function setUp() {
    parent::setUp('og_context', 'entityreference_prepopulate_test');

    $this->user1 = $this->drupalCreateUser(array('bypass node access', 'administer group'));
    $this->drupalLogin($this->user1);

    $type = $this->drupalCreateContentType();
    $this->group_type = $type->type;
    og_create_field(OG_GROUP_FIELD, 'node', $this->group_type);

    $type = $this->drupalCreateContentType();
    $this->group_content_type = $type->type;

    $og_field = og_fields_info(OG_AUDIENCE_FIELD);
    // Enable the prepopulate behavior.
    $og_field['instance']['settings']['behaviors']['prepopulate'] = array(
      'status' => TRUE,
      'action' => 'none',
      'fallback' => 'none',
      'skip_perm' => FALSE,
    );
    og_create_field(OG_AUDIENCE_FIELD, 'node', $this->group_content_type, $og_field);

    $settings = array(
      'type' => $this->group_type,
      'uid' => $this->user1->uid,
      'title' => $this->randomName(),
    );
    $settings[OG_GROUP_FIELD][\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][0]['value'] = 1;
    $this->group1 = $this->drupalCreateNode($settings);

    $settings['title'] = $this->randomName();
    $this->group2 = $this->drupalCreateNode($settings);
  }

  /**
   * Test the providers functionality.
   */
  function testProvidres() {
    $nid1 = $this->group1->nid;
    $nid2 = $this->group2->nid;

    $path = 'node/add/' . str_replace('_', '-', $this->group_content_type);
    $options = array(
      'query' => array(
        OG_AUDIENCE_FIELD => $nid1,
        // Set the OG context. See entityreference_prepopulate_init().
        'gid' => $nid2,
      )
    );

    $instance = field_info_instance('node', OG_AUDIENCE_FIELD, $this->group_content_type);

    $scenarios = array(
      array(
        'message' => 'No providers set - defaults to URL.',
        'providers' => array(),
        'result' => $nid1,
      ),
      array(
        'message' => 'URL provider only.',
        'providers' => array(
          'url' => TRUE,
        ),
        'result' => $nid1,
      ),
      array(
        'message' => 'OG Context provider only.',
        'providers' => array(
          'og_context' => TRUE,
        ),
        'result' => $nid2,
      ),
      array(
        'message' => 'URL provider, and then OG Context provider.',
        'providers' => array(
          'url' => TRUE,
          'og_context' => TRUE,
        ),
        'result' => $nid1,
      ),
      array(
        'message' => 'OG Context provider, and then URL provider.',
        'providers' => array(
          'og_context' => TRUE,
          'url' => TRUE,
        ),
        'result' => $nid2,
      ),
      array(
        'message' => 'Invalid provider.',
        'providers' => array(
          'invalid' => TRUE,
        ),
        'result' => FALSE,
      ),
    );

    foreach ($scenarios as $scenario) {
      $instance['settings']['behaviors']['prepopulate']['providers'] = $scenario['providers'];
      $instance->save();

      $this->drupalGet($path, $options);

      if ($scenario['result']) {
        $this->assertOptionSelected('edit-og-group-ref-und-0-default', $scenario['result'], $scenario['message']);
      }
      else {
        $this->assertNoOptionSelected('edit-og-group-ref-und-0-default', $nid1, $scenario['message']);
        $this->assertNoOptionSelected('edit-og-group-ref-und-0-default', $nid2, $scenario['message']);
      }

    }
  }
}
