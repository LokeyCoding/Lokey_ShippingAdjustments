<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file OSL_LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Mage
 * @package    LKC_ShippingAdjustmentsCore
 * @copyright  Copyright (c) 2009 Lokey Coding, LLC <ip@lokeycoding.com>
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author     Lee Saferite <lee.saferite@lokeycoding.com>
 */


class LKC_ShippingAdjustmentsCore_Helper_Data extends Mage_Core_Helper_Abstract
{

    public function isActive(Mage_Core_Model_Store $store)
    {
        return (bool) $store->getConfig('shipping/lkc_shippingadjustments/active');
    }

    public function getShippingMethods(Mage_Core_Model_Store $store)
    {
        $methods = (array) unserialize($store->getConfig('shipping/lkc_shippingadjustments/shipping_methods'));
        return (empty($methods) ? array('ALL') : $methods);
    }

}
