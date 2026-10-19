<?php

/**
 * @file
 * The site search index (user ask, 19 Oct 2026: "what should we use for this
 * site, Solr or another suggestion?" — Search API on the database: a few
 * hundred items, grouped and weighted, no service to run). One server on
 * the database, one index `site` over Work, News and Team nodes and the
 * Canvas pages: an aggregated title (boost 5), the case study's client (3)
 * and statement (2), and each item's rendered output (1) — the Canvas-built
 * body included — with HTML stripped, stemmed, case-folded, tokenised, and
 * an excerpt highlighted for the results. Idempotent: creates what is
 * missing, leaves what exists. Then `drush cex` and
 * `drush search-api:index`.
 *
 * Usage: ddev exec drush php:script scripts/search-index.php
 */

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;

if (!Server::load('database')) {
  Server::create([
    'id' => 'database',
    'name' => 'Database',
    'description' => "The site's own database — enough for a few hundred items (19 Oct 2026); Solr would be a swap of this server, nothing else.",
    'backend' => 'search_api_db',
    'backend_config' => ['database' => 'default:default', 'min_chars' => 3, 'matching' => 'words', 'autocomplete' => ['suggest_suffix' => TRUE, 'suggest_words' => TRUE]],
  ])->save();
  print "server created\n";
}

if (!Index::load('site')) {
  $text = static fn(string $label, string $path, float $boost, ?string $datasource = NULL, array $extra = []): array => array_filter([
    'label' => $label,
    'datasource_id' => $datasource,
    'property_path' => $path,
    'type' => 'text',
    'boost' => $boost,
  ] + $extra, static fn($v) => $v !== NULL);
  Index::create([
    'id' => 'site',
    'name' => 'Site',
    'description' => 'Work, News, Team and the pages — what the pill searches.',
    'server' => 'database',
    'status' => TRUE,
    'read_only' => FALSE,
    'datasource_settings' => [
      'entity:node' => ['bundles' => ['default' => FALSE, 'selected' => ['work', 'news', 'team_member']], 'languages' => ['default' => TRUE, 'selected' => []]],
      'entity:canvas_page' => ['languages' => ['default' => TRUE, 'selected' => []]],
    ],
    'field_settings' => [
      'title' => $text('Title', 'aggregated_field', 5.0, NULL, ['configuration' => ['type' => 'union', 'fields' => ['entity:node/title', 'entity:canvas_page/title']]]),
      'client' => $text('Client', 'field_work_client', 3.0, 'entity:node'),
      'statement' => $text('Feature statement', 'field_work_statement', 2.0, 'entity:node'),
      'rendered_item' => $text('Rendered output', 'rendered_item', 1.0, NULL, ['configuration' => ['roles' => ['anonymous'], 'view_mode' => ['entity:node' => ['work' => 'search_index', 'news' => 'search_index', 'team_member' => 'search_index'], 'entity:canvas_page' => ['canvas_page' => 'default']]]]),
      'type' => ['label' => 'Content type', 'datasource_id' => 'entity:node', 'property_path' => 'type', 'type' => 'string'],
      'created' => ['label' => 'Created', 'datasource_id' => 'entity:node', 'property_path' => 'created', 'type' => 'date'],
      'status' => ['label' => 'Published', 'datasource_id' => 'entity:node', 'property_path' => 'status', 'type' => 'boolean', 'indexed_locked' => TRUE, 'type_locked' => TRUE],
      'page_status' => ['label' => 'Page published', 'datasource_id' => 'entity:canvas_page', 'property_path' => 'status', 'type' => 'boolean'],
    ],
    'processor_settings' => [
      'add_url' => [],
      'aggregated_field' => [],
      'rendered_item' => [],
      'entity_status' => [],
      'html_filter' => ['fields' => ['rendered_item', 'title', 'client', 'statement'], 'title' => TRUE, 'alt' => TRUE, 'tags' => ['h1' => 5, 'h2' => 3, 'h3' => 2, 'strong' => 2, 'b' => 2]],
      'ignorecase' => ['fields' => ['rendered_item', 'title', 'client', 'statement']],
      'tokenizer' => ['fields' => ['rendered_item', 'title', 'client', 'statement'], 'spaces' => '', 'overlap_cjk' => 1, 'minimum_word_size' => '3'],
      'stemmer' => ['fields' => ['rendered_item', 'title', 'client', 'statement'], 'exceptions' => []],
      'highlight' => ['highlight' => 'always', 'highlight_partial' => TRUE, 'excerpt' => TRUE, 'excerpt_always' => TRUE, 'excerpt_length' => 200, 'exclude_fields' => ['title', 'client', 'statement'], 'prefix' => '<mark>', 'suffix' => '</mark>'],
    ],
    'tracker_settings' => ['default' => ['indexing_order' => 'fifo']],
    'options' => ['cron_limit' => 50, 'index_directly' => TRUE, 'track_changes_in_references' => TRUE],
  ])->save();
  print "index created\n";
}
print "done\n";
