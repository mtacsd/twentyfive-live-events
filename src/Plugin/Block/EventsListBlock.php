<?php

namespace Drupal\twenty_five_live_events\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\twenty_five_live_events\R25LiveEvents;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "twenty_five_live_events_eventslist",
 *   admin_label = @Translation("Events List"),
 *   category = @Translation("25 Live Events")
 * )
 */
class EventsListBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $markup = '<h2>Upcoming Events</h2>';

    \Drupal::logger('twenty_five_live_events')->info('in the example block');
    $api = new R25LiveEvents();

    \Drupal::logger('twenty_five_live_events')->info('about to get events');
    $start = new \DateTime();
    $end = new \DateTime();
    $end->add(new \DateInterval('P3D'));
    $parameters = [
      'start_dt' => $start->format('Ymd'),
      'end_dt' => $end->format('Ymd'),
      'event_state' => 2,
      'node_type' => 'E',
      'scope' => 'extended',
      'event_type_id' => '22+29+33+41+44+43',
    ];

    $events = $api->getEventsList($parameters);

    if (is_array($events)) {
      for ($i = 0; $i < 30; $i++) {
        if (array_key_exists($i, $events)) {
          $this_event = $events[$i];

          $markup .= '<div class="event"><p class="event-title">';
          $markup .= $this_event['name'] . '</p>';
          $markup .= '<p class="event-date">' . $this_event['start_date'] . ' ' . $this_event['start_time'] . '</p>';
          if (strlen($this_event['description']) > 0) {
            $markup .= '<p class="event-description">' . $this_event['description'] . '</p>';
          }
          if (strlen($this_event['location']) > 0) {
            $markup .= '<p class="event-location">' . $this_event['location'] . '</p>';
          }

          $markup .= '</div>';
        }
      }
    }

    $build['content'] = [
      '#markup' => $markup,
    ];

    return $build;
  }

}
