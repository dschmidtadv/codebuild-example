<?php

namespace Drupal\migrate_sandbox\Plugin\migrate\destination;

use Drupal\migrate\Exception\EntityValidationException;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;

/**
 * Provides destination for content entity that we don't save.
 */
class MigrateSandboxEntityContentBase extends MigrateSandboxEntity {

  /**
   * {@inheritdoc}
   */
  public function import(Row $row, array $old_destination_id_values = []) {
    if ($bundle = $this->getBundle($row)) {
      $row->setDestinationProperty($this->getKey('bundle'), $bundle);
    }
    $entity = $this->storage->create($row->getDestination());
    if (!$entity) {
      throw new MigrateException('Unable to create sandbox entity');
    }
    // Add a title to the entity to avoid annoying validation error.
    if (!$entity->label()) {
      $label_key = $entity->getEntityType()->getKey('label');
      $entity->set($label_key, 'Dummy ' . $label_key);
      $this->migration->getIdMap()->saveMessage($row->getSourceIdValues(), $this->t('Dummy value used for entity label to avoid validation error.'), MigrationInterface::MESSAGE_INFORMATIONAL);
    }
    \Drupal::service('tempstore.private')->get('migrate_sandbox')->set('migrate_sandbox.latest', $entity);
    $violations = $entity->validate();
    if (count($violations) > 0) {
      throw new EntityValidationException($violations);
    }
    $entity->delete();
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [];
  }

}
