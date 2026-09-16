<?php

declare(strict_types=1);

namespace Drupal\icon_site\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The enquiry form (templates/contact.html), on any page.
 *
 * Two forms behind one question — "What is this about?", Business enquiry
 * or Careers (user ask, Sep 2026, after the old site's careers modal): the
 * core Contact module's "Contact" and "Careers" forms — Content → Contact
 * forms sets who receives each and the reply the sender sees — built here
 * into the contact-form SDC's two slots and dressed by
 * _icon_site_contact_form_dress(). The block has no settings of its own:
 * words above the question are a Content component placed before it.
 */
#[Block(
  id: 'icon_contact_form',
  admin_label: new TranslatableMarkup('Contact form'),
  category: new TranslatableMarkup('ICON'),
)]
final class ContactFormBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The business enquiry form's id (contact.form.contact).
   */
  public const string FORM = 'contact';

  /**
   * The careers form's id (contact.form.careers).
   */
  public const string CAREERS = 'careers';

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityFormBuilderInterface $entityFormBuilder,
    protected readonly RendererInterface $renderer,
    protected readonly RequestStack $requestStack,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'), $container->get('entity.form_builder'), $container->get('renderer'), $container->get('request_stack'));
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
    // The answer ticked at render: Careers when that form has just come back
    // with errors (the page re-renders on the POST), else the default —
    // otherwise the errors would sit behind the Business enquiry form.
    $posted = (string) ($this->requestStack->getCurrentRequest()?->request->get('form_id') ?? '');
    return [
      '#type' => 'component',
      '#component' => 'icon:contact-form',
      '#props' => [
        'topic' => $posted === 'contact_message_' . self::CAREERS . '_form' ? 'careers' : 'business',
      ],
      '#slots' => [
        // Rendered here, not handed over as the form arrays: the component
        // element insists a slot be a render array all the way down
        // (Element::isRenderArray()), and a built form carries empty
        // children it rejects. render(), not renderPlain(): the block is
        // built inside the page's render, so the forms' attachments and
        // placeholders bubble to it as they would from the arrays.
        'form' => ['#markup' => $this->form(self::FORM)],
        'careers' => ['#markup' => $this->form(self::CAREERS)],
      ],
    ];
  }

  /**
   * One of the site's contact forms, built and rendered.
   *
   * A form whose config is not in yet (a host between code and config)
   * renders as nothing rather than a crash.
   */
  private function form(string $id): MarkupInterface|string {
    if (!$this->entityTypeManager->getStorage('contact_form')->load($id)) {
      return '';
    }
    $message = $this->entityTypeManager->getStorage('contact_message')->create(['contact_form' => $id]);
    return $this->renderer->render($this->entityFormBuilder->getForm($message));
  }

}
