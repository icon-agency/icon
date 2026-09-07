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
 */
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

$articles = json_decode(file_get_contents('/tmp/import/news/articles.json'), TRUE);
$src = '/tmp/import/news';
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

$embeds = ['Being BodyKind this BodyKind August' => 'https://www.youtube-nocookie.com/embed/9kqQ7fBv7bM?rel=0&modestbranding=1&playsinline=1'];
$films = ['esafety-30-secs.mp4' => ['cover' => 'tile-sextortion-campaign.jpg', 'title' => 'If sextortionists were honest — the eSafety campaign film']];

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
  foreach ($node->get('field_news_content')->referencedEntities() as $old) { $old->delete(); }
  $paras = [];
  foreach ($a['blocks'] as $b) {
    switch ($b['type']) {
      case 'prose':
        $html = str_replace(['<div>', '</div>'], '', $b['html']);
        $paras[] = Paragraph::create(['type' => 'prose', 'field_prose_text' => ['value' => $html, 'format' => 'basic_html']]);
        break;
      case 'figure':
        $paras[] = Paragraph::create(['type' => 'news_article_figure', 'field_news_article_figure_image' => ['target_id' => $image($b['url'], $b['alt'])->id()]]);
        break;
      case 'remote_video':
        $paras[] = Paragraph::create(['type' => 'news_article_video', 'field_news_article_video_url' => ['uri' => $embeds[$b['title']]], 'field_news_article_video_title' => $b['title']]);
        break;
      case 'local_video':
        $file = 'esafety-30-secs.mp4';
        $paras[] = Paragraph::create(['type' => 'work_video',
          'field_work_video_media' => ['target_id' => $media('video', $file, $films[$file]['title'])->id()],
          'field_work_video_cover' => ['target_id' => $media('image', $films[$file]['cover'], $films[$file]['title'])->id()],
          'field_work_video_title' => $films[$file]['title']]);
        break;
    }
  }
  foreach ($paras as $p) { $p->save(); }
  $node->set('title', $a['title']);
  $node->set('field_news_category', $a['category']);
  $node->set('field_news_date', $a['date']);
  $node->set('field_news_image', ['target_id' => $image($a['tile']['url'], $a['tile']['alt'])->id()]);
  $node->set('field_news_banner', $a['banner'] ? ['target_id' => $image($a['banner']['url'], $a['banner']['alt'] ?: $a['title'])->id()] : NULL);
  $node->set('field_news_content', array_map(fn(Paragraph $p) => ['target_id' => $p->id(), 'target_revision_id' => $p->getRevisionId()], $paras));
  $node->set('path', ['alias' => $alias]);
  $node->set('status', 1);
  $node->setPromoted(TRUE)->setSticky(FALSE);
  $node->set('created', strtotime($a['date'] . ' 09:00:00'));
  $v = $node->validate();
  foreach ($v as $x) { echo "  ! {$x->getPropertyPath()}: {$x->getMessage()}\n"; }
  if (count($v)) { throw new \RuntimeException('invalid ' . $a['title']); }
  $node->save();
  $touched[$node->id()] = TRUE;
  echo ($created ? 'created ' : 'updated ') . "node/{$node->id()}  $alias  (" . count($paras) . " blocks)\n";
}
// The sample stories with no counterpart on the old site come off the site (unpublished, not deleted).
foreach ($all as $n) {
  if (empty($touched[$n->id()])) { $n->set('status', 0)->setPromoted(FALSE)->save(); echo "unpublished node/{$n->id()}  {$n->label()}\n"; }
}
echo "done\n";
