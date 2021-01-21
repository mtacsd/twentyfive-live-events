<?php

namespace Drupal\twenty_five_live_events\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\twenty_five_live_events\AesEncrypt;

/**
 * Configure 25 Live Events settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'twenty_five_live_events_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['twenty_five_live_events.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['r25user'] = [
      '#type' => 'textfield',
      '#title' => $this->t('25Live UserName'),
      '#default_value' => $this->config('twenty_five_live_events.settings')->get('r25user'),
      '#required' => TRUE,
    ];
    $form['r25password'] = [
      '#type' => 'password',
      '#title' => $this->t('Update 25Live Password'),
      '#default_value' => '',
    ];
    $form['r25school'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Organization name for 25Live (used in the REST API connection strings)'),
      '#default_value' => $this->config('twenty_five_live_events.settings')->get('r25school'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    $r25password = $this->getPasswordFieldFromForm($form_state->getValue('r25password'));

    if (strlen($r25password) == 0) {
      $form_state->setErrorByName('r25password', $this->t('Please enter a Password for the 25Live system.'));
    }

    if (trim($form_state->getValue('r25user')) == '') {
      $form_state->setErrorByName('r25user', $this->t('Please enter a UserName for the 25Live system.'));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Need to encode the password on save.
    $r25password = trim($form_state->getValue('r25password'));

    if (strlen($r25password) > 0) {
      // Run the password save through the system.
      $this->encryptAndSavePassword($r25password);
    }

    // Save the rest of the form items.
    $this->config('twenty_five_live_events.settings')
      ->set('r25user', $form_state->getValue('r25user'))
      ->set('r25school', $form_state->getValue('r25school'))
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Use the AES encryption to store the updated password.
   *
   * @param string $password_string
   *   The password to be encrypted and stored.
   *
   * @uses \twenty_five_live_events\AesEncrypt
   */
  private function encryptAndSavePassword(string $password_string) {
    $aes = new AesEncrypt();
    // Update the Encryption Key.
    $key = $aes->generateKey();

    // Encrypt the password.
    $hashed_pass = $aes->encrypt($password_string, $key);

    // Store the key for later (probably not the best practice
    // but I can't see any other option in Drupal)
    $this->config('twenty_five_live_events.settings')
      ->set('clef', $key)
      ->set('r25password', $hashed_pass)
      ->save();

  }

  /**
   * Ensure that we have a password value to work with.
   *
   * Returns the entered value from the form if present,
   * otherwise returns the saved value from the database.
   *
   * @param string $password_string
   *   The password entered in the form.
   *
   * @return string
   *   The existing or updated password string.
   */
  private function getPasswordFieldFromForm($password_string) : string {
    // Remove whitespace.
    $password_string = trim($password_string);

    if (strlen($password_string) == 0) {
      $password_string = $this->config('twenty_five_live_events.settings')->get('r25password');
    }

    return $password_string;
  }

}
