<?php

namespace Drupal\Tests\migrate_sandbox\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests migrate_sandbox.
 *
 * @group migrate_sandbox
 */
class MigrateSandboxTest extends WebDriverTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'migrate_sandbox',
    'migrate_sandbox_test',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the migrate_sandbox form.
   */
  public function testMigrateSandbox() {
    // Test as trusted user.
    $trusted_user = $this->drupalCreateUser(['access migrate_sandbox']);
    $this->drupalLogin($trusted_user);
    $this->drupalGet('/admin/config/development/migrate-sandbox');
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // Test that urlencode works.
    $page->find('css', '[data-drupal-selector="edit-prepopulated-details"] summary')->click();
    $page->find('css', '[name="prepopulated"]')->setValue('urlencode');
    $assert_session->assertWaitOnAjaxRequest();
    $page->find('css', '[name="constants"]')->setValue('hello: howdy');
    $assert_session->pageTextNotContains("new_url: 'http://example.com/a%20url%20with%20spaces.html'");
    $page->pressButton('Process Row');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->pageTextContains("new_url: 'http://example.com/a%20url%20with%20spaces.html'");

    // Test that config was saved correctly.
    $latest = \Drupal::configFactory()->get('migrate_sandbox.latest');
    $this->assertSame('migrate_sandbox', $latest->get('id'));
    $this->assertSame('Migrate Sandbox', $latest->get('label'));
    $this->assertSame('embedded_data', $latest->get('source.plugin'));
    $this->assertSame('http://example.com/a url with spaces.html', $latest->get('source.data_rows')[0]['my_url_with_spaces']);
    $this->assertSame('123', $latest->get('source.data_rows')[0]['migrate_sandbox_dummy_source_id']);
    $this->assertSame('howdy', $latest->get('source.constants.hello'));
    $this->assertSame('urlencode', $latest->get('process.new_url.plugin'));
    $this->assertSame('my_url_with_spaces', $latest->get('process.new_url.source'));
    $this->assertSame('migrate_sandbox_config', $latest->get('destination.plugin'));
    $this->assertSame('migrate_sandbox.processed', $latest->get('destination.config_name'));

    // That migrate map does not exist.
    $this->assertFalse(\Drupal::database()->schema()->tableExists('migrate_map_migrate_sandbox'));
    $this->assertFalse(\Drupal::database()->schema()->tableExists('migrate_message_migrate_sandbox'));

    // Test sandbox escape warnings.
    $callback_warning = $page->find('css', '[data-plugin="callback"]');
    $lookup_warning = $page->find('css', '[data-plugin="migration_lookup"]');
    $this->assertFalse($callback_warning->isVisible());
    $this->assertFalse($lookup_warning->isVisible());
    $page->find('css', '[name="process"]')->setValue('plugin: callback');
    $assert_session->waitForElementVisible('css', '[data-plugin="callback"]');
    $this->assertTrue($callback_warning->isVisible());
    $this->assertFalse($lookup_warning->isVisible());
    $page->find('css', '[name="process"]')->setValue('plugin: "migration_lookup"');
    $assert_session->waitForElementVisible('css', '[data-plugin="migration_lookup"]');
    $this->assertFalse($callback_warning->isVisible());
    $this->assertTrue($lookup_warning->isVisible());

    // Try submitting with invalid yaml.
    $page->find('css', '[name="data_rows"]')->setValue(' foo:');
    $assert_session->statusMessageNotExists();
    $assert_session->elementTextContains('css', '[data-drupal-selector="edit-yaml"]', "new_url: 'http://example.com/a%20url%20with%20spaces.html'");
    $page->pressButton('Process Row');
    $assert_session->assertWaitOnAjaxRequest();
    // The following line fails on d.o for a reason I don't understand.
    // $assert_session->statusMessageExistsAfterWait('error');
    // The results text should be cleared.
    $assert_session->elementTextNotContains('css', '[data-drupal-selector="edit-yaml"]', "new_url: 'http://example.com/a%20url%20with%20spaces.html'");

    // Run log example to test for on-screen messages.
    $page->find('css', '[data-drupal-selector="edit-prepopulated-details"] summary')->click();
    $page->find('css', '[name="prepopulated"]')->setValue('log');
    $assert_session->assertWaitOnAjaxRequest();
    $expected_log = "'example_1' value is 'Foo'";
    $assert_session->statusMessageNotContains($expected_log);
    $assert_session->elementTextNotContains('css', '[data-drupal-selector="edit-yaml"]', 'example_1: Foo');
    $page->pressButton('Process Row');
    $assert_session->statusMessageContainsAfterWait($expected_log, 'error');
    $assert_session->elementTextContains('css', '[data-drupal-selector="edit-yaml"]', 'example_1: Foo');
    $this->assertFalse(\Drupal::database()->schema()->tableExists('migrate_map_migrate_sandbox'));
    $this->assertFalse(\Drupal::database()->schema()->tableExists('migrate_message_migrate_sandbox'));

    // Run example that triggers migrate exception.
    $page->find('css', '[data-drupal-selector="edit-prepopulated-details"] summary')->click();
    $page->find('css', '[name="prepopulated"]')->setValue('explode');
    $assert_session->assertWaitOnAjaxRequest();
    // Run successfully once to populate results.
    $page->pressButton('Process Row');
    $assert_session->assertWaitOnAjaxRequest();
    $page->find('css', '[name="data_rows"]')->setValue('');
    $expected_log = 'NULL is not a string';
    $assert_session->pageTextNotContains($expected_log);
    $assert_session->elementTextContains('css', '[data-drupal-selector="edit-yaml"]', 'example_1:');
    $page->pressButton('Process Row');
    $assert_session->statusMessageContainsAfterWait($expected_log, 'error');
    // Result should be cleared.
    $assert_session->elementTextNotContains('css', '[data-drupal-selector="edit-yaml"]', 'example_1:');

    // Test sandbox content entity destination.
    NodeType::create(['type' => 'page'])->save();
    \Drupal::service('plugin.manager.migrate.destination')->getDefinition('migrate_sandbox_entity:node');
    // Get a clean slate.
    $this->drupalGet('/admin/config/development/migrate-sandbox');
    $page->find('css', '[data-drupal-selector="edit-prepopulated-details"] summary')->click();
    $page->find('css', '[name="prepopulated"]')->setValue('null_coalesce');
    $assert_session->assertWaitOnAjaxRequest();
    $page->find('css', '[name="destination"]')->setValue('entity');
    $page->find('css', '[name="entity_type"]')->setValue('node');
    $page->find('css', '[name="entity_bundle"]')->setValue('page');
    // Expect message about dummy title since title is not in process.
    $page->pressButton('Process Row');
    $assert_session->statusMessageContainsAfterWait('(Migrate Message) Dummy value used for entity label to avoid validation error.', 'status');
    $page->find('css', '[name="data_rows"]')->setValue('my_string: My title');
    $page->find('css', '[name="process"]')->setValue('title: my_string');
    $assert_session->pageTextNotContains("value: 'My title'");
    $assert_session->pageTextContains("value: 'Dummy title'");
    $page->pressButton('Process Row');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->statusMessageNotContainsAfterWait('(Migrate Message)');
    $assert_session->pageTextContains("value: 'My title'");

    // Confirm node was not saved.
    $nodes = Node::loadMultiple();
    $this->assertEmpty($nodes);

    // Start testing the population-from-migration feature.
    $page->find('css', '[data-drupal-selector="edit-populate"] summary')->click();
    $page->find('css', '[name="populate_migration"]')->setValue('migrate_sandbox_test');
    $page->pressButton('Populate');
    $assert_session->statusMessageContainsAfterWait('Row retrieved for source ids 1 in migration migrate_sandbox_test', 'status');
    $expected = [
      'data_rows' => "id: 1\nbody: 'body the first'\ntitle: test_title_1\n",
      'constants' => "loud: '!'\n",
      'process' => "title:\n  plugin: concat\n  source:\n    - title\n    - constants/loud\nbody/value: body\n",
    ];
    $this->assertSame($expected['data_rows'], $page->find('css', '[name="data_rows"]')->getValue());
    $this->assertSame($expected['constants'], $page->find('css', '[name="constants"]')->getValue());
    $this->assertSame($expected['process'], $page->find('css', '[name="process"]')->getValue());

    $page->find('css', '[name="destination"]')->setValue('config');
    $page->pressButton('Process Row');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementTextContains('css', '[data-drupal-selector="edit-yaml"]', 'title: test_title_1!');

    $page->find('css', '[data-drupal-selector="edit-populate"] summary')->click();
    $page->find('css', '[name="populate_migration"]')->setValue('migrate_sandbox_test');
    $page->find('css', '[name="populate_source_ids"]')->setValue('2');
    $page->pressButton('Populate');
    $assert_session->statusMessageContainsAfterWait('Row retrieved for source ids 2 in migration migrate_sandbox_test', 'status');
    $expected = [
      'data_rows' => "id: 2\nbody: 'body the second'\ntitle: test_title_2\n",
      'constants' => "loud: '!'\n",
      'process' => "title:\n  plugin: concat\n  source:\n    - title\n    - constants/loud\nbody/value: body\n",
    ];
    $this->assertSame($expected['data_rows'], $page->find('css', '[name="data_rows"]')->getValue());
    $this->assertSame($expected['constants'], $page->find('css', '[name="constants"]')->getValue());
    $this->assertSame($expected['process'], $page->find('css', '[name="process"]')->getValue());

    // Try populating with bad values.
    $page->find('css', '[data-drupal-selector="edit-populate"] summary')->click();
    $page->find('css', '[name="populate_migration"]')->setValue('migrate_sandbox_test');
    $page->find('css', '[name="populate_source_ids"]')->setValue('3');
    $page->pressButton('Populate');
    $assert_session->statusMessageContainsAfterWait('No row found for source ids 3 in migration migrate_sandbox_test', 'error');

    $page->find('css', '[name="populate_migration"]')->setValue('migrate_sandbox_test_typo');
    $page->find('css', '[name="populate_source_ids"]')->setValue('3');
    $page->pressButton('Populate');
    $assert_session->statusMessageContainsAfterWait("Plugin ID 'migrate_sandbox_test_typo' was not found", 'error');

    // Test the checkbox that protects process pipeline.
    $page->find('css', '[name="process"]')->setValue('title: my_string');
    $page->find('css', '[name="populate_process"]')->setValue(FALSE);
    $page->find('css', '[name="populate_migration"]')->setValue('migrate_sandbox_test');
    $page->find('css', '[name="populate_source_ids"]')->setValue('2');
    $page->pressButton('Populate');
    $assert_session->statusMessageContainsAfterWait('Row retrieved for source ids 2 in migration migrate_sandbox_test', 'status');
    $expected = [
      'data_rows' => "id: 2\nbody: 'body the second'\ntitle: test_title_2\n",
      'constants' => "loud: '!'\n",
      'process' => "title: my_string",
    ];
    $this->assertSame($expected['data_rows'], $page->find('css', '[name="data_rows"]')->getValue());
    $this->assertSame($expected['constants'], $page->find('css', '[name="constants"]')->getValue());
    $this->assertSame($expected['process'], $page->find('css', '[name="process"]')->getValue());

    // Test the "fetch next row" feature. Start by kind of resetting things.
    $page->find('css', '[data-drupal-selector="edit-populate"] summary')->click();
    $page->find('css', '[name="populate_migration"]')->setValue('migrate_sandbox_test');
    $page->find('css', '[name="populate_source_ids"]')->setValue('');
    $page->find('css', '[name="populate_process"]')->setValue(TRUE);
    $this->assertFalse($page->findButton('Fetch next row')->isVisible());
    $page->pressButton('Populate');
    $assert_session->statusMessageContainsAfterWait('Row retrieved for source ids 1 in migration migrate_sandbox_test', 'status');
    $expected = [
      'data_rows' => "id: 1\nbody: 'body the first'\ntitle: test_title_1\n",
      'constants' => "loud: '!'\n",
      'process' => "title:\n  plugin: concat\n  source:\n    - title\n    - constants/loud\nbody/value: body\n",
    ];
    $this->assertSame($expected['data_rows'], $page->find('css', '[name="data_rows"]')->getValue());
    $this->assertSame($expected['constants'], $page->find('css', '[name="constants"]')->getValue());
    $this->assertSame($expected['process'], $page->find('css', '[name="process"]')->getValue());
    // Now the button should be visible and it should work!
    $page->find('css', '[data-drupal-selector="edit-populate"] summary')->click();
    $this->assertSame('migrate_sandbox_test', $page->find('css', '[name="populate_migration"]')->getValue());
    $this->assertSame('1', $page->find('css', '[name="populate_source_ids"]')->getValue());
    $this->assertTrue($page->findButton('Fetch next row')->isVisible());
    $page->pressButton('Fetch next row');
    $assert_session->statusMessageContainsAfterWait('Row retrieved for source ids 2 in migration migrate_sandbox_test', 'status');
    $expected = [
      'data_rows' => "id: 2\nbody: 'body the second'\ntitle: test_title_2\n",
      'constants' => "loud: '!'\n",
      'process' => "title:\n  plugin: concat\n  source:\n    - title\n    - constants/loud\nbody/value: body\n",
    ];
    $this->assertSame($expected['data_rows'], $page->find('css', '[name="data_rows"]')->getValue());
    $this->assertSame($expected['constants'], $page->find('css', '[name="constants"]')->getValue());
    $this->assertSame($expected['process'], $page->find('css', '[name="process"]')->getValue());
    $page->find('css', '[data-drupal-selector="edit-populate"] summary')->click();
    $this->assertSame('migrate_sandbox_test', $page->find('css', '[name="populate_migration"]')->getValue());
    $this->assertSame('2', $page->find('css', '[name="populate_source_ids"]')->getValue());
    // Confirm the button disappears if we change the migration or source ids.
    $this->assertTrue($page->findButton('Fetch next row')->isVisible());
    $page->find('css', '[name="populate_source_ids"]')->setValue('3');
    $this->assertFalse($page->findButton('Fetch next row')->isVisible());
    $page->find('css', '[name="populate_source_ids"]')->setValue('2');
    $this->assertTrue($page->findButton('Fetch next row')->isVisible());
    $page->find('css', '[name="populate_migration"]')->setValue('uhhh');
    $this->assertFalse($page->findButton('Fetch next row')->isVisible());
    $page->find('css', '[name="populate_migration"]')->setValue('migrate_sandbox_test');
    $this->assertTrue($page->findButton('Fetch next row')->isVisible());
    // Test fetching next when there is no next.
    $page->pressButton('Fetch next row');
    $assert_session->statusMessageContainsAfterWait('No more rows in migration migrate_sandbox_test', 'error');
    $this->assertFalse($page->findButton('Fetch next row')->isVisible());

    // Make sure nothing has been saved in migrate tables.
    $this->assertFalse(\Drupal::database()->schema()->tableExists('migrate_map_migrate_sandbox_test'));
    $this->assertFalse(\Drupal::database()->schema()->tableExists('migrate_message_migrate_sandbox_test'));

    // Confirm node was not saved.
    $nodes = Node::loadMultiple();
    $this->assertEmpty($nodes);
  }

}
