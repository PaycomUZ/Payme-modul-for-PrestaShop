<?php

class PaymeRedirectModuleFrontController extends ModuleFrontController {

	public function init() {

		Tools::redirect('guest-tracking');
	}
}