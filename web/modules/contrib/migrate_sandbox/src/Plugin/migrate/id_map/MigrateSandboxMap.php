<?php

namespace Drupal\migrate_sandbox\Plugin\migrate\id_map;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Render\Markup;
use Drupal\migrate\Plugin\migrate\id_map\NullIdMap;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\MigrateMessageInterface;

/**
 * It spits messages out using messenger. Nothing goes to db.
 *
 * @PluginID("migrate_sandbox")
 */
class MigrateSandboxMap extends NullIdMap {

  /**
   * {@inheritdoc}
   */
  public function setMessage(MigrateMessageInterface $message) {
    $this->message = $message;
  }

  /**
   * {@inheritdoc}
   */
  public function saveMessage(array $source_id_values, $message, $level = MigrationInterface::MESSAGE_ERROR) {
    // The next line prevents double-escaping of the message.
    $message = Markup::create(Xss::filter($message));
    switch ($level) {
      case MigrationInterface::MESSAGE_INFORMATIONAL:
        \Drupal::messenger()->addMessage($this->t('(Migrate Message) @message', ['@message' => $message]), 'status');
        break;

      case MigrationInterface::MESSAGE_NOTICE:
        \Drupal::messenger()->addMessage($this->t('(Migrate Message) @message', ['@message' => $message]), 'warning');
        break;

      default:
        \Drupal::messenger()->addError($this->t('(Migrate Message) @message', ['@message' => $message]));
    }
  }

}
