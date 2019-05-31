<?php

class PaymeNotificationModuleFrontController extends ModuleFrontController {

	public function postProcess() {

		header('Content-type: application/json charset=utf-8');
		require_once(dirname(__FILE__) . '/../../api/PaymeApi.php');

		$api = new PaymeApi();
		$api->setInputArray(file_get_contents("php://input"));

		exit(Tools::jsonEncode($api->parseRequest()));
	}
}
