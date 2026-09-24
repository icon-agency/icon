<?php

declare(strict_types=1);

namespace Drupal\icon_site\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The global footer's words (Content → Footer).
 *
 * The call to action, the newsletter line, the acknowledgement. The offices
 * and the social links are lists of their own, linked from here.
 */
final class FooterSettingsForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
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
  protected function getEditableConfigNames(): array {
    return ['icon_site.footer'];
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
      '#type' => 'url',
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
      '#default_value' => (string) ($this->config('icon_site.footer')->get('login_hero') ?: ''),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('icon_site.footer')
      ->set('abn', trim((string) $form_state->getValue('abn')))
      ->set('privacy_url', trim((string) $form_state->getValue('privacy_url')))
      ->set('concern_url', trim((string) $form_state->getValue('concern_url')))
      ->set('bcorp_url', trim((string) $form_state->getValue('bcorp_url')))
      ->set('eyebrow', trim((string) $form_state->getValue('eyebrow')))
      ->set('talk', trim((string) $form_state->getValue('talk')))
      ->set('touch', trim((string) $form_state->getValue('touch')))
      ->set('touch_url', trim((string) $form_state->getValue('touch_url')))
      ->set('newsletter', trim((string) $form_state->getValue('newsletter')))
      ->set('acknowledgement', trim((string) $form_state->getValue('acknowledgement')))
      ->set('login_hero', (int) $form_state->getValue('login_hero'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
