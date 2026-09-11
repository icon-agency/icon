<?php

/**
 * @file
 * Builds each Work article's Canvas body (field_work_canvas) from its
 * Paragraphs body (field_work_content): the same blocks as the same
 * components, the runs the field template used to infer drawn as Run
 * components, consecutive Gallery rows as sibling Gallery components.
 * The paragraphs are left in place (hidden from the form) so the change
 * can be undone by emptying the Canvas field. Run it again to rebuild.
 *
 * Run: ddev exec "ICON_SEED=1 drush php:script scripts/work-canvas.php"
 * One node, or some: ICON_NIDS=16,300 in the environment as well.
 */

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\user\Entity\User;

require_once __DIR__ . '/_guard.php';

// Drush runs as the anonymous user, who may not keep a published article
// published; the save is made as the site owner.
\Drupal::service('account_switcher')->switchTo(User::load(1));

$uuid = \Drupal::service('uuid');
$version = static function (string $id): string {
  $c = \Drupal::entityTypeManager()->getStorage('component')->load($id);
  if (!$c) {
    throw new \RuntimeException("Component $id is not registered.");
  }
  return (string) $c->get('active_version');
};
// A media item's file URL through the article's image style, for the
// hosted film's src (icon_site_media_source(), the theme's own helper).
$source = static fn($media): array => $media ? icon_site_media_source($media, 'work_media') : [];
// Optional props are left out when empty rather than sent as ''.
$clean = static fn(array $inputs): array => array_filter($inputs, static fn($v) => $v !== '' && $v !== NULL && $v !== []);

$nids = getenv('ICON_NIDS')
  ? array_map('intval', explode(',', (string) getenv('ICON_NIDS')))
  : \Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type', 'work')->execute();

foreach (Node::loadMultiple($nids) as $node) {
  if ($node->bundle() !== 'work') {
    continue;
  }
  $tree = [];
  $add = static function (string $component, array $inputs, ?string $parent = NULL, ?string $slot = NULL) use (&$tree, $uuid, $version): string {
    $id = $uuid->generate();
    $tree[] = [
      'parent_uuid' => $parent,
      'slot' => $slot,
      'uuid' => $id,
      'component_id' => $component,
      'component_version' => $version($component),
      'inputs' => json_encode($inputs ?: new \stdClass(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
      'label' => NULL,
    ];
    return $id;
  };
  // A run is text and what travels with it under one Share rail; the rest
  // breaks the flow (icon_preprocess_field(), the Paragraphs rule).
  $run_types = ['prose', 'work_video', 'news_article_video', 'work_stats'];
  $run = NULL;
  $skipped = [];
  foreach ($node->get('field_work_content')->referencedEntities() as $p) {
    if (!$p instanceof ParagraphInterface) {
      continue;
    }
    $bundle = $p->bundle();
    if (in_array($bundle, $run_types, TRUE)) {
      $run ??= $add('sdc.icon.run', []);
      [$parent, $slot] = [$run, 'content'];
    }
    else {
      $run = NULL;
      [$parent, $slot] = [NULL, NULL];
    }
    switch ($bundle) {
      case 'prose':
        // The Content block's editor is canvas_html_block; the paragraph's
        // basic_html body is the same HTML minus inline images.
        $add('sdc.icon.prose', ['text' => ['value' => $p->get('field_prose_text')->value ?? '', 'format' => 'canvas_html_block']], $parent, $slot);
        break;

      case 'pull_quote':
        $add('sdc.icon.pull-quote', $clean([
          'text' => $p->get('field_pull_quote_text')->value ?? '',
          'variant' => $p->get('field_pull_quote_variant')->value ?: 'italic',
          'cite' => $p->get('field_pull_quote_cite')->value ?? '',
        ]));
        break;

      case 'news_article_video':
        $add('sdc.icon.news-article-video', $clean([
          'embed_url' => ['uri' => $p->get('field_news_article_video_url')->uri ?? ''],
          'title' => $p->get('field_news_article_video_title')->value ?? '',
        ]), $parent, $slot);
        break;

      case 'work_video':
        $film = $source($p->get('field_work_video_media')->entity);
        $cover = $p->get('field_work_video_cover')->entity;
        if (!$film || !$cover) {
          $skipped[] = "$bundle {$p->id()} (no film or cover)";
          break;
        }
        $add('sdc.icon.work-video', [
          'video_src' => ['uri' => 'internal:' . $film['src']],
          'cover' => ['target_id' => $cover->id()],
          'title' => $p->get('field_work_video_title')->value ?: $node->label(),
        ], $parent, $slot);
        break;

      case 'work_stats':
        $band = $add('sdc.icon.work-stats', [], $parent, $slot);
        foreach ($p->get('field_work_stats_items')->referencedEntities() as $stat) {
          $add('sdc.icon.work-stat', $clean([
            'value' => $stat->get('field_work_stat_value')->value ?? '',
            'unit' => $stat->get('field_work_stat_unit')->value ?? '',
            'label' => $stat->get('field_work_stat_label')->value ?? '',
          ]), $band, 'results');
        }
        break;

      case 'work_gallery_row':
        $row = $add('sdc.icon.work-gallery', []);
        foreach ($p->get('field_work_gallery_figures')->referencedEntities() as $figure) {
          $media = $figure->get('field_work_figure_media')->referencedEntities();
          $style = $figure->get('field_work_figure_style')->value ?: 'plain';
          // Editors list the foreground first; the component wants the
          // ground first (icon.theme, the same reversal).
          if (str_starts_with($style, 'layered')) {
            $media = array_reverse($media);
          }
          if (!$media) {
            $skipped[] = "figure {$figure->id()} (no media)";
            continue;
          }
          // A film is its own prop, with the layer it takes (ground first).
          $film = NULL;
          $film_layer = 'ground';
          $pictures = [];
          foreach (array_values($media) as $i => $m) {
            if ($m->bundle() === 'video') {
              $film = ['target_id' => $m->id()];
              $film_layer = $i > 0 ? 'float' : 'ground';
            }
            else {
              $pictures[] = ['target_id' => $m->id()];
            }
          }
          $add('sdc.icon.work-gallery-figure', $clean([
            'media' => $pictures,
            'film' => $film,
            'film_layer' => $film ? $film_layer : '',
            'style' => $style,
            'ground' => $figure->get('field_work_figure_ground')->value ?? '',
            'pad' => $figure->get('field_work_figure_inset')->value ?? '',
          ]), $row, 'figures');
        }
        break;

      case 'work_scroller':
        $items = [];
        $films = [];
        foreach ($p->get('field_work_scroller_media')->referencedEntities() as $m) {
          if ($m->bundle() === 'video') {
            $films[] = ['target_id' => $m->id()];
          }
          else {
            $items[] = ['target_id' => $m->id()];
          }
        }
        $add('sdc.icon.work-scroller', $clean([
          'items' => $items,
          'films' => $films,
          'ground' => $p->get('field_work_scroller_ground')->value ?? '',
        ]));
        break;

      default:
        $skipped[] = "$bundle {$p->id()}";
    }
  }
  $node->set('field_work_canvas', $tree);
  $violations = $node->validate();
  if (count($violations)) {
    print "Node {$node->id()} ({$node->label()}): NOT saved —\n";
    foreach ($violations as $v) {
      print '  ' . $v->getPropertyPath() . ': ' . strip_tags((string) $v->getMessage()) . "\n";
    }
    continue;
  }
  $node->setNewRevision(TRUE);
  $node->setRevisionLogMessage('Body built in Canvas from the Paragraphs body (scripts/work-canvas.php).');
  $node->save();
  \Drupal::service(AutoSaveManager::class)->delete($node);
  print "Node {$node->id()} ({$node->label()}): " . count($tree) . ' components' . ($skipped ? ' — skipped ' . implode(', ', $skipped) : '') . "\n";
}
