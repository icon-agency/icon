<?php

declare(strict_types=1);

namespace Drupal\icon_site\Form;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The global footer's words (Content → Footer).
 *
 * The call to action, the newsletter line, the acknowledgement, the legal
 * line. The offices and the social links are lists of their own, linked
 * from here.
 *
 * SAVED TO STATE, NOT CONFIG (29 Sep 2026): these are an editor's words, and
 * the config object they used to be saved into is exported and imported on
 * every deploy, which reset them (backend audit M8). See
 * icon_site_footer_setting(). The links are checked on the way in (C2).
 */
final class FooterSettingsForm extends FormBase {

  /**
   * The link fields, by their titles, for the validation message.
   */
  private const LINKS = ['touch_url', 'privacy_url', 'concern_url', 'bcorp_url'];

  public function __construct(
    protected readonly StateInterface $state,
    protected readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('state'),
      $container->get('cache_tags.invalidator'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'icon_site_footer';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $footer = icon_site_footer();
    $form['help'] = [
      '#markup' => '<p>' . $this->t('The words in the footer on every page. The offices are their own list — <a href=":offices">Offices</a> — and the social links are a menu — <a href=":social">Social links</a> (add, edit, drag to reorder, delete).', [
        ':offices' => Url::fromRoute('icon_site.offices')->toString(),
        ':social' => Url::fromRoute('entity.menu.edit_form', ['menu' => 'footer-social'])->toString(),
      ]) . '</p>',
    ];
    $form['abn'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ABN'),
      '#description' => $this->t('In the legal line under the acknowledgement — "© (this year) ICON Agency (ABN …)". Leave it empty for no ABN.'),
      '#default_value' => $footer['abn'],
      '#maxlength' => 20,
    ];
    $form['privacy_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Privacy statement link'),
      '#description' => $this->t('Where "Privacy statement." goes, a path or a URL. Leave it empty to drop the words.'),
      '#default_value' => $footer['privacy_url'],
      '#maxlength' => 255,
    ];
    $form['concern_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Raise a concern link'),
      '#description' => $this->t('Where "Raise a concern." goes, a path or a URL. Leave it empty to drop the words.'),
      '#default_value' => $footer['concern_url'],
      '#maxlength' => 255,
    ];
    $form['bcorp_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('B Corp listing URL'),
      '#description' => $this->t('Where the B Corp mark at the foot of the page links — the B Corp directory until the listing is up. Leave it empty to hide the mark.'),
      '#default_value' => $footer['bcorp_url'],
      '#maxlength' => 255,
    ];
    $form['eyebrow'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Call to action eyebrow'),
      '#description' => $this->t('The serif line over the big words — "Make what matters". Leave it empty for none.'),
      '#default_value' => $footer['eyebrow'],
      '#maxlength' => 60,
    ];
    $form['talk'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Call to action'),
      '#description' => $this->t('The big words, one per line on the page — "Let’s talk". Each word rises on its own.'),
      '#default_value' => $footer['talk'],
      '#required' => TRUE,
      '#maxlength' => 40,
    ];
    $form['touch'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Call to action link'),
      '#description' => $this->t('The small line with the arrow — "Get in touch".'),
      '#default_value' => $footer['touch'],
      '#required' => TRUE,
      '#maxlength' => 60,
    ];
    $form['touch_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Where it goes'),
      '#description' => $this->t('A path on this site, like /contact, or a full URL.'),
      '#default_value' => $footer['touch_url'],
      '#required' => TRUE,
      '#maxlength' => 255,
    ];
    $form['newsletter'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Newsletter line'),
      '#default_value' => $footer['newsletter'],
      '#maxlength' => 255,
    ];
    $form['acknowledgement'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Acknowledgement of Country'),
      '#default_value' => $footer['acknowledgement'],
      '#rows' => 4,
    ];
    // The login page's reel: the hero block of a Canvas page.
    $pages = ['' => $this->t('The front page (default)')];
    foreach ($this->entityTypeManager->getStorage('canvas_page')->loadMultiple() as $page) {
      if (icon_site_hero_order_of($page) || $page->access('view')) {
        $pages[$page->id()] = $page->label() . (icon_site_hero_order_of($page) ? '' : ' — ' . $this->t('no hero block'));
      }
    }
    $form['login'] = ['#type' => 'details', '#title' => $this->t('Login page'), '#open' => TRUE];
    $form['login']['login_hero'] = [
      '#type' => 'select',
      '#title' => $this->t('Hero reel to play'),
      '#description' => $this->t('The account pages play the slides of this page’s hero, in its order — so reordering that hero reorders the login page too.'),
      '#options' => $pages,
      '#default_value' => (string) (icon_site_footer_setting('login_hero') ?: ''),
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    foreach (self::LINKS as $key) {
      $value = trim((string) $form_state->getValue($key));
      if (!icon_site_footer_link_is_safe($value)) {
        $form_state->setErrorByName($key, $this->t('%title takes a path on this site starting with a slash, or a full http, https, mailto or tel link.', ['%title' => $form[$key]['#title']]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = [];
    $keys = [
      'abn',
      'privacy_url',
      'concern_url',
      'bcorp_url',
      'eyebrow',
      'talk',
      'touch',
      'touch_url',
      'newsletter',
      'acknowledgement',
    ];
    foreach ($keys as $key) {
      $values[$key] = trim((string) $form_state->getValue($key));
    }
    $values['login_hero'] = (int) $form_state->getValue('login_hero');
    $this->state->set('icon_site.footer', $values);
    $this->cacheTagsInvalidator->invalidateTags(['icon_site_footer']);
    $this->messenger()->addStatus($this->t('The footer has been saved.'));
  }

}
