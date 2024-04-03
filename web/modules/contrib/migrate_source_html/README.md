## Migrate Source HTML

Migrate remote HTML pages into Drupal entities.

### Description
This module provides a HTML and HTML5 data parsers and when used in combination with [Migrate Plus](https://www.drupal.org/project/)
`url` source plugin let you migrate HTML pages - partially or entirely - with the Migration framework.

### Usage

Download and enable the module. [Migrate Plus](https://www.drupal.org/project/) is declared as dependency so make sure
you have it.

#### Examples

Migrate Article's HTML page content to Paragraph(s):

```yaml
# Meta configuration.
id: site_news_text
migration_tags:
  - News
migration_group: default
label: Text Paragraphs
langcode: en
status: true

# Source configuration.
source:
  plugin: url
  urls:
    - https://example.org/news//14281936
    - https://example.org/news//14282128
    - https://example.org/news//14282487
    - https://example.org/news//14282714
    - https://example.org/news//14282732
    - https://example.org/news//14282738
    - https://example.org/news//14282924
    - https://example.org/news//14283283
  data_fetcher_plugin: http
  data_parser_plugin: html5
  item_selector: '/'
  fields:
    - name: content_id
      label: 'Content ID'
      # Use <meta property="post_id" content="4321"/> as row/content ID.
      selector: '//head/meta[@property="post_id"]/@content'
    - name: content
      lable: 'Content'
      # Use <article class="content">*</article> for the content. The wrapper will be included, so to get only children
      # tags use '//article[@class="content"]/*'.
      selector: '//article[@class="content"]/'
  ids:
    content_id:
      type: string

# Destination configuration.
destination:
  plugin: 'entity_reference_revisions:paragraph'
  default_bundle: text

# Process configuration.
process:
  field_text/value:
    -
      plugin: get
      source: 'content'
  field_text/format:
    -
      plugin: default_value
      default_value: full_html
```

Migrate multiple items in the page:

```yaml
# Meta configuration.
id: site_news_text
migration_tags:
  - News
migration_group: default
label: Text Paragraphs
langcode: en
status: true

# Source configuration.
source:
  plugin: url
  urls:
    - https://example.org/news//14281936
  data_fetcher_plugin: http
  data_parser_plugin: html5
  # There are multiple instances of this item in the page. Each of them is considered a migration item/row.
  item_selector: '/article[@class="content"]'
  fields:
    - name: content_id
      label: 'Content ID'
      # Content ID is now in the `id` attribute of each <article> item/row.
      selector: '@id'
    - name: content
      lable: 'Content'
      # Now the current item root is our content
      selector: './'
  ids:
    content_id:
      type: string

# [...]
```
