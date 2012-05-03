<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file OSL_LICENSE.txt
 *
 * @category   Mage
 * @package    Lokey_ShippingAdjustments
 * @copyright  Copyright (c) 2009 Lokey Coding, LLC <ip@lokeycoding.com>
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author     Lee Saferite <lee.saferite@lokeycoding.com>
 */

class Lokey_ShippingAdjustments_Helper_Data extends Mage_Core_Helper_Abstract
{

    public function isActive(Mage_Core_Model_Store $store)
    {
        return (bool)$store->getConfig('shipping/lokey_shippingadjustments/active');
    }

    public function getShippingMethods(Mage_Core_Model_Store $store)
    {
        $methods = (array)unserialize($store->getConfig('shipping/lokey_shippingadjustments/shipping_methods'));
        return (empty($methods) ? array('ALL') : $methods);
    }
}
