<?php
/**
 * Copyright © 2020 Roma Technology Limited. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace RTech\Parcel2Go\Webservice\Exception;

class Parcel2GoOperationException extends \Exception {

  public static function create($responseBody) {
    $response = json_decode($responseBody, true);
    if ($response && isset($response['message'])) {
      $message = $response['message'];
    } else {
      $message = sprintf('Parcel2Go API operation failed. Response: "%s"', $responseBody);
    }

    return new static($message);
  }
}