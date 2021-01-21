<?php

namespace Drupal\twenty_five_live_events;

/**
 * Class R25LiveEvents.
 *
 * Handles requests and decoding of Events from the 25Live system.
 *
 * @package Drupal\twenty_five_live_events
 */
class R25LiveEvents {
  /**
   * The api connection.
   *
   * @var R25LiveConnection
   */
  protected $api = NULL;

  /**
   * R25LiveEvents constructor.
   */
  public function __construct() {
    $this->api = new R25LiveConnection();
  }

  /**
   * Return a list of events.
   *
   * @param array $parameters
   *   The list of query params to pass to events.xml.
   *
   * @return array
   *   The array of event objects.
   */
  public function getEventsList(array $parameters = []) : array {
    /**
     * The events list.
     *
     * @var array
     */
    $events_list = [];

    try {
      // Get the events.
      $api_response = $this->api->request(
        'reservations.xml',
        $parameters
      );

      if ($this->api->getStatus()) {
        switch ($this->api->getStatus('code')) {
          case 401:
            // Clear the status.
            $this->api->resetStatus();

            // Need to reauthenticate.
            $this->api->login();

            if ($this->api->isLoggedIn()) {
              // Try again for the events.
              $api_response = $this->api->request(
                'reservations.xml',
                $parameters
              );

              // If we fail again throw the exeption.
              if ($this->api->getStatus()) {
                throw new \Exception($this->api->getStatus('message'), $this->api->getStatus('code'));
              }
            }
            else {
              // Just throw the exception.
              throw new \Exception($this->api->getStatus('message'), $this->api->getStatus('code'));
            }
          default:
            throw new \Exception($this->api->getStatus('message'), $this->api->getStatus('code'));
        }
      }

      $response_xml = new \DOMDocument();
      $response_xml->loadXML($api_response);
      // Roll through the events and load the events list array.
      foreach ($response_xml->getElementsByTagName('reservation') as $event_xml) {
        // Clear the array.
        $this_event = [
          'id' => 0,
          'name' => '',
          'start_date' => '',
          'start_time' => '',
          'end_date' => '',
          'end_time' => '',
          'location' => '',
          'description' => '',
        ];

        // Load the event data.
        $this_event['id'] = $event_xml->getElementsByTagName('event_id')[0]->textContent;
        $this_event['name'] = $event_xml->getElementsByTagName('event_name')[0]->textContent;
        $event_start = new \DateTime($event_xml->getElementsByTagName('event_start_dt')[0]->textContent);
        $event_end = new \DateTime($event_xml->getElementsByTagName('event_end_dt')[0]->textContent);

        $this_event['start_date'] = $event_start->format('l j F Y');
        $this_event['start_time'] = $event_start->format('g:i a');
        $this_event['end_date'] = $event_end->format('l j F Y');
        $this_event['end_time'] = $event_end->format('g:i a');

        // Test for locations.
        $locations = $event_xml->getElementsByTagName('formal_name');
        if (count($locations) > 0) {
          $this_event['location'] = $locations[0]->textContent;
        }

        // Look for descriptions.
        foreach ($event_xml->getElementsByTagName('event_text') as $text) {
          if ($text->getElementsByTagName('text_type_id')[0]->textContent == 1) {
            $this_event['description'] .= $text->getElementsByTagName('text')[0]->textContent;
          }
        }

        // Add to the list.
        $events_list[] = $this_event;
      }

    }
    catch (\Exception $e) {
      \Drupal::logger('twenty_five_live_events')->info('Failed to get Events: ' . $e->getCode() . ' - ' . $e->getMessage());
    }

    return $events_list;
  }

}
