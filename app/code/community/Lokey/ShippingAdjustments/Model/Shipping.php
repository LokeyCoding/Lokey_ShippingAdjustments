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

class Lokey_ShippingAdjustments_Model_Shipping extends Mage_Shipping_Model_Shipping
{

    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        $store = Mage::app()->getStore($request->getStoreId());
        $active = Mage::helper('Lokey_ShippingAdjustments/Data')->isActive($store);

        // If the module is not active just call the parent method and return
        if (!$active) {
            parent::collectRates($request);
            return $this;
        }

        Mage::dispatchEvent('lokey_shippingadjustments_collectrates_prefilter', array('request' => $request));

        $originalItems = $request->getAllItems();

        // If there are no items just call the parent method and return
        if (count($originalItems) === 0) {
            return parent::collectRates($request);
        }

        // Build an array of all carrier codes
        $carrierCodes = array();
        $carriersConfig = Mage::getStoreConfig('carriers', $store);
        foreach ($carriersConfig as $code => $config) {
            $carrierCodes[] = $code;
        }

        // Check for a carrier code filter already on the request and filter our codes with it
        $limitCarrier = $request->getLimitCarrier();
        if ($limitCarrier) {
            if (!is_array($limitCarrier)) {
                $limitCarrier = array($limitCarrier);
            }
            $carrierCodes = array_intersect($limitCarrier, $carrierCodes);
        }

        // Check if we have any valid carrier codes
        if (count($carrierCodes) == 0) {
            return $this;
        }

        // Grab a list of shipping methods that the module adjusts.
        $shippingMethods = Mage::helper('Lokey_ShippingAdjustments/Data')->getShippingMethods($store);
        if (in_array('ALL', $shippingMethods)) {
            // We want to adjust all the carriers
            $adjustedCarrierCodes = $carrierCodes;
            $carrierCodes = array();
        } else {
            // Build a list of adjusted carriers and remove those codes
            // We cannot remove any code from the base list since we use method level activation, not carrier level
            $adjustedCarrierCodes = array();
            foreach ($shippingMethods as $shippingMethod) {
                $shippingMethod = explode('_', $shippingMethod, 2);
                $adjustedCarrierCodes[] = $shippingMethod[0];
            }
            $adjustedCarrierCodes = array_intersect($carrierCodes, $adjustedCarrierCodes);
        }

        $normalRates = array();
        $filteredRates = array();

        // Generate list of filtered items
        $removedItems = new Varien_Data_Collection();
        Mage::dispatchEvent(
            'lokey_shippingadjustments_raterequest_filter',
            array(
                 'store'         => $store,
                 'request'       => $request,
                 'all_items'     => $originalItems,
                 'removed_items' => $removedItems
            )
        );

        if (count($carrierCodes) > 0) {
            // Get the un-filtered rates
            $request->setLimitCarrier($carrierCodes);
            parent::collectRates($request);
            $normalRates = $this->getResult()->getAllRates();

            // Clear results - We'll build the result manually at the end
            $this->getResult()->reset();
        }

        // Limit this request to carriers that we know will be modified
        $request->setLimitCarrier($adjustedCarrierCodes);

        $filteredItemCount = count($originalItems) - count($removedItems->getItems());

        if ($filteredItemCount == 0) {
            // Special Case - All items are filtered!!!
            // This is not a good case!
            parent::collectRates($request);
            $filteredRates = $this->getResult()->getAllRates();

            foreach ($filteredRates as $k => $rate) {
                $filteredRate = new Mage_Shipping_Model_Rate_Result_Method();
                $filteredRate->setData($rate->getData());
                $filteredRate->setPrice(0.0);
                $filteredRates[$k] = $filteredRate;
            }

            // Clear results - We'll build the result manually at the end
            $this->getResult()->reset();
        } elseif ($filteredItemCount > 0) {
            $filteredItems = array();
            $removeQty = 0;
            $removeWeight = 0.0;
            $removeValue = 0.0;
            $removeDiscountedValue = 0.0;
            foreach ($originalItems as $item) {
                if ($removedItems->getItemById($item->getId())) {
                    $removeQty += $item->getQty();
                    $removeValue += $item->getBaseRowTotal();
                    $removeDiscountedValue += $item->getBaseRowTotalWithDiscount();
                    $removeWeight += $item->getRowWeight();
                } else {
                    $filteredItems[] = $item;
                }
            }
            $request->setPackageValue(max(round($request->getPackageValue() - $removeValue, 2), 0.0));
            $request->setPackageValueWithDiscount(max(round($request->getPackageValueWithDiscount() - $removeValue, 2), 0.0));
            $request->setPackageWeight(max($request->getPackageWeight() - $removeWeight, 0.0));
            $request->setFreeMethodWeight(max($request->getFreeMethodWeight() - $removeWeight, 0.0));
            $request->setPackageQty(max($request->getPackageQty() - $removeQty, 0));
            $request->setAllItems($filteredItems);

            parent::collectRates($request);
            $filteredRates = $this->getResult()->getAllRates();

            // Clear results - We'll build the result manually at the end
            $this->getResult()->reset();
        }

        // Adjust rates
        $adjustments = new Varien_Object(
            array(
                 'order' => 0.0,
                 'items' => array(),
            )
        );

        Mage::dispatchEvent(
            'lokey_shippingadjustments_adjustment_calculate',
            array(
                 'store'       => $store,
                 'request'     => $request,
                 'all_items'   => $originalItems,
                 'adjustments' => $adjustments
            )
        );

        // TODO: convert this to either use Magento internal rounding or locale/currency rounding
        $totalAdjustment = round(floatval($adjustments->getOrder()), 2);
        foreach ($adjustments->getItems() as $itemAdjustment) {
            $totalAdjustment += round(floatval($itemAdjustment), 2);
        }

        $allRates = array();
        foreach ($normalRates as $rate) {
            $allRates[$rate->getCarrier() . '_' . $rate->getMethod()] = $rate;
        }
        foreach ($filteredRates as $rate) {
            if (in_array('ALL', $shippingMethods) || in_array($rate->getCarrier() . '_' . $rate->getMethod(), $shippingMethods)) {
                $rate->setAdjusted(true);
                $rate->setOriginalPrice($rate->getPrice());
                $rate->setAdjustment($totalAdjustment);
                $rate->setPrice($rate->getOriginalPrice() + $totalAdjustment);

                $allRates[$rate->getCarrier() . '_' . $rate->getMethod()] = $rate;
            }
        }
        foreach ($allRates as $rate) {
            $this->getResult()->append($rate);
        }

        Mage::dispatchEvent(
            'lokey_shippingadjustments_collectrates_postfilter',
            array(
                 'store'     => $store,
                 'request'   => $request,
                 'result'    => $this->getResult(),
                 'all_items' => $originalItems
            )
        );

        return $this;
    }

    public function collectCarrierRates($carrierCode, $request)
    {
        $carrier = $this->getCarrierByCode($carrierCode, $request->getStoreId());
        if (!$carrier) {
            return $this;
        }

        $result = $carrier->checkAvailableShipCountries($request);
        if (false !== $result && !($result instanceof Mage_Shipping_Model_Rate_Result_Error)) {
            $result = $carrier->proccessAdditionalValidation($request);
        }

        /*
         * Result will be false if the admin set not to show the shipping module or
         * if the delivery country is not within specific countries
         */
        if (false !== $result) {
            if (!$result instanceof Mage_Shipping_Model_Rate_Result_Error) {
                $request->unsSkip();

                Mage::dispatchEvent(
                    'lokey_shippingadjustments_collectcarrierrates_prefilter',
                    array(
                         'carrier_code' => $carrierCode,
                         'request'      => $request
                    )
                );

                if ($request->getSkip() === true) {
                    return $this;
                }

                $result = $carrier->collectRates($request);

                Mage::dispatchEvent(
                    'lokey_shippingadjustments_collectcarrierrates_postfilter',
                    array(
                         'carrier_code' => $carrierCode,
                         'request'      => $request,
                         'result'       => $result
                    )
                );
            }

            // sort rates by price
            if (method_exists($result, 'sortRatesByPrice')) {
                $result->sortRatesByPrice();
            }

            $this->getResult()->append($result);
        }
        return $this;
    }
}
