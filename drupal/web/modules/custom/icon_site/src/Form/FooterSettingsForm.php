<?php

declare(strict_types=1);

namespace Drupal\icon_site\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * The global footer's words (Content → Footer): the call to action, the
 * newsletter line, the acknowledgement. The offices and the social links
 * are lists of their own, linked from here.
 */
final class FooterSettingsForm extends ConfigFormBase {

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
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('icon_site.footer')
      ->set('talk', trim((string) $form_state->getValue('talk')))
      ->set('touch', trim((string) $form_state->getValue('touch')))
      ->set('touch_url', trim((string) $form_state->getValue('touch_url')))
      ->set('newsletter', trim((string) $form_state->getValue('newsletter')))
      ->set('acknowledgement', trim((string) $form_state->getValue('acknowledgement')))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
