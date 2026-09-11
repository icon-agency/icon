<?php

declare(strict_types=1);

namespace Drupal\icon_site\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The enquiry form (templates/contact.html), on any page.
 *
 * The form is the core Contact module's "Contact" form — Content → Contact
 * forms sets who receives it and the reply the sender sees — built here
 * into the contact-form SDC's slot and dressed by
 * icon_site_form_contact_message_contact_form_alter(). The lead and the
 * intro above the fields are the block's own words.
 */
#[Block(
  id: 'icon_contact_form',
  admin_label: new TranslatableMarkup('Contact form'),
  category: new TranslatableMarkup('ICON'),
)]
final class ContactFormBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The contact form's id (contact.form.contact).
   */
  public const string FORM = 'contact';

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityFormBuilderInterface $entityFormBuilder,
    protected readonly RendererInterface $renderer,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'), $container->get('entity.form_builder'), $container->get('renderer'));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'lead' => 'Simply fill in the form below',
      'intro' => 'Whether it’s a brand refresh, public relations push, new website or end-to-end behaviour change campaign — we’re interested and ready to talk solutions.',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['lead'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Lead'),
      '#default_value' => $this->configuration['lead'],
    ];
    $form['intro'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Intro'),
      '#default_value' => $this->configuration['intro'],
      '#rows' => 3,
      '#description' => $this->t('Who receives the message, and the reply the sender sees, are set at Content → Contact forms.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['lead'] = trim((string) $form_state->getValue('lead'));
    $this->configuration['intro'] = trim((string) $form_state->getValue('intro'));
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access site-wide contact form');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $message = $this->entityTypeManager->getStorage('contact_message')->create(['contact_form' => self::FORM]);
    $form = $this->entityFormBuilder->getForm($message);
    return [
      '#type' => 'component',
      '#component' => 'icon:contact-form',
      '#props' => array_filter([
        'lead' => (string) ($this->configuration['lead'] ?? ''),
        'intro' => (string) ($this->configuration['intro'] ?? ''),
      ]),
      '#slots' => [
        // Rendered here, not handed over as the form array: the component
        // element insists a slot be a render array all the way down
        // (Element::isRenderArray()), and a built form carries empty
        // children it rejects. render(), not renderPlain(): the block is
        // built inside the page's render, so the form's attachments and
        // placeholders bubble to it as they would from the array.
        'form' => ['#markup' => $this->renderer->render($form)],
      ],
    ];
  }

}
