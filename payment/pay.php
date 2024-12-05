<?php

header('Content-Type: application/json');
require './../database_connection.php';
require './../config.php';
require './../middleware/user_authentication.php';

$user = userAuthentication();
$email = $user->email;
$role = $user->role;

if ($_SERVER["REQUEST_METHOD"] !== "POST"){
    echo json_encode(["status" => "false", "message" => "Invalid request."]);
    exit;
}

$inputData = json_decode(file_get_contents('php://input'), true);
if (!$inputData) {
    echo json_encode(["status" => "false", "message" => "Invalid input."]);
    exit;
}

if ($role=="driver") {
    echo json_encode(["status" => "false", "message" => "Unauthorized access."]);
    exit;
}

if(!isset($inputData['booking_id'])){
    echo json_encode(["status" => "false", "message" => "Booking Id Required"]);
    exit;
}

$query = "SELECT 
    b.booking_id,
    b.driver_id,
    b.pickup_location,
    b.dropoff_location,
    b.pickup_datetime,
    b.dropoff_datetime,
    d.driver_id,
    v.vehicle_id,
    p.type,
    p.base_price,
    p.per_km_cost,
    p.per_hour_cost
FROM
    bookings b
JOIN
    drivers d ON b.driver_id = d.driver_id
JOIN
    vehicles v ON d.driver_id = v.driver_id
JOIN
    packages p ON v.type = p.type
WHERE
    b.booking_id = ?;";

$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    echo json_encode(["status" => "false", "message" => "Failed to prepare the query."]);
    exit;
}

mysqli_stmt_bind_param($stmt, "s", $inputData['booking_id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    $bookingData = mysqli_fetch_assoc($result);

    $pickupLocation = $bookingData['pickup_location'];
    $dropoffLocation = $bookingData['dropoff_location'];

    preg_match('/lat\/lng: \(([^,]+),([^)]+)\)/', $pickupLocation, $pickupMatches);
    if (!$pickupMatches) {
        echo json_encode(["status" => "false", "message" => "Invalid pickup location format."]);
        exit;
    }
    $pickupLatitude = (float)$pickupMatches[1];
    $pickupLongitude = (float)$pickupMatches[2];

    preg_match('/lat\/lng: \(([^,]+),([^)]+)\)/', $dropoffLocation, $dropoffMatches);
    if (!$dropoffMatches) {
        echo json_encode(["status" => "false", "message" => "Invalid dropoff location format."]);
        exit;
    }
    $dropoffLatitude = (float)$dropoffMatches[1];
    $dropoffLongitude = (float)$dropoffMatches[2];

    $earthRadius = 6371;
    $latFrom = deg2rad($pickupLatitude);
    $lonFrom = deg2rad($pickupLongitude);
    $latTo = deg2rad($dropoffLatitude);
    $lonTo = deg2rad($dropoffLongitude);

    $latDiff = $latTo - $latFrom;
    $lonDiff = $lonTo - $lonFrom;

    $a = sin($latDiff / 2) * sin($latDiff / 2) +
         cos($latFrom) * cos($latTo) *
         sin($lonDiff / 2) * sin($lonDiff / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    $distance = $earthRadius * $c;

    $pickup_time = strtotime($bookingData['pickup_datetime']);
    $dropoff_time = strtotime($bookingData['dropoff_datetime']);
    $duration_in_seconds = $dropoff_time - $pickup_time;
    $duration = $duration_in_seconds / 3600;

    $basePrice = $bookingData['base_price'];
    $perKmCost = $bookingData['per_km_cost'];
    $perHrCost = $bookingData['per_hour_cost'];

    $totalPrice = $basePrice + ($distance * $perKmCost) + ($duration * $perHrCost);

    $post_data = array();
    $post_data['store_id'] = "odyss67501cd640f28";
    $post_data['store_passwd'] = "odyss67501cd640f28@ssl";
    $post_data['total_amount'] = $totalPrice;
    $post_data['currency'] = "BDT";
    $post_data['tran_id'] = "SSLCZ_TEST_" . uniqid();
    $post_data['success_url'] = "http://" . SERVER_IP . "/odyssey_backend/payment/success.php?bookingId=".$inputData['booking_id']."&&tranId=".$post_data['tran_id']."&&amount=".$post_data['total_amount'];
    $post_data['fail_url'] = "http://" . SERVER_IP . "/odyssey_backend/payment/fail.php";
    $post_data['cancel_url'] = "http://" . SERVER_IP . "/payment/odyssey_backend/cancel.php";

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
    curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);

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
    echo json_encode(["status" => "false", "message" => "Booking not found."]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

?>

