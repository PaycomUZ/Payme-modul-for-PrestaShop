<?php

class PaymeValidationModuleFrontController extends ModuleFrontController {

	public function postProcess() {

		$cart     = $this->context->cart;
		$currency = new Currency((int)$this->context->currency->id);
		$customer = new Customer($cart->id_customer);
		$payme    = new Payme();
		$payme->validateOrder(

			$cart->id,
			_PS_OS_CHEQUE_,
			(float)$cart->getOrderTotal(true, Cart::BOTH),
			$this->module->displayName,
			NULL,
			NULL,
			(int)$this->context->currency->id,
			false,
			$customer->secure_key,
			NULL
		);

		$orderId=Order::getOrderByCartId($cart->id);
		$order = new Order((int)$orderId);

		$p_currencyCod = $currency->iso_code_num ;
		$p_ammount     = (float)$cart->getOrderTotal(true, Cart::BOTH);
		$p_ammount=$p_ammount*100;

		if (Configuration::get('PAYME_TEST_MODE'))
		$paymeUrl=Configuration::get('PAYME_CHECKOUT_URL_TEST');
		else
		$paymeUrl=Configuration::get('PAYME_CHECKOUT_URL');

		$paymeUrl=$paymeUrl.'/'.base64_encode(
											'ac.order_id='.$orderId.
											';a=' .$p_ammount.
											';cr='.$p_currencyCod.
											';m=' .Configuration::get('PAYME_MERCHANT_ID').
											';ct='.Configuration::get('PAYME_RETURN_AFTER').
											';c=' .Configuration::get('PAYME_RETURN_URL')
											);

		Tools::redirect($paymeUrl);
	}
}