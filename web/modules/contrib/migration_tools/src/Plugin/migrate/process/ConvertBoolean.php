<?php

namespace Drupal\migration_tools\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Converts a boolean value and select string values to a Drupal boolean value.
 *
 * Example: TRUE => 1, FALSE => 0.
 *
 * @MigrateProcessPlugin(
 *   id = "convert_boolean"
 * )
 *
 * Example usage: Minimum configuration.
 * @code
 * field_boolean:
 *   plugin: convert_boolean
 *   source: boolean
 * @endcode
 *
 * Example usage: Mapping specific string values.
 * @code
 * field_boolean:
 *   plugin: convert_boolean
 *   source: boolean
 *   map_true:
 *     - yes
 *     - Yes
 *     - si
 *   map_false:
 *     - no
 *     - No
 *     - nada
 * @endcode
 */
class ConvertBoolean extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $map_false = ['false', 'FALSE', 'null', 'NULL', '0'];
    $map_false_from_config = (!empty($this->configuration['map_false'] && is_array($this->configuration['map_false']))) ? $this->configuration['map_false'] : [];
    $map_false = array_merge($map_false, $map_false_from_config);

    $map_true = ['true', 'TRUE', '1'];
    $map_true_from_config = (!empty($this->configuration['map_true'] && is_array($this->configuration['map_true']))) ? $this->configuration['map_true'] : [];
    $map_true = array_merge($map_true, $map_true_from_config);

    if (is_string($value)) {
      if (in_array($value, $map_true)) {
        $value = TRUE;
      }
      elseif (in_array($value, $map_false)) {
        $value = FALSE;
      }
      else {
        // Non-empty strings other than those specifically mapped, get FALSEd.
        $value = FALSE;
      }
    }

    return boolval($value);
  }

}
