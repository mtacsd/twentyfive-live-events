<?php

namespace Drupal\twenty_five_live_events;

use Symfony\Component\HttpClient\HttpClient;

/**
 * Class R25LiveConnection.
 *
 * Handles the authentication and retreival of events from 25Live.
 *
 * @package Drupal\twenty_five_live_events
 */
class R25LiveConnection {

  /**
   * HTTP method for API call.
   *
   * @var string
   */
  protected $method = 'GET';

  /**
   * Configuration settings for the API call.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config = NULL;

  /**
   * Temporary Storage to hold the API session cookie.
   *
   * @var \Drupal\user\PrivateTempStore
   */
  protected $sessionStore = NULL;

  /**
   * Cookie values for the api calls.
   *
   * @var array
   */
  protected $cookies = [];

  /**
   * Status of the call. Easy access to error state and message.
   *
   * @var array
   */
  protected $status = [
    'error' => FALSE,
    'code'  => 0,
    'message' => '',
  ];

  /**
   * Guzzle client to handle the API calls.
   *
   * @var Symfony\Component\HttpClient\HttpClient
   */
  protected $client = NULL;

  /**
   * R25LiveConnection constructor.
   */
  public function __construct() {
    $this->config = \Drupal::config('twenty_five_live_events.settings');
    $this->sessionStore = \Drupal::service('user.private_tempstore')->get('twenty_five_live_events');

    $this->client = HttpClient::create();

    if (!$this->isLoggedIn()) {
      $this->login();
    }
  }

  /**
   * Return the value of the indicated status array member.
   *
   * @param string $key
   *   The value to return.
   *
   * @return mixed
   *   The value of the requested key. NULL for bad key.
   */
  public function getStatus(string $key = 'error') {
    /**
     * The status value to return
     *
     * @var mixed
     */
    $status = NULL;

    if (array_key_exists($key, $this->status)) {
      $status = $this->status[$key];
    }

    return $status;
  }

  /**
   * Get the array of cookies.
   *
   * @return array
   *   The current cookies array
   */
  public function getCookies() : array {
    return $this->cookies;
  }

  /**
   * Get the value of the stored session.
   *
   * @return string
   *   The stored API session variable.
   */
  public function getApiSessionValue() : string {
    return (string) $this->sessionStore->get('api_session');
  }

  /**
   * Returns logged in state.
   *
   * @return bool
   *   The logged in state.
   */
  public function isLoggedIn() : bool {
    $session_cookie = $this->getApiSessionValue();

    if (strlen($session_cookie) < 1) {
      return FALSE;
    }

    // Set the cookie for later use.
    $this->cookies[] = $session_cookie;

    return TRUE;
  }

  /**
   * Make a request of the API endpoint.
   *
   * @param string $xml_doc
   *   The last part of the endpoint.
   * @param array $query_params
   *   Optional list of key/value pairs.
   * @param string $request_body
   *   Optional body of reqest to be sent.
   */
  public function request(string $xml_doc, array $query_params = [], string $request_body = '') {
    /**
     * The array of items to send to the API
     *
     * @var array
     */
    $request = [];

    /**
     * The body of the message returned from the API.
     *
     * @var string
     */
    $body = '';

    // Clear status prior to the request.
    $this->resetStatus();

    try {
      // Handle headers.
      $headers = [
        'Content-Type' => 'text/xml; charset=UTF-8',
        'Accept' => 'text/xml; charset=UTF-8',
      ];

      if (count($this->cookies) > 0) {
        foreach ($this->cookies as $cookie) {
          $headers['Cookie'][] = $cookie;
        }
      }

      $request['headers'] = $headers;

      // Handle paramters.
      if (count($query_params) > 0) {
        $request['query'] = $query_params;
      }

      // Handle body.
      if (strlen($request_body) > 0) {
        $request['body'] = $request_body;
      }

      $endpoint = $this->buildUri($xml_doc);

      $response = $this->client->request(
        $this->method,
        $endpoint,
        $request,
      );

      // Test on the status code.
      $response_code = $response->getStatusCode();
      if ($response_code >= 300) {
        throw new \Exception('API Request Error', $response_code);
      }
      else {
        $this->setStatus(FALSE, $response_code);
      }

      $headers = $response->getHeaders();

      // Save the cookies.
      $this->setCookieValues($headers);

      $body = $response->getContent(FALSE);

    }
    catch (\Exception $e) {
      $this->setStatus(TRUE, $e->getCode(), $e->getMessage());
      $body = $e->getMessage();
    }

    return $body;
  }

  /**
   * Login the the 25Live API.
   *
   * @todo Handle the fail cases.
   */
  public function login() {
    /**
     * The Login XML Document that is maniplulated.
     *
     * @var \DOMDocument
     */
    $login_xml = new \DOMDocument();

    /**
     * The Login Response XML Document that is maniplulated.
     *
     * @var \DOMDocument
     */
    $login_response_xml = new \DOMDocument();

    // First get the challenge.
    $body = $this->request('login.xml');
    $login_xml->loadXML($body);

    // Retreive the challenge token.
    // Need some sort of xpath stuff here.
    $challenge_node = $login_xml->getElementsByTagName('challenge')[0];
    $username_node = $login_xml->getElementsByTagName('username')[0];
    $response_node = $login_xml->getElementsByTagName('response')[0];

    $challenge_token = $challenge_node->textContent;
    \Drupal::logger('twenty_five_live_events')->info('Challenge Token: ' . $challenge_token);

    // Create the response token.
    $response_token = $this->getResponseToken($challenge_token);
    \Drupal::logger('twenty_five_live_events')->info('Response Token: ' . $response_token);
    // Build the response body.
    $response_node->textContent = $response_token;
    $username_node->textContent = $this->config->get('r25user');
    $challenge_node->textContent = '';
    \Drupal::logger('twenty_five_live_events')->info(htmlentities($login_xml->saveXML()));

    // Post the login response.
    $this->method = 'POST';
    $login_response = $this->request('login.xml', [], $login_xml->saveXML());

    // Reset the request method.
    $this->method = 'GET';

    // Verify logged in.
    if (FALSE === $this->getStatus('error')) {
      // The login response will be xml so need to decode it.
      $login_response_xml->loadXML($login_response);
      \Drupal::logger('twenty_five_live_events')->info('Login Post response:');
      \Drupal::logger('twenty_five_live_events')->info(htmlentities($login_response_xml->saveXML()));

      // If the login is successful save the session cookie to use later.
      $this->sessionStore->set('api_session', $this->getApiSessionString());
      $this->cookies[] = $this->getApiSessionString();
      $this->resetStatus();
    }
  }

  /**
   * Return the API session string from the cookies.
   *
   * It is the one that starts 'WSSESSIONID'.
   *
   * @return string
   *   The session string
   */
  private function getApiSessionString() : string {
    foreach ($this->getCookies() as $cookie) {
      if ('WSSESSIONID' === substr($cookie, 0, 11)) {
        return (string) $cookie;
      }
    }

    return '';
  }

  /**
   * Build and return the login response token.
   *
   * Combines the hashed password and challenge token into an encrypted
   * response token.
   *
   * @param string $challenge_token
   *   The login challenge token from 25Live.
   *
   * @return string
   *   The MD5 encrypted response token.
   */
  private function getResponseToken(string $challenge_token) : string {
    $aes = new AesEncrypt();
    // Retrieve the stored encrypted password and the key from the settings.
    $stored_pass = $this->config->get('r25password');
    $key = $this->config->get('clef');

    // Recover the password.
    $password = $aes->decrypt($stored_pass, $key);

    // Encode the password for the token creation.
    $encoded_pass = md5($password);

    return md5($encoded_pass . ':' . $challenge_token);
  }

  /**
   * Takes the action and returns the endpoint uri for the API call.
   *
   * @param string $xml_doc
   *   The xml document to act on, (eg. events.xml).
   *
   * @return string
   *   The full endpoint URI.
   */
  private function buildUri(string $xml_doc) : string {
    // CLean up the action.
    $xml_doc = trim($xml_doc);
    try {
      if (strlen($this->config->get('r25school')) < 1) {
        throw new \Exception('The Organization code must be entered in the admin settings.', 412);
      }

      if (strlen($xml_doc) < 1) {
        throw new \Exception('No action provided.', 406);
      }

      return 'https://webservices.collegenet.com/r25ws/wrd/' . $this->config->get('r25school') . '/run/' . $xml_doc;
    }
    catch (\Exception $e) {
      $this->setStatus(TRUE, $e->getCode(), $e->getMessage());
      return '';
    }
  }

  /**
   * Set the cookie values.
   *
   * @param mixed $headers
   *   The headers object returned by the api call.
   */
  private function setCookieValues($headers) {
    if (array_key_exists('set-cookie', $headers)) {
      foreach ($headers['set-cookie'] as $cookie) {
        $this->cookies[] = $cookie;
      }
    }
  }

  /**
   * Sets the error status array.
   *
   * @param bool $is_error
   *   The error state.
   * @param int $code
   *   The error code (based on HTML status codes).
   * @param string $message
   *   The error message.
   */
  private function setStatus(bool $is_error = FALSE, int $code = 0, string $message = '') {
    $this->status['error'] = $is_error;
    $this->status['code'] = $code;
    $this->status['message'] = $message;
  }

  /**
   * Clear the status array.
   */
  public function resetStatus() {
    $this->status['error'] = FALSE;
    $this->status['code'] = 0;
    $this->status['message'] = '';
  }

}
