<?php
/**
* Copyright © 2020 Roma Technology Limited. All rights reserved.
* See COPYING.txt for license details.
*/
namespace RTech\Parcel2Go\Api\Data;

interface Parcel2GoClientInterface {

  /**
  * Get Parcel2Go access token
  *
  * @param string $uri
  * @param string $clientId
  * @param string $secret
  * @return string
  */
  public function getAccessToken($uri, $clientId, $secret);

  /**
  * Get ISO3 country code from ISO2 country code
  *
  * @param string $iso2Code
  * @return string
  */
  public function getIso3Code($iso2Code);
  
  /**
  * Get Parcel2Go quotes based on payload
  *
  * @param array $payload
  * @return array
  */
  public function getQuotes($payload);
}