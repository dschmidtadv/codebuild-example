<?php

namespace Drupal\migrate_sandbox\Form;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\Config;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Error;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_sandbox\MigrateSandboxMessage;
use Drupal\migrate_sandbox\SandboxMigration;

/**
 * Configure Migrate Sandbox settings for this site.
 */
class MigrateSandboxForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'migrate_sandbox_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'migrate_sandbox.settings',
      'migrate_sandbox.latest',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['#attached']['library'][] = 'migrate_sandbox/migrate_sandbox';
    $form['#prefix'] = '<div id="js-migrate-sandbox-wrapper">';
    $form['#suffix'] = '</div>';
    $form['populate_sandbox_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => 'Populate Sandbox',
      '#tree' => FALSE,
    ];
    $form['populate_sandbox_fieldset']['prepopulated_details'] = [
      '#type' => 'details',
      '#tree' => FALSE,
      '#title' => $this->t('Populate sandbox from starter config'),
      'help' => [
        '#markup' => $this->t('This is most useful if you are using Migrate Sandbox to play with process plugins provided by core and Migrate Plus.'),
      ],
      'prepopulated' => [
        '#title' => $this->t('Select Starter Config'),
        '#description' => $this->t('Almost all process plugins provided by the core Migrate module and the contributed Migrate Plus module have prepopulated examples available. You can select "Start from Scratch" to clear the sandbox completely.'),
        '#type' => 'select',
        '#options' => $this->getSourceOptions(),
        '#prefix' => '<div id="edit-prepopulated-wrapper">',
        '#suffix' => '</div>',
        '#ajax' => [
          'callback' => '::usePrepopulatedSource',
          'wrapper' => 'js-migrate-sandbox-wrapper',
        ],
      ],
    ];
    $form['populate_sandbox_fieldset']['populate'] = [
      '#type' => 'details',
      '#title' => $this->t('Populate sandbox from a real migration'),
      'populate_migration' => [
        '#type' => 'textfield',
        '#title' => $this->t('Migration ID'),
        '#description' => $this->t('The machine name of the migration to use for populating the embedded source.'),
        '#default_value' => $form_state->getValue('populate_migration'),
      ],
      'populate_source_ids' => [
        '#type' => 'textfield',
        '#title' => $this->t('Source IDs'),
        '#description' => $this->t('Colon separate source ids for the SINGLE ROW to return. If blank, the first row will be returned. If the source id you choose is not near the beginning of the migration, it may take a VERY LONG TIME to load.'),
        '#default_value' => $form_state->getValue('populate_source_ids'),
      ],
      'populate_process' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Update Process Pipeline'),
        '#description' => $this->t('If checked, the process pipeline in Migrate Sandbox will be updated to match this migration. If you are actively editing the process pipeline within Migrate Sandbox, you should probably uncheck this box.'),
        '#default_value' => $form_state->getValue('populate_process') ?? TRUE,
      ],
      'populate_button' => [
        '#type' => 'button',
        '#value' => $this->t('Populate'),
        '#ajax' => [
          'callback' => '::updatePopulateFromMigration',
          'wrapper' => 'js-migrate-sandbox-wrapper',
        ],
        '#attributes' => [
          'class' => [
            'button',
            'button--primary',
          ],
        ],
      ],
      'populate_next' => [
        '#type' => 'button',
        '#value' => $this->t('Fetch next row'),
        '#ajax' => [
          'callback' => '::updatePopulateFromMigration',
          'wrapper' => 'js-migrate-sandbox-wrapper',
        ],
        '#attributes' => [
          'title' => $this->t('If populating took a long time, so will fetching the next row. This feature offers convenience but does not improve computational efficiency.'),
        ],
        '#states' => [
          'visible' => [
            'input[name="populate_source_ids"]' => ['value' => 'something to make this invisible'],
            'input[name="populate_migration"]' => ['value' => 'something to make this invisible'],
          ],
        ],
      ],
    ];
    $form['intro'] = [
      '#type' => 'details',
      '#weight' => -100,
      '#title' => $this->t('Help and Disclaimers'),
      0 => [
        '#markup' => '<h3>What is Migrate Sandbox?</h3><p>This is a UI for experimenting with migrate process plugins
                        and migrate process pipelines.</p><p>To that end, example source data and pipelines have been
                        provided for almost all process plugins defined in the core Migrate module and the contributed
                        Migrate Plus module. You can also use the sandbox to debug your custom process plugins or other
                        contributed plugins. Your data can be "migrated" into either sandbox config or a sandbox content
                        entity, neither of which gets saved.</p><p>The sandbox can be populated from a real migration,
                        which allows some debugging of source plugins. When populating from an existing migration,
                        the migration plugin cache is bypassed, which allows for fast development.</p>',
      ],
      1 => [
        '#markup' => '<p><h3>How the Sandbox Works</h3>What happens in the sandbox stays in the sandbox.
                       <ol>
                       <li>The sandbox migration uses a custom id_map that does not save any migrate_map data to the db.</li>
                       <li>Instead of logging errors to the db in migrate_message tables, errors are output using on-screen messages.</li>
                       <li>Special destination plugins migrate data into entities that are displayed in the sandbox but are never saved.</li>
                       </ol>
                       </p>
                       <p>Though nothing goes directly into the database, the latest configuration
                       gets saved as configuration to <code>migrate_sandbox.latest</code>. There is always an option to select
                       <code>migrate_sandbox.latest</code> as your starter config.</p>',
      ],
      2 => [
        '#markup' => '<h4>☞ Sandbox Escape Warnings</h4><p>There are a number of process plugins from Migrate and Migrate Plus
                        that may result in side-effects outside of the sandbox. If you include any of the following process plugins
                        in your sandbox pipeline, there will be a warning above the Save & Run button.</p>
                        <ul>
                          <li>callback</li>
                          <li>dom_migration_lookup (Migrate Plus)</li>
                          <li>download</li>
                          <li>entity_generate (Migrate Plus)</li>
                          <li>file_blob (Migrate Plus)</li>
                          <li>file_copy</li>
                          <li>migration_lookup</li>
                          <li>service (Migrate Plus)</li>
                        </ul>
                      <p>You may still use these plugins in Migrate Sandbox, but you should be aware that there could be
                      persistent consequences. And keep in mind that other contributed or custom process plugins could
                      have persistent side-effects.</p>',
      ],
      3 => [
        '#markup' => '<h3>What Migrate Sandbox is Not</h3><p>Migrate Sandbox has limitations!</p>
                      <ol>
                      <li>Migrate Sandbox is not for use on production sites for any reason.</li>
                      <li>It is not intended to run real migrations.</li>
                      <li>It is not designed for debugging destination plugins.</li>
                      <li>Due to security implications, it should never be used by untrusted users.</li>
                      </ol>
                      </p>',
      ],
    ];
    if (!\Drupal::moduleHandler()->moduleExists('yaml_editor')) {
      $form['intro'][4] = [
        '#type' => 'container',
        '#markup' => $this->t('The <a href="https://drupal.org/project/yaml_editor" target="_blank">Yaml Editor</a> module is highly recommended when using Migrate Sandbox'),
        '#attributes' => [
          'class' => ['messages messages--warning'],
        ],
        '#weight' => -100,
      ];
    }

    $form['source_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => 'source',
      '#tree' => FALSE,
    ];
    if (isset($this->config('migrate_sandbox.latest')->get('source.data_rows')[0])) {
      $data_row_array = $this->config('migrate_sandbox.latest')->get('source.data_rows')[0];
      unset($data_row_array['migrate_sandbox_dummy_source_id']);
      $data_row = Yaml::encode($data_row_array);
    }
    else {
      $data_row = '';
    }
    $form['source_wrapper']['data_rows'] = [
      '#type' => 'textarea',
      '#title' => 'data_rows[0]:',
      '#description' => $this->t('You can only enter one row. It can have lots of properties. But just one row.'),
      '#attributes' => ['data-yaml-editor' => 'true'],
      '#default_value' => $data_row,
    ];
    $form['source_wrapper']['preview'] = [
      '#type' => 'details',
      '#title' => $this->t('View source row as array'),
      'button' => [
        '#type' => 'button',
        '#value' => $this->t('Update'),
        '#ajax' => [
          'callback' => '::updateSourceArrayPreview',
          'wrapper' => 'source-array-preview-wrapper',
        ],
      ],
      'preview' => [
        '#prefix' => '<div id="source-array-preview-wrapper">',
        '#suffix' => '</div>',
      ],
    ];
    $form['source_wrapper']['constants'] = [
      '#type' => 'textarea',
      '#title' => 'constants:',
      '#attributes' => ['data-yaml-editor' => 'true'],
      '#default_value' => $this->config('migrate_sandbox.latest')->get('source.constants') ? Yaml::encode($this->config('migrate_sandbox.latest')->get('source.constants')) : '',
    ];
    $form['process_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => 'process',
      '#tree' => FALSE,
    ];
    $form['process_wrapper']['process'] = [
      '#type' => 'textarea',
      '#title' => 'process:',
      '#attributes' => ['data-yaml-editor' => 'true'],
      '#default_value' => $this->config('migrate_sandbox.latest')->get('process') ? Yaml::encode($this->config('migrate_sandbox.latest')->get('process')) : '',
    ];
    $form['destination_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => 'destination',
      '#tree' => FALSE,
    ];
    if ($destination = $this->config('migrate_sandbox.latest')->get('destination.plugin')) {
      if (str_starts_with($destination, 'migrate_sandbox_entity')) {
        $default_destination = 'entity';
      }
      else {
        $default_destination = 'config';
      }
    }
    else {
      $default_destination = 'config';
    }
    $form['destination_wrapper']['destination'] = [
      '#type' => 'radios',
      '#options' => [
        'config' => $this->t('Sandbox config entity'),
        'entity' => $this->t('Sandbox content entity'),
      ],
      '#default_value' => $default_destination,
      '#description' => $this->t('Either way, the result is stored only temporarily. No entity will be saved.'),
    ];
    if (is_array($this->config('migrate_sandbox.latest')->get('destination.plugin'))) {
      $entity_type_default = explode(':', $this->config('migrate_sandbox.latest')->get('destination.plugin'))[1] ?? 'node';
    }
    else {
      $entity_type_default = 'node';
    }
    $form['destination_wrapper']['entity_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Entity type (e.g. node or taxonomy_term)'),
      '#default_value' => $entity_type_default,
      '#states' => [
        'visible' => [
          ':input[name="destination"]' => ['value' => 'entity'],
        ],
        'required' => [
          ':input[name="destination"]' => ['value' => 'entity'],
        ],
      ],
    ];
    $form['destination_wrapper']['entity_bundle'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default bundle (e.g. article or tags)'),
      '#description' => $this->t('This is required for entities with bundles (like node), but you can leave it blank for entities without bundles (like user.'),
      '#default_value' => $this->config('migrate_sandbox.latest')->get('destination.default_bundle') ?? '',
      '#states' => [
        'visible' => [
          ':input[name="destination"]' => ['value' => 'entity'],
        ],
      ],
    ];
    $form['results'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Results'),
      '#weight' => 1000,
      '#prefix' => '<div id="results-wrapper">',
      '#suffix' => '</div>',
      '#tree' => FALSE,
      'format' => [
        '#type' => 'radios',
        '#title' => $this->t('Show latest results as'),
        '#options' => [
          'yaml' => 'YAML',
          'array' => 'Array',
        ],
        '#default_value' => 'yaml',
      ],
      'yaml' => [
        '#type' => 'container',
        '#states' => [
          'visible' => [
            ':input[name="format"]' => ['value' => 'yaml'],
          ],
        ],
      ],
      'array' => [
        '#type' => 'container',
        '#states' => [
          'visible' => [
            ':input[name="format"]' => ['value' => 'array'],
          ],
        ],
      ],
    ];
    $form['actions']['submit']['#value'] = $this->t('Process Row');
    $form['actions']['submit']['#ajax'] = [
      'callback' => '::ajaxSubmit',
      'wrapper' => 'results-wrapper',
    ];
    $form['actions']['submit']['#type'] = 'button';
    $form['warnings'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attached' => [
        'drupalSettings' => [
          'migrate_sandbox_warnings' => [
            'download',
            'file_blob',
            'file_copy',
            'migration_lookup',
            'entity_generate',
            'callback',
            'service',
          ],
        ],
      ],
      '#value' => '
         <h3 class="warning-header hidden">☞ Sandbox Escape Warning</h3>
         <div class="messages messages--warning hidden" data-plugin="download">The <code>download</code> process plugin will result in files being created outside of the sandbox.</div>
         <div class="messages messages--warning hidden" data-plugin="file_blob">The <code>file_blob</code> process plugin will result in files being created outside of the sandbox.</div>
         <div class="messages messages--warning hidden" data-plugin="file_copy">The <code>file_copy</code> process plugin will result in files being created outside of the sandbox.</div>
         <div class="messages messages--warning hidden" data-plugin="migration_lookup">The <code>migration_lookup</code> process plugin can result in Drupal entities being created and saved outside of the sandbox. Use the <code>no_stub</code> configuration to prevent this.</code></div>
         <div class="messages messages--warning hidden" data-plugin="entity_generate">The <code>entity_generate</code> process plugin will result in Drupal entities being created and saved outside of the sandbox.</div>
         <div class="messages messages--warning hidden" data-plugin="callback">The <code>callback</code> process plugin can cause side-effects outside of the sandbox if the callable is not a pure function.</div>
         <div class="messages messages--warning hidden" data-plugin="service">The <code>service</code> process plugin can cause side-effects outside of the sandbox if the method is not a pure function.</div>
         <div class="messages messages--warning hidden" data-plugin="dom_migration_lookup">The <code>dom_migration_lookup</code> process plugin can result in Drupal entities being created and saved outside of the sandbox. Use the <code>no_stub</code> configuration to prevent this.</code></div>
         ',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#ajax']['callback'] == '::updateSourceArrayPreview') {
      $yaml_fields = [
        'data_rows',
      ];
    }
    else {
      $yaml_fields = [
        'data_rows',
        'constants',
        'process',
      ];
    }
    foreach ($yaml_fields as $field) {
      try {
        Yaml::decode($form_state->getValue($field));
      }
      catch (\Exception $e) {
        $form_state->setErrorByName($field, '(Yaml Validation) ' . $field . ': ' . $e->getMessage());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getErrors()) {
      $id = 'migrate_sandbox';
      $label = 'Migrate Sandbox';
      $source_id = [
        'migrate_sandbox_dummy_source_id' => '123',
      ];
      $ids = [
        'migrate_sandbox_dummy_source_id' => [
          'type' => 'integer',
        ],
      ];
      $source = [
        'plugin' => 'embedded_data',
        'data_rows' => [(Yaml::decode($form_state->getValue('data_rows')) ?? []) + $source_id],
        'constants' => Yaml::decode($form_state->getValue('constants')),
        'ids' => $ids,
      ];
      $process = Yaml::decode($form_state->getValue('process'));
      if ($form_state->getValue('destination') === 'entity') {
        $destination = [
          'plugin' => 'migrate_sandbox_entity:' . $form_state->getValue('entity_type'),
          'default_bundle' => $form_state->getValue('entity_bundle'),
        ];
      }
      else {
        $destination = [
          'plugin' => 'migrate_sandbox_config',
          'config_name' => 'migrate_sandbox.processed',
        ];
      }
      $this->config('migrate_sandbox.latest')
        ->setData([
          'id' => $id,
          'label' => $label,
          'source' => $source,
          'process' => $process,
          'destination' => $destination,
        ])
        ->save();
      $latest = $this->runMigration();
      if ($latest instanceof Config) {
        $results = $latest->get('results');
      }
      elseif ($latest instanceof ContentEntityInterface) {
        $results = $latest->toArray();
      }
      else {
        $results = NULL;
      }
      $form['results']['value']['#value'] = $latest;
      $form['results']['yaml'][0]['#markup'] = '<pre>' . HTML::escape(Yaml::encode($results)) . '</pre>';
      $form['results']['array'][0]['#markup'] = '<pre>' . HTML::escape(print_r($results, TRUE)) . '</pre>';
      $form_state->setRebuild();
    }
    return $form['results'];
  }

  /**
   * {@inheritdoc}
   */
  protected function runMigration() {
    $definition = $this->config('migrate_sandbox.latest')->getRawData();
    $migration = SandboxMigration::create(\Drupal::getContainer(), [], 'migrate_sandbox', $definition);
    $migration->setStatus(MigrationInterface::STATUS_IDLE);
    $executable = new MigrateExecutable($migration, new MigrateSandboxMessage());
    try {
      \Drupal::service('tempstore.private')->get('migrate_sandbox')->delete('migrate_sandbox.latest');
      $executable->import();
      return \Drupal::service('tempstore.private')->get('migrate_sandbox')->get('migrate_sandbox.latest');
    }
    catch (\Throwable $e) {
      $error = Error::decodeException($e);
      \Drupal::messenger()->addError($this->t('(Uncaught Throwable) %type: @message in %function (line %line of %file).', $error));
      $error['@backtrace'] = Error::formatBacktrace($error['backtrace']);
      \Drupal::messenger()->addError($this->t('<pre class="backtrace">@backtrace</pre>', $error));
      return [];
    }
  }

  /**
   * Get array of source options.
   */
  protected function getSourceOptions() {
    $sources = $this->config('migrate_sandbox.settings')->getRawData()['sources'];
    $options = ['' => 'migrate_sandbox.latest'];
    foreach ($sources as $source) {
      $options[$source['name']] = $source['name'];
    }
    return $options;
  }

  /**
   * Handles using prepopulated source data.
   */
  public function usePrepopulatedSource($form, FormStateInterface $form_state) {
    if ($source_name = $form_state->getValue('prepopulated')) {
      $sources = $this->config('migrate_sandbox.settings')->getRawData()['sources'];
      $prepopulated = [];
      foreach ($sources as $source) {
        if ($source['name'] === $form_state->getValue('prepopulated')) {
          $prepopulated = $source;
          break;
        }
      }
    }
    else {
      $prepopulated = $this->config('migrate_sandbox.latest')->getRawData();
      if (empty($prepopulated)) {
        $prepopulated = [
          'source' => [
            'data_rows' => NULL,
            'constants' => NULL,
          ],
          'process' => NULL,
        ];
      }
    }
    if (isset($prepopulated['source']['data_rows'][0])) {
      unset($prepopulated['source']['data_rows'][0]['migrate_sandbox_dummy_source_id']);
      $form['source_wrapper']['data_rows']['#value'] = Yaml::encode($prepopulated['source']['data_rows'][0]);
    }
    else {
      $form['source_wrapper']['data_rows']['#value'] = '';
    }
    $form['source_wrapper']['constants']['#value'] = isset($prepopulated['source']['constants']) ? Yaml::encode($prepopulated['source']['constants']) : '';
    $form['process_wrapper']['process']['#value'] = isset($prepopulated['process']) ? Yaml::encode($prepopulated['process']) : '';
    $form['source_wrapper']['preview']['preview']['#markup'] = NULL;
    $form['results']['yaml'][0]['#markup'] = NULL;
    $form['results']['array'][0]['#markup'] = NULL;
    $form_state->setRebuild();
    return $form;
  }

  /**
   * Updates source array preview.
   */
  public function updateSourceArrayPreview($form, FormStateInterface $form_state) {
    try {
      $data_row_array = Yaml::decode($form_state->getValue('data_rows'));
      $preview = print_r($data_row_array, TRUE);
      $form['source_wrapper']['preview']['preview']['#markup'] = '<pre>' . HTML::escape($preview) . '</pre>';
    }
    catch (\Exception $e) {
      // Message will be shown due to validation function.
    }
    $form_state->setRebuild();
    return $form['source_wrapper']['preview']['preview'];
  }

  /**
   * Populates sandbox using a real migration.
   */
  public function updatePopulateFromMigration($form, FormStateInterface $form_state) {
    $migration_id = $form_state->getValue('populate_migration');
    try {
      // Try instantiating the given migration and getting its source.
      // First, clear the cache to facilitate rapid development.
      \Drupal::service('plugin.manager.migration')->clearCachedDefinitions();
      $migration = \Drupal::service('plugin.manager.migration')->createInstance($migration_id);
      if (empty($migration)) {
        throw new PluginNotFoundException($migration_id);
      }
      $migration_definition = $migration->getPluginDefinition();
      $source_definition = $migration_definition['source'];
      // We have to create a dummy migration to pass the source plugin manager.
      $sandbox_migration = SandboxMigration::create(\Drupal::getContainer(), [], 'migrate_sandbox', $migration->getPluginDefinition());
      // Create an instance of the source that we can iterate over.
      $source = \Drupal::service('plugin.manager.migrate.source')->createInstance($source_definition['plugin'], $source_definition, $sandbox_migration);
      $source->rewind();
      if ($form_state->getValue('populate_source_ids') !== '') {
        $ids = explode(",", $form_state->getValue('populate_source_ids'));
        if ($form_state->getTriggeringElement()['#attributes']['data-drupal-selector'] === 'edit-populate-next') {
          $next = TRUE;
        }
        while ($row = $source->current()) {
          // Strict equality below is cumbersome because input is a string, but
          // the true type of the source id may be an integer.
          if (array_values($row->getSourceIdValues()) == $ids) {
            $data = $row->getSource();
            break;
          }
          $source->next();
        }
        if ($next) {
          $data = NULL;
          $source->next();
          if ($row = $source->current()) {
            $data = $row->getSource();
          }
          else {
            throw new \InvalidArgumentException("No more rows in migration $migration_id");
          }
        }
        if (is_null($data)) {
          throw new \InvalidArgumentException("No row found for source ids {$form_state->getValue('populate_source_ids')} in migration $migration_id");
        }
      }
      else {
        $row = $source->current();
        $data = $row->getSource();
      }
    }
    catch (\Throwable $e) {
      $form['populate_sandbox_fieldset']['populate']['#open'] = TRUE;
      $messages = [
        '#type' => 'status_messages',
      ];
      array_unshift($form['populate_sandbox_fieldset']['populate'], $messages);
      \Drupal::messenger()->addError($e->getMessage());
    }
    if ($row) {
      // Always make sure the source ids field is up-to-date.
      $ids = implode(':', $row->getSourceIdValues());
      $form['populate_sandbox_fieldset']['populate']['populate_source_ids']['#value'] = $ids;
      $form_state->setValue('populate_source_ids', $ids);
      $form['populate_sandbox_fieldset']['populate']['populate_next']['#states'] = [
        'visible' => [
          'input[name="populate_source_ids"]' => ['value' => $ids],
          'input[name="populate_migration"]' => ['value' => $migration_id],
        ],
      ];
    }
    else {
      $form['populate_sandbox_fieldset']['populate']['populate_next']['#states'] = [
        'visible' => [
          'input[name="populate_source_ids"]' => ['value' => 'something to make this invisible'],
          'input[name="populate_migration"]' => ['value' => 'something to make this invisible'],
        ],
      ];
    }
    if ($data) {
      // The data also has things like constants and ids that are really part
      // of the source defintion, not the row. Remove those values.
      $data = array_diff_key($data, $source_definition);
      $form['source_wrapper']['data_rows']['#value'] = Yaml::encode($data);
      \Drupal::messenger()->addStatus("Row retrieved for source ids {$form_state->getValue('populate_source_ids')} in migration $migration_id");
    }
    else {
      $form['source_wrapper']['data_rows']['#value'] = '';
    }
    $form['source_wrapper']['constants']['#value'] = isset($source_definition['constants']) ? Yaml::encode($source_definition['constants']) : '';
    if ($form_state->getValue('populate_process')) {
      $form['process_wrapper']['process']['#value'] = isset($migration_definition['process']) ? Yaml::encode($migration_definition['process']) : '';
    }
    $form['results']['yaml'][0]['#markup'] = NULL;
    $form['results']['array'][0]['#markup'] = NULL;
    $form_state->setRebuild();
    return $form;
  }

}
