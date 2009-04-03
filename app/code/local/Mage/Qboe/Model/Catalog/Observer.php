<?php
/**
 * Quickbooks Online Edition Module for Magento
 *
 * Developed by Xagax Solutions LLC (http://www.xagax.com)
 *
 * @copyright  Module Copyright (c) 2008 Xagax Solutions LLC (http://www.xagax.com)
 */
class Mage_Qboe_Model_Catalog_Observer extends Mage_Qboe_Model_Qboe
{

 	public function catalogProductSaveBefore( Varien_Event_Observer $observer){
 		if ($this->isDisable()){
			return;
		}
 	
 		$event = $observer->getEvent();
		if ($event->getProduct()->getData('type_id') != 'simple'){
			return;
		}
		
		$att_prod_conf = split('[|]', $this->getConfigDataQboe('configu3/salesAttibuteSet',0));
		$setId = $event->getProduct()->getAttributeSetId();
 		if (($setId) && isset($att_prod_conf)) {
			$set = Mage::getModel('eav/entity_attribute_set')->load($setId);
			if(in_array($set->getAttributeSetName(),$att_prod_conf)){
				$stores = Mage::getModel('core/store')->getResourceCollection()->setLoadDefault(false)->load();
				$store_conf = array();
				foreach ($stores as $store) {
					$account = $this->getConfigDataQboe('configu3/salesReceiptAccount',$store->getId());
					$desc = $this->getConfigDataQboe('configu3/addDescription',$store->getId());
					if($account && !(in_array($account.'-'.$desc,$store_conf))){
						//
						$arr_cat_prod[$store->getId()]['account'] = $account;
						$arr_cat_prod[$store->getId()]['desc'] = $desc;
						$store_conf[$store->getId()] = $account.'-'.$desc;
					}
				}
			}
		}	
		if(!isset($arr_cat_prod)){
			$arr_cat_prod[0]['account'] = $this->getConfigDataQboe('configu3/salesReceiptAccount',0);
			$arr_cat_prod[0]['desc'] = $this->getConfigDataQboe('configu3/addDescription',0);		
		}
	
		$salesReceiptAccount = $this->getConfigDataQboe('configu3/salesReceiptAccount');
 		if (!$salesReceiptAccount){
			$messageType = 'conf_error';
 			$statusCode = 'conf_error';
			$statusSeverity = 'error';
			$statusMessage = 'QBOE - Sales Receipt Account not defined';
			$status_db = $conn_db->query("insert into qboe_log(message_type,status_code,status_severity,status_message,date) values('".$messageType."','".$statusCode ."','".$statusSeverity."','".$statusMessage."','".date('Y-m-d H:i:s')."')");
			return;
		}
		
		$appLogin = $this->getConfigDataQboe('configu/ApplicationLogin');
  		$conTicket = $this->getConfigDataQboe('configu/ConnectionTicket');
		$cert = $this->getConfigDataQboe('configu/Cert');
		$appID = $this->getConfigDataQboe('configu/appID');
		$applicationPath = $this->getConfigDataQboe('configu/ApplicationPath');
		$sessionTicket = $this->sessionTicketQboe();
		if(!isset($sessionTicket)){
			return;
		}
		$signonMsgsRq = $this->signonMsgsRq($sessionTicket);
		
		$conn_db = Mage::getSingleton('core/resource')->getConnection('core_write');
		
		if ($event->getProduct()->getOrigData('sku')){
			if ($event->getProduct()->getOrigData('sku') != $event->getProduct()->getData('sku')){
				$querySku = $event->getProduct()->getOrigData('sku');
			}else {
				$querySku = $event->getProduct()->getData('sku');
			}
		}else{
			$querySku = $event->getProduct()->getData('sku');
		}
		
		//foreach por arr_cat_prod
		foreach ($arr_cat_prod as $cat_prod) {
			//QBOE - XML CustomerQueryRq
			$xml = $this->queryQboe($querySku.$cat_prod['desc'],$sessionTicket,'ItemServiceQueryR');		
			$messageType = 'ItemServiceQueryRs';
			$statusCode=$xml['statusCode'];
			$requestID=$xml['statusSeverity'];
			$statusMessage=$xml['statusMessage'];
			
			foreach ($xml->children() as $second_gen) {
				$ListID = $second_gen->ListID;
				$EditSequence= $second_gen->EditSequence;    
			}
			
			switch ($statusCode) {
				case 0:
					unset($QBXML);
					unset($header); 
					unset($ch);
					unset($data);
					$QBXML[0] = '<?xml version="1.0" encoding="utf-8"?>'.
								'<?qbxml version="6.0"?>'.
								'<QBXML>'.
									$signonMsgsRq.
									'<QBXMLMsgsRq onError="stopOnError">'.
										'<ItemServiceModRq>'.
											'<ItemServiceMod>'.
												'<ListID >'.$ListID.'</ListID>'.
												'<EditSequence >'.$EditSequence.'</EditSequence>'.
												'<Name>'.$event->getProduct()->getData('sku').$cat_prod['desc'].'</Name>'.
												'<SalesOrPurchaseMod>'.
													'<Desc>'.$event->getProduct()->getData('name').'</Desc>'.
													'<Price>'.$event->getProduct()->getData('price').'</Price>'.
												'</SalesOrPurchaseMod>'.
											'</ItemServiceMod>'.
										'</ItemServiceModRq>'.
									'</QBXMLMsgsRq>'.
								'</QBXML>';
					$QBXML[0] = str_replace("&", "&amp;", $QBXML[0]);
					
					$header[] = "Content-type: application/x-qbxml";
					$header[] = "Content-length: ".strlen($QBXML[0]);
	
					$handle = fopen("/tmp/curlerrors.txt", "w");
	                           
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_POST, 1);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
					curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
					curl_setopt($ch, CURLOPT_URL, $applicationPath);
					curl_setopt($ch, CURLOPT_TIMEOUT, 60);
					curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
					curl_setopt($ch, CURLOPT_POSTFIELDS, $QBXML[0]);
					curl_setopt($ch, CURLOPT_STDERR, $handle);
					curl_setopt($ch, CURLOPT_VERBOSE, 1);
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
					curl_setopt($ch, CURLOPT_SSLCERT, $cert);
	
					$data = curl_exec($ch);
					if (curl_errno($ch)) {
						//$error = Mage::helper('qboe')->__("Error = ".curl_error($ch));
						//if ($error !== false) {
							//Mage::throwException($error);
						//}
						$messageType = 'ItemServiceModRq';
						if (empty($statusCode)){
							$statusCode = 'error curl';
						}
						if (empty($statusSeverity)){
							$statusSeverity = '';
						}
						if (empty($statusMessage)){
							$statusMessage = curl_error($ch);
						}else{
							$statusMessage = str_replace("'", "", $statusMessage);
						}
						
						$status_db = $conn_db->query("insert into qboe_log(message_type,status_code,status_severity,status_message,date) values('".$messageType."','".$statusCode ."','".$statusSeverity."','".$statusMessage."','".date('Y-m-d H:i:s')."')");
						if (!$status_db) {
							//$error = Mage::helper('qboe')->__('error general save in DB');
							//if ($error !== false) {
								//Mage::throwException($error);
							//}
							return;
						}
						return ;
	
					} else {
						curl_close($ch);
					}

					$tempString = strstr($data, '<ItemServiceModRs');
					$endLocation = strpos($tempString, "</ItemServiceModRs>");
					if(!$endLocation){
						$endLocation = strpos($tempString, " />");
						$xml1 = substr($tempString, 0, $endLocation);
						$xml1 .= "></ItemServiceModRs>";
					}else {
						$xml1 = substr($tempString, 0, $endLocation);
						$xml1 .= "</ItemServiceModRs>";
					}
					
					$xml = simplexml_load_string($xml1);
					
					$messageType = 'ItemServiceModRs';
					$statusCode=$xml['statusCode'];
					$statusSeverity=$xml['statusSeverity'];
					$statusMessage=$xml['statusMessage'];
					
					switch ($statusCode) {
						case 0:
							break;
						default:
							if (empty($statusCode)){
								$statusCode = '';
							}
							if (empty($statusSeverity)){
								$statusSeverity = '';
							}
							if (empty($statusMessage)){
								$statusMessage = '';
							}else{
								$statusMessage = str_replace("'", "", $statusMessage);
							}
							
							$status_db = $conn_db->query("insert into qboe_log(message_type,status_code,status_severity,status_message,date) values('".$messageType."','".$statusCode ."','".$statusSeverity."','".$statusMessage."','".date('Y-m-d H:i:s')."')");
							if (!$status_db) {
								//$error = Mage::helper('qboe')->__('error general save in DB');
								//if ($error !== false) {
									//Mage::throwException($error);
								//}
								return;
							}
							break;
					}
					break;
				case 500:
					unset($QBXML);
					unset($header); 
					unset($ch);
					unset($data);
	
					$QBXML[0] = '<?xml version="1.0" encoding="utf-8"?>'.
								'<?qbxml version="6.0"?>'.
								'<QBXML>'.
									$signonMsgsRq.
									'<QBXMLMsgsRq onError="stopOnError">'.
										'<ItemServiceAddRq>'.
											'<ItemServiceAdd>'.
												'<Name>'.$event->getProduct()->getData('sku').$cat_prod['desc'].'</Name>'.
												'<SalesOrPurchase>'.
													'<Desc>'.$event->getProduct()->getData('name').'</Desc>'.
													'<Price>'.$event->getProduct()->getData('price').'</Price>'.
													'<AccountRef>'.
														'<FullName >'.$cat_prod['account']/*$salesReceiptAccount*/.'</FullName>'.
													'</AccountRef>'.
												'</SalesOrPurchase>'.
											'</ItemServiceAdd>'.
										'</ItemServiceAddRq>'.
									'</QBXMLMsgsRq>'.
								'</QBXML>';
					$QBXML[0] = str_replace("&", "&amp;", $QBXML[0]);
					
					$header[] = "Content-type: application/x-qbxml";
					$header[] = "Content-length: ".strlen($QBXML[0]);
	
					$handle = fopen("/tmp/curlerrors.txt", "w");
	                           
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_POST, 1);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
					curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
					curl_setopt($ch, CURLOPT_URL, $applicationPath);
					curl_setopt($ch, CURLOPT_TIMEOUT, 60);
					curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
					curl_setopt($ch, CURLOPT_POSTFIELDS, $QBXML[0]);
					curl_setopt($ch, CURLOPT_STDERR, $handle);
					curl_setopt($ch, CURLOPT_VERBOSE, 1);
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
					curl_setopt($ch, CURLOPT_SSLCERT, $cert);
	
					$data = curl_exec($ch);
					if (curl_errno($ch)) {
						//$error = Mage::helper('qboe')->__("Error = ".curl_error($ch));
						//if ($error !== false) {
							//Mage::throwException($error);
						//}
						$messageType = 'ItemServiceAddRq';
						if (empty($statusCode)){
							$statusCode = 'error curl';
						}
						if (empty($statusSeverity)){
							$statusSeverity = '';
						}
						if (empty($statusMessage)){
							$statusMessage = curl_error($ch);
						}else{
							$statusMessage = str_replace("'", "", $statusMessage);
						}
						
						$status_db = $conn_db->query("insert into qboe_log(message_type,status_code,status_severity,status_message,date) values('".$messageType."','".$statusCode ."','".$statusSeverity."','".$statusMessage."','".date('Y-m-d H:i:s')."')");
						if (!$status_db) {
							//$error = Mage::helper('qboe')->__('error general save in DB');
							//if ($error !== false) {
								//Mage::throwException($error);
							//}
							return;
						}
						return;
	
					} else {
						curl_close($ch);
					}

					$tempString = strstr($data, '<ItemServiceAddRs');
					$endLocation = strpos($tempString, "</ItemServiceAddRs>");
					if(!$endLocation){
						$endLocation = strpos($tempString, " />");
						$xml1 = substr($tempString, 0, $endLocation);
						$xml1 .= "></ItemServiceAddRs>";
					}else {
						$xml1 = substr($tempString, 0, $endLocation);
						$xml1 .= "</ItemServiceAddRs>";
					}
					
					$xml = simplexml_load_string($xml1);
					
					$messageType = 'ItemServiceAddRs';
					$statusCode=$xml['statusCode'];
					$statusSeverity=$xml['statusSeverity'];
					$statusMessage=$xml['statusMessage'];
					
					switch ($statusCode) {
						case 0:
							break;
						default:
							if (empty($statusCode)){
								$statusCode = '';
							}
							if (empty($statusSeverity)){
								$statusSeverity = '';
							}
							if (empty($statusMessage)){
								$statusMessage = '';
							}else{
								$statusMessage = str_replace("'", "", $statusMessage);
							}
							
							$status_db = $conn_db->query("insert into qboe_log(message_type,status_code,status_severity,status_message,date) values('".$messageType."','".$statusCode ."','".$statusSeverity."','".$statusMessage."','".date('Y-m-d H:i:s')."')");
							if (!$status_db) {
								//$error = Mage::helper('qboe')->__('error general save in DB');
								//if ($error !== false) {
									//Mage::throwException($error);
								//}
								return;
							}
							break;
					}
					break;
				default:
					if (empty($statusCode)){
						$statusCode = '';
					}
					if (empty($statusSeverity)){
						$statusSeverity = '';
					}
					if (empty($statusMessage)){
						$statusMessage = '';
					}else{
						$statusMessage = str_replace("'", "", $statusMessage);
					}
					
					$status_db = $conn_db->query("insert into qboe_log(message_type,status_code,status_severity,status_message,date) values('".$messageType."','".$statusCode ."','".$statusSeverity."','".$statusMessage."','".date('Y-m-d H:i:s')."')");
					if (!$status_db) {
						//$error = Mage::helper('qboe')->__('error general save in DB');
						//if ($error !== false) {
							//Mage::throwException($error);
						//}
						return;
					}
					break;
			}
		}
	}

}