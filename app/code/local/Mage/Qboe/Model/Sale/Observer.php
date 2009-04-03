<?php
/**
 * Quickbooks Online Edition Module for Magento
 *
 * Developed by Xagax Solutions LLC (http://www.xagax.com)
 *
 * @copyright  Module Copyright (c) 2008 Xagax Solutions LLC (http://www.xagax.com)
 */
class Mage_Qboe_Model_Sale_Observer extends Mage_Qboe_Model_Qboe
{

	protected function getCcTypeName($ccType){
		switch($ccType){
			case 'AE': return 'American Express'; break;
			case 'VI': return 'Visa'; break;
			case 'MC': return 'MasterCard'; break;
			case 'DI': return 'Discover'; break;
		}
	}
	
	public function invoiceSaveAfter( Varien_Event_Observer $observer){

		if ($this->isDisable()){
			return;
		}
			
		$event = $observer->getEvent();
		$order =$event->getInvoice()->getOrder();
		$id_order = $order->getIncrementId();

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
		
		$depositAccount = $this->getConfigDataQboe('configu4/depositAccount');
		if (!$depositAccount){
			$messageType = 'conf_error';
 			$statusCode = 'conf_error';
			$statusSeverity = 'error';
			$statusMessage = 'QBOE - Deposit Account not defined';
			$status_db = $conn_db->query("insert into qboe_log(message_type,status_code,status_severity,status_message,date) values('".$messageType."','".$statusCode ."','".$statusSeverity."','".$statusMessage."','".date('Y-m-d H:i:s')."')");
			return;
		}
		
		$creditMemoAccount = $this->getConfigDataQboe('configu2/creditMemoAccount');
		if (!$creditMemoAccount){
			$messageType = 'conf_error';
 			$statusCode = 'conf_error';
			$statusSeverity = 'error';
			$statusMessage = 'QBOE - CreditMemo Account not defined';
			$status_db = $conn_db->query("insert into qboe_log(message_type,status_code,status_severity,status_message,date) values('".$messageType."','".$statusCode ."','".$statusSeverity."','".$statusMessage."','".date('Y-m-d H:i:s')."')");
			return;
		}
		
		$discountsAccount = $this->getConfigDataQboe('configu5/discountsAccount');
		if (!$discountsAccount){
			$messageType = 'conf_error';
 			$statusCode = 'conf_error';
			$statusSeverity = 'error';
			$statusMessage = 'QBOE - Discounts Account not defined';
			$status_db = $conn_db->query("insert into qboe_log(message_type,status_code,status_severity,status_message,date) values('".$messageType."','".$statusCode ."','".$statusSeverity."','".$statusMessage."','".date('Y-m-d H:i:s')."')");
			return;
		}
		
		$salesTaxAccount = $this->getConfigDataQboe('configu6/salesTaxAccount');
		if (!$salesTaxAccount){
			$messageType = 'conf_error';
 			$statusCode = 'conf_error';
			$statusSeverity = 'error';
			$statusMessage = 'QBOE - Tax Account not defined';
			$status_db = $conn_db->query("insert into qboe_log(message_type,status_code,status_severity,status_message,date) values('".$messageType."','".$statusCode ."','".$statusSeverity."','".$statusMessage."','".date('Y-m-d H:i:s')."')");
			return;
		}
		
		$shippingAccount = $this->getConfigDataQboe('configu7/shippingAccount');
		if (!$shippingAccount){
			$messageType = 'conf_error';
 			$statusCode = 'conf_error';
			$statusSeverity = 'error';
			$statusMessage = 'QBOE - Shipping Account not defined';
			$status_db = $conn_db->query("insert into qboe_log(message_type,status_code,status_severity,status_message,date) values('".$messageType."','".$statusCode ."','".$statusSeverity."','".$statusMessage."','".date('Y-m-d H:i:s')."')");
			return;
		}		

		//Store -  Para distingir el producto de wholesale y retail
		/*if($order->getStore()->getCode() == 'gmg_wholesale_english'){
			$gmg_store = ' - Wholesale';
		}else{
			$gmg_store = '';
		}*/
		
		$gmg_store = $this->getConfigDataQboe('configu3/addDescription',$order->getStoreId());
		
		$conn_db = Mage::getSingleton('core/resource')->getConnection('core_write');
		
		//QBOE - XML SalesReceiptQueryRq
		$xml = $this->queryQboe($id_order,$sessionTicket,'SalesReceiptQueryR');
		
		$messageType = 'SalesReceiptQueryRs';
		$statusCode=$xml['statusCode'];
		$requestID=$xml['statusSeverity'];
		$statusMessage=$xml['statusMessage'];
		
		switch ($statusCode) {
			case 0:
				break;
			case 500:
				if ($order->getData('customer_id')) {
					$xml = $this->queryQboe($order->getData('customer_email'),$sessionTicket,'CustomerQueryR');
					
					$messageType = 'CustomerQueryRs';
					$statusCode=$xml['statusCode'];
					$requestID=$xml['statusSeverity'];
					$statusMessage=$xml['statusMessage'];
					
					foreach ($xml->children() as $second_gen) {
						$ListID = $second_gen->ListID;
						$EditSequence= $second_gen->EditSequence;    
					}
					
					if (isset($ListID)){
						$customerRef = '<CustomerRef>'.
											'<ListID >'.$ListID.'</ListID>'.
										'</CustomerRef>';
					}else{
						$customerRef = '<CustomerRef>'.
											'<FullName>'.$order->getData('customer_email').'</FullName>'.
										'</CustomerRef>';
					}
					$customerName = $order->getData('customer_email');
				}else{
					//Si es un guest, hay que registrarlo en qboe con  el email mas (GUEST)
					//si el guest ya existe en qboe, se acualizan los datos
					$billingGuest = $order->getBillingAddress();
					if($billingGuest->getRegionCode()){
						$billRegionCodeGuest = $billingGuest->getRegionCode();
					} else{
						$billRegionCodeGuest = $billingGuest->getRegion();
					}

					$shippingGuest = $order->getShippingAddress();
					if($shippingGuest->getRegionCode()){
						$shipRegionCodeGuest = $shippingGuest->getRegionCode();
					} else{
						$shipRegionCodeGuest = $shippingGuest->getRegion();
					}
					
					$billAdrrGuest  = '<Addr1>'.$billingGuest->getName().'</Addr1>';
					$billAdrrGuest .= '<Addr2>'.$order->getData('customer_email').'</Addr2>';
					$billAdrrGuest .= '<Addr3>'.$billingGuest->getStreet(1).'</Addr3>';
					if ($billingGuest->getStreet(2)){
						$billAdrrGuest .= '<Addr4>'.$billingGuest->getStreet(2).'</Addr4>';
					}
					$billAdrrGuest .= '<City>'.$billingGuest->getCity().'</City>';
					if ($billRegionCodeGuest){
						$billAdrrGuest .= '<State>'.$billRegionCodeGuest.'</State>';
					}
					$billAdrrGuest .= '<PostalCode>'.$billingGuest->getPostcode().'</PostalCode>';
					$billAdrrGuest .= '<Country>'.$billingGuest->getCountry().'</Country>';
					
					$shipAdrrGuest  = '<Addr1>'.$shippingGuest->getName().'</Addr1>';
					$shipAdrrGuest .= '<Addr2>'.$order->getData('customer_email').'</Addr2>';
					$shipAdrrGuest .= '<Addr3>'.$shippingGuest->getStreet(1).'</Addr3>';
			 		if ($shippingGuest->getStreet(2)){
						$shipAdrrGuest .= '<Addr4>'.$shippingGuest->getStreet(2).'</Addr4>';
					}
					$shipAdrrGuest .= '<City>'.$shippingGuest->getCity().'</City>';
					if ($shipRegionCodeGuest){
						$shipAdrrGuest .= '<State>'.$shipRegionCodeGuest.'</State>';
					}
					$shipAdrrGuest .= '<PostalCode>'.$shippingGuest->getPostcode().'</PostalCode>';
					$shipAdrrGuest .= '<Country>'.$shippingGuest->getCountry().'</Country>';
						
					if($billingGuest->getData('company')) {
						$companyNameGuest = '<CompanyName>'.$billingGuest->getData('company').'</CompanyName>';
					}else{
						$companyNameGuest = '';
					}
					if($billingGuest->getData('telephone')) {
						$phoneGuest = '<Phone>'.$billingGuest->getData('telephone').'</Phone>';
					}else{
						$phoneGuest = '';
					}
					
					//QBOE - XML CustomerQueryRq
					$xml = $this->queryQboe($order->getData('customer_email').'(GUEST)',$sessionTicket,'CustomerQueryR');
					
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
														'<Name >'.$order->getData('customer_email').'(GUEST)'.'</Name>'.
														$companyNameGuest.
														'<FirstName >'.$billingGuest->getFirstname().'</FirstName>'.
														'<LastName >'.$billingGuest->getLastname().'</LastName>'.
														'<BillAddress>'.
															$billAdrrGuest.
														'</BillAddress>'.
														'<ShipAddress>'.
															$shipAdrrGuest.
														'</ShipAddress>'.
														$phoneGuest.
														'<Email >'.$order->getData('customer_email').'</Email>'.
													'</CustomerMod>'.
												'</CustomerModRq>'.
											'</QBXMLMsgsRq>'.
										'</QBXML>';
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
									//return;
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
							
							foreach ($xml->children() as $second_gen) {
								$ListID = $second_gen->ListID;
								$EditSequence= $second_gen->EditSequence;    
							}
							
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
										//return;
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
														'<Name >'.$order->getData('customer_email').'(GUEST)'.'</Name>'.
														$companyNameGuest.
														'<FirstName >'.$billingGuest->getFirstname().'</FirstName>'.
														'<LastName >'.$billingGuest->getLastname().'</LastName>'.
														'<BillAddress>'.
															$billAdrrGuest.
														'</BillAddress>'.
														'<ShipAddress>'.
															$shipAdrrGuest.
														'</ShipAddress>'.
														$phoneGuest.
														'<Email >'.$order->getData('customer_email').'</Email>'.
													'</CustomerAdd>'.
												'</CustomerAddRq>'.
											'</QBXMLMsgsRq>'.
										'</QBXML>';
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
									//return;
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
							
							foreach ($xml->children() as $second_gen) {
								$ListID = $second_gen->ListID;
								$EditSequence= $second_gen->EditSequence;    
							}
							
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
										//return;
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
								//return;
							}
							break;
					}
					
					if (isset($ListID)){
						$customerRef = '<CustomerRef>'.
											'<ListID >'.$ListID.'</ListID>'.
										'</CustomerRef>';
					}else{
						$customerRef = '<CustomerRef>'.
											'<FullName>'.$order->getData('customer_email').'(GUEST)'.'</FullName>'.
										'</CustomerRef>';
					}
					$customerName = $order->getData('customer_email').'(GUEST)';
				}
				
				unset($QBXML);
				unset($header); 
				unset($ch);
				unset($data);
				
				foreach ($order->getAllItems() as $orderItem) {				
					if(!isset($salesReceiptLineAdds)){
						$salesReceiptLineAdds = '';
					}
					if( ($orderItem->getSku() !== 'snug-tug') && ($orderItem->getSku() !== 'wholesale-snug-tug')){
						if ($orderItem->getParentItem()){
							$quantity = $orderItem->getParentItem()->getQtyOrdered();
						} else {
							$quantity = $orderItem->getQtyOrdered();
						}
	
						$salesReceiptLineAdds .= '<SalesReceiptLineAdd>'.
													'<ItemRef>'.
														'<FullName>'.$orderItem->getSku().$gmg_store.'</FullName>'.
													'</ItemRef>'.
													'<Quantity>'.$quantity.'</Quantity>'.
													'<Rate>'.number_format(round($orderItem->getPrice(),2),2).'</Rate>'.
													'<ServiceDate>'.date('Y-m-d').'</ServiceDate>'.
												'</SalesReceiptLineAdd>';
					}
				}
				
				$billing = $order->getBillingAddress();
				if($billing->getRegionCode()){
					$billRegionCode = $billing->getRegionCode();
				} else{
					$billRegionCode = $billing->getRegion();
				}

				$shipping = $order->getShippingAddress();
		 		if($shipping->getRegionCode()){
					$shipRegionCode = $shipping->getRegionCode();
				} else{
					$shipRegionCode = $shipping->getRegion();
				}

				if ($order->getData('customer_id')){
					$billAdrr = '<Addr1>'.$billing->getStreet(1).'</Addr1>';
					if ($billing->getStreet(2)){
						$billAdrr .= '<Addr2>'.$billing->getStreet(2).'</Addr2>';
					}
					
					$shipAdrr = '<Addr1>'.$shipping->getStreet(1).'</Addr1>';
			 		if ($shipping->getStreet(2)){
						$shipAdrr .= '<Addr2>'.$shipping->getStreet(2).'</Addr2>';
					}
				}else {
					$billAdrr  = '<Addr1>'.$billing->getName().'</Addr1>';
					$billAdrr .= '<Addr2>'.$order->getData('customer_email').'</Addr2>';
					$billAdrr .= '<Addr3>'.$billing->getStreet(1).'</Addr3>';
					if ($billing->getStreet(2)){
						$billAdrr .= '<Addr4>'.$billing->getStreet(2).'</Addr4>';
					}
					
					$shipAdrr  = '<Addr1>'.$shipping->getName().'</Addr1>';
					$shipAdrr .= '<Addr2>'.$order->getData('customer_email').'</Addr2>';
					$shipAdrr .= '<Addr3>'.$shipping->getStreet(1).'</Addr3>';
			 		if ($shipping->getStreet(2)){
						$shipAdrr .= '<Addr4>'.$shipping->getStreet(2).'</Addr4>';
					}
				}
				if ($order->getData('discount_amount')> 0){
					$discountAmount = '<DiscountLineAdd>'.
											'<Amount >-'.number_format(round($order->getData('discount_amount'),2),2).'</Amount>'.
											'<AccountRef>'.
												'<FullName>'.$discountsAccount.'</FullName>'.
											'</AccountRef>'. 
										'</DiscountLineAdd>';
				}else{
					$discountAmount = '';
				}
				if ($order->getData('tax_amount')>0){
					$taxAmount = '<SalesTaxLineAdd>'.
									'<Amount >'.number_format(round($order->getData('tax_amount'),2),2).'</Amount>'.
									'<AccountRef>'.
										'<FullName>'.$salesTaxAccount.'</FullName>'.
									'</AccountRef>'.
								'</SalesTaxLineAdd>';
				}else{
					$taxAmount = '';
				}
				if ($order->getData('shipping_amount') > 0){
					$shippingAmount = '<ShippingLineAdd>'.
											'<Amount >'. number_format(round($order->getData('shipping_amount'),2),2).'</Amount>'.
											'<AccountRef>'.
												'<FullName>'.$shippingAccount.'</FullName>'.
											'</AccountRef>'.
										'</ShippingLineAdd>';
				}else{
					$shippingAmount = '';
				}
				
				if($order->getPayment()->getCreditCardTransIdCapture()){
					$creditCardTransIdCapture = '<CheckNumber>'.$order->getPayment()->getCreditCardTransIdCapture().'</CheckNumber>';
				}else{
					$creditCardTransIdCapture = '';
				}
				
				if($order->getPayment()->getCcType()){
					$paymentMethodRef ='<PaymentMethodRef>'.
											'<FullName >'.$this->getCcTypeName($order->getPayment()->getCcType()).'</FullName>'.
										'</PaymentMethodRef>';
				}else{
					$paymentMethodRef = '';
				}
				
				//Crear la orden
				$QBXML[0] = '<?xml version="1.0" encoding="utf-8"?>'.
							'<?qbxml version="6.0"?>'.
							'<QBXML>'.
								$signonMsgsRq.
								'<QBXMLMsgsRq onError="stopOnError">'.
									'<SalesReceiptAddRq>'.
										'<SalesReceiptAdd>'.
											$customerRef.
											'<TxnDate>'.date('Y-m-d').'</TxnDate>'.
											'<RefNumber>'.$id_order.'</RefNumber>'.
											'<BillAddress>'.
												$billAdrr.
												'<City>'.$billing->getCity().'</City>'.
												'<State>'.$billRegionCode.'</State>'.
												'<PostalCode>'.$billing->getPostcode().'</PostalCode>'.
												'<Country>'.$billing->getCountry().'</Country>'.
											'</BillAddress>'.
											'<ShipAddress>'.
												$shipAdrr.
												'<City>'.$shipping->getCity().'</City>'.
												'<State>'.$shipRegionCode.'</State>'.
												'<PostalCode>'.$shipping->getPostcode().'</PostalCode>'.
												'<Country>'.$shipping->getCountry().'</Country>'.
											'</ShipAddress>'.
											$creditCardTransIdCapture.
											$paymentMethodRef.
											'<ShipDate>'.date('Y-m-d',mktime(0,0,0, date('m'), date('d')+1,date('Y'))).'</ShipDate>'.
											'<DepositToAccountRef>'.
												'<FullName>'.$depositAccount.'</FullName>'.
											'</DepositToAccountRef>'.
											$salesReceiptLineAdds.
											$discountAmount.
											$taxAmount.
											$shippingAmount.
										'</SalesReceiptAdd>'.
									'</SalesReceiptAddRq>'.
								'</QBXMLMsgsRq>'.
							'</QBXML>';
	
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
					$messageType = 'SalesReceiptAddRq';
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
				$tempString = strstr($data, '<SalesReceiptAddRs');
				$endLocation = strpos($tempString, "</SalesReceiptAddRs>");
				if(!$endLocation){
					$endLocation = strpos($tempString, " />");
					$xml1 = substr($tempString, 0, $endLocation);
					$xml1 .= "></SalesReceiptAddRs>";
				}else {
					$xml1 = substr($tempString, 0, $endLocation);
					$xml1 .= "</SalesReceiptAddRs>";
				}
				
				$xml = simplexml_load_string($xml1);
				
				$messageType = 'SalesReceiptAddRs';
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
	
	public function orderCreditmemo( Varien_Event_Observer $observer){
		if ($this->isDisable()){
			return;
		}
	
		$event = $observer->getEvent();
		$order =$event->getCreditmemo()->getOrder();
		$id_order = $order->getIncrementId();
		$creditmemo = $event->getCreditmemo();
		
		if ($creditmemo->getOfflineRequested()){
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
		
		$depositAccount = $this->getConfigDataQboe('configu4/depositAccount');
		if (!$depositAccount){
			$messageType = 'conf_error';
 			$statusCode = 'conf_error';
			$statusSeverity = 'error';
			$statusMessage = 'QBOE - Deposit Account not defined';
			$status_db = $conn_db->query("insert into qboe_log(message_type,status_code,status_severity,status_message,date) values('".$messageType."','".$statusCode ."','".$statusSeverity."','".$statusMessage."','".date('Y-m-d H:i:s')."')");
			return;
		}
		
		$creditMemoAccount = $this->getConfigDataQboe('configu2/creditMemoAccount');
		if (!$creditMemoAccount){
			$messageType = 'conf_error';
 			$statusCode = 'conf_error';
			$statusSeverity = 'error';
			$statusMessage = 'QBOE - CreditMemo Account not defined';
			$status_db = $conn_db->query("insert into qboe_log(message_type,status_code,status_severity,status_message,date) values('".$messageType."','".$statusCode ."','".$statusSeverity."','".$statusMessage."','".date('Y-m-d H:i:s')."')");
			return;
		}
		
		$discountsAccount = $this->getConfigDataQboe('configu5/discountsAccount');
		if (!$discountsAccount){
			$messageType = 'conf_error';
 			$statusCode = 'conf_error';
			$statusSeverity = 'error';
			$statusMessage = 'QBOE - Discounts Account not defined';
			$status_db = $conn_db->query("insert into qboe_log(message_type,status_code,status_severity,status_message,date) values('".$messageType."','".$statusCode ."','".$statusSeverity."','".$statusMessage."','".date('Y-m-d H:i:s')."')");
			return;
		}
		
		$salesTaxAccount = $this->getConfigDataQboe('configu6/salesTaxAccount');
		if (!$salesTaxAccount){
			$messageType = 'conf_error';
 			$statusCode = 'conf_error';
			$statusSeverity = 'error';
			$statusMessage = 'QBOE - Tax Account not defined';
			$status_db = $conn_db->query("insert into qboe_log(message_type,status_code,status_severity,status_message,date) values('".$messageType."','".$statusCode ."','".$statusSeverity."','".$statusMessage."','".date('Y-m-d H:i:s')."')");
			return;
		}
		
		$shippingAccount = $this->getConfigDataQboe('configu7/shippingAccount');
		if (!$shippingAccount){
			$messageType = 'conf_error';
 			$statusCode = 'conf_error';
			$statusSeverity = 'error';
			$statusMessage = 'QBOE - Shipping Account not defined';
			$status_db = $conn_db->query("insert into qboe_log(message_type,status_code,status_severity,status_message,date) values('".$messageType."','".$statusCode ."','".$statusSeverity."','".$statusMessage."','".date('Y-m-d H:i:s')."')");
			return;
		}
		//$shippingExpenses = $this->getConfigDataQboe('configu8/shippingExpenses');
		
		//Store -  Para distingir el producto de wholesale y retail
		//if($order->getStore()->getCode() == 'gmg_wholesale_english'){
			//$gmg_store = ' - Wholesale';
		//}else{
			//$gmg_store = '';
		//}
		$gmg_store = $this->getConfigDataQboe('configu3/addDescription',$order->getStoreId());
		
		$conn_db = Mage::getSingleton('core/resource')->getConnection('core_write');
		
		//QBOE - XML CreditMemoQueryRq
		$xml = $this->queryQboe($id_order,$sessionTicket,'CreditMemoQueryR');

		$messageType = 'CreditMemoQueryRs';
		$statusCode=$xml['statusCode'];
		$requestID=$xml['statusSeverity'];
		$statusMessage=$xml['statusMessage'];
		
		switch ($statusCode) {
			case 0:
				break;
			case 500:
				unset($QBXML);
				unset($header); 
				unset($ch);
				unset($data);
				
				if ($order->getData('customer_id')) {
					$customerRef = '<CustomerRef>'.
										'<FullName>'.$order->getData('customer_email').'</FullName>'.
									'</CustomerRef>';
					$customerName = $order->getData('customer_email');
				}else{
					$customerRef = '<CustomerRef>'.
										'<FullName>'.$order->getData('customer_email').'(GUEST)'.'</FullName>'.
									'</CustomerRef>';
					$customerName = $order->getData('customer_email').'(GUEST)';
				}
				
				$creditMemoLineAdd = '';
				
				foreach ($creditmemo->getAllItems() as $item) {				
					if($item->getQty() > 0){
						if ($item->getParentItem()){
								$price = $item->getParentItem()->getPrice();
						} else {
								$price = $item->getPrice();
						}
						// - Wholesale
						$creditMemoLineAdd .= '<CreditMemoLineAdd>'.
													'<ItemRef>'.
														'<FullName>'.$item->getSku().$gmg_store.'</FullName>'.
													'</ItemRef>'.
													'<Desc>'.$item->getSku().'</Desc>'.
													'<Quantity>'.$item->getQty().'</Quantity>'.
													'<Rate>'.number_format(round($price,2),2).'</Rate>'.
												'</CreditMemoLineAdd>';	
					}
				}
				
				if ($creditmemo->getAdjustment()){
					$creditMemoLineAdd .= '<CreditMemoLineAdd>'.
												'<Desc>Adjustment</Desc>'.
												'<Amount>'.number_format(round($creditmemo->getAdjustment(),2),2).'</Amount>'.
											'</CreditMemoLineAdd>';
				}
				
				$billing = $order->getBillingAddress();
				if($billing->getRegionCode()){
					$billRegionCode = $billing->getRegionCode();
				} else{
					$billRegionCode = $billing->getRegion();
				}
		
				$shipping = $order->getShippingAddress();
		 		if($shipping->getRegionCode()){
					$shipRegionCode = $shipping->getRegionCode();
				} else{
					$shipRegionCode = $shipping->getRegion();
				}
		
				if ($order->getData('customer_id')){
					$billAdrr = '<Addr1>'.$billing->getStreet(1).'</Addr1>';
					if ($billing->getStreet(2)){
						$billAdrr .= '<Addr2>'.$billing->getStreet(2).'</Addr2>';
					}
					
					$shipAdrr = '<Addr1>'.$shipping->getStreet(1).'</Addr1>';
			 		if ($shipping->getStreet(2)){
						$shipAdrr .= '<Addr2>'.$shipping->getStreet(2).'</Addr2>';
					}
				}else {
					$billAdrr  = '<Addr1>'.$billing->getName().'</Addr1>';
					$billAdrr .= '<Addr2>'.$order->getData('customer_email').'</Addr2>';
					$billAdrr .= '<Addr3>'.$billing->getStreet(1).'</Addr3>';
					if ($billing->getStreet(2)){
						$billAdrr .= '<Addr4>'.$billing->getStreet(2).'</Addr4>';
					}
					
					$shipAdrr  = '<Addr1>'.$shipping->getName().'</Addr1>';
					$shipAdrr .= '<Addr2>'.$order->getData('customer_email').'</Addr2>';
					$shipAdrr .= '<Addr3>'.$shipping->getStreet(1).'</Addr3>';
			 		if ($shipping->getStreet(2)){
						$shipAdrr .= '<Addr4>'.$shipping->getStreet(2).'</Addr4>';
					}
				}
		
				if ($creditmemo->getDiscountAmount() > 0){
					$discountAmount = '<DiscountLineAdd>'.
											'<Amount>-'.number_format(round($creditmemo->getDiscountAmount(),2),2).'</Amount>'. 
											'<AccountRef>'.
												'<FullName>'.$discountsAccount.'</FullName>'.
											'</AccountRef>'. 
										'</DiscountLineAdd>';
				}else{
					$discountAmount = '';
				}
				if ($creditmemo->getTaxAmount()>0){
					$taxAmount = '<SalesTaxLineAdd>'.
									'<Amount>'.number_format(round($creditmemo->getTaxAmount(),2),2).'</Amount>'. 
									'<AccountRef>'.
										'<FullName>'.$salesTaxAccount.'</FullName>'.
									'</AccountRef>'.
								'</SalesTaxLineAdd>';
				}else{
					$taxAmount = '';
				}
				if ($creditmemo->getShippingAmount() > 0){
					$shippingAmount = '<ShippingLineAdd>'.
											'<Amount>'.number_format(round($creditmemo->getShippingAmount(),2),2).'</Amount>'.
											'<AccountRef>'.
												'<FullName>'.$shippingAccount.'</FullName>'.
											'</AccountRef>'.
										'</ShippingLineAdd>';
				}else{
					$shippingAmount = '';
				}
				//add credit memo to order
				$QBXML[0] = '<?xml version="1.0" encoding="utf-8"?>'.
							'<?qbxml version="6.0"?>'.
							'<QBXML>'.
								$signonMsgsRq.
								'<QBXMLMsgsRq onError="stopOnError">'.
									'<CreditMemoAddRq>'.
										'<CreditMemoAdd>'.
											$customerRef.
											'<ARAccountRef>'.
												'<FullName>'.$creditMemoAccount.'</FullName>'.
											'</ARAccountRef>'.
											'<TxnDate>'.date('Y-m-d').'</TxnDate>'.
											'<RefNumber>'.$id_order.'</RefNumber>'.
											'<BillAddress>'.
												$billAdrr.
												'<City>'.$billing->getCity().'</City>'.
												'<State>'.$billRegionCode.'</State>'.
												'<PostalCode>'.$billing->getPostcode().'</PostalCode>'.
												'<Country>'.$billing->getCountry().'</Country>'.
											'</BillAddress>'.
											'<ShipAddress>'.
												$shipAdrr.
												'<City>'.$shipping->getCity().'</City>'.
												'<State>'.$shipRegionCode.'</State>'.
												'<PostalCode>'.$shipping->getPostcode().'</PostalCode>'.
												'<Country>'.$shipping->getCountry().'</Country>'.
											'</ShipAddress>'.
											'<Memo>'.$id_order.'</Memo>'.
											$creditMemoLineAdd.
											$discountAmount.
											$taxAmount.
											$shippingAmount.
										'</CreditMemoAdd>'.
									'</CreditMemoAddRq>'.
								'</QBXMLMsgsRq>'.
							'</QBXML>';

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
					$messageType = 'CreditMemoAddRq';
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

				$tempString = strstr($data, '<CreditMemoAddRs');
				$endLocation = strpos($tempString, "</CreditMemoAddRs>");
				if(!$endLocation){
					$endLocation = strpos($tempString, " />");
					$xml1 = substr($tempString, 0, $endLocation);
					$xml1 .= "></CreditMemoAddRs>";
				}else {
					$xml1 = substr($tempString, 0, $endLocation);
					$xml1 .= "</CreditMemoAddRs>";
				}
				
				$xml = simplexml_load_string($xml1);
				
				$messageType = 'CreditMemoAddRs';
				$statusCode=$xml['statusCode'];
				$statusSeverity=$xml['statusSeverity'];
				$statusMessage=$xml['statusMessage'];
				
				switch ($statusCode) {
					case 0:
						unset($QBXML);
						unset($header); 
						unset($ch);
						unset($data);
						//incremento AR y descuento de bank			
						$QBXML[0] = '<?xml version="1.0" encoding="utf-8"?>'.
									'<?qbxml version="6.0"?>'.
									'<QBXML>'.
										$signonMsgsRq.
										'<QBXMLMsgsRq onError="stopOnError">'.
											'<JournalEntryAddRq>'.
												'<JournalEntryAdd>'.
													'<TxnDate>'.date('Y-m-d').'</TxnDate>'.
													'<RefNumber>'.$id_order.'</RefNumber>'.
													'<Memo>'.$id_order.'</Memo>'.
													'<JournalDebitLine>'.
														'<AccountRef>'.
															'<FullName>'.$creditMemoAccount.'</FullName>'.
														'</AccountRef>'.
														'<Amount>'.number_format(round($creditmemo->getData('grand_total'),2),2).'</Amount>'.
														'<Memo>Debit</Memo>'.
														'<EntityRef>'.
															'<FullName>'.$customerName.'</FullName>'.
														'</EntityRef>'.
													'</JournalDebitLine>'.
													'<JournalCreditLine>'.
														'<AccountRef>'.
															'<FullName>'.$depositAccount.'</FullName>'.
														'</AccountRef>'.
														'<Amount>'.number_format(round($creditmemo->getData('grand_total'),2),2).'</Amount>'.
														'<Memo>Credit</Memo>'.
													'</JournalCreditLine>'.
												'</JournalEntryAdd>'.
											'</JournalEntryAddRq>'.
										'</QBXMLMsgsRq>'.
									'</QBXML>';
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
							$messageType = 'JournalEntryAddRq';
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
						$tempString = strstr($data, '<JournalEntryAddRs');
						$endLocation = strpos($tempString, "</JournalEntryAddRs>");
						if(!$endLocation){
							$endLocation = strpos($tempString, " />");
							$xml1 = substr($tempString, 0, $endLocation);
							$xml1 .= "></JournalEntryAddRs>";
						}else {
							$xml1 = substr($tempString, 0, $endLocation);
							$xml1 .= "</JournalEntryAddRs>";
						}
						
						$xml = simplexml_load_string($xml1);
						
						$messageType = 'JournalEntryAddRs';
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
  public function invoiceVoid( Varien_Event_Observer $observer){
  		if ($this->isDisable()){
			return;
		}
  
  		$event = $observer->getEvent();
		$invoice_id = $event->getControllerAction()->getRequest()->getParam('invoice_id');
		$invoice = Mage::getModel('sales/order_invoice')->load($invoice_id);
		
		$id_order = $invoice->getOrder()->getIncrementId();
		
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
		
		//QBOE - XML SalesReceiptQueryR
		$xml = $this->queryQboe($id_order,$sessionTicket,'SalesReceiptQueryR');

		$messageType = 'SalesReceiptQueryRs';
		$statusCode=$xml['statusCode'];
		$requestID=$xml['statusSeverity'];
		$statusMessage=$xml['statusMessage'];
		
  		foreach ($xml->children() as $second_gen) {
			$txnId = $second_gen->TxnID;   
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
									'<TxnDelRq>'.
										'<TxnDelType >SalesReceipt</TxnDelType>'.
										'<TxnID >'.$txnId.'</TxnID>'.
									'</TxnDelRq>'.
								'</QBXMLMsgsRq>'.
							'</QBXML>';

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
					$messageType = 'TxnDelRq';
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

				$tempString = strstr($data, '<TxnDelRs');
				$endLocation = strpos($tempString, "</TxnDelRs>");
				if(!$endLocation){
					$endLocation = strpos($tempString, " />");
					$xml1 = substr($tempString, 0, $endLocation);
					$xml1 .= "></TxnDelRs>";
				}else {
					$xml1 = substr($tempString, 0, $endLocation);
					$xml1 .= "</TxnDelRs>";
				}
				
				$xml = simplexml_load_string($xml1);
				
				$messageType = 'TxnDelRs';
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
