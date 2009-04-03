<?php
/**
 * Quickbooks Online Edition Module for Magento
 *
 * Developed by Xagax Solutions LLC (http://www.xagax.com)
 *
 * @copyright  Module Copyright (c) 2008 Xagax Solutions LLC (http://www.xagax.com)
 */

$installer = $this;

$installer->startSetup();

$installer->run("
DROP TABLE IF EXISTS `{$this->getTable('qboe_log')}`;
CREATE TABLE IF NOT EXISTS `{$this->getTable('qboe_log')}` (
  `qboe_log_id` int(20) NOT NULL auto_increment,
  `message_type` varchar(50) NOT NULL,
  `status_code` varchar(50) NOT NULL,
  `status_severity` varchar(50) default NULL,
  `status_message` varchar(250) default NULL,
  `date` datetime NOT NULL,
  KEY `qboe_log_id` (`qboe_log_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;
");

$installer->endSetup();
