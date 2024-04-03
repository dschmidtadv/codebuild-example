<?php

namespace Drupal\Tests\migrate_source_html\Kernel\Plugin\migrate_plus\data_parser;

use Drupal\KernelTests\KernelTestBase;

/**
 * Test of the data_parser SimpleXml migrate_plus plugin.
 *
 * @group migrate_plus
 */
class HtmlTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['migrate', 'migrate_plus', 'migrate_source_html'];

  /**
   * Path for the xml file.
   *
   * @var string
   */
  protected $path;

  /**
   * The plugin manager.
   *
   * @var \Drupal\migrate_plus\DataParserPluginManager
   */
  protected $pluginManager;

  /**
   * The plugin configuration.
   *
   * @var array
   */
  protected $configuration;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->path = $this->container->get('module_handler')
      ->getModule('migrate_source_html')->getPath();
    $this->pluginManager = $this->container
      ->get('plugin.manager.migrate_plus.data_parser');
    $this->configuration = [
      'plugin' => 'url',
      'data_fetcher_plugin' => 'file',
      'data_parser_plugin' => 'html5',
      'destination' => 'node',
      'urls' => [],
      'ids' => ['id' => ['type' => 'string']],
      'fields' => [
        [
          'name' => 'id',
          'label' => 'Id',
          'selector' => '//head/meta[@property="post_id"]/@content',
        ],
        [
          'name' => 'content',
          'label' => 'Content',
          'selector' => '//article[@class="content"]',
        ],
      ],
      'item_selector' => '/',
    ];
  }

  /**
   * Tests migrate single article for each page.
   */
  public function testSingleArticle(): void {
    $this->configuration['urls'][0] = $this->path . '/tests/data/page-01.html';
    $this->configuration['urls'][1] = $this->path . '/tests/data/page-02.html';
    $parser = $this->pluginManager->createInstance('html5', $this->configuration);
    $expected = [
      [
        'id' => '4321',
        'content' => '<article class="content"><h4>Headings 01</h4><p>Paragraph 01.01</p><p>Paragraph 01.02 <a href="https://drupal.org">Drupal</a></p></article>',
      ],
      [
        'id' => '4322',
        'content' => '<article class="content"><h4>Headings 02</h4><p>Paragraph 02.01</p><p>Paragraph 02.02 <a href="https://drupal.org">Drupal</a></p></article>',
      ],
    ];
    $this->assertResults($expected, $parser);
  }

  /**
   * Tests migrate multiple pages, multi articles per page.
   */
  public function testMultipleArticles(): void {
    $this->configuration['urls'][0] = $this->path . '/tests/data/page-multiarticle-01.html';
    $this->configuration['urls'][1] = $this->path . '/tests/data/page-multiarticle-02.html';

    $this->configuration['item_selector'] = '//article[@class="content"]';

    // Id is now inside the <article> "id" attribute value.
    $this->configuration['fields'][0]['selector'] = '@id';

    // Content is now the root of the "item_selector".
    $this->configuration['fields'][1]['selector'] = '.';

    $parser = $this->pluginManager->createInstance('html5', $this->configuration);
    $expected = [
      [
        'id' => '43211',
        'content' => '<article class="content" id="43211"><h4>Headings 01</h4><p>Paragraph 01.01</p><p>Paragraph 01.02 <a href="https://drupal.org">Drupal</a></p></article>',
      ],
      [
        'id' => '43212',
        'content' => '<article class="content" id="43212"><h4>Headings 02</h4><p>Paragraph 02.01</p><p>Paragraph 02.02 <a href="https://drupal.org">Drupal</a></p></article>',
      ],
      [
        'id' => '43221',
        'content' => '<article class="content" id="43221"><h4>Headings 03</h4><p>Paragraph 03.01</p><p>Paragraph 03.02 <a href="https://drupal.org">Drupal</a></p></article>',
      ],
      [
        'id' => '43222',
        'content' => '<article class="content" id="43222"><h4>Headings 04</h4><p>Paragraph 04.01</p><p>Paragraph 04.02 <a href="https://drupal.org">Drupal</a></p></article>',
      ],
    ];
    $this->assertResults($expected, $parser);
  }

  /**
   * Parses and asserts the results match expectations.
   *
   * @param array $expected
   *   The expected results, one array for each expected row/item data.
   * @param \Traversable $parser
   *   An iterable data result to parse.
   */
  protected function assertResults(array $expected, \Traversable $parser) {
    $data = [];
    foreach ($parser as $item) {
      $data[] = $item;
    }
    $this->assertEquals($expected, $data);
  }

}
