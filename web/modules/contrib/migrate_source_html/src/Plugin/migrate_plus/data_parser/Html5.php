<?php

namespace Drupal\migrate_source_html\Plugin\migrate_plus\data_parser;

use Masterminds\HTML5 as Html5Parser;

/**
 * Obtain Html5 data for migration.
 *
 * @DataParser(
 *   id = "html5",
 *   title = @Translation("Html5")
 * )
 */
class Html5 extends Html {

  /**
   * {@inheritdoc}
   */
  protected function loadHtmlIntoDocument($html) {
    $document = new \DOMDocument($this->configuration['version'], $this->configuration['encoding']);
    $html5 = new Html5Parser([
      'target_document' => $document,
      'disable_html_ns' => TRUE,
    ]);
    $html5->loadHTML($html);

    return $document;
  }

}
