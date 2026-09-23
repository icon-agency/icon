<?php

/**
 * @file
 * The expertise pages in the Digital page's layout.
 *
 * 23 Sep 2026 (user call: "see this example for digital … can you do the
 * same for /creative … then /communications, /reputation and /production").
 * Each page is built from the old site's own words (iconagency.com.au's
 * icon-creative, pr-communications, reputation and production pages) in
 * the arrangement Matt gave the Digital page in the editor: the split
 * masthead opening dark, the filmstrip, the intro as a third beside two
 * columns (the approach, who we are), a pull quote, the "services" line as
 * a third beside a Large paragraph, the services three to a row — the
 * first row opening on a light-grey Featured box with a slash list — each
 * a Content block (an h4 and a plain list of Small items) over a spacer, a
 * closing quote, and the Work: latest rail narrowed to the page's work.
 *
 * Creative keeps its own masthead picture and filmstrip; the other three
 * borrow Digital's as placeholders until the pictures are chosen (user
 * call on Digital: "Don't worry about the images"). Found by alias and
 * rebuilt, or created; the open draft is dropped. The main menu's items go
 * to the new aliases. Run it again to reset a page to this.
 *
 * Run: ddev exec "ICON_SEED=1 drush php:script scripts/expertise-pages.php"
 *   or one page: ICON_SEED=1 ICON_PAGE=creative drush php:script …
 */

require_once __DIR__ . '/_guard.php';

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\user\Entity\User;

\Drupal::service('account_switcher')->switchTo(User::load(1));
$uuid = \Drupal::service('uuid');
$etm = \Drupal::entityTypeManager();
$storage = $etm->getStorage('canvas_page');
$aliases = \Drupal::service('path_alias.repository');
$version = static function (string $id): string {
  $c = \Drupal::entityTypeManager()->getStorage('component')->load($id);
  if (!$c) {
    throw new \RuntimeException("Component $id is not registered.");
  }
  return (string) $c->get('active_version');
};
$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES);
$li = static fn(array $items): string => implode('', array_map(fn($i) => '<li><p class="is-small">' . $e($i) . '</p></li>', $items));

/**
 * A page's pieces from another page's tree: the children of the first
 * component of a kind, in a slot — the Digital filmstrip's cards, a
 * masthead's aside — as [component_id, slot, inputs] to re-add.
 */
$borrow = static function (int $page_id, string $component_id, string $slot): array {
  $p = \Drupal::entityTypeManager()->getStorage('canvas_page')->load($page_id);
  if (!$p) {
    return [];
  }
  $items = $p->get('components')->getValue();
  $parent = NULL;
  foreach ($items as $i) {
    if ($i['component_id'] === $component_id && empty($i['parent_uuid'])) {
      $parent = $i['uuid'];
      break;
    }
  }
  $out = [];
  foreach ($items as $i) {
    if ($parent && ($i['parent_uuid'] ?? '') === $parent && ($i['slot'] ?? '') === $slot) {
      $out[] = [$i['component_id'], $slot, is_string($i['inputs']) ? (json_decode($i['inputs'], TRUE) ?: []) : (array) $i['inputs']];
    }
  }
  return $out;
};

// ---- The pages ---------------------------------------------------------------
$pages = [
  'creative' => [
    'title' => 'Creative',
    'alias' => '/creative',
    'menu' => 'Creative',
    'description' => 'As a creative agency, we make brands matter through the application of deep human insights and connected creative thinking. Ideas that won’t be ignored and results that can’t be denied.',
    'masthead' => 'Unmissable ideas. Undeniable impact.',
    'borrow_from' => NULL, // its own masthead picture and filmstrip
    'intro_caps' => 'Independent thinking<br>that makes you matter.',
    'approach' => ['Creative agency approach', 'From behaviour change campaigns inspired by deep local insight to digital transformation ideas focussed on the needs of future generations, our teams are putting brands, products and culture-defining messages in front of the right people and in the right places at the right time. Ideas that won’t be ignored and results that can’t be denied – that’s what matters to us.'],
    'who' => ['What we do and why we do it', 'A creative agency of makers, shakers, interdisciplinary groundbreakers. Thinking, playing, testing, never resting – we make your story matter. Whether it’s strategic consultation to build a new brand or a creative idea that makes you part of culture, we make it…and we make it matter.'],
    'quote' => 'There’s a lot of noise on a lot of channels, and very little time to make your point. We put the right idea in the right place at the right time.',
    'services_caps' => 'Creative<br>services',
    'services_line' => 'Engage with us to deliver a single service or a fully realised campaign.',
    'featured' => ['Brand', 'Campaigns', 'Behaviour change', 'Storytelling'],
    'services' => [
      ['Creative', 'Powerful ideas that let brands reimagine the old and ideate the new.', ['Creative workshops', 'Territory exploration', 'Concept development and storyboards', 'Print and direct mail', 'End-to-end integrated campaigns', 'Social and digital marketing campaigns']],
      ['Brand design', 'Building and driving business growth through brands.', ['Brand transformation', 'Brand communications', 'Brandmark design', 'Brand governance and management', 'Employer branding and EVP']],
      ['Strategic planning', 'Mining deep human insights and data to shape break-through creative campaigns.', ['Behavioural science', 'Data insights', 'Research and analysis', 'Campaign strategy', 'Social and content strategy', 'Communications design', 'Digital strategy and planning', 'Media planning']],
      ['Behaviour change', 'Applying human science and behavioural economics to drive new behaviour for the public good.', ['Behavioural mapping', 'Research design and testing', 'Qualitative and quantitative research', 'Campaign planning and set-up', 'Integrated design and management', 'Campaign analytics and evaluation']],
      ['Branded storytelling', 'Building brand love through authentic, engaging content.', ['Native content', 'Brand and corporate storytelling', 'Video and testimonial films', 'Whitepapers', 'User-generated content campaigns', 'Employee advocacy', 'Infographics', 'Video and audio production']],
    ],
    'closing' => 'Ideas that won’t be ignored and results that can’t be denied – that’s what matters to us.',
    'work' => ['caps' => 'Creative work', 'category' => 'creative'],
  ],
  'communications' => [
    'title' => 'Communications',
    'alias' => '/communications',
    'menu' => 'Communications',
    'description' => 'Brilliant ideas don’t distinguish between earned, owned and shared media. Neither do we. Our expert consultants drive impactful conversations, telling your story in the places and to the people that matter.',
    'masthead' => 'Make your voice matter.',
    'borrow_from' => 8,
    'intro_caps' => 'We tell stories that blend channels,<br>cross borders and win the headline.',
    'approach' => ['Communications approach', 'Brilliant ideas don’t distinguish between earned, owned and shared media. Neither do we. For us, it’s smart, connected thinking. Our expert consultants drive impactful conversations, telling your story in the places and to the people that matter. ICON’s communications department makes what matters to you, matter to your audience.'],
    'who' => ['Expertise', 'Global behemoths, Aussie success stories, ambitious brands, and governments count on us to raise their profile and change perceptions and behaviours. From integrated public relations PR to social media, digital content, media strategies and creative, we work at the forefront of modern communications practice. We’re also one of Australia’s most awarded PR agencies, earning multiple agency of the year awards both nationally and on the global stage.'],
    'quote' => 'Combining storytelling and creativity with critical thinking and industry knowledge.',
    'services_caps' => 'Communications<br>services',
    'services_line' => 'From specialist services to integrated campaigns, talk to us.',
    'featured' => ['Corporate', 'Government', 'Media relations', 'Social'],
    'services' => [
      ['Corporate communications', 'Working to protect, promote and amplify trust in organisations.', ['Strategic communications advisory', 'CEO and executive profiling', 'Thought leadership', 'Internal communications', 'Issues and crisis management', 'Technology PR']],
      ['Government relations & public affairs', 'Leveraging our deep understanding of government to elevate issues into the national conversation and connect clients with decision makers.', ['Community awareness and influencing campaigns', 'Stakeholder engagement and consultation', 'Ministerial and departmental engagement', 'Lobbying (via our trusted third party specialists)']],
      ['Behaviour change campaigns', 'Leading engagement through integrated PR, communications and creative tactics.', []],
      ['Financial & market communications', 'Expert guidance to build and enhance shareholder value.', ['Banking and investment communications', 'Market and shareholder relations', 'Investor communications', 'PR for start-ups and fundraising']],
      ['Community & stakeholder engagement', 'Build advocacy and strengthen relationships with key stakeholders.', ['Stakeholder engagement and mapping', 'Focus groups', 'Consumer research', 'Facilitated workshops']],
      ['Media relations & publicity', 'Driving earned media through targeted public relations.', ['Media strategy and advisory', 'Op-eds and byline editorial', 'Media events/press conference', 'Press office', 'Newsjacking']],
      ['Social media & influencer marketing', 'More than half the world uses social media. We help you join the tribe.', []],
      ['Media & presentation training', 'Preparing executives and leaders to communicate with confidence.', ['Media training', 'Presentation training', 'Public speaking', 'Social media training']],
    ],
    'closing' => 'Make what matters. Let’s talk.',
    'work' => ['caps' => 'Communications work', 'category' => 'communications'],
  ],
  'reputation' => [
    'title' => 'Reputation',
    'alias' => '/reputation',
    'menu' => 'Reputation',
    'description' => 'We shape and defend reputation through powerful media influence, communications expertise, political networks and data-informed tactics.',
    'masthead' => 'Build what matters.',
    'borrow_from' => 8,
    'intro_caps' => 'Reputation<br>matters.',
    'approach' => ['Our approach', 'We shape and defend reputation through powerful media influence, communications expertise, political networks and data-informed tactics, guided by Mark Forbes, former Editor-in-Chief of The Age, alongside Benjamin Haslem, one of Australia’s most experienced Public Affairs practitioners.'],
    'who' => ['Sectors we serve', ['Energy and renewables', 'Health', 'Superannuation and investment', 'Government', 'Education', 'Banking and Finance', 'Charities and not-for-profit', 'Aged care', 'Legal']],
    'quote' => 'We provide the skills to enhance reputation amidst the complexities of the modern media landscape, growing thought leaders and coaching media performers while strategically navigating complex issues and crises.',
    'services_caps' => 'Reputation<br>services',
    'services_line' => 'Enabling clients to communicate successfully with media, communities, customers, stakeholders and government.',
    'featured' => ['Crisis', 'Media training', 'Public affairs', 'Content'],
    'services' => [
      ['Reputation management', 'Helping clients anticipate, manage and mitigate reputation risks.', ['Thought leadership', 'Media liaison', 'Strategic integrated campaigns', 'Issues management', 'Stakeholder engagement', 'Digital, SEM and SEO optimisation']],
      ['Crisis communications', 'Trusted, expert counsel when you need it most.', ['Strategic counsel', 'Crisis management', 'Crisis and issues planning', 'Crisis simulation', 'Social listening and tactics', '24/7 crisis response and support']],
      ['Content creation', 'Join the conversation, shape the narrative with the right content at the right time.', ['Messaging and narrative', 'Storytelling', 'Internal and external communications', 'Video, photography and infographics', 'Digital and social media assets', 'Reports, white papers, EDMs']],
      ['Media training', 'Advance your communications agenda with customised training and expert advisory.', ['Spokesperson media training', 'Executive briefing and coaching', 'Television interview preparation', 'Presentation for executives', 'Crisis communications training', 'Crisis simulation']],
      ['Public affairs', 'We help you communicate your views on public policy issues by engaging with the right stakeholders to deliver better policy and legislation across all levels of government.', ['Government relations and public policy solutions', 'Corporate communications', 'Stakeholder & community engagement', 'Public affairs strategy and campaign advice', 'Preparation for parliamentary hearings and inquiries', 'Political and regulatory risk analysis', 'Litigation communications counsel']],
    ],
    'closing' => 'Make what matters. Let’s talk.',
    'work' => ['caps' => 'Reputation work', 'category' => 'reputation'],
  ],
  'production' => [
    'title' => 'Production',
    'alias' => '/production',
    'menu' => 'Production',
    'description' => 'We are creatives, writers, filmmakers, directors, producers, editors and photographers. We produce work for every channel — from TVC to mobile and from documentary to YouTube shorts.',
    'masthead' => 'Make it stand out. Make it memorable.',
    'borrow_from' => 8,
    'intro_caps' => 'What you make,<br>makes you.',
    'approach' => ['Production approach', 'We are creatives, writers, filmmakers, directors, producers, editors and photographers. Based in Melbourne, we collaborate with a network of producers and creators across Australia. We start conversations. Build communities. Grow brands. Engage people to better the world we live in.'],
    'who' => ['Capabilities', ['Online', 'Long-form documentary and narrative', 'TVC', 'Vodcasts', 'Podcasts', 'Interviews', 'Testimonials', 'Training videos']],
    'quote' => 'Decades of industry experience, technical skills and passion to make your project matter.',
    'services_caps' => 'Production<br>services',
    'services_line' => 'We produce work for every channel. From TVC to mobile and from documentary to YouTube shorts, we’re ready to make your project memorable.',
    'featured' => ['TVC', 'Documentary', 'Podcasts', 'Photography'],
    'services' => [
      ['Concept development', '', ['Creative development', 'Art direction', 'Treatments', 'Storyboarding', 'Script writing']],
      ['Pre production', '', ['Talent management', 'Location management', 'Crew management', 'Set design', 'Wardrobe']],
      ['Production', '', ['Direction', 'Cinematography', 'Photography', 'Live streaming', 'Lighting & grip', 'Sound']],
      ['Post production', '', ['Editing', 'Colour grading', 'GFX/VFX', 'Sound design and mix']],
      ['Animation', '', ['2D animation', '3D animation', 'GFX/VFX']],
      ['Live streaming', '', ['Streaming', 'Webinars', 'Training and support']],
      ['Photography', '', ['Brand', 'Lifestyle', 'Product', 'Portrait', 'Events']],
    ],
    'closing' => 'Make what matters. Let’s talk.',
    'work' => ['caps' => 'Production work', 'category' => ''],
  ],
];

$only = getenv('ICON_PAGE') ?: NULL;
$autosave = \Drupal::service(AutoSaveManager::class);

foreach ($pages as $key => $spec) {
  if ($only && $only !== $key) {
    continue;
  }
  // ---- find or create --------------------------------------------------------
  $page = NULL;
  $path = $aliases->lookupByAlias($spec['alias'], 'en');
  if ($path && preg_match('#^/page/(\d+)$#', $path['path'], $m)) {
    $page = $storage->load($m[1]);
  }
  if (!$page) {
    $page = $storage->create(['title' => $spec['title'], 'owner' => 1]);
    print "{$spec['title']}: no page yet, creating one.\n";
  }
  // The page's own masthead picture and filmstrip, when it has them (Creative),
  // else Digital's as placeholders.
  $source = $spec['borrow_from'] ?? $page->id();
  $aside = $page->id() ? $borrow((int) $page->id(), 'sdc.icon.split-masthead', 'aside') : [];
  $cards = $page->id() ? $borrow((int) $page->id(), 'sdc.icon.filmstrip', 'cards') : [];
  if (!$aside && $spec['borrow_from']) {
    $aside = $borrow((int) $spec['borrow_from'], 'sdc.icon.split-masthead', 'aside');
  }
  if (!$cards && $spec['borrow_from']) {
    $cards = $borrow((int) $spec['borrow_from'], 'sdc.icon.filmstrip', 'cards');
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
  $prose = static fn(string $html): array => ['text' => ['value' => $html, 'format' => 'canvas_html_block']];

  // ---- 1. the masthead, dark, the divider above, the picture beside ---------
  // the masthead draws its own lines, top and bottom (23 Sep 2026); no divider
  $masthead = $add('sdc.icon.split-masthead', ['eyebrow' => $spec['title'], 'title' => $spec['masthead'], 'style' => 'caps', 'heading_level' => 'h1', 'opening' => 'dark']);
  foreach ($aside as [$cid, $slot, $inputs]) {
    $add($cid, $inputs, $masthead, $slot);
  }
  // ---- 2. the filmstrip ------------------------------------------------------
  $strip = $add('sdc.icon.filmstrip', ['label' => $spec['title'] === 'Creative' ? 'ICON Creative' : 'The ICON team']);
  foreach ($cards as [$cid, $slot, $inputs]) {
    $add($cid, $inputs, $strip, $slot);
  }
  // ---- 3. the intro: the caps line as a third, the two columns beside -------
  $row = $add('sdc.icon.columns', ['columns' => 2, 'gap' => 'normal', 'box' => 'none', 'split' => 'narrow-wide']);
  $add('sdc.icon.prose', $prose('<h2>' . $spec['intro_caps'] . '</h2>'), $row, 'column_1');
  $pair = $add('sdc.icon.columns', ['columns' => 2, 'split' => 'even', 'gap' => 'wide', 'box' => 'none'], $row, 'column_2');
  $add('sdc.icon.prose', $prose('<h4>' . $e($spec['approach'][0]) . '</h4><p>' . $e($spec['approach'][1]) . '</p>'), $pair, 'column_1');
  $who = is_array($spec['who'][1])
    ? '<h4>' . $e($spec['who'][0]) . '</h4><ul class="list-plain">' . $li($spec['who'][1]) . '</ul>'
    : '<h4>' . $e($spec['who'][0]) . '</h4><p class="is-normal">' . $e($spec['who'][1]) . '</p>';
  $add('sdc.icon.prose', $prose($who), $pair, 'column_2');
  // ---- 4. the quote ----------------------------------------------------------
  $add('sdc.icon.pull-quote', ['text' => $spec['quote'], 'variant' => 'upright']);
  // ---- 5. "… services", the Large line beside --------------------------------
  $row = $add('sdc.icon.columns', ['columns' => 2, 'gap' => 'wide', 'box' => 'none', 'split' => 'narrow-wide']);
  $add('sdc.icon.prose', $prose('<h2>' . $spec['services_caps'] . '</h2>'), $row, 'column_1');
  $add('sdc.icon.prose', $prose('<p class="is-large">' . $e($spec['services_line']) . '</p>'), $row, 'column_2');
  // ---- 6. the services, three to a row, the Featured box first --------------
  $service = static function (array $s) use ($e, $li): string {
    $html = '<h4 class="is-caps">' . $e($s[0]) . '</h4>'; // Heading 4 — Caps (user call, 23 Sep 2026)
    if ($s[2]) {
      $html .= '<ul class="list-plain">' . $li($s[2]) . '</ul>';
    }
    else {
      $html .= '<p class="is-small">' . $e($s[1]) . '</p>';
    }
    return $html;
  };
  $cells = [['featured']];
  foreach ($spec['services'] as $s) {
    $cells[] = $s;
  }
  foreach (array_chunk($cells, 3) as $three) {
    $row = $add('sdc.icon.columns', ['columns' => 3, 'gap' => 'wide', 'box' => 'none', 'split' => 'even']);
    foreach ($three as $i => $cell) {
      $slot = 'column_' . ($i + 1);
      if ($cell === ['featured']) {
        $box = $add('sdc.icon.columns', ['columns' => 1, 'split' => 'even', 'gap' => 'tight', 'box' => 'light-grey'], $row, $slot);
        $add('sdc.icon.prose', $prose('<h4>Featured</h4><ul class="list-slash">' . $li($spec['featured']) . '</ul>'), $box, 'column_1');
        continue;
      }
      $add('sdc.icon.prose', $prose($service($cell)), $row, $slot);
      $add('sdc.icon.spacer', ['size' => 'normal'], $row, $slot);
    }
  }
  // ---- 7. the closing quote, the rail ---------------------------------------
  $add('sdc.icon.pull-quote', ['text' => $spec['closing'], 'variant' => 'upright']);
  $add('block.icon_work_latest', ['label' => 'Work: latest', 'label_display' => '0', 'accent' => 'Latest', 'caps' => $spec['work']['caps'], 'category' => $spec['work']['category'], 'count' => 6, 'rule_top' => FALSE, 'rule_bottom' => FALSE]);

  // ---- save ------------------------------------------------------------------
  $page->set('title', $spec['title']);
  $page->set('description', $spec['description']);
  $page->set('components', $tree);
  $page->set('path', ['alias' => $spec['alias']]);
  $page->setPublished(TRUE);
  if ($page->hasField('moderation_state')) {
    $page->set('moderation_state', 'published');
  }
  $violations = $page->validate();
  $bad = array_filter(iterator_to_array($violations), fn($v) => !str_contains((string) $v->getPropertyPath(), 'moderation'));
  if ($bad) {
    print "{$spec['title']}: NOT saved — " . strip_tags((string) reset($bad)->getMessage()) . " (" . reset($bad)->getPropertyPath() . ")\n";
    continue;
  }
  $page->setNewRevision(TRUE);
  $page->setRevisionLogMessage("{$spec['title']} page built in the Digital layout from the old site's words.");
  $page->save();
  $autosave->delete($page);
  print "{$spec['title']}: canvas_page {$page->id()}, " . count($tree) . " components at {$spec['alias']}\n";

  // ---- the menu --------------------------------------------------------------
  foreach ($etm->getStorage('menu_link_content')->loadByProperties(['menu_name' => 'main', 'title' => $spec['menu']]) as $link) {
    if ($link->getParentId()) {
      $link->set('link', ['uri' => 'internal:' . $spec['alias'], 'options' => []])->save();
      print "  main menu: {$spec['menu']} → {$spec['alias']}\n";
    }
  }
}
print "Done.\n";
