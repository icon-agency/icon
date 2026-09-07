<?php

/**
 * @file
 * The global footer's content, as the design system ships it: the four
 * offices (Office content, with their icons as Icon media from
 * sample-content/icons/office-*.svg), the footer's words (icon_site.footer)
 * and the social links (the footer-social menu). The footer template reads
 * all of it (templates/includes/site-footer.html.twig).
 *
 * Run from drupal/:  ddev drush php:script scripts/offices-content.php
 * Idempotent: offices are matched by city, icons by name, links by title.
 */

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Drupal\system\Entity\Menu;

$source_dir = DRUPAL_ROOT . '/../sample-content/icons';
$fs = \Drupal::service('file_system');
$dest = 'public://icons';
$fs->prepareDirectory($dest, $fs::CREATE_DIRECTORY | $fs::MODIFY_PERMISSIONS);
$media_storage = \Drupal::entityTypeManager()->getStorage('media');

/** One Icon media per SVG, reused across runs (matched by name). */
$icon = function (string $file, string $name) use ($source_dir, $dest, $fs, $media_storage): Media {
  $existing = $media_storage->loadByProperties(['bundle' => 'icon', 'name' => $name]);
  if ($existing) {
    return reset($existing);
  }
  $uri = $fs->copy("$source_dir/$file", "$dest/$file", $fs::EXISTS_REPLACE);
  $f = File::create(['uri' => $uri, 'status' => 1]);
  $f->save();
  $weight = (int) ($media_storage->getAggregateQuery()->accessCheck(FALSE)->condition('bundle', 'icon')->aggregate('field_logo_weight', 'MAX')->execute()[0]['field_logo_weight_max'] ?? 0) + 1;
  $m = Media::create(['bundle' => 'icon', 'name' => $name, 'field_media_file' => ['target_id' => $f->id()], 'field_logo_weight' => $weight]);
  $m->save();
  return $m;
};

$offices = [
  ['Melbourne', 'Naarm', "Suite 1, Level 2\n132 Gwynne Street\nCremorne VIC 3121 Australia", '03 9642 4107', 'melbourne@iconagency.com.au', 'office-melbourne.svg', 'Office — Melbourne'],
  ['Sydney', 'Gadigal', "Suite 5, Level 6\n2–12 Foveaux Street\nSurry Hills NSW 2010 Australia", '02 6185 2860', 'sydney@iconagency.com.au', 'office-sydney.svg', 'Office — Sydney'],
  ['Brisbane', 'Meanjin', "25 King Street\nBowen Hills\nBrisbane QLD 4006 Australia", '07 3155 6528', 'brisbane@iconagency.com.au', 'office-brisbane.svg', 'Office — Brisbane'],
  ['Fiji', 'Suva', "Suva Business Center\n177–181 Victoria Parade\nSuva, Fiji", '', 'fiji@iconagency.com.au', 'office-fiji.svg', 'Office — Fiji'],
];
$node_storage = \Drupal::entityTypeManager()->getStorage('node');
foreach ($offices as $i => [$city, $place, $address, $phone, $email, $file, $icon_name]) {
  $existing = $node_storage->loadByProperties(['type' => 'office', 'title' => $city]);
  $node = $existing ? reset($existing) : Node::create(['type' => 'office', 'uid' => 1, 'title' => $city]);
  $node->set('field_office_place', $place);
  $node->set('field_office_address', $address);
  $node->set('field_office_phone', $phone);
  $node->set('field_office_email', $email);
  $node->set('field_office_icon', ['target_id' => $icon($file, $icon_name)->id()]);
  $node->set('field_office_weight', $i);
  $node->set('status', 1);
  $node->save();
  echo ($existing ? 'updated ' : 'created ') . "office $city (node/{$node->id()})\n";
}

// The words.
\Drupal::configFactory()->getEditable('icon_site.footer')
  ->set('talk', 'Let’s talk')
  ->set('touch', 'Get in touch')
  ->set('touch_url', '/contact')
  ->set('newsletter', 'Join our newsletter for industry insights, news and our latest work')
  ->set('acknowledgement', 'ICON recognises the First Peoples of this nation and their ongoing connection to culture and country. We acknowledge First Nations Peoples as the Traditional Owners, Custodians and Lore Keepers of the world’s oldest living culture and pay respects to their Elders past, present and emerging.')
  ->save();
echo "footer words set\n";

// The social links: a menu, so add / edit / drag / delete are Drupal's own.
if (!Menu::load('footer-social')) {
  Menu::create(['id' => 'footer-social', 'label' => 'Social links', 'description' => 'The social links in the global footer, in order.'])->save();
  echo "created menu footer-social\n";
}
$links = [['LinkedIn', 'https://www.linkedin.com/company/iconagency'], ['Instagram', 'https://www.instagram.com/iconagency']];
$link_storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
foreach ($links as $w => [$title, $url]) {
  $existing = $link_storage->loadByProperties(['menu_name' => 'footer-social', 'title' => $title]);
  if (!$existing) {
    MenuLinkContent::create(['title' => $title, 'link' => ['uri' => $url], 'menu_name' => 'footer-social', 'weight' => $w, 'expanded' => FALSE])->save();
    echo "created link $title\n";
  }
}
echo "done\n";
