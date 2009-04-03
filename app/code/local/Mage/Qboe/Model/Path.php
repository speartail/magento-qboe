<?php
class Mage_Qboe_Model_Path
{
/**
 * Quickbooks Online Edition Module for Magento
 *
 * Developed by Xagax Solutions LLC (http://www.xagax.com)
 *
 * @copyright  Module Copyright (c) 2008 Xagax Solutions LLC (http://www.xagax.com)
 */
    public function toOptionArray()
    {
        return array(
            array('value'=>'https://webapps.ptc.quickbooks.com/j/AppGateway', 'label'=>__('Test')),
            array('value'=>'https://webapps.quickbooks.com/j/AppGateway', 'label'=>__('Production')),
        );
    }

}