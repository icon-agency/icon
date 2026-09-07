<?php

/**
 * @file
 * Mirrors news stories from iconagency.com.au into News content — see the
 * header of extract.py for the steps. Reads /tmp/import/news/articles.json
 * (extract.py output) and the media files beside it; matches a story by title
 * (a sample story with the same title is updated, never duplicated), then by
 * alias; unpublishes the sample stories with no counterpart on the old site.
 * A local film becomes a Work video paragraph (the news body allows it), a
 * YouTube film a News article video whose embed URL is listed in $embeds.
 *
 * Run from drupal/:  ddev drush php:script /tmp/import/news/import.php
 * (the script reads its own folder, so any folder works)
 */
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

require_once __DIR__ . '/../_guard.php';

// The folder this script sits in holds articles.json, the media files and
// (optionally) all-urls.txt — push them all together, run from there.
$src = __DIR__;
$articles = json_decode(file_get_contents("$src/articles.json"), TRUE);
$fs = \Drupal::service('file_system');
$dest = 'public://news';
$fs->prepareDirectory($dest, $fs::CREATE_DIRECTORY | $fs::MODIFY_PERMISSIONS);

$local = function (string $url): string {
  $base = str_replace(' ', '-', rawurldecode(basename(parse_url($url, PHP_URL_PATH))));
  $base = str_replace(['(', ')'], '', $base);
  if (file_exists("$GLOBALS[src]/$base")) { return $base; }
  $jpg = preg_replace('/\.png$/i', '.jpg', $base);
  if (file_exists("$GLOBALS[src]/$jpg")) { return $jpg; }
  throw new \RuntimeException("missing file for $url ($base)");
};
$GLOBALS['src'] = $src;
$media = function (string $bundle, string $file, string $alt) use ($src, $dest, $fs): Media {
  $existing = \Drupal::entityTypeManager()->getStorage('media')->loadByProperties(['bundle' => $bundle, 'name' => $file]);
  if ($existing) { return reset($existing); }
  $uri = $fs->copy("$src/$file", "$dest/$file", $fs::EXISTS_REPLACE);
  $f = File::create(['uri' => $uri, 'status' => 1]); $f->save();
  $field = $bundle === 'video' ? ['field_media_video_file' => ['target_id' => $f->id()]] : ['field_media_image' => ['target_id' => $f->id(), 'alt' => $alt]];
  $m = Media::create(['bundle' => $bundle, 'name' => $file] + $field); $m->save();
  return $m;
};
$image = fn(string $url, string $alt) => $media('image', $local($url), $alt);

// A block's oEmbed URL (YouTube / Vimeo, from the old page's inline state)
// → the embed URL the News article video paragraph plays. A film the state
// did not carry can be named here by its title.
$embeds = ['Being BodyKind this BodyKind August' => 'https://www.youtube-nocookie.com/embed/9kqQ7fBv7bM?rel=0&modestbranding=1&playsinline=1'];
$embed = function (string $oembed): string {
  if (preg_match('#(?:youtube\.com/watch\?(?:.*&)?v=|youtu\.be/|youtube\.com/embed/)([A-Za-z0-9_-]{6,})#', $oembed, $m)) {
    return "https://www.youtube-nocookie.com/embed/{$m[1]}?rel=0&modestbranding=1&playsinline=1";
  }
  if (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $oembed, $m)) {
    return "https://player.vimeo.com/video/{$m[1]}?dnt=1";
  }
  return '';
};
// A hosted film (an mp4 the old site served) → a Work video paragraph: the
// file, its poster from the old site (else the story's tile) as the cover.
$films = ['esafety-30-secs.mp4' => ['cover' => 'tile-sextortion-campaign.jpg', 'title' => 'If sextortionists were honest — the eSafety campaign film']];
// The old site's every story path: a News item whose alias is one of them
// was mirrored (this run or an earlier one) and is left alone; the rest are
// the invented samples, unpublished.
$mirrored = file_exists("$src/all-urls.txt") ? array_flip(array_filter(array_map('trim', file("$src/all-urls.txt")))) : [];

$storage = \Drupal::entityTypeManager()->getStorage('node');
$all = $storage->loadByProperties(['type' => 'news']);
$byTitle = [];
foreach ($all as $n) { $byTitle[mb_strtolower(trim($n->label()))] = $n; }
$touched = [];

foreach ($articles as $a) {
  $alias = parse_url($a['source'] ?: '', PHP_URL_PATH) ?: ('/news/' . preg_replace('/^.*\/news\//', '', $a['file']));
  $node = $byTitle[mb_strtolower(trim($a['title']))] ?? NULL;
  if (!$node) {
    $nid = \Drupal::service('path_alias.repository')->lookupByAlias($alias, 'en')['path'] ?? NULL;
    $node = $nid ? Node::load((int) substr($nid, 6)) : NULL;
  }
  $created = !$node;
  $node = $node ?: Node::create(['type' => 'news', 'uid' => 1]);
  // the body it had is replaced only once the new one has saved (below)
  $old_body = $node->get('field_news_content')->referencedEntities();
  $paras = [];
  foreach ($a['blocks'] as $b) {
    switch ($b['type']) {
      case 'prose':
        $html = str_replace(['<div>', '</div>'], '', $b['html']);
        $paras[] = Paragraph::create(['type' => 'prose', 'field_prose_text' => ['value' => $html, 'format' => 'basic_html']]);
        break;
      case 'figure':
        try { $paras[] = Paragraph::create(['type' => 'news_article_figure', 'field_news_article_figure_image' => ['target_id' => $image($b['url'], $b['alt'])->id()]]); }
        catch (\RuntimeException $e) { echo "  ! figure skipped in {$a['title']}: {$e->getMessage()}\n"; }
        break;
      case 'remote_video':
        $url = $embeds[$b['title']] ?? $embed($b['oembed'] ?? '');
        if (!$url) { echo "  ! no embed for film '{$b['title']}' in {$a['title']}\n"; break; }
        $paras[] = Paragraph::create(['type' => 'news_article_video', 'field_news_article_video_url' => ['uri' => $url], 'field_news_article_video_title' => $b['title']]);
        break;
      case 'local_video':
        try { $file = $local($b['file'] ?? 'esafety-30-secs.mp4'); }
        catch (\RuntimeException $e) { echo "  ! film missing for {$a['title']}: {$b['file']}\n"; break; }
        $known = $films[$file] ?? NULL;
        $title = $known['title'] ?? (preg_replace('/\.mp4$/i', '', $b['title'] ?: $file) ?: $a['title']);
        $cover_file = $known['cover'] ?? NULL;
        if (!$cover_file && !empty($b['poster'])) { try { $cover_file = $local($b['poster']); } catch (\RuntimeException $e) { $cover_file = NULL; } }
        $cover = $cover_file ? $media('image', $cover_file, $title) : $image($a['tile']['url'], $a['tile']['alt']);
        $paras[] = Paragraph::create(['type' => 'work_video',
          'field_work_video_media' => ['target_id' => $media('video', $file, $title)->id()],
          'field_work_video_cover' => ['target_id' => $cover->id()],
          'field_work_video_title' => $title]);
        break;
    }
  }
  foreach ($paras as $p) { $p->save(); }
  $node->set('title', $a['title']);
  $node->set('field_news_category', $a['category']);
  $node->set('field_news_date', $a['date']);
  try { $tile = $image($a['tile']['url'], $a['tile']['alt']); }
  catch (\RuntimeException $e) {
    // no tile file: the banner stands in; with neither, the story is skipped
    try { $tile = $a['banner'] ? $image($a['banner']['url'], $a['title']) : NULL; } catch (\RuntimeException $e2) { $tile = NULL; }
    if (!$tile) { echo "  ! SKIPPED (no tile file) {$a['title']}: {$e->getMessage()}\n"; foreach ($paras as $p) { $p->delete(); } continue; }
    echo "  ! tile missing for {$a['title']} — banner used\n";
  }
  $node->set('field_news_image', ['target_id' => $tile->id()]);
  $banner = NULL;
  if ($a['banner']) { try { $banner = $image($a['banner']['url'], $a['banner']['alt'] ?: $a['title']); } catch (\RuntimeException $e) { echo "  ! banner skipped in {$a['title']}\n"; } }
  $node->set('field_news_banner', $banner ? ['target_id' => $banner->id()] : NULL);
  $node->set('field_news_content', array_map(fn(Paragraph $p) => ['target_id' => $p->id(), 'target_revision_id' => $p->getRevisionId()], $paras));
  $node->set('path', ['alias' => $alias]);
  $node->set('status', 1);
  $node->setPromoted(TRUE)->setSticky(FALSE);
  $node->set('created', strtotime($a['date'] . ' 09:00:00'));
  $v = $node->validate();
  foreach ($v as $x) { echo "  ! {$x->getPropertyPath()}: {$x->getMessage()}\n"; }
  if (count($v)) { throw new \RuntimeException('invalid ' . $a['title']); }
  $node->save();
  foreach ($old_body as $old) { $old->delete(); }
  $touched[$node->id()] = TRUE;
  echo ($created ? 'created ' : 'updated ') . "node/{$node->id()}  $alias  (" . count($paras) . " blocks)\n";
}
// The sample stories with no counterpart on the old site come off the site
// (unpublished, not deleted). A story mirrored in an earlier run is known by
// its alias, one of the old site's paths (all-urls.txt beside the JSON).
foreach ($all as $n) {
  if (!empty($touched[$n->id()])) { continue; }
  $alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $n->id());
  if (isset($mirrored[$alias])) { continue; }
  if ($n->isPublished()) { $n->set('status', 0)->setPromoted(FALSE)->save(); echo "unpublished node/{$n->id()}  {$n->label()}\n"; }
}
echo "done\n";
