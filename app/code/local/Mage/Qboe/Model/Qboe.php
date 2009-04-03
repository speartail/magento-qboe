<?php
/**
 * Quickbooks Online Edition Module for Magento
 *
 * Developed by Xagax Solutions LLC (http://www.xagax.com)
 *
 * @copyright  Module Copyright (c) 2008 Xagax Solutions LLC (http://www.xagax.com)
 */
class Mage_Qboe_Model_Qboe extends Mage_Core_Model_Abstract
{

 public function getModuleName(){
	return 'Mage_Qboe';
 }

 public function isDisable(){
	if (Mage::getStoreConfig('advanced/modules_disable_output/'.$this->getModuleName())) {
		return Mage::getStoreConfig('advanced/modules_disable_output/'.$this->getModuleName());
	}else{
		return 0;	
	}
 }

public function getConfigDataQboe($field,$store = null)
    {
        $path = 'qboe/'.$field;
		$config = Mage::getStoreConfig($path,$store);
        return $config;
    }

public function sessionTicketQboe()
  {
	$appLogin = $this->getConfigDataQboe('configu/ApplicationLogin');
	$conTicket = $this->getConfigDataQboe('configu/ConnectionTicket');
	$cert = $this->getConfigDataQboe('configu/Cert');
	$appID = $this->getConfigDataQboe('configu/appID');
	$applicationPath = $this->getConfigDataQboe('configu/ApplicationPath');
	
	$conn_db = Mage::getSingleton('core/resource')->getConnection('core_write');
    
	//connection ticket QBOE
	$QBXML[0] = '<?xml version="1.0" ?>'.
				'<?qbxml version="6.0"?>'.
				'<QBXML>'.
					'<SignonMsgsRq>'.
						'<SignonAppCertRq>'.
							'<ClientDateTime>'.date('Y-m-d\TH:i:s').'</ClientDateTime>'.
							'<ApplicationLogin>'.$appLogin.'</ApplicationLogin>'.
							'<ConnectionTicket>'.$conTicket.'</ConnectionTicket>'.
							'<Language>English</Language>'.
							'<AppID>'.$appID.'</AppID>'.
							'<AppVer>1</AppVer>'.
						'</SignonAppCertRq>'.
					'</SignonMsgsRq>'.
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
		$messageType = 'SignonAppCertRq';
		if (empty($statusCode)){
			$statusCode = 'error curl';
		}
		if (empty($statusSeverity)){
			$statusSeverity = '';
		}
		if (empty($statusMessage)){
			$statusMessage = curl_error($ch);
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
	
	$tempString = strstr($data, "<SessionTicket>");
	$endLocation = strpos($tempString, "</SessionTicket>");
	$sessionTicket = substr($tempString, 15, $endLocation - 15);
	
	return $sessionTicket;
  }

  public function signonMsgsRq($sessionTicket){
		$appID = $this->getConfigDataQboe('configu/appID');
		$signonMsgsRq = '<SignonMsgsRq>'.
							'<SignonTicketRq>'.
								'<ClientDateTime>'.date('Y-m-d\TH:i:s').'</ClientDateTime>'.
								'<SessionTicket>'.$sessionTicket.'</SessionTicket>'.
								'<Language>English</Language>'.
								'<AppID>'.$appID.'</AppID> '.
								'<AppVer>1.0</AppVer>'.
					 		'</SignonTicketRq>'.
						'</SignonMsgsRq>';
		return $signonMsgsRq;
  }
  
	
/* ########################### QBOE - Querys ##############################
	 *
	 * $xmlText sacar la ultima letra, por ejemplo: AccountQueryRq seria AccountQueryR
	 * Sirve para:
		 * AccountQueryRq - 
		 * CheckQueryRq -  se agrega <EntityFilter>
		 * ClassQueryRq
		 * CompanyQueryRq no, reenplazar  FullName por OwnerID
		 * CreditCardChargeQueryRq no, reemplazar FullName por RefNumber*
		 * CreditCardCreditQueryRq no, reemplazar FullName por RefNumber*
		 * CreditMemoQueryRq no, reemplazar FullName por RefNumber*
		 * CustomerQueryRq
		 * DateDrivenTermsQueryRq
		 * EmployeeQueryRq
		 * EntityQueryRq
		 * InvoiceQueryRq no, reemplazar FullName por RefNumber*
		 * ItemQueryRq
		 * ItemServiceQueryRq
		 * JournalEntryQueryRq no, reemplazar FullName por RefNumber*
		 * PaymentMethodQueryRq 
		 * ReceivePaymentQueryRq no, reemplazar FullName por RefNumber*
		 * SalesReceiptQueryRq no, reemplazar FullName por RefNumber*
		 * StandardTermsQueryRq
		 * TimeTrackingQueryRq no, se agrega TimeTrackingEntityFilter
		 * VendorCreditQueryRq no, se agrega EntityFilter
		 * VendorQueryRq
	 */


 public function queryQboe($value,$sessionTicket,$xmlText){

	$cert = $this->getConfigDataQboe('configu/Cert');
	$applicationPath = $this->getConfigDataQboe('configu/ApplicationPath');
	$signonMsgsRq = $this->signonMsgsRq($sessionTicket);
	
	$conn_db = Mage::getSingleton('core/resource')->getConnection('core_write');
	
	$xml_option	='FullName';
	$addTxt1 = '';
	$addTxt2 = '';
	  
	switch($xmlText){
		case 'CheckQueryR':
			$addTxt1 = '<EntityFilter>';
			$addTxt2 = '</EntityFilter>';
			break;
		case 'TimeTrackingQueryR':
			$addTxt1 = '<TimeTrackingEntityFilter>';
			$addTxt2 = '</TimeTrackingEntityFilter>';
			break;
		case 'VendorCreditQueryR':
			$addTxt1 = '<EntityFilter>';
			$addTxt2 = '</EntityFilter>';
			break;
		case 'CompanyQueryR':
			$xml_option = 'OwnerID';
			break;
		case 'CreditCardChargeQueryR':
			$xml_option = 'RefNumber';
			break;
		case 'CreditCardCreditQueryR':
			$xml_option = 'RefNumber';
			break;
		case 'CreditMemoQueryR':
			$xml_option = 'RefNumber';
			break;		
		case 'InvoiceQueryR':
			$xml_option = 'RefNumber';
			break;	
		case 'JournalEntryQueryR':
			$xml_option = 'RefNumber';
			break;
		case 'ReceivePaymentQueryR':
			$xml_option = 'RefNumber';
			break;
		case 'SalesReceiptQueryR':
			$xml_option = 'RefNumber';
			break;
		default:
			$addTxt1 = '';
			$addTxt2 = '';
			$xml_option	='FullName';
	}
	unset($QBXML);
	unset($header); 
	unset($ch);
	unset($data);
	$QBXML[0] = '<?xml version="1.0" ?>'.
				'<?qbxml version="6.0"?>'.
				'<QBXML>'.
					$signonMsgsRq.
					'<QBXMLMsgsRq onError="stopOnError">'.
						'<'.$xmlText.'q>'.
							$addTxt1.
								'<'.$xml_option.' >'.$value.'</'.$xml_option.'>'.
							$addTxt2.
						'</'.$xmlText.'q>'.
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
		$messageType = $xmlText.'q';
		if (empty($statusCode)){
			$statusCode = 'error curl';
		}
		if (empty($statusSeverity)){
			$statusSeverity = '';
		}
		if (empty($statusMessage)){
			$statusMessage = curl_error($ch);
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

	$tempString = strstr($data, "<".$xmlText."s");
	$endLocation = strpos($tempString, "</".$xmlText."s>");
	if(!$endLocation){
		$endLocation = strpos($tempString, "/>");
		$xml1 = substr($tempString, 0, $endLocation);
		$xml1 .= "></".$xmlText."s>";
	}else {
		$xml1 = substr($tempString, 0, $endLocation);
		$xml1 .= "</".$xmlText."s>";
	}

	$xml = simplexml_load_string($xml1);
	return $xml;	
  }
}
