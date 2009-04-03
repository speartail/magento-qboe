<?php
/**
 * Quickbooks Online Edition Module for Magento
 *
 * Developed by Xagax Solutions LLC (http://www.xagax.com)
 *
 * @copyright  Module Copyright (c) 2008 Xagax Solutions LLC (http://www.xagax.com)
 */
class Mage_Qboe_Helper_Data extends Mage_Core_Helper_Abstract
{
	const XML_PATH_QBMS_METHODS = 'qboe';

	public function getMethodInstance($code)
    {
		$key = self::XML_PATH_QBMS_METHODS.'/'.$code.'/model';
        $class = Mage::getStoreConfig($key);
        if (!$class) {
            Mage::throwException($this->__('Can not configuration for payment method with code: %s', $code));
        }
        return Mage::getModel($class);
    }
}
