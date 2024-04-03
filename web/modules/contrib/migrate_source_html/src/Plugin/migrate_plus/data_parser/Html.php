<?php

namespace Drupal\migrate_source_html\Plugin\migrate_plus\data_parser;

use Drupal\migrate_plus\DataParserPluginBase;

/**
 * Obtain Html data for migration.
 *
 * @DataParser(
 *   id = "html",
 *   title = @Translation("Html")
 * )
 */
class Html extends DataParserPluginBase {

  /**
   * Array of matches from item_selector.
   *
   * @var array
   */
  protected $matches = [];

  /**
   * The DOMXpath instance attachd to current document.
   *
   * @var \DOMXPath
   */
  protected $xpath;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configuration += $this->defaultValues();
  }

  /**
   * Supply default values of all optional parameters.
   *
   * @return array
   *   An array with keys the optional parameters and values the corresponding
   *   defaults.
   */
  protected function defaultValues() {
    return [
      'item_selector' => '/',
      'version' => '1.0',
      'encoding' => 'UTF-8',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function openSourceUrl(string $url) :bool {
    $content = $this->getDataFetcherPlugin()->getResponseContent($url);

    if (empty($content)) {
      return FALSE;
    }

    $document = $this->loadHtmlIntoDocument($content);

    $this->xpath = new \DOMXPath($document);
    /** @var \DOMNodeList $item */
    foreach ($this->xpath->query($this->configuration['item_selector']) as $item) {
      $this->matches[] = $item;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function fetchNextRow() :void {
    $target_element = array_shift($this->matches);

    // If we've found the desired element, populate the currentItem and
    // currentId with its data.
    if ($target_element !== FALSE && !is_null($target_element)) {
      foreach ($this->fieldSelectors() as $field_name => $xpath) {
        // Initialise the current field name.
        $this->currentItem[$field_name] = NULL;
        foreach ($this->xpath->query($xpath, $target_element) as $value) {
          if ($value instanceof \DOMElement) {
            // The query can return a DOMNodeList, and so a list of DOMElements.
            // In that case concatenate the query results.
            $this->currentItem[$field_name] .= (string) $value->ownerDocument->saveHTML($value);
          }
          else {
            // In any other case, i.e. DOMAttr, try your best by getting the
            // DOMNode value.
            $this->currentItem[$field_name] = (string) $value->nodeValue;
          }
        }
      }
    }
  }

  /**
   * Get DOMDocument object for provided HTML.
   *
   * By making this a protected class, can be easily extended with different
   * wrappers/parsers like \Masterminds\HTML5.
   *
   * @param string $html
   *   The HTML to be processed. A full html page is expected.
   *
   * @return \DOMDocument
   *   The DOMDocument for provided HTML.
   */
  protected function loadHtmlIntoDocument($html) {
    $document = new \DOMDocument($this->configuration['version'], $this->configuration['encoding']);
    $document->loadHTML($html);

    return $document;
  }

}
