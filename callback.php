<?php
$application->connectDb();
$application->initSession();
$application->initPlugins();

ob_start();

try {
	file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/uploads/logs/yookassa.log', date('Y.m.d H:i:s')." request ".var_export($_REQUEST)." "."\n", FILE_APPEND);
    $id = $_REQUEST['id'];
	$event = $_REQUEST['event'];
	file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/uploads/logs/yookassa.log', date('Y.m.d H:i:s')." id ".$id." "."\n", FILE_APPEND);
	file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/uploads/logs/yookassa.log', date('Y.m.d H:i:s')." event ".$event." "."\n", FILE_APPEND);
	if(isset($id)){
		$oid = self::getDbConnection()->fetchColumn('SELECT order_id FROM sale_payment_transactions WHERE transaction_id=?',[$id]);
	}
	file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/uploads/logs/yookassa.log', date('Y.m.d H:i:s')." oid ".$oid." "."\n", FILE_APPEND);
	$order = \Sale\Order::getById( $oid );
	$gateway = $order->getPaymentGateway();
    
    $oid = $gateway->getOrderByTransaction( $id );
    if ($oid != $order->id) {
        throw new \Exception('Order check failed');
    }
		
	// Операция подтверждена
	if  (isset($event) && $event == 'payment.succeeded') {
		$order->paymentSuccess();
		try {
			$gateway->sendReceiptSell();
		} catch (\Exception $e) {}
	}
	
	header("HTTP/1.1 200 OK");
	print 'OK';		
	
}
catch (\Exception $e) {
	
	header( "HTTP/1.1 500 ".trim(preg_replace('/\s+/', ' ', $e->getMessage())) );
	print $e->getMessage();
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/uploads/logs/yookassa.log', date('Y.m.d H:i:s')." ".$_SERVER['QUERY_STRING']." ".$e->getMessage()."\n", FILE_APPEND);
	 
}

$data = ob_get_contents();
ob_end_flush();
//file_put_contents(__DIR__.'/log'.time().'.txt', $data);