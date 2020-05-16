<?php
/**
* Copyright © 2020 Roma Technology Limited. All rights reserved.
* See COPYING.txt for license details.
*/
namespace RTech\Parcel2Go\Model\Config\Source;

class ProductCategories implements \Magento\Framework\Option\ArrayInterface {
  protected $_categoryFactory;
  protected $_categoryCollectionFactory;

  public function __construct(
    \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
    \Magento\Catalog\Model\CategoryFactory $categoryFactory
  ) {
    $this->_categoryCollectionFactory = $categoryCollectionFactory;
    $this->_categoryFactory = $categoryFactory;
  }

  public function toOptionArray() {
    $arr = $this->_toArray();
    $ret = [];
    foreach ($arr as $key => $value){
      $ret[] = [
        'value' => $key,
        'label' => $value
      ];
    }
    return $ret;
  }

  private function _toArray(){
    $categories = $this->getCategoryCollection(true, false, false, false);
    $catagoryList = array();
    foreach ($categories as $category){
      $catagoryList[$category->getEntityId()] = __($this->_getParentName($category->getPath()) . $category->getName());
    }
    return $catagoryList;
  }

	private function getCategoryCollection($isActive = true, $level = false, $sortBy = false, $pageSize = false) {
		$collection = $this->_categoryCollectionFactory->create();
		$collection->addAttributeToSelect('*');

		if ($isActive) {
			$collection->addIsActiveFilter();
		}

		if ($level) {
			$collection->addLevelFilter($level);
		}

		if ($sortBy) {
			$collection->addOrderField($sortBy);
		}

		if ($pageSize) {
			$collection->setPageSize($pageSize);
		}

		return $collection;
	}

  private function _getParentName($path = ''){
    $parentName = '';
    $rootCats = array(1,2);
    $catTree = explode("/", $path);
    array_pop($catTree);
    if($catTree && (count($catTree) > count($rootCats))){
      foreach ($catTree as $catId){
        if(!in_array($catId, $rootCats)){
          $category = $this->_categoryFactory->create()->load($catId);
          $categoryName = $category->getName();
          $parentName .= $categoryName . ' -> ';
        }
      }
    }
    return $parentName;
  }
}