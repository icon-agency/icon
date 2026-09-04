<?php

/**
 * @file
 * Sample News content — the twelve stories of templates/news-b.html plus the
 * PRCA article of templates/news-article.html, with its body built from
 * paragraphs (prose / film / article figure / pull quote — each rendered by
 * the SDC of the same name). The Butterfly story carries the optional 2:1
 * banner (news-article-butterfly.html), so both head variants are on the site.
 *
 * Run from drupal/:  ddev drush php:script scripts/news-sample-content.php
 * Idempotent: re-running updates the same nodes (matched by path alias).
 * Images come from drupal/sample-content/news/ (copies of the repo assets).
 */

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

$source_dir = DRUPAL_ROOT . '/../sample-content/news';
$fs = \Drupal::service('file_system');
$dest = 'public://news';
$fs->prepareDirectory($dest, $fs::CREATE_DIRECTORY | $fs::MODIFY_PERMISSIONS);

/** One image media per file, reused across runs (matched by name). */
$media = function (string $file, string $alt) use ($source_dir, $dest, $fs): Media {
  $existing = \Drupal::entityTypeManager()->getStorage('media')->loadByProperties(['bundle' => 'image', 'name' => $file]);
  if ($existing) {
    return reset($existing);
  }
  $uri = $fs->copy("$source_dir/$file", "$dest/$file", $fs::EXISTS_REPLACE);
  $f = File::create(['uri' => $uri, 'status' => 1]);
  $f->save();
  $m = Media::create(['bundle' => 'image', 'name' => $file, 'field_media_image' => ['target_id' => $f->id(), 'alt' => $alt]]);
  $m->save();
  return $m;
};

/** A body block: one paragraph entity of the given type (unsaved; the node saves it). */
$block = fn(string $type, array $fields) => Paragraph::create(['type' => $type] + $fields);
$prose = fn(string $html) => $block('prose', ['field_prose_text' => ['value' => $html, 'format' => 'basic_html']]);
$figure = fn(Media $m) => $block('news_article_figure', ['field_news_article_figure_image' => ['target_id' => $m->id()]]);
$quote = fn(string $text) => $block('pull_quote', ['field_pull_quote_text' => $text, 'field_pull_quote_variant' => 'upright']);
$film = fn(string $embed_url, string $title) => $block('news_article_video', ['field_news_article_video_url' => ['uri' => $embed_url], 'field_news_article_video_title' => $title]);

/** Create or update a news node (matched by alias). */
$story = function (string $alias, string $title, string $category, string $date, Media $tile, array $body, ?Media $banner = NULL) {
  $nid = \Drupal::service('path_alias.repository')->lookupByAlias($alias, 'en')['path'] ?? NULL;
  $node = $nid ? Node::load((int) substr($nid, 6)) : NULL;
  if (!$node) {
    $node = Node::create(['type' => 'news', 'uid' => 1]);
  }
  $node->set('title', $title);
  $node->set('field_news_category', $category);
  $node->set('field_news_date', $date);
  $node->set('field_news_image', ['target_id' => $tile->id()]);
  $node->set('field_news_banner', $banner ? ['target_id' => $banner->id()] : NULL);
  foreach ($node->get('field_news_content')->referencedEntities() as $old) {
    $old->delete();
  }
  foreach ($body as $p) {
    $p->save();
  }
  $node->set('field_news_content', array_map(fn(Paragraph $p) => ['target_id' => $p->id(), 'target_revision_id' => $p->getRevisionId()], $body));
  $node->set('path', ['alias' => $alias]);
  $node->set('status', 1);
  $node->setPromoted(TRUE)->setSticky(FALSE); // every sample story in the homepage feed
  $node->set('created', strtotime($date));
  $violations = $node->validate();
  if (count($violations)) {
    foreach ($violations as $v) {
      echo "  ! {$v->getPropertyPath()}: {$v->getMessage()}\n";
    }
    throw new \RuntimeException("Validation failed for $title");
  }
  $node->save();
  echo ($nid ? 'updated ' : 'created ') . "node/{$node->id()}  $alias\n";
};

$filler = fn(string $lede) => [
  $prose("<p>$lede</p><p>More on this story soon — this is placeholder body copy from the sample-content script; replace it with the real article's blocks.</p>"),
];

// ── The twelve stories of the listing (templates/news-b.html) ───────────────
$stories = [
  ['/news/butterfly-foundation-body-kind', 'ICON and Butterfly Foundation partner in new campaign to help Australians rethink negative self body talk', 'our-work', '2026-08-09', 'Butterfly_BodyKind.jpg', 'Three campaign talent standing together in a photography studio'],
  ['/news/icon-expands-into-the-pacific', 'ICON expands into the Pacific with a new Suva studio', 'agency', '2026-07-24', '587006301_18538192765046950_6531438719646800553_n.jpg', 'Two ICON staff outside the new Suva Business Center office'],
  ['/news/agency-of-the-year-2026', 'ICON named Agency of the Year at the 2026 Government Communications Awards', 'awards', '2026-07-14', '708333855_18588965047046950_2864532459197602570_n.jpg', 'The ICON team on stage accepting the award'],
  ['/news/veteran-family-wellbeing-agency-website', 'ICON launches new wellbeing agency website for veterans and families', 'our-work', '2026-06-30', 'Tile-DVA-Wellbeing.png', 'Homepage of the Veteran and Family Wellbeing Agency site on desktop and mobile'],
  ['/news/esafety-sextortion-scams', 'ICON helps eSafety expose the tactics behind sextortion scams', 'our-work', '2026-06-29', 'tile-sextortion-campaign.jpg', "Still from the campaign film: an AI-generated woman captioned I'm not real, I'm here to blackmail you"],
  ['/news/integrated-communications-government-trust', 'Why integrated communications help government services earn trust and drive action', 'insights', '2026-06-25', 'how-to-deliver-a-successful-government-campaign.png', 'Blue title card reading How to deliver a successful government campaign'],
  ['/news/behavioural-economics-public-campaigns', 'Behavioural economics in public campaigns: making the right choice the easy one', 'insights', '2026-06-12', 'money-man-news.png', 'Illustration of a man juggling oversized coins and banknotes'],
  ['/news/democracy-cards-gold-comms-council', 'Democracy Cards wins gold for public education at the 2026 Comms Council Awards', 'awards', '2026-05-30', 'Democracy-Cards-Tile.jpg', 'The Democracy Cards deck fanned out on a table'],
  ['/news/new-head-of-creative-melbourne', 'ICON welcomes a new Head of Creative in Melbourne', 'agency', '2026-05-19', '589584976_18542426581046950_6244899432408296144_n.jpg', 'ICON creatives reviewing work pinned to the studio wall'],
  ['/news/designing-for-trust-accessibility', 'Designing for trust: accessibility as a service obligation, not a checklist', 'insights', '2026-05-06', 'health-services-campaign.png', 'Health worker with a patient, from the health services campaign'],
  ['/news/permanent-protection-visa-cald', 'Permanent Protection Visa campaign reaches CALD communities in nine languages', 'our-work', '2026-04-21', 'PPV.jpg', 'Permanent Protection Visa campaign artwork with multilingual text'],
  ['/news/inside-the-cremorne-studio-critique-weeks', 'Inside the Cremorne studio: how ICON runs critique weeks', 'agency', '2026-04-09', '657741830_18574186735046950_6711972987338616974_n.jpg', 'The Cremorne studio mid-critique, work displayed on the far screen'],
];
$banners = [
  '/news/butterfly-foundation-body-kind' => ['butterfly22.png', 'The three BodyKind campaign talent together in the photography studio, between a roll of seamless and a softbox'],
];
foreach ($stories as [$alias, $title, $category, $date, $file, $alt]) {
  $banner = isset($banners[$alias]) ? $media(...$banners[$alias]) : NULL;
  $story($alias, $title, $category, $date, $media($file, $alt), $filler($title . '.'), $banner);
}

// ── The article (templates/news-article.html), body as paragraphs ───────────
$story(
  '/news/three-time-winner-prca-awards-singapore',
  'ICON is a three-time winner at the PRCA Awards in Singapore',
  'insights',
  '2026-11-12',
  $media('prca-award-01.png', 'Three PRCA APAC Awards trophies lined up in the ICON studio'),
  [
    $prose('<p>Fresh from the PRCA APAC Awards in Singapore: ICON is proud to celebrate three award wins that reflect the breadth of work we create across audiences, industries and challenges.</p><p>Recognition is never the goal, but it’s always meaningful when work that matters is recognised. From World Expo 2025 to Meta’s scam awareness movement and our own AI transformation, these PRCA APAC Award wins reflect impact in many different forms.</p>'),
    $film('https://www.youtube-nocookie.com/embed/9kqQ7fBv7bM?rel=0&modestbranding=1&playsinline=1', 'Campaign film — demo embed'),
    $figure($media('meta-scams.png', 'Illustration from the Is This Legit? campaign: a suited robot scratching its head, on the Meta red')),
    $prose('<h2>Multi-Country Campaign Award (APAC)</h2><p>Is This Legit? How Meta turned scam awareness into an interactive, social-first movement across APAC.</p><h3>In partnership with Meta</h3><p>Scams are one of the most pressing digital issues facing communities across the region. Partnering with Meta, we helped create a campaign that met audiences where they already are: online, social and constantly connected.</p><p>Is This Legit? transformed scam awareness into an engaging, participatory movement across APAC, using social-first storytelling and interactive experiences to educate audiences in a way that felt relevant, accessible and shareable.</p><p>The campaign was awarded the PRCA APAC Multi-Country Campaign Award, recognising work that successfully connected with audiences across multiple markets while delivering meaningful regional impact.</p>'),
    $figure($media('osaka.png', 'Collage of the Australia pavilion campaign at World Expo 2025 Osaka across social and on-site media')),
    $quote('Chasing the Sun at the World Expo 2025: why Australia’s pavilion was the event of the year'),
    $prose('<h2>Event/Launch of the Year</h2><h3>In partnership with the Department of Foreign Affairs &amp; Trade and Sunny Side Up (SSU)</h3><p>Australia’s presence at World Expo 2025 Osaka was designed to do more than attract attention; it was built to create connection. Together with DFAT, we helped bring Australia’s story to life on a global stage through an experience that captured imagination, culture and innovation.</p><p>The campaign was recognised with the PRCA Award for Event/Launch of the Year, celebrating work that created a lasting impression for international audiences and elevated Australia’s presence at one of the world’s biggest cultural events.</p>'),
    $figure($media('prca-award-02.png', 'A face rendered in points of light on deep blue — ICON’s AI transformation')),
    $quote('How ICON transformed with AI: at the nexus between culture and tech'),
    $prose('<h2>Employee Engagement Award</h2><p>Transformation is never just about technology; it’s about people. This award recognised ICON’s own internal AI transformation journey and the work undertaken to embed new ways of thinking, working and collaborating across the business.</p><p>By focusing equally on culture and capability, the initiative helped position AI not as a disruption, but as an opportunity for teams to evolve together.</p><p>Winning the PRCA APAC Employee Engagement Award reflects the importance of creating change that people genuinely want to be part of.</p>'),
  ],
);
echo "done\n";
