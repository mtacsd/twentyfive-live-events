<?php

namespace Drupal\twenty_five_live_events\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for 25 Live Events routes.
 */
class TwentyFiveLiveEventsController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build() {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

}
