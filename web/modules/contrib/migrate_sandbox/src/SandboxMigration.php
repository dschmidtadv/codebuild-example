<?php

namespace Drupal\migrate_sandbox;

use Drupal\migrate\Plugin\Migration;

/**
 * Use our map.
 */
class SandboxMigration extends Migration {

  /**
   * {@inheritdoc}
   */
  public function getIdMap() {
    if (!isset($this->idMapPlugin)) {
      $this->idMapPlugin = $this->idMapPluginManager->createInstance('migrate_sandbox', [], $this);
    }
    return $this->idMapPlugin;
  }

}
