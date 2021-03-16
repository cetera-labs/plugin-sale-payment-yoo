<?php
namespace SalePaymentYoo;

use YooCheckout\Client;

class Gateway extends \Sale\PaymentGateway\GatewayAbstract {
		
	public static function getInfo()
	{
		$t = \Cetera\Application::getInstance()->getTranslator();
		
		return [
			'name'        => 'Ю-Касса',
			'description' => '',
			'icon'        => '/plugins/sale-payment-yandex/images/yandex.png',
			'params' => [	
				[
					'name'       => 'shopId',
					'xtype'      => 'textfield',
					'fieldLabel' => $t->_('Идентификатор магазина *'),
					'allowBlank' => false,
				],	
				[
					'name'       => 'shopSecret',
					'xtype'      => 'textfield',
					'fieldLabel' => $t->_('Секретный ключ *'),
					'allowBlank' => false,
				],                
				[
					'name'       => 'paymentType',
					'xtype'      => 'textfield',
					'fieldLabel' => $t->_('Способ оплаты'),
					'xtype'      => 'combobox',
					'value'      => '',
					'store'      => [
						['',  $t->_('выбор на стороне Яндекс.Кассы')],
						['yandex_money',$t->_('оплата из кошелька в Яндекс.Деньгах')],
						['bank_card',$t->_('оплата с произвольной банковской карты')],
					],
				],
				[
					'name'       => 'orderBundle',
					'xtype'      => 'checkbox',
					'fieldLabel' => 'Передача корзины товаров (кассовый чек 54-ФЗ)',
				],
				[
					'name'       => 'receiptAfterPayment',
					'xtype'      => 'checkbox',
					'fieldLabel' => 'Cценарий "Сначала платеж, потом чек"',
				],				
				[
					'name'       => 'tax_system_code',
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
					'name'       => 'vat_code',
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
                [
                    'name' => 'paymentSubjectType',
                    'fieldLabel' => $t->_('Признак предмета расчёта'),
                    'xtype' => 'combobox',
                    'value' => '',
                    'store' => [
                        ['commodity', $t->_('Товар')],
                        ['excise', $t->_('Подакцизный товар')],
                        ['job', $t->_('Работа')],
                        ['service', $t->_('Услуга')],
                        ['gambling_bet', $t->_('Ставка в азартной игре')],
                        ['gambling_prize', $t->_('Выигрыш в азартной игре')],
                        ['lottery', $t->_('Лотерейный билет')],
                        ['lottery_prize', $t->_('Выигрыш в лотерею')],
                        ['intellectual_activity', $t->_('Результаты интеллектуальной деятельности')],
                        ['payment', $t->_('Платеж')],
                        ['agent_commission', $t->_('Агентское вознаграждение')],
                        ['composite', $t->_('Несколько вариантов')],
                        ['another', $t->_('Другое')],
                    ],
                    'allowBlank' => false,
                ],
                [
                    'name' => 'paymentMethodType',
                    'fieldLabel' => $t->_('Признак способа расчёта'),
                    'xtype' => 'combobox',
                    'value' => '',
                    'store' => [
                        ['full_prepayment', $t->_('Полная предоплата')],
                        ['partial_prepayment', $t->_('Частичная предоплата')],
                        ['advance', $t->_('Аванс')],
                        ['full_payment', $t->_('Полный расчет')],
                        ['partial_payment', $t->_(' частичный расчет и кредит')],
                        ['credit', $t->_('Кредит')],
                        ['credit_payment', $t->_('Выплата по кредиту')],
                    ],
                    'allowBlank' => false,
                ],				
			]			
		];
	}
	
	public function pay( $return = '' )
	{
        header('Location: '.$this->getPayUrl( $return ));
        die();          
	}

    public function getPayUrl( $return = '' ) {
        $paymentData = $this->getPaymentData( $return );
        
        $client = new Client();
        $client->setAuth($this->params['shopId'], $this->params['shopSecret']);
        $response = $client->createPayment(
            $paymentData,
            uniqid('', true)
        );
        
        if(isset($response->status) and ($response->status != "canceled") and isset($response->confirmation->confirmation_url) and $response->confirmation->confirmation_url) {
            $this->saveTransaction($response->id, $response);
            return $response->confirmation->confirmation_url;         
        }  
        else {
            throw new \Exception('Что-то пошло не так');
        }        
    }
	
	public function getPaymentData( $return = '' )
	{
		if (!$return) $return = \Cetera\Application::getInstance()->getServer()->getFullUrl();
		
        $paymentData = [
            'amount' => [
                'value' => $this->order->getTotal(),
                'currency' => $this->order->getCurrency()->code,
            ],              
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => $return,
            ],
            'capture' => true,
            'description' => 'Заказ №'.$this->order->id,    
            'metadata' => [
                'order_id' => $this->order->id,
            ]           
        ];
        
        if ($this->params['orderBundle'] && !$this->params['receiptAfterPayment']) {
            $paymentData['receipt'] = $this->getReciept();
        }        

        if ($this->params['paymentType']) {
            $paymentData['payment_method_data'] = [
                'type' => $this->params['paymentType'],
            ];
        }

		return $paymentData;
	}
	
	public function getItems()
	{
		$items = [];
		
		$i = 1;
		foreach ($this->order->getProducts() as $p) {
			$items[] = [
				'description' => $p['name'],
				'quantity'    => intval($p['quantity']),
				"amount" => [
					"value" => $p['price'],
					"currency" => $this->order->getCurrency()->code
				],   
				"vat_code" => $this->params['vat_code'],
				"payment_subject" => $this->params['paymentSubjectType'],
				"payment_mode" => $this->params['paymentMethodType'],
			];
		}

		return $items;		
	}		
	
	public function getReciept()
	{
		$items = $this->getItems();

        $customer = [
            "full_name" => $this->order->getName()
        ];
        
        if ($this->order->getEmail()) {
            $customer['email'] = $this->order->getEmail();
        }
        $phone = preg_replace('/\D/','',$this->order->getPhone());
        if ($phone) {
            $customer['phone'] = $phone;
        }

		$receipt = [
			'send' => true,
			'type' => 'payment',
			"customer" => $customer,
		    "tax_system_code" => $this->params['tax_system_code'],
		    "items" => $items,
			'settlements' => [
				[
				    'type' => 'cashless',
				    'amount' => [
						'value' => $this->order->getTotal(),
						'currency' => $this->order->getCurrency()->code,
				    ]
				]
			]		   
		];

		return $receipt;
	}
}