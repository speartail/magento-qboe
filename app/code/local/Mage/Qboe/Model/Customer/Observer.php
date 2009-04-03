<?php
/**
 * Quickbooks Online Edition Module for Magento
 *
 * Developed by Xagax Solutions LLC (http://www.xagax.com)
 *
 * @copyright  Module Copyright (c) 2008 Xagax Solutions LLC (http://www.xagax.com)
 */
class Mage_Qboe_Model_Customer_Observer extends Mage_Qboe_Model_Qboe
{

 	public function customerSaveBefore( Varien_Event_Observer $observer){
		if ($this->isDisable()){
			return;
		}
 		
 		$event = $observer->getEvent();
		
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
		
		if ($event->getCustomer()->getOrigData('email')){
			if ($event->getCustomer()->getOrigData('email') != $event->getCustomer()->getData('email')){
				$queryEmail = $event->getCustomer()->getOrigData('email');
			}else {
				$queryEmail = $event->getCustomer()->getData('email');
			}
		}else{
			$queryEmail = $event->getCustomer()->getData('email');
		}
		
		if ($event->getCustomer()->getData('middlename')){
			$middleName = '<MiddleName >'.$event->getCustomer()->getData('middlename').'</MiddleName>';
		}else{
			$middleName = '';
		}
		$defaultBilling = $event->getCustomer()->getData('default_billing');
		$defaultShipping = $event->getCustomer()->getData('default_shipping');
		
		$companyName = '';
		$phone = '';
		$region = Mage::getModel('directory/region');
		if ($defaultBilling) {
			foreach ($event->getCustomer()->getAddresses() as $address) {
				if ($address->getData('entity_id') == $defaultBilling) {
					$billAddress  = '<BillAddress>';
					$billAddress .= 	'<Addr1>'.$address->getData('street').'</Addr1>';
					$billAddress .= 	'<City>'.$address->getData('city').'</City>';
					if($regionId = (int) $address->getData('region_id')){
						$billRegion = $region->load($regionId);
						$billAddress .= '<State>'.$billRegion->getData('code').'</State>';
					} elseif ($address->getData('region')) {
						$billAddress .= '<State>'.$address->getData('region').'</State>';
					}
					$billAddress .= 	'<PostalCode>'.$address->getData('postcode').'</PostalCode>';
					$billAddress .= 	'<Country>'.$address->getData('country_id').'</Country>';
					$billAddress .= '</BillAddress>';
					if($address->getData('company')) {
						$companyName = '<CompanyName>'.$address->getData('company').'</CompanyName>';
					}
					if($address->getData('telephone')) {
						$phone = '<Phone>'.$address->getData('telephone').'</Phone>';
					}
				}
				if ($address->getData('entity_id') == $defaultShipping) {
					$shipAddress  = '<ShipAddress>';
					$shipAddress .= 	'<Addr1>'.$address->getData('street').'</Addr1>';
					$shipAddress .= 	'<City>'.$address->getData('city').'</City>';
					if($regionId = (int) $address->getData('region_id')){
						$shipRegion = $region->load($regionId);
						$shipAddress .= '<State>'.$shipRegion->getData('code').'</State>';
					} elseif ($address->getData('region')) {
						$shipAddress .= '<State>'.$address->getData('region').'</State>';
					}
					$shipAddress .= 	'<PostalCode>'.$address->getData('postcode').'</PostalCode>';
					$shipAddress .= 	'<Country>'.$address->getData('country_id').'</Country>';
					$shipAddress .= '</ShipAddress>';
				}
				if (!empty($billAddress) && !empty($shipAddress)){
					break;
				}
			}
		}
		if (empty($billAddress)){
			$billAddress = '';
		}
		if (empty($shipAddress)){
			$shipAddress = '';
		}
		
		//QBOE - XML CustomerQueryRq
		$xml = $this->queryQboe($queryEmail,$sessionTicket,'CustomerQueryR');
		
		$messageType = 'CustomerQueryRs';
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
									'<CustomerModRq>'.
										'<CustomerMod>'.
											'<ListID >'.$ListID.'</ListID>'.
											'<EditSequence >'.$EditSequence.'</EditSequence>'.
											'<Name >'.$event->getCustomer()->getData('email').'</Name>'.
											$companyName.
											'<FirstName >'.$event->getCustomer()->getData('firstname').'</FirstName>'.
											$middleName.
											'<LastName >'.$event->getCustomer()->getData('lastname').'</LastName>'.
											$billAddress.
											$shipAddress.
											$phone.
											'<Email >'.$event->getCustomer()->getData('email').'</Email>'.
										'</CustomerMod>'.
									'</CustomerModRq>'.
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
					/*$error = Mage::helper('qboe')->__("Error = ".curl_error($ch));
					if ($error !== false) {
						Mage::throwException($error);
					}*/
					$messageType = 'CustomerModRq';
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
						/*$error = Mage::helper('qboe')->__('error general save in DB');
						if ($error !== false) {
							Mage::throwException($error);
						}*/
						return;
					}
					return ;

				} else {
					curl_close($ch);
				}
				
				$tempString = strstr($data, '<CustomerModRs');
				$endLocation = strpos($tempString, "</CustomerModRs>");
				if(!$endLocation){
					$endLocation = strpos($tempString, " />");
					$xml1 = substr($tempString, 0, $endLocation);
					$xml1 .= "></CustomerModRs>";
				}else {
					$xml1 = substr($tempString, 0, $endLocation);
					$xml1 .= "</CustomerModRs>";
				}
				
				$xml = simplexml_load_string($xml1);
				
				$messageType = 'CustomerModRs';
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
							/*$error = Mage::helper('qboe')->__('error general save in DB');
							if ($error !== false) {
								Mage::throwException($error);
							}*/
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
									'<CustomerAddRq>'.
										'<CustomerAdd>'.
											'<Name >'.$event->getCustomer()->getData('email').'</Name>'.
											$companyName.
											'<FirstName >'.$event->getCustomer()->getData('firstname').'</FirstName>'.
											$middleName.
											'<LastName >'.$event->getCustomer()->getData('lastname').'</LastName>'.
											$billAddress.
											$shipAddress.
											$phone.
											'<Email >'.$event->getCustomer()->getData('email').'</Email>'.
										'</CustomerAdd>'.
									'</CustomerAddRq>'.
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
					/*$error = Mage::helper('qboe')->__("Error = ".curl_error($ch));
					if ($error !== false) {
						Mage::throwException($error);
					}*/
					$messageType = 'CustomerAddRq';
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
						/*$error = Mage::helper('qboe')->__('error general save in DB');
						if ($error !== false) {
							Mage::throwException($error);
						}*/
						return;
					}
					return;

				} else {
					curl_close($ch);
				}
				
				$tempString = strstr($data, '<CustomerAddRs');
				$endLocation = strpos($tempString, "</CustomerAddRs>");
				if(!$endLocation){
					$endLocation = strpos($tempString, " />");
					$xml1 = substr($tempString, 0, $endLocation);
					$xml1 .= "></CustomerAddRs>";
				}else {
					$xml1 = substr($tempString, 0, $endLocation);
					$xml1 .= "</CustomerAddRs>";
				}
				
				$xml = simplexml_load_string($xml1);
				
				$messageType = 'CustomerAddRs';
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
							/*$error = Mage::helper('qboe')->__('error general save in DB');
							if ($error !== false) {
								Mage::throwException($error);
							}*/
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
					/*$error = Mage::helper('qboe')->__('error general save in DB');
					if ($error !== false) {
						Mage::throwException($error);
					}*/
					return;
				}
				break;
		}

	}

}