<?php
use YooCheckout\Model\Notification\NotificationSucceeded;
use YooCheckout\Model\Notification\NotificationRefundSucceeded;
use YooCheckout\Model\Notification\NotificationWaitingForCapture;
use YooCheckout\Model\NotificationEventType;

$application->connectDb();
$application->initSession();
$application->initPlugins();

try {
    
    $source = file_get_contents('php://input');	
    $requestBody = json_decode($source, true);
        
    if ($requestBody['event'] === NotificationEventType::PAYMENT_SUCCEEDED) {
        
        // успешный платеж
        
        //file_put_contents(__DIR__.'/log_payment_source'.time().'.txt', $source);
        
        $notification = new NotificationSucceeded($requestBody);
        $payment = $notification->getObject();  

        if (!isset( $payment->metadata['order_id'] )) {
            throw new \Exception('order_id not provided in request body');
        }    
        
        $order = \Sale\Order::getById( $payment->metadata['order_id'] );
        $gateway = $order->getPaymentGateway();
        
        $oid = $gateway->getOrderByTransaction( $payment->id );
        if ($oid != $order->id) {
            throw new \Exception('Order check failed');
        } 

        if ($gateway->params['orderBundle'] && $gateway->params['receiptAfterPayment']) {
            
            $client = new \SalePaymentYoo\Client();
            $client->setAuth($gateway->params['shopId'], $gateway->params['shopSecret']);        
            
            $resp = $client->getReceipts([
                'payment_id' => $payment->id,
            ]);

            if (count($resp->getItems()) == 0) {
            
                $receipt = $gateway->getReciept();
                $receipt['payment_id'] = $payment->id;
                
                $resp = $client->createReceiptNew(
                    $receipt,
                    uniqid('', true)
                );
            
            }

            //file_put_contents(__DIR__.'/log_payment_receipt'.time().'.txt', var_export($receipt, true));
        }

        $order->paymentSuccess();
        
    }
    elseif ($requestBody['event'] === NotificationEventType::REFUND_SUCCEEDED) {
        
        // успешный возврат
        
        file_put_contents(__DIR__.'/log_refund_source'.time().'.txt', $source);
        
        $notification = new NotificationRefundSucceeded($requestBody);
        $refund = $notification->getObject();
        
        $oid = $application->getDbConnection()->fetchColumn('SELECT order_id FROM sale_payment_transactions WHERE transaction_id=?',[$refund->getPaymentId()]);
        
        if (!$oid) {
            throw new \Exception('Transaction '.$refund->getPaymentId().' not found');
        }
        
        $order = \Sale\Order::getById( $oid );
        $gateway = $order->getPaymentGateway();
        
        if ($gateway->params['orderBundle'] && $gateway->params['receiptAfterPayment']) {
            
            $client = new \SalePaymentYoo\Client();
            $client->setAuth($gateway->params['shopId'], $gateway->params['shopSecret']);  

            $receipt = $gateway->getReciept();
            $receipt['refund_id'] = $refund->id;
            $receipt['type'] = 'refund';  
            
                        
            if ($refund->getAmount()->getValue() != $order->getTotal()) {
                // Частичный возврат. Формируем чек на сумму возврата.
                $receipt['settlements'] = [
                    [
                        'type' => 'cashless',
                        'amount' => [
                            'value' => $refund->getAmount()->getValue(),
                            'currency' => $refund->getAmount()->getCurrency(),
                        ]
                    ]		   
                ];  
                $receipt['items'] = [
                    [
                        'description' => 'Частичный возврат',
                        'quantity'    => 1,
                        "amount" => [
                            'value' => $refund->getAmount()->getValue(),
                            'currency' => $refund->getAmount()->getCurrency(),
                        ],   
                        "vat_code" => $gateway->params['vat_code'],
                        "payment_subject" => $gateway->params['paymentSubjectType'],
                        "payment_mode" => $gateway->params['paymentMethodType'],
                    ]               
                ];
            }
            
            $resp = $client->createReceiptNew(
                $receipt,
                uniqid('', true)
            );

            file_put_contents(__DIR__.'/log_refund_receipt'.time().'.txt', var_export($receipt, true));
        }        
        
        $order->setPaid(\Sale\Order::PAY_REFUND)->save();
        
    }
    elseif ($requestBody['event'] === NotificationEventType::PAYMENT_CANCELED) {
        //file_put_contents(__DIR__.'/log_cancel_source'.time().'.txt', $source);
    }
    else {
        throw new \Exception('Event not permitted');
    }  

    
	header("HTTP/1.1 200 OK");
	print 'OK';		    
    
}
catch (\Exception $e) {
	
	header("HTTP/1.1 500 ".$e->getMessage());
	print $e->getMessage();
	
	file_put_contents(__DIR__.'/log_error'.time().'.txt', $e->getMessage()."\n\n\n".'In file '.$e->getFile().' on line: '.$e->getLine()."\n\nStack trace:\n".$exception->getTraceAsString()."\n\n\n".$source);
	
}