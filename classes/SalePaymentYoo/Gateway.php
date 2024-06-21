<?php
namespace SalePaymentYoo;
use \YooKassa\Client;
class Gateway extends \Sale\PaymentGateway\GatewayAtol {


    public static function getInfo2()
	{    
		return [
			'name'        => 'Юкасса',
			'description' => '',
			'icon'        => '/plugins/sale-payment-sber/images/icon.png',
			'params' => [		
				[
					'name'       => 'shopID',
					'xtype'      => 'textfield',
					'fieldLabel' => 'ID магазина, полученный при подключении',
					'allowBlank' => false,
				],
                [
					'name'       => 'secret',
					'xtype'      => 'textfield',
					'fieldLabel' => 'Секретный ключ, полученный при подключении',
					'allowBlank' => false,
				],                             
                [
					'xtype'      => 'displayfield',
					'fieldLabel' => 'URL-адрес для callback уведомлений',
					'value'      => '//'.$_SERVER['HTTP_HOST'].'/cms/plugins/sale-payment-yoo/callback.php'
				],
                [
					'name'       => 'returnURL',
					'xtype'      => 'textfield',
					'fieldLabel' => 'Страница после совершения платежа',
					'allowBlank' => false,
				],	
                [
					'name'       => 'orderBundle',
					'xtype'      => 'checkbox',
					'fieldLabel' => 'Передача корзины товаров (кассовый чек 54-ФЗ)',
				],
                [
                    'name'       => 'paymentObject',
                    'xtype'      => 'combobox',
                    'fieldLabel' => 'Тип оплачиваемой позиции',
                    'value'      => 1,
                    'store'      => [
                        ['commodity', 'товар'],
                        ['excise', 'подакцизный товар'],
                        ['job', 'работа'],
                        ['service', 'услуга'],
                        ['payment', 'платеж'],
                        ['casino', 'Платеж казино'],
                        ['gambling_bet', 'ставка азартной игры'],
                        ['gambling_prize', 'выигрыш азартной игры'],
                        ['lottery', 'лотерейный билет'],
                        ['lottery_prize', 'выигрыш лотереи'],
                        ['intellectual_activity', 'предоставление РИД'],
                        ['agent_commission', 'агентское вознаграждение'],
                        ['composite', 'составной предмет расчёта'],
                        ['another', 'иной предмет расчёта'],
                    ],
                ],
                [
                    "xtype"          => 'checkbox',
                    "name"           => 'test_mode',
                    "boxLabel"       => 'Тестовый режим',
                    "inputValue"     => 1,
                    "uncheckeDvalue" => 0
                ], 
                [
                    'name'       => 'paymentMethod',
                    'xtype'      => 'combobox',
                    'fieldLabel' => 'Тип оплаты',
                    'value'      => 1,
                    'store'      => [
                        ['full_prepayment', 'полная предварительная оплата до момента передачи предмета расчёта'],
                        ['partial_prepayment', 'частичная предварительная оплата до момента передачи предмета расчёта'],
                        ['advance', 'аванс'],
                        ['full_payment', 'полная оплата в момент передачи предмета расчёта'],
                        ['partial_payment', 'частичная оплата предмета расчёта в момент его передачи с последующей оплатой в кредит'],
                        ['credit', 'передача предмета расчёта без его оплаты в момент его передачи с последующей оплатой в кредит'],
                        ['credit_payment', 'оплата предмета расчёта после его передачи с оплатой в кредит'],
                    ],
                ],                
				[
					'name'       => 'taxSystem',
					'xtype'      => 'combobox',
					'fieldLabel' => 'Система налогообложения',
					'value'      => 0,
					'store'      => [
						[1, 'общая СН'],
						[2, 'упрощенная СН (доходы)'],
						[3, 'упрощенная СН (доходы минус расходы)'],
						[4, 'единый налог на вмененный доход'],
						[5, 'единый сельскохозяйственный налог'],
						[6, 'патентная СН'],
					],
				], 
				[
					'name'       => 'taxType',
					'xtype'      => 'combobox',
					'fieldLabel' => 'Ставка НДС для товаров',
					'value'      => 0,
					'store'      => [
						[1, 'без НДС'],
						[2, 'НДС по ставке 0%'],
						[3, 'НДС чека по ставке 10%'],
                        [4, 'НДС чека по ставке 20%'],
						[5, 'НДС чека по расчетной ставке 10/110'],
                        [6, 'НДС чека по расчётной ставке 20/120'],
					],
				],  						
				
			]			
		];  
    }
    public function getPaymentId(){

        $orderId = $this->order->id;
        return  self::getDbConnection()->fetchColumn('SELECT transaction_id FROM sale_payment_transactions WHERE order_id=?',[$orderId]);
    }
    public function cancel( )
    {
        $client = new \YooKassa\Client();
        $client->setAuth($this->params['shopID'], $this->params['secret']);
        $idempotenceKey = uniqid('', true);
        $paymentId = $this->getPaymentId();
        try {
            $response = $client->cancelPayment($paymentId, $idempotenceKey);
            if (isset($response['status']) && $response['status'] == "canceled") {
                $this->order->setPaid(\Sale\Order::PAY_CANCEL)->save();		
            }
            else {
                throw new \Exception("Платёж ".$paymentId." не отменён");
            } 
        } catch (\Exception $e) {
            $response = $e;
        }
    }

	public function pay( $return = '', $payParams = [] )
	{
        header('Location: '.$this->getPayUrl( $return ));
        die();          
	}
    
	public function getStatus() {
		$params = [
			'shopID'    => $this->params['shopID'],
            'secret'    => $this->params['secret']
		]; 
        $client = new \YooKassa\Client();
        $client->setAuth($this->params['shopID'], $this->params['secret']);
        $paymentId = $this->getPaymentId();
        try {
            $response = $client->getPaymentInfo($paymentId);
        } catch (\Exception $e) {
            $response = $e;
        }			
		return $response;
	}  

    public function getPayUrl( $return = '', $payParams = [] )
	{
        if (!$return) $return = \Cetera\Application::getInstance()->getServer()->getFullUrl();

		$params = [
			'shopID'    => $this->params['shopID'],
            'secret'    => $this->params['secret'],
            'orderNumber' => $this->order->id,
            'amount'      => $this->order->getTotal(),
            'returnURL'    => $this->params['returnURL'],
            'additionalOfdParams' => []
		]; 
        
        
        $phone = preg_replace('/\D/','',$this->order->getPhone());
        $client = new \YooKassa\Client();
        $client->setAuth($this->params['shopID'], $this->params['secret']);
        $idempotenceKey = uniqid('', true);
        try {
            $idempotenceKey = uniqid('', true);
            $payment['amount']['value'] = (float)$params['amount'];
            $payment['amount']['currency'] = 'RUB';
            $payment['confirmation']['type'] = 'redirect';
            $payment['confirmation']['locale'] = 'ru_RU';
            $payment['confirmation']['return_url'] = $return;
            $payment['capture'] = true;
            $payment['description'] = 'Заказ '.$params['orderNumber'];
            $payment['metadata']['orderNumber'] = $params['orderNumber'];
            $payment['receipt']['items'] = $this->getItems();
            $payment['receipt']['tax_system_code'] = $this->params['taxSystem'];
            if ($this->order->getEmail()) {
                $payment['receipt']['customer']['email'] = $this->order->getEmail();
            }
            if ($phone) {
                $payment['receipt']['customer']['phone']  = $phone;
            } 
            if ($this->order->getName()) {
                $payment['receipt']['customer']['full_name']  = $this->order->getName();
            }
            $response = $client->createPayment($payment,$idempotenceKey);
            $status= $response->getStatus();
            $confirmationUrl = $response->getConfirmation()->getConfirmationUrl();
            if (isset($status) and ($status != "canceled") and isset($confirmationUrl)){
                $this->saveTransaction($response->getId(), $payment);
                return $confirmationUrl;
            }
            else {
                throw new \Exception('Что-то пошло не так');
            }  
        } catch (\Exception $e) {
            $response = $e;
        }			
	     
    }
    
	public function getItems()
	{
        $items = [];
        foreach ($this->order->getProducts() as $p) {
            $items[] = [
                'description' => $p['name'],
                'quantity' => intval($p['quantity']),
                'amount' => [
                    'value' => $p['price'],
                    'currency' => 'RUB'
                ],
                'payment_mode' =>  $this->params['paymentMethod'],
                'measure' => 'piece',
                'payment_subject' => $this->params['paymentObject'], 
                'country_of_origin_code' => 'RU',
                'vat_code' => $this->params['taxType'], 
                'itemCode' => $p['id'],
            ];
        }
        return $items;
    }        
        
    public static function isRefundAllowed() {
        return true;
    }
    
    private function getOrderId() {
        $data = $this->getTransactions();
        
        if (!count($data)) {
            throw new \Exception('Нет информации о платеже');
        }
        $orderId = null;
        foreach ($data as $d) {
            if (isset($d['data']['orderId'])) {
                $orderId = $d['data']['orderId'];
                break;
            }
            if (isset($d['data']['mdOrder'])) {
                $orderId = $d['data']['mdOrder'];
                break;
            }            
        }
        if (!$orderId) {
            throw new \Exception('Не получилось определить параметры платежа');
        }

        return $orderId;
    }
    
    public function refund( $items = null ) {
              
		$params = [
			'shopID'    => $this->params['shopID'],
            'secret'    => $this->params['secret'],
            'orderId'     => $this->getOrderId(),
            'amount'      => $this->order->getTotal(),
		];
        $client = new \YooKassa\Client();
        $client->setAuth($this->params['shopID'], $this->params['secret']);
        $idempotenceKey = uniqid('', true);

        if ($items !== null) {
            $i = [];
            $amount = 0;
            foreach ($items as $key => $item) {
                if ($item['quantity_refund'] <= 0) continue;
                $price = $item['price'];
                $amount += intval($item['quantity_refund']) * $price;
                $i[] = [
                    'positionId' => $key+1,
                    'name'       => $item['name'],
                    'quantity' => [
                        'value'   => intval($item['quantity_refund']),
                        'measure' => 'шт.'
                    ],
                    'itemAmount' => intval($item['quantity_refund']) * $price,  
                    'itemCode'   => $item['id'], 
                    'itemPrice'  => $price, 
                ];
            }
        }


        try {
            $refund['payment_id'] = $this->getPaymentId();
            $refund['amount']['value'] = $amount;
            $refund['amount']['currency'] = 'RUB';
            $response = $client->createRefund($refund,$idempotenceKey);
            $status=$response->getStatus();

            if(isset($status) && $status == "succeeded" ){
                $res = $this->sendReceiptRefund( $items );
                return;	
            }
            else {
                throw new \Exception('Ошибка в процессе возврата');
            }   
        } catch (\Exception $e) {
            $response = $e;
        }    
    } 
    
}