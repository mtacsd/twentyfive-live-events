<?php

namespace Drupal\twenty_five_live_events;

/**
 * Class AesEncrypt.
 *
 * Handles AES 256 encryption and decryption functions.
 */
class AesEncrypt {

  /**
   * Generate a new base64 encoded key.
   *
   * @return string
   *   The new encoded key.
   */
  public function generateKey() : string {
    return base64_encode(openssl_random_pseudo_bytes(32));
  }

  /**
   * Encrypt the provided string with the initialization vector attached.
   *
   * @param string $raw_string
   *   The string to encrypt.
   * @param string $key
   *   The base64 encoded key.
   *
   * @return string
   *   The encrypted string.
   */
  public function encrypt(string $raw_string, string $key) : string {
    // Decode the base64 encoded key.
    $plain_key = base64_decode($key);

    // Generate the initialization vector.
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));

    // Encrypt the provided string.
    $encrypted_string = openssl_encrypt($raw_string, 'aes-256-cbc', $plain_key, 0, $iv);

    // Attach the initialization vector to the encrypted_string
    // and base64 endcode the lot.
    return base64_encode($encrypted_string . '||' . $iv);
  }

  /**
   * Decrypt the provided string using the provided key.
   *
   * @param string $encrypted_string
   *   The string to decrypt.
   * @param string $key
   *   The base64 encoded key.
   *
   * @return string
   *   The original plaintext string.
   */
  public function decrypt(string $encrypted_string, string $key) : string {
    // Decode the base64 encoded key.
    $plain_key = base64_decode($key);

    // Decode and split apart the encrypted string to get what we want to
    // decrypt and the initialization vector.
    list($encrypted_text, $iv) = explode('||', \base64_decode($encrypted_string), 2);

    // Perform the final decryption and return th plaintext string.
    return openssl_decrypt($encrypted_text, 'aes-256-cbc', $plain_key, 0, $iv);
  }

}
