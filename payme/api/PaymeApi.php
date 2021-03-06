<?php

class PaymeApi {

	private $errorInfo ="";
	private $errorCod =0;
	private $request_id=0;
	private $responceType=0;
	private $result =true;
	private $inputArray;
	private $lastTransaction;
	private $statement;

	public function construct() {}

	public function parseRequest() {

		if ( (!isset($this->inputArray)) || empty($this->inputArray) ) {

			$this->setErrorCod(-32700,"empty inputArray");

		} else {

			$parsingJsonError=false;

			switch (json_last_error()){

				case JSON_ERROR_NONE: break;
				default: $parsingJson=true; break;
			}

			if ($parsingJsonError) {

				$this->setErrorCod(-32700,"parsingJsonError");

			} else {

				// Request ID
				if (!empty($this->inputArray['id']) ) {

					$this->request_id = filter_var($this->inputArray['id'], FILTER_SANITIZE_NUMBER_INT);
				}

					 if ($_SERVER['REQUEST_METHOD']!='POST') $this->setErrorCod(-32300);
				else if(! isset($_SERVER['PHP_AUTH_USER']))  $this->setErrorCod(-32504,"логин пустой");
				else if(! isset($_SERVER['PHP_AUTH_PW']))	 $this->setErrorCod(-32504,"пароль пустой");
			}
		}

		if ($this->result) {

			$merchantKey="";

			if (Configuration::get('PAYME_TEST_MODE')) {

				$merchantKey=html_entity_decode(Configuration::get('PAYME_SECRET_KEY_TEST'));

			} else {

				$merchantKey=html_entity_decode(Configuration::get('PAYME_SECRET_KEY'));
			}

			if( $merchantKey != html_entity_decode($_SERVER['PHP_AUTH_PW']) ) {

				$this->setErrorCod(-32504,"неправильный  пароль");

			} else {

				if ( method_exists($this,"payme_".$this->inputArray['method'])) {

					$methodName="payme_".$this->inputArray['method'];
					$this->$methodName();

				} else {

					$this->setErrorCod(-32601, $this->inputArray['method'] );
				}
			}
		}

		return $this->GenerateResponse();
	}

	public function payme_CheckPerformTransaction() {

		$order_id = filter_var( $this->inputArray['params']['account']['order_id'], FILTER_SANITIZE_NUMBER_INT);

		// Поиск заказа по order_id
		$order = new Order($order_id);

		// Заказ не найден
		if (! $order ) {

			$this->setErrorCod(-31050,'order_id');

		// Заказ найден
		} else {

			// Поиск транзакции по order_id
			$this->getLastTransactionForOrder($order_id);

			// Транзакция нет
			if (! $this->lastTransaction ) {

				// Проверка состояния заказа
				if ($order->current_state!=_PS_OS_CHEQUE_ ) {

					$this->setErrorCod(-31052, 'order_id');

				// Сверка суммы заказа
				} else  if ( ($order->getTotalProductsWithoutTaxes()*100) != $this->inputArray['params']['amount'] ) {

					$this->setErrorCod(-31001, 'order_id'); 

				// Allow true
				} else {

					$this->responceType=1;
				} 

			// Существует транзакция
			} else {

				$this->setErrorCod(-31051, 'order_id');
			}
		}
	}

	public function payme_CreateTransaction() {

		// Поиск заказа по order_id
		$order_id = filter_var( $this->inputArray['params']['account']['order_id'], FILTER_SANITIZE_NUMBER_INT);
		$order = new Order($order_id);

		// Поиск транзакции по id
		$transactionId=$this->inputArray['params']['id'];
		$this->getLastTransaction($transactionId);

		// Существует транзакция
		if ($this->lastTransaction) {

			$paycom_time_integer=$this->datetime2timestamp($this->lastTransaction['create_time']) *1000;
			$paycom_time_integer=$paycom_time_integer+43200000;

			// Проверка состояния заказа
			if ($order->current_state!=_PS_OS_CHEQUE_ ) {

				$this->setErrorCod(-31052, 'order_id');

			// Проверка состояния транзакции
			} else if ($this->lastTransaction['state']!=1){

				$this->setErrorCod(-31008, 'order_id');

			// Проверка времени создания транзакции
			} else if ($paycom_time_integer <= $this->timestamp2milliseconds(time())){

				// Отменит reason = 4
				$updateData = array(
						 'cancel_time' => date('Y-m-d H:i:s'),
						 'reason'  	   => 4,
						 'state'  	   => -1
				);
				Db::getInstance()->update('payme_transactions', $updateData, 'transaction_id= '.(int)$this->lastTransaction['transaction_id'] );
				$order->setCurrentState(_PS_OS_CANCELED_);

				$this->getLastTransaction($transactionId);
				$this->responceType=2;

			// Всё OK
			} else {

				$this->responceType=2;
			}

		// Транзакция нет
		} else {

			// Заказ не найден
			if (! $order ) {

				$this->setErrorCod(-31050,'order_id');

			// Заказ найден
			} else {

				// Проверка состояния заказа 
				if ($order->current_state!=_PS_OS_CHEQUE_ ) {

					$this->setErrorCod(-31052, 'order_id');

				// Сверка суммы заказа 	
				} else  if ( ($order->getTotalProductsWithoutTaxes()*100) != $this->inputArray['params']['amount'] ) {

					$this->setErrorCod(-31001, 'order_id');

				// Запись транзакцию state=1
				} else {

					// Поиск транзакции по order_id
					$this->getLastTransactionForOrder($order_id);

					// Транзакция нет
					if (! $this->lastTransaction ) {

						$this->SaveOrder((
										$order->getTotalProductsWithoutTaxes()*100),
										$order_id, 
										$order_id,
										$this->inputArray['params']['time'],
										$this->timestamp2datetime($this->inputArray['params']['time'] ),
										$this->inputArray['params']['id'] 
										);

						$this->getLastTransactionForOrder($order_id);
						$this->responceType=2;

					// Существует транзакция
					} else {

						$this->setErrorCod(-31051, 'order_id');
					}
				}
			}
		}
	}

	public function payme_CheckTransaction() {

		// Поиск транзакции по id
		$transactionId=$this->inputArray['params']['id'];
		$this->getLastTransaction($transactionId);

		// Существует транзакция
		if ($this->lastTransaction) {

			$this->responceType=2;

		// Транзакция нет
		} else {

			$this->setErrorCod(-31003);
		}
	}

	public function payme_PerformTransaction() {

		// Поиск транзакции по id
		$transactionId=$this->inputArray['params']['id'];
		$this->getLastTransaction($transactionId);

		// Существует транзакция
		if ( $this->lastTransaction ) {

			// Поиск заказа по order_id
			$order = new Order($this->lastTransaction['order_id']);

			// Проверка состояние транзакцие
			if ($this->lastTransaction['state']==1) {

				$paycom_time_integer=$this->datetime2timestamp($this->lastTransaction['create_time']) *1000;
				$paycom_time_integer=$paycom_time_integer+43200000;

				// Проверка времени создания транзакции	
				if( $paycom_time_integer <= $this->timestamp2milliseconds(time()) ) {

					// Отменит reason = 4
					$updateData = array(
							 'cancel_time' => date('Y-m-d H:i:s'),
							 'reason'  	   => 4,
							 'state'  	   => -1
					);
					Db::getInstance()->update('payme_transactions', $updateData, 'transaction_id= '.(int)$this->lastTransaction['transaction_id'] );
					$order->setCurrentState(_PS_OS_CANCELED_);

				// Всё Ok
				} else {

					// Оплата
					$updateData = array(
							 'perform_time' => date('Y-m-d H:i:s'),
							 'state'  	    => 2
					);
					Db::getInstance()->update('payme_transactions', $updateData, 'transaction_id= '.(int)$this->lastTransaction['transaction_id'] );
					$order->setCurrentState(_PS_OS_PAYMENT_);
				}

				$this->responceType=2;
				$this->getLastTransaction($transactionId);

			// Cостояние не 1
			} else {

				// Проверка состояние транзакцие
				if ($this->lastTransaction['state']==2) {

					$this->responceType=2;

				// Cостояние не 2
				} else {

					$this->setErrorCod(-31008);
				}
			}

		// Транзакция нет
		} else {

			$this->setErrorCod(-31003);
		}
	}

	public function payme_CancelTransaction() {

		// Поиск транзакции по id
		$transactionId=$this->inputArray['params']['id'];
		$this->getLastTransaction($transactionId);

		// Существует транзакция
		if ($this->lastTransaction) {

			// Поиск заказа по order_id
			$order = new Order($this->lastTransaction['order_id']);

			$reasonCencel=filter_var($this->inputArray['params']['reason'], FILTER_SANITIZE_NUMBER_INT);

			// Проверка состояние транзакцие
			if ($this->lastTransaction['state']==1) {

				// Отменит state = -1
				$updateData = array(
							 'cancel_time' => date('Y-m-d H:i:s'),
							 'reason'  	   => $reasonCencel,
							 'state'  	   => -1
				);
				Db::getInstance()->update('payme_transactions', $updateData, 'transaction_id= '.(int)$this->lastTransaction['transaction_id'] );
				$order->setCurrentState(_PS_OS_CANCELED_);

			// Cостояние 2
			} else if ($this->lastTransaction['state']==2) {

				// Отменит state = -2
				$updateData = array(
							 'cancel_time' => date('Y-m-d H:i:s'),
							 'reason'  	   => $reasonCencel,
							 'state'  	   => -2
				);
				Db::getInstance()->update('payme_transactions', $updateData, 'transaction_id= '.(int)$this->lastTransaction['transaction_id'] );
				$order->setCurrentState(_PS_OS_CANCELED_);

			// Cостояние
			} else {

				// Ничего не надо делать
			}

			$this->responceType=2;
			$this->getLastTransaction($transactionId);

		// Транзакция нет
		} else {

			$this->setErrorCod(-31003);
		}
	}

	protected function getLastTransaction ($v_transaction_id ) {

		$sql = new DbQuery();
		$sql->select('*');
		$sql->from('payme_transactions', 'c');
		$sql->where("c.paycom_transaction_id = '".$v_transaction_id."'");
		
		$arrayOfArray=Db::getInstance()->executeS($sql);
		if($arrayOfArray)
		$this->lastTransaction=$arrayOfArray[0]; 
	}

	protected function getLastTransactionForOrder($v_order_id ) {

		$sql = new DbQuery();
		$sql->select('*');
		$sql->from('payme_transactions', 'c');
		$sql->where("c.order_id = ".$v_order_id);

		$arrayOfArray=Db::getInstance()->executeS($sql);
		if($arrayOfArray)
		$this->lastTransaction=$arrayOfArray[0]; 
	}

	public function payme_ChangePassword() {

		Configuration::updateValue('PAYME_SECRET_KEY_TEST', $this->inputArray['params']['password']);

		$this->responceType=3;
	}

	public function payme_GetStatement() {

		$query="SELECT 
					t.paycom_time,
					t.paycom_transaction_id,
					t.amount,
					t.order_id,
					t.create_time,
					t.perform_time,
					t.cancel_time,
					t.state,
					t.reason,
					t.receivers
				FROM  "._DB_PREFIX_."payme_transactions t 
				WHERE t.paycom_time_datetime>='".$this->timestamp2datetime($this->inputArray['params']['from'] )."' and 
					  t.paycom_time_datetime<='".$this->timestamp2datetime($this->inputArray['params']['to'] )  ."'
				ORDER BY t.paycom_time_datetime ";

		$rows = Db::getInstance()->ExecuteS($query);

		$responseArray = array();
		$transactions  = array();

		foreach ($rows as $row) {

			array_push($transactions,array(

				"id"		   => $row["paycom_transaction_id"],
				"time"		   => $row['paycom_time']  ,
				"amount"	   => $row["amount"],
				"account"	   => array("order_id" => $row["order_id"]),
				"create_time"  => (is_null($row['create_time']) ? null: $this->datetime2timestamp( $row['create_time']) ) ,
				"perform_time" => (is_null($row['perform_time'])? null: $this->datetime2timestamp( $row['perform_time'])) ,
				"cancel_time"  => (is_null($row['cancel_time']) ? null: $this->datetime2timestamp( $row['cancel_time']) ) ,
				"transaction"  => $row["order_id"],
				"state"		   => (int) $row['state'],
				"reason"	   => (is_null($row['reason'])?null:(int) $row['reason']) ,
				"receivers"	=> null
			)) ;
		}

		$responseArray['result'] = array( "transactions"=> $transactions );

		$this->responceType=4;
		$this->statement=$responseArray;
	}

	public function GenerateResponse() {

		if ($this->errorCod==0) {

			if ($this->responceType==1) {

				$responseArray = array('result'=>array( 'allow' => true )); 

			} else if ($this->responceType==2) {

				$responseArray = array(); 
				$responseArray['id']	 = $this->request_id;
				$responseArray['result'] = array(

					"create_time"	=> $this->datetime2timestamp($this->lastTransaction['create_time']) *1000,
					"perform_time"  => $this->datetime2timestamp($this->lastTransaction['perform_time'])*1000,
					"cancel_time"   => $this->datetime2timestamp($this->lastTransaction['cancel_time']) *1000,
					"transaction"	=>  $this->lastTransaction['cms_order_id'], //$this->order_id,
					"state"			=> (int)$this->lastTransaction['state'],
					"reason"		=> (is_null($this->lastTransaction['reason'])?null:(int)$this->lastTransaction['reason'])
				);

			} else if ($this->responceType==3) {

				$responseArray = array('result'=>array( 'success' => true ));

			} else if ($this->responceType==4) {
				
				$responseArray=$this->statement;
			}

		} else {

			$responseArray['id']	= $this->request_id;
			$responseArray['error'] = array (

				'code'   =>(int)$this->errorCod,
				'message'=> array(

					"ru"=>$this->getGenerateErrorText($this->errorCod,"ru"),
					"uz"=>$this->getGenerateErrorText($this->errorCod,"uz"),
					"en"=>$this->getGenerateErrorText($this->errorCod,"en"),
					"data" =>$this->errorInfo
				)
			);
		}

		return $responseArray;
	}

	public function SaveOrder($amount,$orderId,$cmsOrderId,$paycomTime,$paycomTimeDatetime,$paycomTransactionId ) {

		$sql = new DbQuery();
		$sql->select('*');
		$sql->from('payme_transactions', 'c'); 
		$sql->where("c.cms_order_id = '".(is_null( $cmsOrderId )? 0:$cmsOrderId )."'");
		$sql->where("c.order_id = ".(is_null( $orderId )? 0:$orderId) );
		$sql->where("c.amount = ".$amount );

		$transactionCnt = Db::getInstance()->executeS($sql);

		if (! $transactionCnt) {

			$insertData = array(
					 'create_time'  		 => date('Y-m-d H:i:s'),
					 'amount'  				 => $amount, 
					 'state'  				 => 1, 
					 'order_id'  			 => (is_null( $orderId )  ?  0:$orderId),
					 'cms_order_id' 		 => (is_null( $cmsOrderId )? 0:$cmsOrderId ),
					 'paycom_time'           => $paycomTime,
					 'paycom_time_datetime'  => $paycomTimeDatetime,
					 'paycom_transaction_id' => $paycomTransactionId
				  );

			Db::getInstance()->insert("payme_transactions", $insertData);
		}
	}

	public function getGenerateErrorText($codeOfError,$codOfLang){

		$listOfError=array ('-31001' => array(
										  "ru"=>'Неверная сумма.',
										  "uz"=>'Неверная сумма.',
										  "en"=>'Неверная сумма.'
										),
							'-31003' => array(
										  "ru"=>'Транзакция не найдена.',
										  "uz"=>'Транзакция не найдена.',
										  "en"=>'Транзакция не найдена.'
										),
							'-31008' => array(
										  "ru"=>'Невозможно выполнить операцию.',
										  "uz"=>'Невозможно выполнить операцию.',
										  "en"=>'Невозможно выполнить операцию.'
										),
							'-31050' => array(
										  "ru"=>'Заказ не найден.',
										  "uz"=>'Заказ не найден.',
										  "en"=>'Заказ не найден.'
										),
							'-31051' => array(
										  "ru"=>'Существует транзакция.',
										  "uz"=>'Существует транзакция.',
										  "en"=>'Существует транзакция.'
										),
							'-31052' => array(
											"ru"=>'Заказ уже оплачен.',
											"uz"=>'Заказ уже оплачен.',
											"en"=>'Заказ уже оплачен.'
										),
										
							'-32300' => array(
										  "ru"=>'Ошибка возникает если метод запроса не POST.',
										  "uz"=>'Ошибка возникает если метод запроса не POST.',
										  "en"=>'Ошибка возникает если метод запроса не POST.'
										),
							'-32600' => array(
										  "ru"=>'Отсутствуют обязательные поля в RPC-запросе или тип полей не соответствует спецификации',
										  "uz"=>'Отсутствуют обязательные поля в RPC-запросе или тип полей не соответствует спецификации',
										  "en"=>'Отсутствуют обязательные поля в RPC-запросе или тип полей не соответствует спецификации'
										),
							'-32700' => array(
										  "ru"=>'Ошибка парсинга JSON.',
										  "uz"=>'Ошибка парсинга JSON.',
										  "en"=>'Ошибка парсинга JSON.'
										),
							'-32600' => array(
										  "ru"=>'Отсутствуют обязательные поля в RPC-запросе или тип полей не соответствует спецификации.',
										  "uz"=>'Отсутствуют обязательные поля в RPC-запросе или тип полей не соответствует спецификации.',
										  "en"=>'Отсутствуют обязательные поля в RPC-запросе или тип полей не соответствует спецификации.'
										),
							'-32601' => array(
										  "ru"=>'Запрашиваемый метод не найден. В RPC-запросе имя запрашиваемого метода содержится в поле data.',
										  "uz"=>'Запрашиваемый метод не найден. В RPC-запросе имя запрашиваемого метода содержится в поле data.',
										  "en"=>'Запрашиваемый метод не найден. В RPC-запросе имя запрашиваемого метода содержится в поле data.'
										),
							'-32504' => array(
										  "ru"=>'Недостаточно привилегий для выполнения метода.',
										  "uz"=>'Недостаточно привилегий для выполнения метода.',
										  "en"=>'Недостаточно привилегий для выполнения метода.'
										),
							'-32400' => array(
										  "ru"=>'Системная (внутренняя ошибка). Ошибку следует использовать в случае системных сбоев: отказа базы данных, отказа файловой системы, неопределенного поведения и т.д.',
										  "uz"=>'Системная (внутренняя ошибка). Ошибку следует использовать в случае системных сбоев: отказа базы данных, отказа файловой системы, неопределенного поведения и т.д.',
										  "en"=>'Системная (внутренняя ошибка). Ошибку следует использовать в случае системных сбоев: отказа базы данных, отказа файловой системы, неопределенного поведения и т.д.'
										)
							);

		return $listOfError[$codeOfError][$codOfLang];
	}

	public function timestamp2datetime($timestamp){
		// if as milliseconds, convert to seconds
		if (strlen((string)$timestamp) == 13) {
			$timestamp = $this->timestamp2seconds($timestamp);
		}

		// convert to datetime string
		return date('Y-m-d H:i:s', $timestamp);
	}

	public function timestamp2seconds($timestamp) {
		// is it already as seconds
		if (strlen((string)$timestamp) == 10) {
			return $timestamp;
		}

		return floor(1 * $timestamp / 1000);
	}

	public function timestamp2milliseconds($timestamp) {
		// is it already as milliseconds
		if (strlen((string)$timestamp) == 13) {
			return $timestamp;
		}

		return $timestamp * 1000;
	}

	public function datetime2timestamp($datetime) {

		if ($datetime) {

			return strtotime($datetime);
		}

		return $datetime;
	}

	public function setErrorCod($cod_,$info=null) {

		$this->errorCod=$cod_;

		if ($info!=null) $this->errorInfo=$info;

		if ($cod_!=0) {

			$this->result=false;
		}
	}

	public function getInputArray() {

		return $this->inputArray;
	}

	public function setInputArray($i_Array) {

		$this->inputArray = json_decode($i_Array, true); 
	}
}
