<?php

header('Content-Type: application/json');
require './../database_connection.php';
require './../config.php';
require './../middleware/user_authentication.php';

// Authenticate user and get their details
$user = userAuthentication();
$email = $user->email;
$role = $user->role;

// Check if the user is authorized and the request method is GET
if ($_SERVER["REQUEST_METHOD"] !== "GET"){
    echo json_encode(["status" => "false", "message" => "Invalid request."]);
    exit;
}

$inputData = json_decode(file_get_contents('php://input'), true);
if (!$inputData) {
    echo json_encode(["status" => "false", "message" => "Invalid input."]);
    exit;
}

if(!isset($inputData['driver_id']) || !isset($inputData['pickup_datetime']) || !isset($inputData['dropoff_datetime']) || !isset($inputData['pickup_location']) || !isset($inputData['dropoff_location'])){
    echo json_encode(["status" => "false", "message" => "All fields are required"]);
    exit;
}

if (!in_array($role, ["driver", "renter"])) {
    echo json_encode(["status" => "false", "message" => "Unauthorized access. Role must be either 'driver' or 'renter'."]);
    exit;
}

$query = "SELECT 
    b.booking_id,
    d.driver_id,
    v.vehicle_id,
    p.type,
    p.base_price,
    p.per_km_cost,
    p.per_hour_cost,
    b.pickup_location,
    b.dropoff_location,
    b.pickup_datetime,
    b.dropoff_datetime
FROM
    bookings b
JOIN
    drivers d ON b.driver_id = d.driver_id
JOIN
    vehicles v ON d.driver_id = v.driver_id
JOIN
    packages p ON v.type = p.type
WHERE
    d.driver_id = ? 
    AND b.pickup_datetime = ? 
    AND b.dropoff_datetime = ? 
    AND b.pickup_location = ? 
    AND b.dropoff_location = ?";

$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    echo json_encode(["status" => "false", "message" => "Failed to prepare the query."]);
    exit;
}

mysqli_stmt_bind_param(
    $stmt,
    "sssss",
    $inputData['driver_id'],
    $inputData['pickup_datetime'], 
    $inputData['dropoff_datetime'],
    $inputData['pickup_location'],
    $inputData['dropoff_location']
);

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

    $response = [
        'distance' => number_format($distance, 2),
        'duration' => number_format($duration, 2),
        'total_price' => number_format($totalPrice, 2)
    ];

    echo json_encode(["status" => "true", "message" => "Data fetched successfully", "data" => $response]);
} else {
    echo json_encode(["status" => "false", "message" => "Driver not found."]);
}

// Close statement and connection
mysqli_stmt_close($stmt);
mysqli_close($conn);

?>
