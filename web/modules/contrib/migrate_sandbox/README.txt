INTRODUCTION
------------
Migrate Sandbox is a UI where developers can quickly and safely experiment
with migrate process plugins and migrate process pipelines.

To that end, example source data and pipelines have been provided for almost
all process plugins defined in the core Migrate module and the contributed
Migrate Plus module. That's examples for almost 50 plugins! You can also use
the sandbox to debug your custom process plugins or other contributed plugins.

Migrate Sandbox is an especially good tool for grokking the hard-to-grok
process plugins such as sub_process, migration_lookup, entity_generate,
and transpose, not to mention all the dom-related ones.

The starter config provided for each of these plugins gives you a great
jumping-off point.

See the project page for more information, including lots of screenshots.
https://www.drupal.org/project/migrate_sandbox

REQUIREMENTS
------------
The core Migrate module.

RECOMMENDED MODULES
-------------------
Yaml Editor: if you enable yaml_editor the editing experience will be much, much
  better. The main screenshot on the project page has yaml_editor enabled.

Migrate Plus: If you're doing migrations, you would probably benefit from this
  module. There are many examples in Migrate Sandbox for plugins from Migrate Plus.

Migrate Example: This is a sub-module of Migrate Plus. The migration_lookup example
  provided with Migrate Sandbox is based on the beer_term migration in Migrate
  Example. Running that migration would make the example work without any editing.

INSTALLATION
------------
This module is intended for development purposes only. It should never be used on
a production site. You should disable it when not using it.

Ideally you should require this module using composer, like with any typical contrib
module. You should do whatever works for you, though. It's just a development module,
and it will never have dependencies outside of core.

CONFIGURATION
-------------
The input used for running the most recent sandbox migration is saved to
migrate_sandbox.latest. There is always an option to select migrate_sandbox.latest
as your starter config.

MAINTAINERS
-----------
danflanagan8, migrate enthusiast
