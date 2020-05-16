<?php
/**
* Copyright © 2020 Roma Technology Limited. All rights reserved.
* See COPYING.txt for license details.
*/
namespace RTech\Parcel2Go\Model\Config\Source;

class FixedPercent implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [['value' => 'fixed', 'label' => __('Fixed')], ['value' => 'percent', 'label' => __('Percent')]];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return ['fixed' => __('Fixed'), 'percent' => __('Percent')];
    }
}