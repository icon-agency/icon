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
   * The groups, in order, with their labels.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   The label by group key.
   */
  private function groups(): array {
    return [
      'work' => $this->t('Work'),
      'news' => $this->t('News'),
      'page' => $this->t('Pages'),
      'team' => $this->t('People'),
    ];
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
   * The panel's JSON: the results in their groups.
   *
   * The panel is the search, so it gets everything, to a cap per group.
   */
  public function suggest(Request $request): CacheableJsonResponse {
    $keys = trim((string) ($request->query->get('q') ?? ''));
    $groups = mb_strlen($keys) >= 2 ? $this->search($keys, 100, 25) : [];
    $response = new CacheableJsonResponse([
      'keys' => $keys,
      'total' => array_sum(array_map(static fn(array $g) => $g['total'], $groups)),
      'groups' => array_values($groups),
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
    foreach ($results->getResultItems() as $item) {
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
        'url' => $entity->toUrl()->toString(),
        'eyebrow' => $this->eyebrowOf($entity),
        'excerpt' => (string) ($item->getExcerpt() ?? ''),
        'image' => $this->imageOf($entity),
      ];
    }
    return array_filter($groups, static fn(array $g) => $g['total'] > 0);
  }

  /**
   * Which group an item belongs to, or NULL to leave it out.
   */
  private function groupOf(ContentEntityInterface $entity): ?string {
    if ($entity->getEntityTypeId() === 'canvas_page') {
      return 'page';
    }
    if ($entity instanceof NodeInterface) {
      return match ($entity->bundle()) {
        'work' => 'work',
        'news' => 'news',
        'team_member' => 'team',
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
      return (string) $this->t('Page');
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
   * A small picture for the row, from the item's own tile or photo.
   */
  private function imageOf(ContentEntityInterface $entity): ?string {
    if (!$entity instanceof NodeInterface) {
      return NULL;
    }
    $field = match ($entity->bundle()) {
      'work' => 'field_work_tile',
      'news' => 'field_news_image',
      'team_member' => 'field_team_photo',
      default => NULL,
    };
    if (!$field || !$entity->hasField($field) || $entity->get($field)->isEmpty()) {
      return NULL;
    }
    $media = $entity->get($field)->entity;
    $source = $media?->getSource();
    if (!$media || !$source) {
      return NULL;
    }
    $fileField = $source->getSourceFieldDefinition($media->bundle->entity)?->getName();
    $file = $fileField ? $media->get($fileField)->entity : NULL;
    if (!$file || !str_starts_with((string) $file->getMimeType(), 'image/')) {
      return NULL;
    }
    $style = $this->entityTypeManager->getStorage('image_style')->load('thumbnail');
    return $style ? $style->buildUrl($file->getFileUri()) : $this->fileUrlGenerator->generateString($file->getFileUri());
  }

}
