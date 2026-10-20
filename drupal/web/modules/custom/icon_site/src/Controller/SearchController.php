<?php

declare(strict_types=1);

namespace Drupal\icon_site\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The site search: the results page, and the panel's JSON (19 Oct 2026).
 *
 * One query on Search API's `site` index, grouped by what the item is —
 * Work, News, Pages, People — in that order, each with an eyebrow, a
 * thumbnail and the highlighted excerpt the index gives. The page is the
 * full set; the panel (js/search-panel.js) reads a few per group as you
 * type, the way an iPad's search extends below its field.
 */
final class SearchController extends ControllerBase {

  /**
   * The groups, once read.
   *
   * @var array<string, string>|null
   */
  private ?array $groups = NULL;

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('file_url_generator'),
    );
  }

  /**
   * The groups, in order: the nav's own sections, then Pages.
   *
   * The main menu's top-level links in their order (user call, 20 Oct 2026:
   * "the filters need to match, i.e. All WORK EXPERTISE ABOUT NEWS
   * CONTACT"), Work and News among them, then Pages for a page outside the
   * menu.
   *
   * @return array<string, string>
   *   Key (the section's slug) to label.
   */
  private function groups(): array {
    if ($this->groups !== NULL) {
      return $this->groups;
    }
    $this->groups = [];
    if ($this->entityTypeManager->hasDefinition('menu_link_content')) {
      $storage = $this->entityTypeManager->getStorage('menu_link_content');
      $ids = $storage->getQuery()->accessCheck(FALSE)
        ->condition('menu_name', 'main')
        ->condition('enabled', 1)
        ->notExists('parent')
        ->sort('weight')
        ->execute();
      foreach ($storage->loadMultiple($ids) as $link) {
        $this->groups[$this->slug($link->getTitle())] = (string) $link->getTitle();
      }
    }
    $this->groups += [
      'work' => (string) $this->t('Work'),
      'news' => (string) $this->t('News'),
      'pages' => (string) $this->t('Pages'),
    ];
    return $this->groups;
  }

  /**
   * A section title as a group key.
   */
  private function slug(string $title): string {
    return trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($title)) ?? '', '-');
  }

  /**
   * The results page.
   */
  public function page(Request $request): array {
    $keys = trim((string) ($request->query->get('keys') ?? $request->query->get('q') ?? ''));
    $groups = $keys !== '' ? $this->search($keys, 60) : [];
    $build = [
      '#theme' => 'icon_search_results',
      '#keys' => $keys,
      '#groups' => $groups,
      '#total' => array_sum(array_map(static fn(array $g) => count($g['items']), $groups)),
      '#title' => $keys !== '' ? $this->t('Search: @keys', ['@keys' => $keys]) : $this->t('Search'),
    ];
    (new CacheableMetadata())
      ->addCacheContexts(['url.query_args:keys', 'url.query_args:q'])
      ->addCacheTags(['search_api_list:site'])
      ->applyTo($build);
    return $build;
  }

  /**
   * The panel's JSON: the results in their groups, and in one list.
   *
   * The one list is the index's order of relevance (the panel's All filter).
   * The panel is the search, so it gets everything, to a cap per group.
   */
  public function suggest(Request $request): CacheableJsonResponse {
    $keys = trim((string) ($request->query->get('q') ?? ''));
    $groups = mb_strlen($keys) >= 2 ? $this->search($keys, 100, 25) : [];
    $all = [];
    foreach ($groups as $group) {
      foreach ($group['items'] as $item) {
        $all[] = $item + ['group' => $group['key'], 'group_label' => $group['label']];
      }
    }
    usort($all, static fn(array $a, array $b) => $a['rank'] <=> $b['rank']);
    $response = new CacheableJsonResponse([
      'keys' => $keys,
      'total' => array_sum(array_map(static fn(array $g) => $g['total'], $groups)),
      'groups' => array_values($groups),
      'all' => array_slice($all, 0, 60),
      // The nav's sections, every one, for the chips (Pages is no section).
      'sections' => array_values(array_map(
        static fn(string $key, string $label) => ['key' => $key, 'label' => $label],
        array_keys(array_diff_key($this->groups(), ['pages' => 1])),
        array_diff_key($this->groups(), ['pages' => 1]),
      )),
    ]);
    $response->addCacheableDependency((new CacheableMetadata())
      ->addCacheContexts(['url.query_args:q'])
      ->addCacheTags(['search_api_list:site'])
      ->setCacheMaxAge(300));
    return $response;
  }

  /**
   * Runs the query and shapes the results into the groups.
   *
   * @return array<string, array{key: string, label: string, total: int, items: array<int, array<string, mixed>>}>
   *   The groups that have results, in order.
   */
  private function search(string $keys, int $limit, ?int $perGroup = NULL): array {
    $index = $this->entityTypeManager->getStorage('search_api_index')->load('site');
    if (!$index) {
      return [];
    }
    $query = $index->query();
    $query->keys($keys);
    $query->range(0, $limit);
    $query->sort('search_api_relevance', 'DESC');
    $results = $query->execute();
    $groups = [];
    foreach ($this->groups() as $key => $label) {
      $groups[$key] = ['key' => $key, 'label' => (string) $label, 'total' => 0, 'items' => []];
    }
    $rank = 0;
    foreach ($results->getResultItems() as $item) {
      $rank++;
      try {
        $entity = $item->getOriginalObject()?->getValue();
      }
      catch (\Throwable) {
        continue;
      }
      if (!$entity instanceof ContentEntityInterface || !$entity->access('view')) {
        continue;
      }
      $key = $this->groupOf($entity);
      if (!$key) {
        continue;
      }
      $groups[$key]['total']++;
      if ($perGroup !== NULL && count($groups[$key]['items']) >= $perGroup) {
        continue;
      }
      $groups[$key]['items'][] = [
        'title' => $entity->label(),
        'url' => $this->urlOf($entity),
        'eyebrow' => $this->eyebrowOf($entity),
        'excerpt' => (string) ($item->getExcerpt() ?? ''),
        'image' => $this->imageOf($entity),
        'rank' => $rank,
      ];
    }
    return array_filter($groups, static fn(array $g) => $g['total'] > 0);
  }

  /**
   * A row's address: a person's is their panel on the About page.
   *
   * /about/<slug>, as the team grid's cards link (user catch, 20 Oct 2026:
   * a person's row went to /node/302); everything else is its own page.
   */
  private function urlOf(ContentEntityInterface $entity): string {
    if ($entity instanceof NodeInterface && $entity->bundle() === 'team_member' && function_exists('icon_site_team_slug')) {
      return '/about/' . icon_site_team_slug($entity->label());
    }
    return $entity->toUrl()->toString();
  }

  /**
   * Which group an item belongs to, or NULL to leave it out.
   *
   * A case study is Work and a story News; a person goes under About, the
   * team's page; a Canvas page goes under its section of the nav (its own
   * top-level link, or its parent's), else Pages.
   */
  private function groupOf(ContentEntityInterface $entity): ?string {
    $groups = $this->groups();
    if ($entity->getEntityTypeId() === 'canvas_page') {
      $key = $this->slug($this->sectionOf($entity));
      return $key !== '' && isset($groups[$key]) ? $key : 'pages';
    }
    if ($entity instanceof NodeInterface) {
      return match ($entity->bundle()) {
        'work' => 'work',
        'news' => 'news',
        'team_member' => isset($groups['about']) ? 'about' : 'pages',
        default => NULL,
      };
    }
    return NULL;
  }

  /**
   * The line over the title: the client, the category and date, the role.
   */
  private function eyebrowOf(ContentEntityInterface $entity): string {
    if (!$entity instanceof NodeInterface) {
      return '';
    }
    $value = static fn(string $field): string => $entity->hasField($field) ? trim((string) $entity->get($field)->value) : '';
    return match ($entity->bundle()) {
      'work' => $value('field_work_client'),
      'news' => trim(ucfirst(str_replace('-', ' ', $value('field_news_category'))) . ' / ' . $this->dateFormatter->format($entity->getCreatedTime(), 'custom', 'j M Y'), ' /'),
      'team_member' => $value('field_team_role'),
      default => '',
    };
  }

  /**
   * The section of the nav a page sits in.
   *
   * The page's own main-menu link when it is top-level ("About"), else its
   * parent's ("Expertise" for the practice pages); nothing for a page that
   * is not in the menu.
   */
  private function sectionOf(ContentEntityInterface $entity): string {
    if (!$this->entityTypeManager->hasDefinition('menu_link_content')) {
      return '';
    }
    $storage = $this->entityTypeManager->getStorage('menu_link_content');
    $path = $entity->toUrl()->toString();
    $links = $storage->loadByProperties(['menu_name' => 'main', 'link__uri' => 'internal:' . $path]);
    $parent = NULL;
    foreach ($links as $link) {
      $parent = $link->getParentId();
      if (!$parent) {
        return (string) $link->getTitle();
      }
    }
    if (!$parent || !str_starts_with($parent, 'menu_link_content:')) {
      return '';
    }
    $parents = $storage->loadByProperties(['uuid' => substr($parent, strlen('menu_link_content:'))]);
    $parent = reset($parents);
    return $parent ? (string) $parent->getTitle() : '';
  }

  /**
   * A row's thumbnail: the first picture the item can offer.
   *
   * A case study reads its tile, then the wide tile, the banner and the
   * ground of a layered tile; a film among them gives its poster. A story
   * reads its picture, a person their photo. A Canvas page has none.
   */
  private function imageOf(ContentEntityInterface $entity): ?string {
    if (!$entity instanceof NodeInterface) {
      return NULL;
    }
    $fields = match ($entity->bundle()) {
      'work' => ['field_work_tile', 'field_work_tile_wide', 'field_work_banner', 'field_work_legacy_layers'],
      'news' => ['field_news_image'],
      'team_member' => ['field_team_photo'],
      default => [],
    };
    foreach ($fields as $field) {
      if (!$entity->hasField($field) || $entity->get($field)->isEmpty()) {
        continue;
      }
      $file = $this->pictureOf($entity->get($field)->entity);
      if ($file) {
        $style = $this->entityTypeManager->getStorage('image_style')->load('thumbnail');
        return $style ? $style->buildUrl($file->getFileUri()) : $this->fileUrlGenerator->generateString($file->getFileUri());
      }
    }
    return NULL;
  }

  /**
   * The image file a media item shows: its own picture, or a film's poster.
   */
  private function pictureOf(?ContentEntityInterface $media): ?FileInterface {
    if (!$media instanceof MediaInterface) {
      return NULL;
    }
    $candidates = [];
    $fileField = $media->getSource()?->getSourceFieldDefinition($media->bundle->entity)?->getName();
    if ($fileField && $media->hasField($fileField)) {
      $candidates[] = $media->get($fileField)->entity;
    }
    if ($media->hasField('field_media_poster')) {
      $candidates[] = $media->get('field_media_poster')->entity;
    }
    foreach ($candidates as $file) {
      if ($file instanceof FileInterface && str_starts_with((string) $file->getMimeType(), 'image/')) {
        return $file;
      }
    }
    return NULL;
  }

}
