<?php

namespace Drupal\migrate_sandbox\Plugin\migrate\destination;

use Drupal\Core\Entity\EntityInterface;
use Drupal\migrate\Plugin\migrate\destination\Entity;
use Drupal\migrate\Row;

/**
 * Provides a generic destination to "import" entities without saving.
 *
 * @MigrateDestination(
 *   id = "migrate_sandbox_entity",
 *   deriver = "Drupal\migrate_sandbox\Plugin\Derivative\MigrateSandboxEntity"
 * )
 */
abstract class MigrateSandboxEntity extends Entity {

  /**
   * {@inheritdoc}
   */
  protected function updateEntity(EntityInterface $entity, Row $row) {
    // This function is empty on purpose, as this module should save nothing.
  }

}
