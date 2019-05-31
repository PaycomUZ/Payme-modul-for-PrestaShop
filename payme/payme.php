<?php
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
	exit;
}

class Payme extends PaymentModule {

	protected $_html = '';
	private $formErrors = array();

	public $payme_merchant_id;
	public $payme_secret_key;
	public $payme_secret_key_test;

	public $payme_test_mode;
	public $payme_checkout_url;
	public $payme_checkout_url_test;

	public $payme_return_url;
	public $payme_return_after;
	public $payme_add_product_information;

	public $payme_endpoint_url;

	public function __construct() {

		$this->name = 'payme';
		$this->tab = 'payments_gateways';
		$this->version = '1.0.0.1';
		$this->author = 'Paycom';
		//$this->controllers = array('payment', 'validation','redirect');
		$this->ps_versions_compliancy = [
			'min' => '1.7',
			'max' => _PS_VERSION_
		];

		$this->setPaymentAttributes();

		parent::__construct();

		$this->displayName	  = 'Payme';
		$this->description	  = $this->l('DESCRIPTION');
		$this->confirmUninstall = $this->l('DELETE_CONFIRM');
	}

	public function install() {

		if ( !parent::install() || !$this->registerHook('paymentOptions') || !$this->registerHook('paymentReturn') )
		return false;

		Configuration::updateValue('PAYME_MERCHANT_ID','');
		Configuration::updateValue('PAYME_SECRET_KEY', '');
		Configuration::updateValue('PAYME_SECRET_KEY_TEST', '');

		Configuration::updateValue('PAYME_TEST_MODE', '');
		Configuration::updateValue('PAYME_CHECKOUT_URL', '');
		Configuration::updateValue('PAYME_CHECKOUT_URL_TEST', '');

		Configuration::updateValue('PAYME_RETURN_URL', '');
		Configuration::updateValue('PAYME_RETURN_AFTER', '');
		Configuration::updateValue('PAYME_ADD_PRODUCT_INFORMATION', '');

		Configuration::updateValue('PAYME_ENDPOINT_URL', '');

		Db::getInstance()->execute(
			"CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_."payme_transactions` (
			`transaction_id` bigint(11) NOT NULL AUTO_INCREMENT COMMENT 'идентификатор транзакции ',
			`paycom_transaction_id` char(25) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Номер или идентификатор транзакции в биллинге мерчанта. Формат строки определяется мерчантом.',
			`paycom_time` varchar(13) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Время создания транзакции Paycom.',
			`paycom_time_datetime` datetime DEFAULT NULL COMMENT 'Время создания транзакции Paycom.',
			`create_time` datetime NOT NULL COMMENT 'Время добавления транзакции в биллинге мерчанта.',
			`perform_time` datetime DEFAULT NULL COMMENT 'Время проведения транзакции в биллинге мерчанта',
			`cancel_time` datetime DEFAULT NULL COMMENT 'Время отмены транзакции в биллинге мерчанта.',
			`amount` int(11) NOT NULL COMMENT 'Сумма платежа в тийинах.',
			`state` int(11) NOT NULL DEFAULT '0' COMMENT 'Состояние транзакции',
			`reason` tinyint(2) DEFAULT NULL COMMENT 'причина отмены транзакции.',
			`receivers` varchar(500) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'JSON array of receivers',
			`order_id` bigint(20) NOT NULL COMMENT 'заказ',
			`cms_order_id` char(20) COLLATE utf8_unicode_ci NOT NULL COMMENT 'номер заказа CMS',
			`is_flag_test` enum('Y','N') COLLATE utf8_unicode_ci NOT NULL,
			PRIMARY KEY (`transaction_id`),
			UNIQUE KEY `paycom_transaction_id` (`paycom_transaction_id`),
			UNIQUE KEY `order_id` (`order_id`,`paycom_transaction_id`),
			KEY `state` (`state`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=2;"
		);

		return true;
	}

	public function uninstall() {

		Db::getInstance()->execute("DROP TABLE IF EXISTS `"._DB_PREFIX_."payme_transactions`;");
		
		if ( 
			!Configuration::deleteByName('PAYME_MERCHANT_ID')||
			!Configuration::deleteByName('PAYME_SECRET_KEY')||
			!Configuration::deleteByName('PAYME_SECRET_KEY_TEST')||

			!Configuration::deleteByName('PAYME_TEST_MODE')||
			!Configuration::deleteByName('PAYME_CHECKOUT_URL')||
			!Configuration::deleteByName('PAYME_CHECKOUT_URL_TEST')||

			!Configuration::deleteByName('PAYME_RETURN_URL')||
			!Configuration::deleteByName('PAYME_RETURN_AFTER')||
			!Configuration::deleteByName('PAYME_ADD_PRODUCT_INFORMATION')||
			
			!Configuration::deleteByName('PAYME_ENDPOINT_URL')||

			!parent::uninstall() )
		return false;	

		return true;
	}

	public function hookPaymentOptions($params) {

		if (!$this->active) return;

		if (!$this->checkCurrency($params['cart'])) return;

		$newOption = new PaymentOption();
		$newOption->setCallToActionText($this->l('PAY_WITH_PAYME'))
			->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
			->setAdditionalInformation($this->context->smarty->fetch('module:payme/views/templates/front/payment_request.tpl'));

		return array($newOption);
	}

	public function hookPaymentReturn($params) {

		if (!$this->active)
		return;

		return $this->display(__FILE__, 'payment_return.tpl');
	}

	public function getContent() {

		if (Tools::isSubmit('btnSubmit')) {

			$this->formCheck();

			if (!sizeof($this->formErrors)) {

				$this->saveConfig();
				$this->_html .= $this->displayConfirmation($this->l('Settings updated'));

			} else {

				foreach ($this->formErrors as $err) {
					$this->_html .= $this->displayError($err);
				}
			}
		} else {

			$this->_html .= '<br />';
		}

			 $defVal_PAYME_CHECKOUT_URL=$this->getSetting('payme_checkout_url', $this->payme_checkout_url);
		if(! $defVal_PAYME_CHECKOUT_URL) 
			 $defVal_PAYME_CHECKOUT_URL='https://checkout.paycom.uz';

			 $defVal_PAYME_CHECKOUT_URL_TEST=$this->getSetting('payme_checkout_url_test',$this->payme_checkout_url_test);
		if(! $defVal_PAYME_CHECKOUT_URL_TEST) 
			 $defVal_PAYME_CHECKOUT_URL_TEST='https://test.paycom.uz';

			 $defVal_PAYME_ENDPOINT_URL=$this->getSetting('payme_endpoint_url',$this->payme_endpoint_url);
		if(! $defVal_PAYME_ENDPOINT_URL)
			 $defVal_PAYME_ENDPOINT_URL= 'http://'.htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8').'/module/payme/notification';

			 $defVal_PAYME_RETURN_URL=$this->getSetting('payme_return_url', $this->payme_return_url);
		if(! $defVal_PAYME_RETURN_URL) 
			 $defVal_PAYME_RETURN_URL= 'http://'.htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8').'/module/payme/redirect';

		$this->smarty->assign(
			array(

				'PAYME_MERCHANT_ID'             => $this->getSetting('payme_merchant_id', 			 $this->payme_merchant_id),
				'PAYME_SECRET_KEY'              => $this->getSetting('payme_secret_key', 			 $this->payme_secret_key),
				'PAYME_SECRET_KEY_TEST'         => $this->getSetting('payme_secret_key_test', 		 $this->payme_secret_key_test),
				'PAYME_TEST_MODE'               => $this->getSetting('payme_test_mode', 			 $this->payme_test_mode),
				'PAYME_RETURN_AFTER'            => $this->getSetting('payme_return_after', 			 $this->payme_return_after),
				'PAYME_ADD_PRODUCT_INFORMATION' => $this->getSetting('payme_add_product_information',$this->payme_add_product_information),

				'PAYME_RETURN_URL'              => $defVal_PAYME_RETURN_URL,
				'PAYME_CHECKOUT_URL'            => $defVal_PAYME_CHECKOUT_URL,
				'PAYME_CHECKOUT_URL_TEST'       => $defVal_PAYME_CHECKOUT_URL_TEST,
				'PAYME_ENDPOINT_URL'       		=> $defVal_PAYME_ENDPOINT_URL,

				'action' 						=> $_SERVER['REQUEST_URI'],
				'returnAfterList' 				=> $this->getReturnAfterList(),
				'productInformationList' 		=> $this->getProductInformationList(),
				'this' 							=> $this
			)
		);

		$this->_html .= $this->display(__FILE__, 'settings.tpl');

		return $this->_html;
	}

	public function getSetting($name, $value) {

		return htmlentities(Tools::getValue($name, $value), ENT_COMPAT, 'UTF-8');
	}

	public function setPaymentAttributes() {

		$config = Configuration::getMultiple( array(

		'PAYME_MERCHANT_ID',
		'PAYME_SECRET_KEY',
		'PAYME_SECRET_KEY_TEST',
		'PAYME_TEST_MODE',
		'PAYME_CHECKOUT_URL',
		'PAYME_CHECKOUT_URL_TEST',
		'PAYME_RETURN_URL',
		'PAYME_RETURN_AFTER',
		'PAYME_ADD_PRODUCT_INFORMATION',
		'PAYME_ENDPOINT_URL'
		));

		if (isset($config['PAYME_MERCHANT_ID'])) 			 $this->payme_merchant_id 			  = $config['PAYME_MERCHANT_ID'];
		if (isset($config['PAYME_SECRET_KEY']))  			 $this->payme_secret_key  			  = $config['PAYME_SECRET_KEY'];
		if (isset($config['PAYME_SECRET_KEY_TEST']))  		 $this->payme_secret_key_test  		  = $config['PAYME_SECRET_KEY_TEST'];
		if (isset($config['PAYME_TEST_MODE'])) 				 $this->payme_test_mode 			  = $config['PAYME_TEST_MODE'];
		if (isset($config['PAYME_CHECKOUT_URL']))  			 $this->payme_checkout_url  		  = $config['PAYME_CHECKOUT_URL'];
		if (isset($config['PAYME_CHECKOUT_URL_TEST']))  	 $this->payme_checkout_url_test  	  = $config['PAYME_CHECKOUT_URL_TEST'];
		if (isset($config['PAYME_RETURN_URL'])) 			 $this->payme_return_url 			  = $config['PAYME_RETURN_URL'];
		if (isset($config['PAYME_RETURN_AFTER']))  			 $this->payme_return_after  		  = $config['PAYME_RETURN_AFTER'];
		if (isset($config['PAYME_ADD_PRODUCT_INFORMATION'])) $this->payme_add_product_information = $config['PAYME_ADD_PRODUCT_INFORMATION'];
		if (isset($config['PAYME_ENDPOINT_URL'])) 			 $this->payme_endpoint_url 			  = $config['PAYME_ENDPOINT_URL'];
	}

	private function formCheck() {

			if (empty($_POST['PAYME_MERCHANT_ID'])) 		$this->formErrors[] = $this->l('PAYME_MERCHANT_ID is required');
		elseif (empty($_POST['PAYME_SECRET_KEY']))  		$this->formErrors[] = $this->l('PAYME_SECRET_KEY is required');
		elseif (empty($_POST['PAYME_SECRET_KEY_TEST'])) 	$this->formErrors[] = $this->l('PAYME_SECRET_KEY_TEST is required');
		elseif (empty($_POST['PAYME_CHECKOUT_URL']))  		$this->formErrors[] = $this->l('PAYME_CHECKOUT_URL is required');
		elseif (empty($_POST['PAYME_CHECKOUT_URL_TEST']))	$this->formErrors[] = $this->l('PAYME_CHECKOUT_URL_TEST is required');
		elseif (empty($_POST['PAYME_RETURN_URL']))  		$this->formErrors[] = $this->l('PAYME_RETURN_URL is required');
		elseif (empty($_POST['PAYME_ENDPOINT_URL']))  		$this->formErrors[] = $this->l('PAYME_ENDPOINT_URL is required');
	}

	private function saveConfig() {

		Configuration::updateValue('PAYME_MERCHANT_ID', 			$_POST['PAYME_MERCHANT_ID']);
		Configuration::updateValue('PAYME_SECRET_KEY',  			$_POST['PAYME_SECRET_KEY']);
		Configuration::updateValue('PAYME_SECRET_KEY_TEST', 		$_POST['PAYME_SECRET_KEY_TEST']);
		Configuration::updateValue('PAYME_TEST_MODE', 				$_POST['PAYME_TEST_MODE']);
		Configuration::updateValue('PAYME_CHECKOUT_URL',  			$_POST['PAYME_CHECKOUT_URL']);
		Configuration::updateValue('PAYME_CHECKOUT_URL_TEST',  		$_POST['PAYME_CHECKOUT_URL_TEST']);
		Configuration::updateValue('PAYME_RETURN_URL', 				$_POST['PAYME_RETURN_URL']);
		Configuration::updateValue('PAYME_RETURN_AFTER',  			$_POST['PAYME_RETURN_AFTER']);
		Configuration::updateValue('PAYME_ADD_PRODUCT_INFORMATION', $_POST['PAYME_ADD_PRODUCT_INFORMATION']);
		Configuration::updateValue('PAYME_ENDPOINT_URL', 			$_POST['PAYME_ENDPOINT_URL']);

		$this->setPaymentAttributes();
	}

	public function checkCurrency($cart) {
		
		$currency_order = new Currency($cart->id_currency);
		$currencies_module = $this->getCurrency($cart->id_currency);
		if (is_array($currencies_module))
			foreach ($currencies_module as $currency_module)
				if ($currency_order->id == $currency_module['id_currency'])
					return true;
		return false;
	}

	public function getProductInformationList() {

		return array(
			'Y' => $this->l('YES'),
			'N' => $this->l('NO')
		);
	}

	public function getReturnAfterList() {

		return array(
			'0'	 => $this->l('INSTANTLY'),
			'15000' => $this->l('S15'),
			'30000' => $this->l('S30'),
			'60000' => $this->l('S60')
		);
	}
}