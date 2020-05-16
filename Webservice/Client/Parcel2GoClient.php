<?php
/**
* Copyright © 2018 Roma Technology Limited. All rights reserved.
* See COPYING.txt for license details.
*/
namespace RTech\Parcel2Go\Webservice\Client;

use RTech\Parcel2Go\Api\Data\Parcel2GoClientInterface;
use RTech\Parcel2Go\Webservice\Exception\Parcel2GoCommunicationException;
use RTech\Parcel2Go\Webservice\Exception\Parcel2GoOperationException;

class Parcel2GoClient implements Parcel2GoClientInterface {

  const ACCESS_TOKEN_API = 'auth/connect/token';
  const QUOTES_API = 'api/quotes';
  const COUNTRIES_API = 'api/countries';

  const GET = \Zend\Http\Request::METHOD_GET;
  const POST = \Zend\Http\Request::METHOD_POST;
  const PUT = \Zend\Http\Request::METHOD_PUT;
  const DELETE = \Zend\Http\Request::METHOD_DELETE;

  const TYPE_X_WWW_FORM_ENCODED = 'application/x-www-form-urlencoded';
  const TYPE_JSON = 'application/json';

  protected $_zendClient;
  protected $_logger;

  private $uri;
  private $accessToken = null;
  private $countries = null;

  public function __construct(
    \Zend\Http\Client $zendClient
  ) {
    $this->_zendClient = $zendClient;
    $this->_logger = \Magento\Framework\App\ObjectManager::getInstance()->get(\Psr\Log\LoggerInterface::class);
  }

  protected function callParcel2Go($uri, $method, $contentType, $parameters) {
    $this->_zendClient->reset();
    $this->_zendClient->setUri($uri);
    $this->_zendClient->setMethod($method);
    $requestHeaders = $this->_zendClient->getRequest()->getHeaders();
    $requestHeaders->addHeaders(['Content-Type' => $contentType]);
    if ($this->accessToken) {
      $requestHeaders->addHeaders([
        'Authorization' => 'Bearer ' . $this->accessToken,
      ]);
    }

    if ($method === self::GET || $method === self::DELETE) {
      $this->_zendClient->setParameterGet($parameters);
    } else {
      if ($contentType == self::TYPE_JSON) {
        $this->_zendClient->setRawBody(json_encode($parameters));
      } else {
        $this->_zendClient->setParameterPost($parameters);
      }
    }

    try {
      $this->_zendClient->send();
      $response = $this->_zendClient->getResponse();
    }
    catch (\Zend\Http\Exception\RuntimeException $runtimeException) {
      throw Parcel2GoCommunicationException::runtime($runtimeException->getMessage());
    }
    $errorCodes = [
      \Zend\Http\Response::STATUS_CODE_400,
      \Zend\Http\Response::STATUS_CODE_401,
      \Zend\Http\Response::STATUS_CODE_403,
      \Zend\Http\Response::STATUS_CODE_404,
      \Zend\Http\Response::STATUS_CODE_405,
      \Zend\Http\Response::STATUS_CODE_406,
      \Zend\Http\Response::STATUS_CODE_429,
      \Zend\Http\Response::STATUS_CODE_500,
    ];
    if (in_array($response->getStatusCode(), $errorCodes)) {
      throw Parcel2GoOperationException::create($response->getBody());
    }
    // unknown error response codes
    if (!$response->isSuccess()) {
      throw new Parcel2GoCommunicationException($response->getBody());
    }
    return json_decode($response->getBody(), true);
  }

  /**
  * @inheritdoc
  */
  public function getAccessToken($uri, $clientId, $secret) {
    $this->uri = substr($uri, -1) == '/' ? $uri : $uri .'/';
    $response = $this->callParcel2Go($this->uri . self::ACCESS_TOKEN_API, self::POST, self::TYPE_X_WWW_FORM_ENCODED, [
      'grant_type' => 'client_credentials',
      'scope' => 'public-api payment',
      'client_id' => $clientId,
      'client_secret' => $secret
    ]);
    try {
      $this->accessToken = $response['access_token'];
    } catch (\Exception $e) {
      throw new Parcel2GoOperationException(__('Unable to authorise Parcel2Go'));
    }
  }

  /**
  * @inheritdoc
  */
  public function getIso3Code($iso2Code) {
    if (!$this->countries) {
      $this->countries = $this->callParcel2Go($this->uri . self::COUNTRIES_API, self::GET, 'application/json', []);
    }
    $position = array_search($iso2Code, array_column($this->countries, 'Iso2Code'));
    if ($position === false) {
      return '';
    }
    return $this->countries[$position]['Iso3Code'];
  }
  
  /**
  * @inheritdoc
  */
  public function getQuotes($payload) {
    return $this->callParcel2Go($this->uri . self::QUOTES_API, self::POST, self::TYPE_JSON, $payload)['Quotes'] ?? [];
  }
}
