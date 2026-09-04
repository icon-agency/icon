<?php

/**
 * @file
 * Sample Work content — the twelve projects of templates/work-landing-b.html,
 * four of them with case-study bodies after the client folios (iCanQuit, The
 * Athlete's Foot, Melbourne Marathon / Nike, MoAD) and the master's block
 * order. Run from drupal/:  ddev drush php:script scripts/work-sample-content.php
 * Idempotent: re-running updates the same nodes (matched by path alias) and
 * replaces their blocks. Media come from drupal/sample-content/work/.
 */

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

$source_dir = DRUPAL_ROOT . '/../sample-content/work';
$fs = \Drupal::service('file_system');
$dest = 'public://work';
$fs->prepareDirectory($dest, $fs::CREATE_DIRECTORY | $fs::MODIFY_PERMISSIONS);

/** One media item per file, reused across runs (matched by name); videos become video media. */
$media = function (string $file, string $alt = '') use ($source_dir, $dest, $fs): Media {
  $is_video = str_ends_with($file, '.mp4');
  $bundle = $is_video ? 'video' : 'image';
  $existing = \Drupal::entityTypeManager()->getStorage('media')->loadByProperties(['bundle' => $bundle, 'name' => $file]);
  if ($existing) {
    return reset($existing);
  }
  $uri = $fs->copy("$source_dir/$file", "$dest/$file", $fs::EXISTS_REPLACE);
  $f = File::create(['uri' => $uri, 'status' => 1]);
  $f->save();
  $m = Media::create(['bundle' => $bundle, 'name' => $file] + ($is_video
    ? ['field_media_video_file' => ['target_id' => $f->id()]]
    : ['field_media_image' => ['target_id' => $f->id(), 'alt' => $alt ?: pathinfo($file, PATHINFO_FILENAME)]]));
  $m->save();
  return $m;
};

$block = fn(string $type, array $fields) => Paragraph::create(['type' => $type] + $fields);
$prose = fn(string $html) => $block('prose', ['field_prose_text' => ['value' => $html, 'format' => 'basic_html']]);
$quote = fn(string $text, string $cite = '') => $block('pull_quote', ['field_pull_quote_text' => $text, 'field_pull_quote_variant' => 'upright', 'field_pull_quote_cite' => $cite]);
$row = fn(array $medias, string $style = 'default', string $ground = '', string $pad = '') => $block('work_gallery_row', [
  'field_work_gallery_media' => array_map(fn(Media $m) => ['target_id' => $m->id()], $medias),
  'field_work_gallery_style' => $style,
  'field_work_gallery_ground' => $ground,
  'field_work_gallery_pad' => $pad,
]);
$film = fn(Media $video, Media $cover, string $title) => $block('work_video', ['field_work_video_media' => ['target_id' => $video->id()], 'field_work_video_cover' => ['target_id' => $cover->id()], 'field_work_video_title' => $title]);
$embed = fn(string $url, string $title) => $block('news_article_video', ['field_news_article_video_url' => ['uri' => $url], 'field_news_article_video_title' => $title]);
$scroller = fn(array $medias, string $ground = '#2e2e2e') => $block('work_scroller', ['field_work_scroller_media' => array_map(fn(Media $m) => ['target_id' => $m->id()], $medias), 'field_work_scroller_ground' => $ground]);
$stats = function (array $triples) use ($block): Paragraph {
  $items = [];
  foreach ($triples as [$value, $unit, $label]) {
    $s = $block('work_stat', ['field_work_stat_value' => $value, 'field_work_stat_unit' => $unit, 'field_work_stat_label' => $label]);
    $s->save();
    $items[] = ['target_id' => $s->id(), 'target_revision_id' => $s->getRevisionId()];
  }
  return $block('work_stats', ['field_work_stats_items' => $items]);
};

/** Create or update a work node (matched by alias). */
$project_names = [
  '/work/raise-it' => 'Raise It', '/work/permanent-protection-visa' => 'Permanent Protection Visa', '/work/fit-for-every-run' => 'Fit for Every Run',
  '/work/icanquit' => 'iCanQuit', '/work/democracy-cards' => 'Democracy Cards', '/work/melbourne-marathon-running-wings' => 'Running Wings',
  '/work/care-closer-to-home' => 'Care Closer to Home', '/work/australian-space-agency' => 'Space Agency website', '/work/alcohol-and-drug-foundation' => 'ADF campaign',
  '/work/fogo' => 'FOGO', '/work/is-this-legit' => 'Is This Legit?', '/work/icanquit-app' => 'iCanQuit app',
];
$project = function (array $d) use ($project_names) {
  $nid = \Drupal::service('path_alias.repository')->lookupByAlias($d['alias'], 'en')['path'] ?? NULL;
  $node = $nid ? Node::load((int) substr($nid, 6)) : NULL;
  if (!$node) {
    $node = Node::create(['type' => 'work', 'uid' => 1]);
  }
  foreach ($node->get('field_work_content')->referencedEntities() as $old) {
    $old->delete();
  }
  $body = $d['body'] ?? [];
  foreach ($body as $p) {
    $p->save();
  }
  $node->set('title', $d['title']);
  $node->set('field_work_client', $d['client']);
  // The short project name lists and pickers show (the title is the card's headline).
  $node->set('field_work_project', $project_names[$d['alias']] ?? ucwords(str_replace('-', ' ', substr($d['alias'], 6))));
  $node->set('field_work_category', $d['categories']);
  $node->set('field_work_deliverables', $d['deliverables'] ?? []);
  $node->set('field_work_statement', $d['statement'] ?? '');
  $node->set('field_work_tile', ['target_id' => $d['tile']->id()]);
  $node->set('field_work_banner', isset($d['banner']) ? ['target_id' => $d['banner']->id()] : NULL);
  $node->set('field_work_bg', $d['bg'] ?? '#0000ff');
  $node->set('field_work_ink', $d['ink'] ?? '#ffffff');
  $node->set('field_work_content', array_map(fn(Paragraph $p) => ['target_id' => $p->id(), 'target_revision_id' => $p->getRevisionId()], $body));
  $node->set('path', ['alias' => $d['alias']]);
  $node->set('status', 1);
  $node->set('created', $d['created']);
  $violations = $node->validate();
  if (count($violations)) {
    foreach ($violations as $v) {
      echo "  ! {$v->getPropertyPath()}: {$v->getMessage()}\n";
    }
    throw new \RuntimeException("Validation failed for {$d['title']}");
  }
  $node->save();
  echo ($nid ? 'updated ' : 'created ') . "node/{$node->id()}  {$d['alias']}\n";
};

$filler = fn(string $title, string $client) => [$prose("<h2>Overview</h2><p>$title — a case study for $client. This is placeholder body copy from the sample-content script; replace it with the project's blocks.</p>")];
$t = strtotime('2026-08-20');
$day = 86400;

// ── The twelve projects, in the landing's order (newest first) ──────────────
$icq = [
  'alias' => '/work/icanquit', 'title' => 'iCanQuit is more than breaking a habit—it’s an identity transformation', 'client' => 'Cancer Institute NSW',
  'categories' => ['websites', 'behaviour-change'], 'bg' => '#ebe5fe', 'ink' => '#441170', 'created' => $t - 3 * $day,
  'tile' => $media('icanquit.png', 'iCanQuit — Cancer Institute NSW'), 'banner' => $media('icq-banner.jpg', 'The iCanQuit service on desktop and phone'),
  'statement' => 'iCanQuit is more than breaking a habit—it’s an identity transformation.',
  'deliverables' => ['Behavioural research & strategy', 'Information architecture', 'Content design & writing', 'UX/UI design', 'Clickable prototypes', 'User interviews & usability testing', 'Custom illustrations', 'Video production'],
  'body' => [
    $prose('<h2>Overview</h2><p>ICON partnered with Cancer Institute NSW to design and validate the next evolution of the iCanQuit digital service, supporting people across NSW to quit smoking and vaping.</p><p>The project focused on establishing a robust, evidence-based UX foundation that could scale across platforms, support diverse user needs, and integrate with the wider quit ecosystem.</p><p>Our role was to lead the end-to-end UX design and testing program, working closely with the Cancer Institute team to test early, test often, and reduce delivery risk.</p>'),
    $row([$media('icq-dashboard.png', 'The iCanQuit dashboard'), $media('icq-congratulations-forum.png', 'Congratulations screen and the community forum')]),
    $prose('<h2>Background</h2><p>Smoking remains one of the leading preventable causes of illness and death in NSW. While many people want to quit, the journey is rarely linear.</p><p>iCanQuit is a long-standing digital service designed to support people through that journey. As user expectations, devices and digital behaviours evolved, the service needed to evolve with them.</p><p>The challenge was not simply to redesign an interface. The service needed to support people at moments of vulnerability, encourage sustained behaviour change and remain accessible to everyone.</p>'),
    $row([$media('icq-app-screens.png', 'iCanQuit app screens')]),
    $row([$media('icq-mood.png', 'The mood check-in'), $media('icq-sign-up.png', 'Sign up')], 'portrait'),
    $row([$media('icq-homepage.png', 'The iCanQuit homepage on desktop and mobile')], 'layered', '#2e2e2e'),
    $prose('<h2>What we made</h2><p>We delivered a comprehensive UX design and validation program across three stages:</p><ul><li>Project kickoff and discovery workshops to align on goals, review existing research and information architecture, and confirm functional and behavioural requirements.</li><li>Low-fidelity wireframes for the full iCanQuit ecosystem: onboarding, dashboards, behaviour tracking, quit activities, forums and notifications.</li><li>High-fidelity UI prototypes for both website and app, refined through structured usability testing.</li></ul><p>User testing was central to the approach. ICON recruited and managed 48 participants across multiple rounds of testing.</p>'),
    $row([$media('icq-savings-timeline.png', 'Savings and withdrawal timeline'), $media('icq-website-screens.png', 'Website screens: topics, forum, decision tree')]),
    $prose('<h2>Why it mattered</h2><p>Quitting smoking is a complex, emotional and highly personal journey. Getting the experience right was critical to trust, engagement and long-term impact.</p>'),
    $stats([['48', 'participants', 'Recruited and tested across multiple rounds'], ['3', 'stages', 'Discovery, wireframes, high-fidelity prototypes'], ['2', 'platforms', 'Website and app, one design system'], ['0', 'rework', 'Validated before build']]),
    $prose('<p>By validating the service design before build, the project reduced delivery risk, avoided costly rework and gave Cancer Institute NSW confidence that the next iCanQuit would meet real needs.</p>'),
  ],
];
$taf = [
  'alias' => '/work/fit-for-every-run', 'title' => 'Celebrating movement in all its forms', 'client' => 'The Athlete’s Foot',
  'categories' => ['brand'], 'bg' => '#910098', 'ink' => '#ffffff', 'created' => $t - 2 * $day,
  'tile' => $media('athletes-foot-fit-for-every-run.mp4'), 'banner' => $media('athletes-foot-fit-for-every-run.mp4'),
  'statement' => 'Fit for every run — a brand platform that celebrates movement in all its forms.',
  'deliverables' => ['Brand strategy', 'Creative concept', 'Film production', 'Out of home', 'Social content'],
  'body' => [
    $prose('<h2>The idea</h2><p>Running is not one thing. It is the first kilometre and the fortieth, the school drop-off dash and the marathon. Fit for every run puts every runner in the frame.</p>'),
    $prose('<h2>The posters</h2><p>A poster series carried the platform across the retail network and out of home.</p>'),
    $scroller([$media('taf-poster-1.png', 'Poster one'), $media('taf-poster-2.png', 'Poster two'), $media('taf-poster-3.png', 'Poster three'), $media('taf-poster-4.png', 'Poster four')]),
    $prose('<h2>The film</h2><p>The hero film, cut for sound.</p>'),
    $film($media('taf-hero-film.mp4'), $media('taf-film-cover.png', 'Fit for every run — the film'), 'Fit for every run — the film'),
    $row([$media('taf-billboard.png', 'The campaign on a billboard')]),
    $row([$media('taf-montage.png', 'Campaign montage')]),
    $quote('Every runner, every run — the platform gave the whole network one story to tell.', 'The Athlete’s Foot'),
  ],
];
$nike = [
  'alias' => '/work/melbourne-marathon-running-wings', 'title' => 'Giving a running festival wings across every channel', 'client' => 'Melbourne Marathon Festival',
  'categories' => ['brand', 'creative'], 'bg' => '#cc0000', 'ink' => '#ffffff', 'created' => $t - 9 * $day,
  'tile' => $media('running-wings-campaign.jpeg', 'Running Wings campaign hero'), 'banner' => $media('nike-banner.mp4'),
  'statement' => 'A festival, not a race: a campaign that ran across every channel the city has.',
  'deliverables' => ['Campaign creative', 'Film', 'Social', 'Press and partnerships'],
  'body' => [
    $prose('<h2>Race day</h2><p>The reel over the start-line crowd — the layered figure, film floating on a photograph.</p>'),
    $row([$media('nike-bg.jpg', 'The start line'), $media('nike-race-day-reel.mp4')], 'layered'),
    $row([$media('nike-female-runners.png', 'Runners on Swanston Street')]),
    $prose('<p>Placeholder copy from the sample-content script; replace with the project story.</p>'),
  ],
];
$moad = [
  'alias' => '/work/democracy-cards', 'title' => 'Democracy Cards – pre-election public education campaign', 'client' => 'Museum of Australian Democracy (MoAD)',
  'categories' => ['creative'], 'bg' => '#58b4e4', 'ink' => '#08283c', 'created' => $t - 4 * $day,
  'tile' => $media('democracy-cards-tile.jpg', 'The Democracy Cards deck'), 'banner' => $media('moad-hero.png', 'Women with the Democracy Cards'),
  'statement' => 'Fifty-two questions about how the country works, in a deck that fits in a pocket.',
  'deliverables' => ['Campaign strategy', 'Creative', 'Print', 'Film', 'Social'],
  'body' => [
    $prose('<h2>The deck</h2><p>Placeholder copy from the sample-content script; replace with the project story.</p>'),
    $row([$media('moad-hand-cards.jpg', 'A hand holding the cards'), $media('moad-men-cards.jpg', 'Posing with the cards')]),
    $row([$media('moad-creative-collection.jpg', 'The campaign creative collection')]),
    $embed('https://www.youtube-nocookie.com/embed/c1DjQLPgLbE', 'Question of the Day — Democracy Cards'),
  ],
];
$projects = [
  ['alias' => '/work/raise-it', 'title' => 'Transforming the elephant in the room into conversations that change lives', 'client' => 'PHN North Western Melbourne', 'categories' => ['behaviour-change'], 'tile' => $media('nwmphn-raise-it.mp4'), 'created' => $t],
  ['alias' => '/work/permanent-protection-visa', 'title' => 'Changing CALD community conversations on protection visas', 'client' => 'Permanent Protection Visa', 'categories' => ['communications'], 'tile' => $media('ppv.jpg', 'Permanent Protection Visa community campaign'), 'created' => $t - 1 * $day],
  $taf, $icq, $moad,
  ['alias' => '/work/care-closer-to-home', 'title' => 'Connecting communities with care closer to home', 'client' => 'Victorian Department of Health', 'categories' => ['communications'], 'tile' => $media('health-services-campaign.png', 'Health services campaign creative'), 'created' => $t - 5 * $day],
  ['alias' => '/work/australian-space-agency', 'title' => 'A launch pad for Australia’s space ambitions', 'client' => 'Australian Space Agency', 'categories' => ['websites'], 'tile' => $media('space-agency-website.png', 'Australian Space Agency website on desktop and mobile'), 'created' => $t - 6 * $day],
  ['alias' => '/work/alcohol-and-drug-foundation', 'title' => 'Shifting the conversation on alcohol and other drugs', 'client' => 'Alcohol and Drug Foundation', 'categories' => ['reputation'], 'tile' => $media('alcohol-and-drug-foundation.mp4'), 'created' => $t - 7 * $day],
  ['alias' => '/work/fogo', 'title' => 'Turning kitchen scraps into a kerbside habit', 'client' => 'City of Whittlesea', 'categories' => ['creative'], 'tile' => $media('fogo-hero-campaign.png', 'FOGO kerbside campaign creative'), 'created' => $t - 8 * $day],
  $nike,
  ['alias' => '/work/is-this-legit', 'title' => 'Turning scam awareness into a social-first movement', 'client' => 'Meta', 'categories' => ['reputation'], 'tile' => $media('meta-is-this-legit.png', 'Is This Legit? — Meta scam awareness campaign'), 'created' => $t - 10 * $day],
  ['alias' => '/work/icanquit-app', 'title' => 'A quit companion built around identity change', 'client' => 'Cancer Institute NSW', 'categories' => ['websites'], 'tile' => $media('icanquit-app.png', 'iCanQuit app screens'), 'created' => $t - 11 * $day],
];
foreach ($projects as $d) {
  if (empty($d['body'])) {
    $d['body'] = $filler($d['title'], $d['client']);
  }
  $project($d);
}
echo "done\n";
