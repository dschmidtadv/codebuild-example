<?php

namespace Drupal\migrate_sandbox;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\migrate\MigrateMessage;

/**
 * Use messenger instead of logger.
 */
class MigrateSandboxMessage extends MigrateMessage {

  /**
   * {@inheritdoc}
   */
  public function display($message, $type = 'status') {
    $type = $this->map[$type] ?? RfcLogLevel::NOTICE;
    \Drupal::messenger()->addMessage('(Migrate Log) ' . $message, $type);
  }

}
