<?php

namespace Drupal\migrate_sandbox\Plugin\Derivative;

use Drupal\migrate\Plugin\Derivative\MigrateEntity;

/**
 * Deriver for destination that doesn't save entities.
 */
class MigrateSandboxEntity extends MigrateEntity {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach ($this->entityDefinitions as $entity_type => $entity_info) {
      if (is_subclass_of($entity_info->getClass(), 'Drupal\Core\Entity\ContentEntityInterface')) {
        $class = 'Drupal\migrate_sandbox\Plugin\migrate\destination\MigrateSandboxEntityContentBase';
        $this->derivatives[$entity_type] = [
          'id' => "migrate_sandbox_entity:$entity_type",
          'class' => $class,
          'requirements_met' => 1,
          'provider' => $entity_info->getProvider(),
        ];
      }
    }
    return $this->derivatives;
  }

}
