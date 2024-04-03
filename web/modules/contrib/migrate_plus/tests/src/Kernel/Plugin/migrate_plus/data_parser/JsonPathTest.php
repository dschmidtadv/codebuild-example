<?php

declare(strict_types = 1);

namespace Drupal\Tests\migrate_plus\Kernel\Plugin\migrate_plus\data_parser;

use Drupal\KernelTests\KernelTestBase;

/**
 * Test of the data_parser Json Path migrate_plus plugin.
 *
 * @group migrate_plus
 */
class JsonPathTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['migrate', 'migrate_plus'];

  /**
   * Tests missing properties in json file.
   *
   * @param string $file
   *   File name in tests/data/ directory of this module.
   * @param array $ids
   *   Array of ids to pass to the plugin.
   * @param array $fields
   *   Array of fields to pass to the plugin.
   * @param array $expected
   *   Expected array from json decoded file.
   *
   * @dataProvider jsonPathBaseDataProvider
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Exception
   */
  public function testJsonPathExpressions($file, array $ids, array $fields, array $expected): void {
    $path = $this->container
      ->get('module_handler')
      ->getModule('migrate_plus')
      ->getPath();
    $url = $path . '/tests/data/' . $file;

    /** @var \Drupal\migrate_plus\DataParserPluginManager $plugin_manager */
    $plugin_manager = $this->container
      ->get('plugin.manager.migrate_plus.data_parser');
    $conf = [
      'plugin' => 'url',
      'data_fetcher_plugin' => 'file',
      'data_parser_plugin' => 'jsonpath',
      'destination' => 'node',
      'urls' => [$url],
      'ids' => $ids,
      'fields' => $fields,
      'item_selector' => '$.persons.*',
    ];
    $json_parser = $plugin_manager->createInstance('jsonpath', $conf);

    $data = [];
    foreach ($json_parser as $item) {
      $data[] = $item;
    }

    $this->assertEquals($expected, $data);
  }

  /**
   * Provides multiple test cases for the testJsonPathExpressions method.
   *
   * @return array
   *   The test cases.
   */
  public function jsonPathBaseDataProvider(): array {
    return [
      'json path expressions' => [
        'file' => 'json_path_data_parser.json',
        'ids' => ['id' => ['type' => 'integer']],
        'fields' => [
          [
            'name' => 'id',
            'label' => 'Id',
            'selector' => 'id',
          ],
          [
            'name' => 'names',
            'label' => 'Names',
            'selector' => '..name',
          ],
          [
            'name' => 'children',
            'label' => 'Children',
            'selector' => '.children.*.name',
          ],
          [
            'name' => 'age',
            'label' => 'Age',
            'selector' => '.children.[?(@.age > 6)]',
          ],
          [
            'name' => 'total_children',
            'label' => 'Total Children',
            'selector' => '.children.length',
          ],
        ],
        'expected' => [
          [
            'id' => 1,
            'names' => [
              'Elizabeth',
              'Elizabeth Junior',
              'Jane',
            ],
            'children' => [
              'Elizabeth Junior',
              'Jane',
            ],
            'age' => [
              [
                'name' => 'Elizabeth Junior',
                'age' => 8,
              ],
              [
                'name' => 'Jane',
                'age' => 8,
              ],
            ],
            'total_children' => 2,
          ],
          [
            'id' => 2,
            'names' => [
              'George',
              'George Junior',
            ],
            'children' => 'George Junior',
            'age' => [
              'name' => 'George Junior',
              'age' => 10,
            ],
            'total_children' => 1,
          ],
          [
            'id' => 3,
            'names' => [
              'Peter',
              'James',
              'Lucy',
            ],
            'children' => [
              'James',
              'Lucy',
            ],
            'age' => [],
            'total_children' => 2,
          ],
          [
            'id' => 4,
            'names' => 'Jane',
            'children' => [],
            'age' => [],
            'total_children' => [],
          ],
        ],
      ],
    ];
  }

}
