<?php

namespace Drupal\migrate_sandbox\Plugin\migrate\destination;

use Drupal\migrate\Plugin\migrate\destination\Config;
use Drupal\migrate\Row;

/**
 * Destination used by Migrate Sandbox to migrate into unsaved config.
 *
 * @MigrateDestination(
 *   id = "migrate_sandbox_config"
 * )
 */
class MigrateSandboxConfig extends Config {

  /**
   * {@inheritdoc}
   */
  public function import(Row $row, array $old_destination_id_values = []) {
    // We prepend 'results.' to the keys so we can validate schema.
    foreach ($row->getRawDestination() as $key => $value) {
      if (isset($value) || !empty($this->configuration['store null'])) {
        $this->config->set('results.' . str_replace(Row::PROPERTY_SEPARATOR, '.', $key), $value);
      }
    }
    $ids[] = $this->config->getName();
    \Drupal::service('tempstore.private')->get('migrate_sandbox')->set('migrate_sandbox.latest', $this->config);
    return $ids;
  }

}
