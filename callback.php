<?php
$application->connectDb();
$application->initSession();
$application->initPlugins();

ob_start();

try {
    
    $out_summ = $_REQUEST["OutSum"];
    $inv_id = $_REQUEST["InvId"];
    $crc = $_REQUEST["SignatureValue"];
    $order = \Sale\Order::getById($inv_id);

    $gateway = $order->getPaymentGateway();

    if (!$gateway->checkIfTransactionHasAlreadyBeenProcessed($crc)) {
        $merchant = $gateway->params;
        $password = !$merchant["test_mode"] ? $merchant["shop_password2"] : $merchant["test_shop_password2"];
        $crc = strtoupper($crc);
        $my_crc = strtoupper(md5("$out_summ:$inv_id:$password")); // формируем новый ключ

        if ($my_crc != $crc) // проверка корректности подписи
        {
            throw new \Exception("bad sign");
        }

        // признак успешно проведенной операции
        // success
        header("HTTP/1.1 200 OK");
        echo "OK$inv_id\n";

        $order->paymentSuccess();
        $gateway->saveTransaction($crc, $_REQUEST);
    }
    else {
        throw new \Exception("bad sign");
    }
	
}
catch (\Exception $e) {
	
	header( "HTTP/1.1 500 ".trim(preg_replace('/\s+/', ' ', $e->getMessage())) );
	print $e->getMessage();
	 
}

$data = ob_get_contents();
ob_end_flush();
//file_put_contents(__DIR__.'/log'.time().'.txt', $data);