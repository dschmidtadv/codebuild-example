<?php

namespace Drupal\migration_tools\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Compares two values, and if the comparison is true, passes the source thru.
 *
 * @MigrateProcessPlugin(
 * id = "gate_comparator",
 * handle_multiples = TRUE
 * )
 *
 * Example usage: Compare value A to value B. If TRUE use the process pipeline
 * source.  If FALSE, use the 'when_false_value'.
 * @code
 * field_some_text_field:
 *   plugin: gate_comparator
 *   value_a: a string or number to compare or 'source' to use the source.
 *   comparison: = comparison to evaluate [=, <, >, !=, <=, >=]
 *   value_b: the other string or number to compare
 *   when_false_value: A value to use if the comparison is FALSE
 *   source:  The source value to use if the comparison is TRUE
 *
 * @endcode
 *
 * Example usage: Choosing the bigger value between two source fields.
 *
 * @code
 *  field_some_text_field:
 *   plugin: gate_comparator
 *   value_a: cost
 *   comparison: '>'
 *   value_b: average_cost
 *   when_false_value: average_cost
 *   source: cost
 *
 * @endcode
 */
class GateComparator extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $compare_data = $this->getCompareData($value, $row);
    $false_value = $compare_data['when_false_value'];
    switch ($compare_data['comparison']) {
      case '=':
      case '==':
        return ($compare_data['value_a'] == $compare_data['value_b']) ? $value : $false_value;

      case '===':
        return ($compare_data['value_a'] === $compare_data['value_b']) ? $value : $false_value;

      case '!=':
      case '<>':
        return ($compare_data['value_a'] != $compare_data['value_b']) ? $value : $false_value;

      case '!==':
        return ($compare_data['value_a'] !== $compare_data['value_b']) ? $value : $false_value;

      case '>':
        return ($compare_data['value_a'] > $compare_data['value_b']) ? $value : $false_value;

      case '>=':
        return ($compare_data['value_a'] >= $compare_data['value_b']) ? $value : $false_value;

      case '<':
        return ($compare_data['value_a'] < $compare_data['value_b']) ? $value : $false_value;

      case '<=':
        return ($compare_data['value_a'] <= $compare_data['value_b']) ? $value : $false_value;

      default:
        // It should never make it here, but just in case, move on.
        return $value;
    }
  }

  /**
   * Validates and returns the comparison data.
   *
   * @param mixed $value
   *   The value of the source from the plugin process chain.
   * @param \Drupal\migrate\Row $row
   *   The migration row.
   *
   * @return array
   *   The array of data to be used to compare.
   *
   * @throws \InvalidArgumentException
   *   If the required config is not present, or not proper.
   */
  protected function getCompareData(mixed $value, Row $row) {
    $required_settings = [
      'value_a',
      'value_b',
      'comparison',
      'when_false_value',
    ];
    foreach ($required_settings as $required_setting) {
      if (!isset($this->configuration[$required_setting])) {
        throw new \InvalidArgumentException("The element `{$required_setting}` must be defined in order to use this process plugin");
      }
    }
    $allowed_comparators = ['=', '==', '===', '!=', '<>', '!==', '<', '<=', '>', '>='];
    if (!in_array($this->configuration['comparison'], $allowed_comparators)) {
      throw new \InvalidArgumentException("The comparison `{$this->configuration['comparison']}` is not a valid comparison.");
    }
    $compare_data = [
      'comparison' => $this->configuration['comparison'],
      'value_b' => $this->optionallyGrabMatchingTokenValue($this->configuration['value_b'], $row),
      'when_false_value' => $this->optionallyGrabMatchingTokenValue($this->configuration['when_false_value'], $row),
    ];
    if (($this->configuration['value_a'] === 'source') || ($this->configuration['value_a'] === '@source')) {
      // Value A has intentionally been set to use the source.
      $compare_data['value_a'] = $value;
    }
    else {
      $compare_data['value_a'] = $this->optionallyGrabMatchingTokenValue($this->configuration['value_a'], $row);
    }

    return $compare_data;
  }

  /**
   * {@inheritdoc}
   */
  public function multiple() {
    return TRUE;
  }

  /**
   * Grabs a value from a token referencing a source or destination property.
   *
   * @param mixed $value
   *   The value of an entry in config.
   * @param \Drupal\migrate\Row $row
   *   The migration row.
   *
   * @return mixed
   *   Either the original value, or the value matching the destination or
   *   source property token/field in the row.
   */
  protected function optionallyGrabMatchingTokenValue(mixed $value, Row $row): mixed {
    // Check if the value with priority given to destination, since the
    // destination may have been processed from the source.
    $possible_token = ltrim($value, '@');
    if ($row->hasDestinationProperty($possible_token) || $row->hasSourceProperty($possible_token)) {
      // It is a token, so grab the value of the destination item.
      $value = $row->getDestinationProperty($possible_token) ?? $row->getSourceProperty($possible_token);
    }
    // @todo This needs to include a check for source constants / defaults.
    // It basically needs to look for something like "constants/main_color" and
    // see if $row['source']['constants']['main_color'] exists.
    return $value;
  }

}
