<?php
/**
 * Copyright © 2020 Roma Technology Limited. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace RTech\Parcel2Go\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Sales\Model\Order\Shipment;
use Magento\Framework\Exception\ValidatorException;

class Parcel2Go extends AbstractCarrier implements CarrierInterface {

  const SERVICE = [
    'fast' => 'Next Day+',
    'medium' => 'Two Day+', 
    'slow' => 'Standard'
  ];
  protected $_code = 'parcel2go';
  protected $_isFixed = true;

  private $rateResultFactory;
  private $rateMethodFactory;
  private $encryptor;
  private $logger;
  private $parcel2GoClient;
  private $productRepository;

  public function __construct(
    \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
    \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
    \Magento\Framework\Encryption\EncryptorInterface $encryptor,
    \Psr\Log\LoggerInterface $logger,
    \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
    \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
    \RTech\Parcel2Go\Api\Data\Parcel2GoClientInterface $parcel2GoClient,
    \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
    array $data = []
  ) {
    parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);

    $this->rateResultFactory = $rateResultFactory;
    $this->rateMethodFactory = $rateMethodFactory;
    $this->encryptor = $encryptor;
    $this->logger = $logger;
    $this->parcel2GoClient = $parcel2GoClient;
    $this->productRepository = $productRepository;
  }

  public function collectRates(RateRequest $request) {
    if (!$this->getConfigFlag('active')) {
        return false;
    }

    try {
      $this->parcel2GoClient->getAccessToken(
        $this->getConfigData('url'),
        $this->getConfigData('clientid'),
        $this->encryptor->decrypt($this->getConfigData('clientsecret')));


      $origIso2Code = $this->getDefaultValue($request->getOrigCountryId(), Shipment::XML_PATH_STORE_COUNTRY_ID);
      $parcels = $this->getParcels($request);
      if (empty($parcels)) {
        return false;
      }

      $payload = [
        'CollectionAddress' => [
          'Country' => $this->parcel2GoClient->getIso3Code($origIso2Code),
          'Postcode' => $this->getDefaultValue($request->getOrigPostcode(), Shipment::XML_PATH_STORE_ZIP)
        ],
        'DeliveryAddress' => [
          'Country' => $this->parcel2GoClient->getIso3Code($request->getDestCountryId()),
          'Postcode' => $request->getDestPostcode()
        ],
        'Parcels' => $parcels
      ];
      
      $now = strtotime(date('c'));
      $dispatchTime = !empty($this->getConfigData('dispatchtime')) ? $this->getConfigData('dispatchtime') * 3600 : 0;
      $slots = [];
      
      foreach ($this->parcel2GoClient->getQuotes($payload) as $quote) {
        if ($quote['Service']['CollectionType'] == 'Collection' && $quote['Service']['DeliveryType'] == 'Door') {
          if (strtotime($quote['CutOff']) - $now > $dispatchTime) {
            $classification = strtolower($quote['Service']['Classification'] ?? 'slow');
            $lowest = $slots[$classification] ?? $quote;
            if ($lowest['TotalPriceExVat'] >= $quote['TotalPriceExVat']) {
              $slots[$classification] = $quote;
            }
          }
        }
      }

      $feeType = $this->getConfigData('feetype');
      $fee = $this->getConfigData('fee');
      $result = $this->rateResultFactory->create();
      foreach ($slots as $key => $slot) {
        $method = $this->rateMethodFactory->create();

        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title'));

        $method->setMethod($key);
        $method->setMethodTitle(__(self::SERVICE[$key]) . ' - ' . $slot['Service']['Name']);

        $cost = $slot['TotalPriceExVat'];
        if ($feeType == 'fixed') {
          $cost += $fee;
        } else {
          $cost += ($cost * ($fee / 100));
        }
        $method->setPrice($cost);
        $method->setCost($cost);
        $result->append($method);
      }
      return $result;
    } catch (\Exception $e) {
      $this->logger->error(__('Parcel2Go Shipping Error:'), ['exception' => $e]);
      return false;
    }
  }

  public function getAllowedMethods() {
    return [
      'fast' => 'Fast',
      'medium' => 'Medium',
      'slow' => 'Slow'
    ];
  }

  protected function getDefaultValue($origValue, $pathToValue) {
    if (!$origValue) {
      $origValue = $this->_scopeConfig->getValue(
        $pathToValue,
        \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
        $this->getStore()
      );
    }

    return $origValue;
  }

  private function getParcels($request) {
    $parcel = [];
    $restrictedGroups = str_getcsv($this->getConfigData('restricted'));

    // Need to check each item for restricted product and parcel each
    foreach ($request->getAllItems() as $item) {
      $product = $this->productRepository->getById($item->getProduct()->getId());
      // Check for restricted product and return no parcels if found
      if (count(array_intersect($restrictedGroups, $product->getCategoryCollection()->getAllIds()))) {
        return [];
      }
      $itemParcel = array_merge([
        'Value' => $item->getPrice(),
        'Weight' => $item->getWeight()
        ],
      $this->getBoxSize($this->getBoxSizeAttributeValue($product, $this->getConfigData('boxattribute'), $request->getStoreId())));
      for ($i = 0; $i < $item->getQty(); $i++) {
        $parcel[] = $itemParcel;
      }
    }

    // If total weight less or equal to minimum weight
    // ship as a single parcel in the minimum sized box
    $minWeight = $this->getConfigData('minweight');
    if ($request->getPackageWeight() <= $minWeight) {
      $parcel[] = array_merge([
        'Value' => $request->getPackageValue(),
        'Weight' => $minWeight
        ],
        $this->getBoxSize($this->getConfigData('minbox')));
    }

    return $parcel;
  }

  private function getBoxSize($dimensions) {
    preg_match_all('/\d+/', $dimensions, $matches);
    if (count($matches[0]) != 3) {
      $this->logger->warning(__('Parcel2Go invalid dimensions string %1', $dimensions));
      preg_match_all('/\d+/', $this->getConfigData('minbox'), $matches);
      if (count($matches[0]) != 3) {
        throw new ValidatorException(__('Parcel2Go invalid minbox dimensions configuration'));
      }
    }
    rsort($matches[0]);
    return [
      'Length' => $matches[0][0],
      'Width'=> $matches[0][1],
      'Height'=> $matches[0][2]
    ];
  }

  private function getBoxSizeAttributeValue($product, $attribute, $storeId) {
    try {
      $value = '';
      if ($product->getResource()->getAttribute($attribute)) {
        $found = $product->getResource()->getAttribute($attribute)->setStoreId($storeId)->getFrontend()->getValue($product);
        if ($found != false) {
          $value = $found;
        }
      }
      return $value;
    } catch (\Exception $e) {
      return $value;
    }
  }
}
