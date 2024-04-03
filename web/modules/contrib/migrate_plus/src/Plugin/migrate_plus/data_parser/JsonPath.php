<?php

declare(strict_types = 1);

namespace Drupal\migrate_plus\Plugin\migrate_plus\data_parser;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate_plus\DataParserPluginBase;
use Flow\JSONPath\JSONPath as JSONPathSelector;

/**
 * Obtain JSON data for migration using JSONPath selectors.
 *
 * @DataParser(
 *   id = "jsonpath",
 *   title = @Translation("JSONPath")
 * )
 */
class JsonPath extends DataParserPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Iterator over the JSON data.
   */
  protected ?\ArrayIterator $iterator = NULL;

  /**
   * Retrieves the JSON data and returns it as an array.
   *
   * @param string $url
   *   URL of a JSON feed.
   *
   * @return array
   *   The selected data to be iterated.
   *
   * @throws \GuzzleHttp\Exception\RequestException
   * @throws \Flow\JSONPath\JSONPathException
   */
  protected function getSourceData(string $url): array {
    $response = $this->getDataFetcherPlugin()->getResponseContent($url);

    // Convert objects to associative arrays.
    $source_data = json_decode($response, TRUE, 512, JSON_THROW_ON_ERROR);

    // If json_decode() has returned NULL, it might be that the data isn't
    // valid utf8 - see http://php.net/manual/en/function.json-decode.php#86997.
    if (is_null($source_data)) {
      $utf8response = utf8_encode($response);
      $source_data = json_decode($utf8response, TRUE, 512, JSON_THROW_ON_ERROR);
    }

    $source_data = (new JSONPathSelector($source_data))->find($this->itemSelector);

    return $source_data->getData();
  }

  /**
   * {@inheritdoc}
   */
  protected function openSourceUrl(string $url): bool {
    // (Re)open the provided URL.
    $source_data = $this->getSourceData($url);
    $this->iterator = new \ArrayIterator($source_data);
    return TRUE;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Flow\JSONPath\JSONPathException
   */
  protected function fetchNextRow(): void {
    $current = $this->iterator->current();
    if ($current) {
      foreach ($this->fieldSelectors() as $field_name => $selector) {
        $field_data = (new JSONPathSelector($current))->find($selector);

        if ($field_data->count() == 1) {
          $field_data = $field_data->first();

          // When retrieving nested data the first item can be another JSONPath
          // object. We need to extract its data.
          if ($field_data instanceof JSONPathSelector) {
            $field_data = $field_data->getData();
          }
        }
        else {
          $field_data = $field_data->getData();
        }

        // JSONPath converts JSON objects to PHP objects which cause Migrate to
        // fail when using the SubProcess process plugin. This function
        // recursively converts those objects to arrays to prevent this issue.
        $toArray = function ($x) use (&$toArray) {
          return (is_scalar($x) || is_null($x)) ? $x : array_map($toArray, (array) $x);
        };
        $field_data = $toArray($field_data);

        $this->currentItem[$field_name] = $field_data;
      }
      if (!empty($this->configuration['include_raw_data'])) {
        $this->currentItem['raw'] = $current;
      }
      $this->iterator->next();
    }
  }

}
