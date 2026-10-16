<?php

/**
 * @file
 * The mirror's media helpers, shared (16 Oct 2026): the fetch, the image
 * (resized on the way in, or taken as it is when the file is already in
 * place), the film, the pick by kind and the pixel-size reader. Required
 * by work-mirror.php and by work-embeds.php, which adds media to folios
 * already built. Defines $etm, $fs, $http, $fetch, $image, $video,
 * $media_for and $dims in the including script's scope.
 */

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

$etm = \Drupal::entityTypeManager();
$fs = \Drupal::service('file_system');
$uuid = \Drupal::service('uuid');
$http = \Drupal::httpClient();
$version = static function (string $id): string {
  $c = \Drupal::entityTypeManager()->getStorage('component')->load($id);
  if (!$c) {
    throw new \RuntimeException("Component $id is not registered.");
  }
  return (string) $c->get('active_version');
};
$slug = static fn(string $s): string => trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(\Drupal::transliteration()->transliterate($s))), '-');

/**
 * Downloads a URL to a temp file; NULL when it cannot.
 */
$fetch = static function (string $url) use ($http, $fs): ?string {
  $tmp = $fs->tempnam('temporary://', 'mirror-');
  // a file of our own — public://… — is copied, not fetched (a folio built
  // from supplied artwork rather than an old page; 16 Oct 2026, the Veteran
  // and Family Wellbeing Agency website)
  if (str_starts_with($url, 'public://')) {
    $path = $fs->realpath($url);
    if (!$path || !file_exists($path) || !copy($path, $fs->realpath($tmp))) {
      print "    ! no such file $url\n";
      return NULL;
    }
    return $tmp;
  }
  try {
    $http->get($url, ['sink' => $fs->realpath($tmp), 'timeout' => 300, 'headers' => ['User-Agent' => 'Mozilla/5.0 (ICON mirror)']]);
  }
  catch (\Throwable $e) {
    print "    ! could not fetch $url: " . $e->getMessage() . "\n";
    return NULL;
  }
  return $tmp;
};

/**
 * An image media from an old-site original, resized on the way in.
 *
 * @param string $role
 *   photo (2400 long edge, JPEG unless transparent) or tile (1600, PNG kept).
 */
$image = static function (string $url, string $alt, string $role, string $dir) use ($etm, $fs, $fetch): ?Media {
  $name = rawurldecode(basename(parse_url($url, PHP_URL_PATH)));
  $base = preg_replace('/\.(png|jpe?g|webp|gif)$/i', '', $name);
  $existing = $etm->getStorage('media')->loadByProperties(['bundle' => 'image', 'name' => $name]);
  if ($existing) {
    return reset($existing);
  }
  // A file ALREADY IN PLACE is used as it is: the host has no Imagick, so a
  // folio is built locally and its resized files travel up before the run
  // there (16 Oct 2026, user ask: "push our new folio items … to the upsun
  // drupal version"); the file is the local run's, resized and renamed.
  $slug = preg_replace('/[^A-Za-z0-9._-]+/', '-', $base);
  foreach (['jpg', 'png'] as $ext) {
    if (($path = $fs->realpath("$dir/$slug.$ext")) && file_exists($path)) {
      $f = File::create(['uri' => "$dir/$slug.$ext", 'status' => 1]);
      $f->save();
      $m = Media::create(['bundle' => 'image', 'name' => $name, 'field_media_category' => 'work', 'field_media_image' => ['target_id' => $f->id(), 'alt' => $alt ?: $base]]);
      $m->save();
      return $m;
    }
  }
  $tmp = $fetch($url);
  if (!$tmp) {
    return NULL;
  }
  $max = $role === 'tile' ? 1600 : 2400;
  try {
    $im = new \Imagick($fs->realpath($tmp));
    $im->setFirstIterator();
    $alpha = $im->getImageAlphaChannel() && (float) $im->getImageChannelMean(\Imagick::CHANNEL_ALPHA)['mean'] < 65535 * 0.999;
    $w = $im->getImageWidth();
    $h = $im->getImageHeight();
    if (max($w, $h) > $max) {
      $im->resizeImage($w >= $h ? $max : 0, $h > $w ? $max : 0, \Imagick::FILTER_LANCZOS, 1);
    }
    $im->stripImage();
    if ($alpha || $role === 'tile' && preg_match('/\.png$/i', $name)) {
      $im->setImageFormat('png');
      $ext = 'png';
    }
    else {
      $im->setImageFormat('jpeg');
      $im->setImageCompressionQuality(85);
      $ext = 'jpg';
    }
    $fs->prepareDirectory($dir, $fs::CREATE_DIRECTORY | $fs::MODIFY_PERMISSIONS);
    $uri = "$dir/" . preg_replace('/[^A-Za-z0-9._-]+/', '-', $base) . ".$ext";
    $im->writeImage($fs->realpath($uri) ?: $uri);
    $im->destroy();
  }
  catch (\Throwable $e) {
    print "    ! could not process $name: " . $e->getMessage() . "\n";
    return NULL;
  }
  finally {
    @$fs->delete($tmp);
  }
  $f = File::create(['uri' => $uri, 'status' => 1]);
  $f->save();
  $m = Media::create(['bundle' => 'image', 'name' => $name, 'field_media_category' => 'work', 'field_media_image' => ['target_id' => $f->id(), 'alt' => $alt ?: $base]]);
  $m->save();
  return $m;
};

/**
 * A video media from an old-site film, as it is.
 */
$video = static function (string $url, string $name, string $dir) use ($etm, $fs, $fetch): ?Media {
  $existing = $etm->getStorage('media')->loadByProperties(['bundle' => 'video', 'name' => $name]);
  if ($existing) {
    return reset($existing);
  }
  // a film already in place (see the image's note) is used as it is
  $uri = "$dir/" . preg_replace('/[^A-Za-z0-9._-]+/', '-', $name);
  if (!(($path = $fs->realpath($uri)) && file_exists($path))) {
    $tmp = $fetch($url);
    if (!$tmp) {
      return NULL;
    }
    $fs->prepareDirectory($dir, $fs::CREATE_DIRECTORY | $fs::MODIFY_PERMISSIONS);
    $uri = $fs->move($tmp, $uri, $fs::EXISTS_REPLACE);
  }
  $f = File::create(['uri' => $uri, 'status' => 1]);
  $f->save();
  $m = Media::create(['bundle' => 'video', 'name' => $name, 'field_media_category' => 'work', 'field_media_video_file' => ['target_id' => $f->id()]]);
  $m->save();
  return $m;
};

$media_for = static function (array $item, string $role, string $dir) use ($image, $video): ?Media {
  if ($item['kind'] === 'video') {
    return !empty($item['url']) ? $video($item['url'], $item['file'], $dir) : NULL;
  }
  return $image($item['url'], $item['alt'] ?? '', $role, $dir);
};

/**
 * A media's pixel size: an image from its file, a film from its MP4 header.
 *
 * The MP4's `tkhd` box carries the video track's width and height as 16.16
 * fixed-point numbers, and a rotation matrix that a phone's film may carry
 * (then width and height swap). No ffmpeg in the container, so read it here.
 */
$dims = static function (Media $m) use ($fs): array {
  $field = $m->bundle() === 'video' ? 'field_media_video_file' : 'field_media_image';
  $file = $m->get($field)->entity;
  if (!$file) {
    return [0, 0];
  }
  $path = $fs->realpath($file->getFileUri());
  if ($m->bundle() === 'image') {
    $i = @getimagesize($path);
    return $i ? [(int) $i[0], (int) $i[1]] : [0, 0];
  }
  $h = fopen($path, 'rb');
  $data = fread($h, min(filesize($path), 8 * 1024 * 1024));
  fclose($h);
  $pos = 0;
  while (($pos = strpos($data, 'tkhd', $pos)) !== FALSE) {
    $v = ord($data[$pos + 4]);
    // The box body follows the type: version and flags (4), the times, track
    // id and duration (20, or 32 in version 1), reserved (8), layer, group,
    // volume and reserved (8), then the matrix (36), then width and height.
    $matrix = $pos + 4 + 4 + ($v === 1 ? 32 : 20) + 8 + 8;
    $w = unpack('N', substr($data, $matrix + 36, 4))[1] / 65536;
    $hh = unpack('N', substr($data, $matrix + 40, 4))[1] / 65536;
    if ($w > 0 && $hh > 0) {
      $a = unpack('N', substr($data, $matrix, 4))[1];
      $d = unpack('N', substr($data, $matrix + 16, 4))[1];
      // a == 0 and d == 0: the track is rotated a quarter turn.
      return ($a === 0 && $d === 0) ? [(int) $hh, (int) $w] : [(int) $w, (int) $hh];
    }
    $pos += 4;
  }
  return [0, 0];
};
