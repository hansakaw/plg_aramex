<?php

defined ('_JEXEC') or die('Restricted access');
if (!class_exists ('vmPSPlugin')) {
	require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

class plgVmShipmentAramex extends vmPSPlugin {
	/**
	 * @param object $subject
	 * @param array  $config
	 */
	function __construct (& $subject, $config) {

		parent::__construct ($subject, $config);

		$this->_loggable = TRUE;
		$this->_tablepkey = 'id';
		$this->_tableId = 'id';
		$this->tableFields = array_keys ($this->getTableSQLFields ());
		$varsToPush = $this->getVarsToPush ();
		$this->setConfigParameterable ($this->_configTableFieldName, $varsToPush);

	}

	/**
	 * Create the table for this plugin if it does not yet exist.
	 *
	 * @author ValÃ©rie Isaksen
	 */
	public function getVmPluginCreateTableSQL () {

		return $this->createTableSQL ('Shipment Aramex Table');
	}

	/**
	 * @return array
	 */
	function getTableSQLFields () {

		$SQLfields = array(
			'id'                           => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id'          => 'int(11) UNSIGNED',
			'order_number'                 => 'char(32)',
			'virtuemart_shipmentmethod_id' => 'mediumint(1) UNSIGNED',
			'shipment_name'                => 'varchar(5000)',
			'order_weight'                 => 'decimal(10,4)',
			'shipment_weight_unit'         => 'char(3) DEFAULT \'KG\'',
			'shipment_cost'                => 'decimal(10,2)',
			'shipment_package_fee'         => 'decimal(10,2)',
			'tax_id'                       => 'smallint(1)',
			'reference'                    => 'varchar(250)',
			'labelurl'                       => 'varchar(250)',
			'labelpath'                       => 'varchar(250)',

		);
		return $SQLfields;
	}

	/**
	 * This method is fired when showing the order details in the frontend.
	 * It displays the shipment-specific data.
	 *
	 * @param integer $virtuemart_order_id The order ID
	 * @param integer $virtuemart_shipmentmethod_id The selected shipment method id
	 * @param string  $shipment_name Shipment Name
	 * @return mixed Null for shipments that aren't active, text (HTML) otherwise
	 * @author ValÃ©rie Isaksen
	 * @author Max Milbers
	 */
	public function plgVmOnShowOrderFEShipment ($virtuemart_order_id, $virtuemart_shipmentmethod_id, &$shipment_name) {

		$this->onShowOrderFE ($virtuemart_order_id, $virtuemart_shipmentmethod_id, $shipment_name);
	}

	/**
	 * This event is fired after the order has been stored; it gets the shipment method-
	 * specific data.
	 *
	 * @param int    $order_id The order_id being processed
	 * @param object $cart  the cart
	 * @param array  $order The actual order saved in the DB
	 * @return mixed Null when this method was not selected, otherwise true
	 * @author Valerie Isaksen
	 */
	function plgVmConfirmedOrder (VirtueMartCart $cart, $order) {

		$app		= JFactory::getApplication();

		if (!($method = $this->getVmPluginMethod ($order['details']['BT']->virtuemart_shipmentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement ($method->shipment_element)) {
			return FALSE;
		}
		$db = & JFactory::getDBO();
		
	
		
		if (!class_exists('CurrencyDisplay'))
			require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php');
		$currency = CurrencyDisplay::getInstance($cart->vendor->vendor_currency);

		if($cart->ST)
		{
			$shipto = $cart->ST;	
			$sql = "select country_2_code from #__virtuemart_countries where virtuemart_country_id = ".$cart->ST['virtuemart_country_id'];
			$db->setQuery($sql);
			$shipto['country'] = $db->loadResult();
		}
		else
		if($cart->BT && $cart->STsameAsBT)
		{
			$sql = "select country_2_code from #__virtuemart_countries where virtuemart_country_id = ".$cart->BT['virtuemart_country_id'];
			$db->setQuery($sql);
			$shipto = $cart->BT;	
			$shipto['country'] = $db->loadResult();
		}

		$aramex_items = array();
		foreach($cart->products as $pro)
		{
			
			$prow = number_format(ShopFunctions::convertWeightUnit ($pro->product_weight, $pro->product_weight_uom, 'KG') * $pro->quantity,3);
			$prow = $prow ? $prow : 0;
			$aramex_items[]	= array(
				'PackageType'	=> 'Box',
				'Quantity'		=> $pro->quantity,
				'Weight'		=> array(
					'Value'	=> $prow,
					'Unit'	=> 'KG'
				),
				'Comments'		=> 'Docs',
				'Reference'		=> '',
			);

			
		}

		//create Shipment
		$params['Shipper'] = array(
		
			'Reference1' 	=> $cart->order_number,
			'Reference2' 	=> $cart->customer_number,
			'AccountNumber' => $method->account_number,
			
			'PartyAddress'	=> array(
				'Line1'					=> $method->account_address,
				'City'					=> $method->account_city,
				//'StateOrProvinceCode'	=> $method->addcount_state,
				'PostCode'				=> $method->account_zipcode,
				'CountryCode'			=> $method->account_country_code
			),
								
			'Contact'		=> array(
					'Department'			=> $method->shipper_department,
					'PersonName'			=> $method->shipper_personname,
					'Title'					=> $method->shipper_title,
					'CompanyName'			=> $method->shipper_companyname,
					'PhoneNumber1'			=> $method->shipper_phonenumber1,
					'PhoneNumber1Ext'		=> $method->shipper_phonenumber1ext,
					'PhoneNumber2'			=> $method->shipper_phonenumber2,
					'PhoneNumber2Ext'		=> $method->shipper_phonenumber2ext,
					'FaxNumber'				=> $method->shipper_faxnumber,
					'CellPhone'				=> $method->shipper_cellphone,
					'EmailAddress'			=> $method->shipper_emailaddress,
					'Type'					=> ''
				)
			);
												
		$params['Consignee']	= array(
				'Reference1' 	=> $cart->order_number,
				'Reference2' 	=> $cart->customer_number,
				'AccountNumber' => $method->account_number,
				'PartyAddress'	=> array(
					'Line1'					=> $shipto['address_1'],
					'Line2'					=> $shipto['address_2'],
					'Line3'					=> '',
					'City'					=> $shipto['city'],
					'StateOrProvinceCode'	=> $shipto['state'],
					'PostCode'				=> $shipto['zip'],
					'CountryCode'			=> $shipto['country'],
				),
										
				'Contact'		=> array(
					'Department'			=> '',
					'PersonName'			=> $shipto['first_name'].' '.$shipto['last_name'],
					'Title'					=> '',
					'CompanyName'			=> $shipto['first_name'].' '.$shipto['last_name'],
					'PhoneNumber1'			=> $shipto['phone_1'],
					'PhoneNumber1Ext'		=> '',
					'PhoneNumber2'			=> $shipto['phone_2'],
					'PhoneNumber2Ext'		=> '',
					'FaxNumber'				=> '',
					'CellPhone'				=> $shipto['phone_1'],
					'EmailAddress'			=> $shipto['email'],
					'Type'					=> ''
				),
			);
			
			$params['Reference1'] 				= $cart->order_number; //'Shpt0001';
			$params['Reference2'] 				= $cart->customer_number;
			$params['Reference3'] 				= '';
			$params['ForeignHAWB'] 				= $method->foreignhawb;

			$params['TransportType'] 			= 0;
			$params['ShippingDateTime'] 		= time() ;
			$params['DueDate'] 					= time() + (7 * 24 * 60 * 60); //date('m/d/Y g:i:sA');
			$params['PickupLocation'] 			= 'Reception';
			$params['PickupGUID'] 				= '';
			$params['Comments'] 				= $method->description_of_goods;
			$params['AccountingInstrcutions'] 	= '';
			$params['OperationsInstructions'] 	= '';									
			
			$params['Details']= array(
					'Dimensions' => array(
						'Length'				=> 10,
						'Width'					=> 10,
						'Height'				=> 10,
						'Unit'					=> 'CM',
						
					),
					

					'ActualWeight' => array(
						'Value'					=> number_format($this->getOrderWeight ($cart,'KG'),3),
						'Unit'					=> 'KG'
					),
					
					'ProductGroup' 			=> $method->product_group,
					'ProductType'			=> $method->product_type,
					'PaymentType'			=> $method->payment_type,
					'PaymentOptions' 		=> $method->payment_options,
					'Services'				=> $method->services,
					'NumberOfPieces'		=> 1,
					'DescriptionOfGoods' 	=> $method->description_of_goods,
					'GoodsOriginCountry' 	=> $method->goods_country,
					'CurrencyCode'			=> 	$currency->_vendorCurrency_code_3,			
					'Items' 				=> $aramex_items
			);
			
			
			$params['Details']['CashOnDeliveryAmount'] = array(
					'Value'					=> $cart->pricesUnformatted['billTotal'],
					'CurrencyCode'			=> $currency->_vendorCurrency_code_3
			);

			$params['Details']['CustomsValueAmount'] = array(
					'Value'					=> $cart->pricesUnformatted['billTotal'],
					'CurrencyCode'			=> $currency->_vendorCurrency_code_3
			);



			if($method->insurance_amount)
						{
							$params['Details']['InsuranceAmount'] = array(
									'Value'					=> $method->insurance_amount,
									'CurrencyCode'			=> $currency->_vendorCurrency_code_3
							);
						}
			if($method->cash_additional_amount)
						{
							$params['Details']['CashAdditionalAmount'] = array(
									'Value'					=> $method->cash_additional_amount,
									'CurrencyCode'			=> $currency->_vendorCurrency_code_3
							);

							$params['Details']['CashAdditionalAmountDescription'] = $method->cash_additional_desc;
						}
			if($method->collect_amount)
						{
							$params['Details']['CollectAmount'] = array(
									'Value'					=> $method->collect_amount,
									'CurrencyCode'			=> $currency->_vendorCurrency_code_3
							);
						}




			$major_par['Shipments'][] 	= $params;	
		
			$major_par['ClientInfo'] = array(
										'AccountCountryCode'	=> $method->account_country_code,
										'AccountEntity'		 	=> $method->account_entity,
										'AccountNumber'		 	=> $method->account_number,
										'AccountPin'		 	=> $method->account_pin,
										'UserName'			 	=> $method->username,
										'Password'			 	=> $method->password,
										'Version'			 	=> $method->version
									);
			
			$major_par['LabelInfo']	= array(
										'ReportID' 				=> 9201,
										'ReportType'			=> 'URL',
			);

		if($method->ship_mode) //live mode
			$soapClient = new SoapClient(JURI::root().'plugins/vmshipment/aramex/shipping-services-api-wsdl.wsdl');
		else//test mode
			$soapClient = new SoapClient(JURI::root().'plugins/vmshipment/aramex/shipping-services-api-wsdl_test.wsdl');
		
		$auth_call = $soapClient->CreateShipments($major_par);	

		try 
		{
			$auth_call = $soapClient->CreateShipments($major_par);
			if($auth_call->HasErrors) //error
			{
				if(count($auth_call->Notifications->Notification) > 1)
				{
					foreach($auth_call->Notifications->Notification as $notify_error)
					{
						JError::raiseWarning(500,'Aramex: ' . $notify_error->Code .' - '. $notify_error->Message);
					}
				} 
				else 
				{
					JError::raiseWarning(500,'Aramex: ' . $auth_call->Shipments->ProcessedShipment->Notifications->Notification->Code . ' - '. $auth_call->Shipments->ProcessedShipment->Notifications->Notification->Message);
				}


				$app->redirect(JRoute::_('index.php?option=com_virtuemart&view=cart'));exit;
			}
				
			else 
			{
					
					//save file
					$filename = basename($auth_call->Shipments->ProcessedShipment->ShipmentLabel->LabelURL);
					file_put_contents(JPATH_ROOT.DS.'media'.DS.$filename, file_get_contents($auth_call->Shipments->ProcessedShipment->ShipmentLabel->LabelURL));
					
					$values['virtuemart_order_id'] = $order['details']['BT']->virtuemart_order_id;
					$values['order_number'] = $order['details']['BT']->order_number;
					$values['virtuemart_shipmentmethod_id'] = $order['details']['BT']->virtuemart_shipmentmethod_id;
					$values['shipment_name'] = $this->renderPluginName ($method);
					$values['order_weight'] = $this->getOrderWeight ($cart, $method->weight_unit);
					$values['shipment_weight_unit'] = 'KG';
					$values['shipment_cost'] = $cart->pricesUnformatted['salesPricePayment'];
					$values['shipment_package_fee'] = $method->package_fee;
					$values['tax_id'] = $method->tax_id;
					$values['reference'] = $auth_call->Shipments->ProcessedShipment->ID;
					$values['labelurl'] = $auth_call->Shipments->ProcessedShipment->ShipmentLabel->LabelURL;
					$values['labelpath'] = $filename;
					$this->storePSPluginInternalData ($values);
					//Send admin email
					$mailfrom	= $app->getCfg('mailfrom');
					$fromname	= $app->getCfg('fromname');
					$sitename	= $app->getCfg('sitename');

					$admin_body = $method->admin_body;
					$admin_body = str_replace("{shopper_name}",$shipto['first_name']. ' '. $shipto['last_name'],$admin_body);
					$admin_body = str_replace("{order_number}",$order['details']['BT']->order_number,$admin_body);
					$admin_body = str_replace("{reference_id}",$auth_call->Shipments->ProcessedShipment->ID,$admin_body);

					$mail = JFactory::getMailer();
					$mail->addRecipient($method->admin_email);
					$mail->setSender(array($mailfrom, $fromname));
					$mail->setSubject($method->admin_subject);
					$mail->setBody($admin_body);
					$mail->addAttachment(JPATH_ROOT.DS.'media'.DS.$filename);
					$sent = $mail->Send();
					//shopper
					$shopper_body = $method->shopper_body;
					$shopper_body = str_replace("{shopper_name}",$shipto['first_name']. ' '. $shipto['last_name'],$shopper_body);
					$shopper_body = str_replace("{order_number}",$order['details']['BT']->order_number,$shopper_body);
					$shopper_body = str_replace("{reference_id}",$auth_call->Shipments->ProcessedShipment->ID,$shopper_body);

					$mail = JFactory::getMailer();
					$mail->addRecipient($shipto['email']);
					$mail->setSender(array($mailfrom, $fromname));
					$mail->setSubject($method->shopper_subject);
					$mail->setBody($shopper_body);
					$mail->addAttachment(JPATH_ROOT.DS.'media'.DS.$filename);
					$sent = $mail->Send();

				
			}
								
				
		} 
		catch (SoapFault $fault) 
		{
			JError::raiseWarning(500,$fault->faultstring);
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * This method is fired when showing the order details in the backend.
	 * It displays the shipment-specific data.
	 * NOTE, this plugin should NOT be used to display form fields, since it's called outside
	 * a form! Use plgVmOnUpdateOrderBE() instead!
	 *
	 * @param integer $virtuemart_order_id The order ID
	 * @param integer $virtuemart_shipmentmethod_id The order shipment method ID
	 * @param object  $_shipInfo Object with the properties 'shipment' and 'name'
	 * @return mixed Null for shipments that aren't active, text (HTML) otherwise
	 * @author Valerie Isaksen
	 */
	public function plgVmOnShowOrderBEShipment ($virtuemart_order_id, $virtuemart_shipmentmethod_id) {

		if (!($this->selectedThisByMethodId ($virtuemart_shipmentmethod_id))) {
			return NULL;
		}
		$html = $this->getOrderShipmentHtml ($virtuemart_order_id);
		return $html;
	}

	/**
	 * @param $virtuemart_order_id
	 * @return string
	 */
	function getOrderShipmentHtml ($virtuemart_order_id) {

		$db = JFactory::getDBO ();
		$q = 'SELECT * FROM `' . $this->_tablename . '` '
			. 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;
		$db->setQuery ($q);
		if (!($shipinfo = $db->loadObject ())) {
			vmWarn (500, $q . " " . $db->getErrorMsg ());
			return '';
		}

		if (!class_exists ('CurrencyDisplay')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php');
		}

		$currency = CurrencyDisplay::getInstance ();
		$tax = ShopFunctions::getTaxByID ($shipinfo->tax_id);
		$taxDisplay = is_array ($tax) ? $tax['calc_value'] . ' ' . $tax['calc_value_mathop'] : $shipinfo->tax_id;
		$taxDisplay = ($taxDisplay == -1) ? JText::_ ('COM_VIRTUEMART_PRODUCT_TAX_NONE') : $taxDisplay;

		$html = '<table class="adminlist">' . "\n";
		$html .= $this->getHtmlHeaderBE ();
		$html .= $this->getHtmlRowBE ('WEIGHT_COUNTRIES_SHIPPING_NAME', $shipinfo->shipment_name);
		$html .= $this->getHtmlRowBE ('WEIGHT_COUNTRIES_WEIGHT', $shipinfo->order_weight . ' ' . ShopFunctions::renderWeightUnit ($shipinfo->shipment_weight_unit));
		$html .= $this->getHtmlRowBE ('WEIGHT_COUNTRIES_COST', $currency->priceDisplay ($shipinfo->shipment_cost));
		$html .= $this->getHtmlRowBE ('WEIGHT_COUNTRIES_PACKAGE_FEE', $currency->priceDisplay ($shipinfo->shipment_package_fee));
		$html .= $this->getHtmlRowBE ('WEIGHT_COUNTRIES_TAX', $taxDisplay);
		$html .= '</table>' . "\n";

		return $html;
	}

	/**
	 * @param VirtueMartCart $cart
	 * @param                $method
	 * @param                $cart_prices
	 * @return int
	 */
	function getCosts (VirtueMartCart $cart, $method, $cart_prices) 
	{
		$db = & JFactory::getDBO();
		if($cart->ST)
		{
			$dest_city = $cart->ST['city'];
			$sql = "select country_2_code from #__virtuemart_countries where virtuemart_country_id = ".$cart->ST['virtuemart_country_id'];
			$db->setQuery($sql);
			$dest_country = $db->loadResult();	
			$dest_zip = $cart->ST['zip'];
			$dest_address = $cart->ST['address_1'];
		}
		else
		if($cart->BT && $cart->STsameAsBT)
		{
			$dest_city = $cart->BT['city'];
			$sql = "select country_2_code from #__virtuemart_countries where virtuemart_country_id = ".$cart->BT['virtuemart_country_id'];
			$db->setQuery($sql);
			$dest_country = $db->loadResult();	
			$dest_zip = $cart->BT['zip'];
			$dest_address = $cart->BT['address_1'];
		}
		//
		if (!class_exists('CurrencyDisplay'))
			require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php');
		$currency = CurrencyDisplay::getInstance($cart->vendor->vendor_currency);
		
		if($dest_country && $dest_city)
		{
			//calculate price
			//count products
			$tq = 0;
			foreach($cart->products as $pro)
			{
				$tq += $pro->quantity;	
			}
			//calculate weight
			$weight = $this->getOrderWeight ($cart,'KG');
			$params = array(
			'ClientInfo'  			=> array(
										'AccountCountryCode'	=> $method->account_country_code,
										'AccountEntity'		 	=> $method->account_entity,
										'AccountNumber'		 	=> $method->account_number,
										'AccountPin'		 	=> $method->account_pin,
										'UserName'			 	=> $method->username,
										'Password'			 	=> $method->password,
										'Version'			 	=> $method->version
									),
									
			'Transaction' 			=> array(
										'Reference1'			=> $cart->customer_number, 
										'Reference2'			=> '002' 
									),
												
			'OriginAddress' 	 	=> array(
									'Line1'						=> $method->account_address,
									'City'						=> $method->account_city,
									//'StateOrProvinceCode'		=> $method->account_state,
									'PostCode'					=> $method->account_zipcode,
									'CountryCode'				=> $method->account_country_code
								),
			
			
								
			'DestinationAddress' 	=> array(
									'Line1'					=> $dest_address,
									'City'					=> $dest_city,
									'CountryCode'			=> $dest_country,
									'PostCode'				=> $dest_zip,
								),
			'ShipmentDetails'		=> array(
										'PaymentType'			 => $method->payment_type,
										'ProductGroup'			 => $method->product_group,
										'ProductType'			 => $method->product_type,
										'ActualWeight' 			 => array('Value' => $weight, 'Unit' => 'KG'),
										'ChargeableWeight' 	     => array('Value' => $weight, 'Unit' => 'KG'),
										'NumberOfPieces'		 => $tq,
										'CurrencyCode'			 => $currency->_vendorCurrency_code_3
									),
			);
		
			if($method->ship_mode) //live mode
			{
				$soapClient = new SoapClient(JURI::root().'plugins/vmshipment/aramex/aramex-rates-calculator-wsdl.wsdl', array('trace' => 1));
			}
			else
			{
				$soapClient = new SoapClient(JURI::root().'plugins/vmshipment/aramex/aramex-rates-calculator-wsdl_test.wsdl', array('trace' => 1));
			}

			$results = $soapClient->CalculateRate($params);	
			if($results->HasErrors)
			{
				if(count($results->Notifications->Notification) > 1)
				{
					foreach($results->Notifications->Notification as 	$noti)
					{
						JError::raiseWarning('500', 'Aramex Shipment: '.$noti->Code.' --  '.$noti->Message);	
					}
				}
				else
				{
					foreach($results->Notifications as 	$noti)
					{
						JError::raiseWarning('500', 'Aramex Shipment: '.$noti->Code.' --  '.$noti->Message);	
					}
				}
			}
			else
				return $results->TotalAmount->Value;
		}
		else
			JError::raiseWarning(500,"Shipping Address is empty");
		//return $result;
		//return 100;
	}

	/**
	 * @param \VirtueMartCart $cart
	 * @param int             $method
	 * @param array           $cart_prices
	 * @return bool
	 */
	protected function checkConditions ($cart, $method, $cart_prices) {

		return true;		
	}

	/**
	 * @param $method
	 */
	function convert (&$method) {

		//$method->weight_start = (float) $method->weight_start;
		//$method->weight_stop = (float) $method->weight_stop;
		$method->orderamount_start =  (float)str_replace(',','.',$method->orderamount_start);
		$method->orderamount_stop =   (float)str_replace(',','.',$method->orderamount_stop);
		$method->zip_start = (int)$method->zip_start;
		$method->zip_stop = (int)$method->zip_stop;
		$method->nbproducts_start = (int)$method->nbproducts_start;
		$method->nbproducts_stop = (int)$method->nbproducts_stop;
		$method->free_shipment = (float)str_replace(',','.',$method->free_shipment);
	}

	

	/**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
	 *
	 * @author ValÃ©rie Isaksen
	 *
	 */
	function plgVmOnStoreInstallShipmentPluginTable ($jplugin_id) {

		return $this->onStoreInstallPluginTable ($jplugin_id);
	}

	/**
	 * @param VirtueMartCart $cart
	 * @return null
	 */
	public function plgVmOnSelectCheckShipment (VirtueMartCart &$cart) {
		
		$db = & JFactory::getDBO();
		if($cart->ST)
		{
			$shipto = $cart->ST;	
			$sql = "select country_2_code from #__virtuemart_countries where virtuemart_country_id = ".$cart->ST['virtuemart_country_id'];
			$db->setQuery($sql);
			$shipto['country'] = $db->loadResult();
		}
		else
		if($cart->BT && $cart->STsameAsBT)
		{
			$sql = "select country_2_code from #__virtuemart_countries where virtuemart_country_id = ".$cart->BT['virtuemart_country_id'];
			$db->setQuery($sql);
			$shipto = $cart->BT;	
			$shipto['country'] = $db->loadResult();
		}
		if($shipto['first_name'] && $shipto['last_name'] && $shipto['address_1'] && $shipto['phone_1'] && $shipto['city'] && $shipto['zip'] && $shipto['email'])
			$this->OnSelectCheck ($cart);
		else
		{
			if(!$shipto['first_name'])
				JError::raiseWarning(500,'Shipping Address: Invalid first name' );
			if(!$shipto['last_name'])
				JError::raiseWarning(500,'Shipping Address: Invalid last name' );
			if(!$shipto['address_1'])
				JError::raiseWarning(500,'Shipping Address: Invalid address' );
			if(!$shipto['city'])
				JError::raiseWarning(500,'Shipping Address: Invalid city' );
			if(!$shipto['zip'])
				JError::raiseWarning(500,'Shipping Address: Invalid zipcode' );
			if(!$shipto['email'])
				JError::raiseWarning(500,'Shipping Address: Invalid email' );
			if(!$shipto['phone_1'])
				JError::raiseWarning(500,'Shipping Address: Invalid phone number' );

			return false;
		}
	}

	/**
	 * plgVmDisplayListFE
	 * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for example
	 *
	 * @param object  $cart Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean True on success, false on failures, null when this plugin was not selected.
	 * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
	 *
	 * @author Valerie Isaksen
	 * @author Max Milbers
	 */
	public function plgVmDisplayListFEShipment (VirtueMartCart $cart, $selected = 0, &$htmlIn) {

		return $this->displayListFE ($cart, $selected, $htmlIn);
	}

	/**
	 * @param VirtueMartCart $cart
	 * @param array          $cart_prices
	 * @param                $cart_prices_name
	 * @return bool|null
	 */
	public function plgVmOnSelectedCalculatePriceShipment (VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {

		return $this->onSelectedCalculatePrice ($cart, $cart_prices, $cart_prices_name);
	}

	/**
	 * plgVmOnCheckAutomaticSelected
	 * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	 * The plugin must check first if it is the correct type
	 *
	 * @author Valerie Isaksen
	 * @param VirtueMartCart cart: the cart object
	 * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
	 *
	 */
	function plgVmOnCheckAutomaticSelectedShipment (VirtueMartCart $cart, array $cart_prices, &$shipCounter) {

		if ($shipCounter > 1) {
			return 0;
		}

		return $this->onCheckAutomaticSelected ($cart, $cart_prices, $shipCounter);
	}

	/**
	 * This method is fired when showing when priting an Order
	 * It displays the the payment method-specific data.
	 *
	 * @param integer $_virtuemart_order_id The order ID
	 * @param integer $method_id  method used for this order
	 * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
	 * @author Valerie Isaksen
	 */
	function plgVmonShowOrderPrint ($order_number, $method_id) {

		return $this->onShowOrderPrint ($order_number, $method_id);
	}

	function plgVmDeclarePluginParamsShipment ($name, $id, &$data) {

		return $this->declarePluginParams ('shipment', $name, $id, $data);
	}


	/**
	 * @author Max Milbers
	 * @param $data
	 * @param $table
	 * @return bool
	 */
	function plgVmSetOnTablePluginShipment(&$data,&$table){

		$name = $data['shipment_element'];
		$id = $data['shipment_jplugin_id'];

		if (!empty($this->_psType) and !$this->selectedThis ($this->_psType, $name, $id)) {
			return FALSE;
		} else {
			return $this->setOnTablePluginParams ($name, $id, $table);
		}
	}

	
}
?>