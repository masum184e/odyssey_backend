<?php

header('Content-Type: application/json');
require './../database_connection.php';
require './../config.php';

$request = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(!isset($request['amount'])){
        echo json_encode(['status' => 'false', 'message' => 'Amount is required.']);
        exit; 
    }

    $post_data = array();
    $post_data['store_id'] = "odyss67501cd640f28";
    $post_data['store_passwd'] = "odyss67501cd640f28@ssl";
    $post_data['total_amount'] = $request['amount'];
    $post_data['currency'] = "BDT";
    $post_data['tran_id'] = "SSLCZ_TEST_" . uniqid();
    $post_data['success_url'] = "http://" . SERVER_IP . "/odyssey_backend/pay/new_sslcz_gw/success.php";
    $post_data['fail_url'] = "http://" . SERVER_IP . "/odyssey_backend/pay/new_sslcz_gw/fail.php";
    $post_data['cancel_url'] = "http://" . SERVER_IP . "/pay/odyssey_backendnew_sslcz_gw/cancel.php";

    $post_data['emi_option'] = '1';
    $post_data['cus_name'] = 'Odyssey User';
    $post_data['cus_email'] = 'admin@odyssey.com';
    $post_data['cus_phone'] = '+8801400095352';
    $post_data['cus_add1'] = "Dhaka";
    $post_data['cus_city'] = "Dhaka";
    $post_data['cus_country'] = "Bangladesh";
    $post_data['shipping_method'] = "NO";
    $post_data['product_name'] = "Odyssey Service";
    $post_data['product_category'] = "Rent";
    $post_data['product_profile'] = "general";

    $direct_api_url = "https://sandbox.sslcommerz.com/gwprocess/v4/api.php";

    $handle = curl_init();
    curl_setopt($handle, CURLOPT_URL, $direct_api_url);
    curl_setopt($handle, CURLOPT_TIMEOUT, 30);
    curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($handle, CURLOPT_POST, 1);
    curl_setopt($handle, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false); // KEEP IT FALSE IF YOU RUN FROM LOCAL PC

    $content = curl_exec($handle);

    $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

    if ($code == 200 && !(curl_errno($handle))) {
        curl_close($handle);
        $sslcommerzResponse = $content;
    } else {
        curl_close($handle);
        echo json_encode(["status" => "false", "message" => "Failed to Connect Payment Gateway"]);
        exit;
    }

    // PARSE THE JSON RESPONSE
    $sslcz = json_decode($sslcommerzResponse, true);
    if (isset($sslcz['GatewayPageURL']) && $sslcz['GatewayPageURL'] != "") {
        // THERE ARE MANY WAYS TO REDIRECT - Javascript, Meta Tag or Php Header Redirect or Other
        echo json_encode([
            "status" => "true",
            "message" => "Payment Successfully Completed",
            "url" => $sslcz['GatewayPageURL']
        ]);
        
        // echo "<meta http-equiv='refresh' content='0;url=" . $sslcz['GatewayPageURL'] . "'>";
        // header("Location: ". $sslcz['GatewayPageURL']);
        exit;
    } else {
    echo json_encode(["status" => "false", "message" => "JSON Data Parsing error!!"]);
    }
} else {
    echo json_encode(["status" => "false", "message" => "Invalid Request"]);
}
?>
