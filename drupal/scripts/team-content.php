<?php

/**
 * @file
 * The leadership team, from iconagency.com.au/about (10 Sep 2026).
 *
 * Creates or updates the six Team member items with their portraits from
 * assets/profile/ (Image media, in the Intro folder). Re-runnable: matched
 * by name.
 *
 * Run: ddev exec "ICON_SEED=1 drush php:script scripts/team-content.php"
 */

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;

require_once __DIR__ . '/_guard.php';

$fs = \Drupal::service('file_system');
// Inside DDEV only drupal/ is mounted, so the portraits ride in
// sample-content/ like the offices' icons do; assets/profile/ is the design
// system's copy of the same files.
$source = DRUPAL_ROOT . '/../sample-content/team';
$dest = 'public://team';
$fs->prepareDirectory($dest, $fs::CREATE_DIRECTORY | $fs::MODIFY_PERMISSIONS);

$portrait = function (string $file, string $alt) use ($fs, $source, $dest): Media {
  $existing = \Drupal::entityTypeManager()->getStorage('media')->loadByProperties(['bundle' => 'image', 'name' => $file]);
  if ($existing) {
    return reset($existing);
  }
  $uri = $fs->copy("$source/$file", "$dest/$file", $fs::EXISTS_REPLACE);
  $f = File::create(['uri' => $uri, 'status' => 1]);
  $f->save();
  $m = Media::create([
    'bundle' => 'image',
    'name' => $file,
    'field_media_image' => ['target_id' => $f->id(), 'alt' => $alt],
    'field_media_category' => 'intro',
  ]);
  $m->save();
  return $m;
};

$p = static fn(string ...$paras): string => implode('', array_map(static fn($t) => "<p>$t</p>", $paras));

$team = [
  ['Joanne Painter', 'Group Managing Director', 'joanne-painter.png', 'https://www.linkedin.com/in/joannepainter/', $p(
    'As Group Managing Director, Joanne leads the ICON team across four Australian offices, driving the company’s strategic growth agenda while continuing to nurture our purpose-driven culture. Since 2017 she has spearheaded ICON’s national expansion program, opening our first interstate office in Canberra followed closely by Sydney and Brisbane.',
    'With 25 years of experience in communications, Joanne is a recognised leader in Australian public relations, serving on several industry boards and the national Board of the Public Relations Institute of Australia.',
    'With a proven track record in strategic communications, Joanne consults widely to business owners, Directors and the C-Suite on change management, media strategy, creative integration and behaviour change.',
  )],
  ['Chris Dodds', 'Managing Director, Digital', 'chris-dodds.png', 'https://www.linkedin.com/in/chrisdoddsiconmd/', $p(
    'Chris manages ICON’s digital division to help drive strategy, innovation, and client service.',
    'Based in Melbourne, Chris started his career in graphic design before completing a Masters Degree in Digital Media at RMIT University. Since founding ICON in 2002, he has helped build and guide the agency’s integrated service model – combining creative, digital and PR thinking into a cohesive team driven by respect and innovation. Chris oversees ICON’s safe implementation of AI across the agency, and is passionate about creating accessible services for people of all backgrounds and abilities.',
    'Chris is a member of RMIT University’s Industry Advisory Committee and a board member of Express Media, an NFP dedicated to developing, supporting, and promoting young writers.',
  )],
  ['Georgina Rees', 'Executive Director, Brand, Creative and Communications', 'georgina-rees.png', '', $p(
    'As Executive Director of Brand, Creative and Communications, Georgina leads ICON’s integrated communications offering, bringing together strategy, creative, content and communications to help organisations build trust, engage audiences and drive meaningful change.',
    'She oversees ICON’s Brand, Creative and Communications teams, providing strategic leadership across major client programs and ensuring work is insight-led, creatively ambitious and commercially effective.',
    'Georgina has worked with organisations including Salesforce, Figma, Novartis, the Department of Foreign Affairs and Trade, the World Health Organization, and the United Nations. Drawing on experience across government, technology, health and social impact sectors, she advises clients on brand strategy, audience engagement, communications and integrated campaign development.',
  )],
  ['Mark Forbes', 'Director of Reputation', 'mark-forbes.png', '', $p(
    'Mark is a Walkley-award winning journalist and former Editor-in-Chief of The Age in Melbourne. He has also worked at Four Corners and Channel Seven, in the Federal and State parliament press galleries and as an overseas correspondent.',
    'Mark brings almost 30 years of success at the highest levels of communications practice to his strategic communications services, including issues management, crisis management, interview coaching and media relations.',
    'Specialising in reputation building communications strategies, he has raised the profile of major companies, guided global manufacturers through controversial issues and assisted CEOs enhance their reputations.',
  )],
  ['Alex Wadelton', 'Creative Director', 'alex-wadelton.png', '', $p(
    'Alex is one of Australia’s most respected creative minds. He was the mastermind behind the creation of a statue honouring Indigenous footballer Nicky Winmar’s iconic stance against racism. He helped put an end to Australian supermarkets’ obsession with short-term plastic promotions with his social activist group Future Landfill, and has raised millions of dollars for a range of charities through creative thinking.',
    'He is the co-author, with Russel Howcroft, of the best-selling The Right-Brain Workout Volumes 1 &amp; 2, which have helped more than 20,000 people to re-train their brains to be more creative.',
    'Alex has won more than a hundred international advertising awards, and as Creative Director and Writer, created a wide array of powerful behaviour change advertising campaigns for Respect Victoria, the Victorian Responsible Gambling Foundation, Suicide Prevention Australia, Vision Australia, and many more.',
  )],
  ['Matt White', 'UX Design Director', 'matt-white.png', '', $p(
    'Matt is an award-winning UX design lead with two decades of experience in Australia and the UK. With a deep understanding of digital branding and visual design, Matt designs enriched digital experiences based on user-centred principles. He is passionate about helping brands communicate their story online, and believes in building seamless digital products through championing the people he is designing for.',
    'Matt’s design work has received many awards and accolades including the AWWWARDS, CSS Design Awards, AMI and Golden Target Awards. He has led UX design for clients such as the Department of Defence, Air Force, Mondelez, NDIS, Maurice Blackburn Lawyers, Artbank, ABCC, ASEA, iSelect, Kinetic Super, MCRI, The Royal Melbourne Hospital, Sustainability Victoria, and MyGuestList.',
  )],
];

$storage = \Drupal::entityTypeManager()->getStorage('node');
foreach ($team as $i => [$name, $role, $file, $linkedin, $bio]) {
  $existing = $storage->loadByProperties(['type' => 'team_member', 'title' => $name]);
  $node = $existing ? reset($existing) : Node::create(['type' => 'team_member', 'uid' => 1, 'title' => $name]);
  $node->set('field_team_role', $role);
  $node->set('field_team_bio', ['value' => $bio, 'format' => 'basic_html']);
  $node->set('field_team_photo', ['target_id' => $portrait($file, "Portrait of $name")->id()]);
  $node->set('field_team_linkedin', $linkedin ? ['uri' => $linkedin] : []);
  $node->set('field_team_weight', $i);
  $node->set('status', 1);
  $node->save();
  echo ($existing ? 'updated ' : 'created ') . "$name (node/{$node->id()})\n";
}
