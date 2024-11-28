<?php

header('Content-Type: application/json');
require './../database_connection.php';
require './../config.php';
require './../middleware/user_authentication.php';

$user = userAuthentication();
$email = $user->email;
$role = $user->role;

// Check if the user is authorized and the request method is POST
if ($_SERVER["REQUEST_METHOD"] !== "POST" || $role !== "renter") {
    echo json_encode(["status" => "false", "message" => "Unauthorized access or invalid request method."]);
    exit;
}

// Retrieve renter ID based on the authenticated user's email
$query = "SELECT renter_id FROM renters WHERE email = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$renter = $result->fetch_assoc();

if (!$renter) {
    echo json_encode(["status" => "false", "message" => "Renter not found."]);
    exit;
}

$renterId = $renter['renter_id'];

// Read and decode JSON input
$inputData = json_decode(file_get_contents('php://input'), true);
if (!$inputData) {
    echo json_encode(["status" => "false", "message" => "Invalid input."]);
    exit;
}

// Retrieve and validate booking details from input data
$driverId = $inputData['driver_id'] ?? null;
$pickupDatetime = $inputData['pickup_datetime'] ?? null;
$dropoffDatetime = $inputData['dropoff_datetime'] ?? null;
$pickupLocation = $inputData['pickup_location'] ?? '';
$dropoffLocation = $inputData['dropoff_location'] ?? '';
$numberOfPassengers = $inputData['number_of_passengers'] ?? null;
$numberOfStoppages = $inputData['number_of_stoppages'] ?? null;

// Validate mandatory fields
if (empty($driverId) || empty($pickupDatetime) || empty($dropoffDatetime) || empty($pickupLocation) || empty($dropoffLocation) || !isset($numberOfPassengers) || !isset($numberOfStoppages)) {
    echo json_encode(["status" => "false", "message" => "All fields are required."]);
    exit;
}

// Validate numeric fields
if (!is_numeric($numberOfPassengers) || !is_numeric($numberOfStoppages)) {
    echo json_encode(["status" => "false", "message" => "Number of passengers and stoppages must be numeric."]);
    exit;
}

// Validate date range
if (strtotime($dropoffDatetime) <= strtotime($pickupDatetime)) {
    echo json_encode(["status" => "false", "message" => "Dropoff datetime must be later than the pickup datetime."]);
    exit;
}

// Insert booking into the database
$query = "INSERT INTO bookings 
    (driver_id, renter_id, pickup_datetime, dropoff_datetime, pickup_location, dropoff_location, number_of_passengers, number_of_stoppages, booking_status) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";

$stmt = $conn->prepare($query);
$stmt->bind_param("iissssii", $driverId, $renterId, $pickupDatetime, $dropoffDatetime, $pickupLocation, $dropoffLocation, $numberOfPassengers, $numberOfStoppages);

if ($stmt->execute()) {
    echo json_encode(["status" => "true", "message" => "Booking request created successfully."]);
} else {
    echo json_encode(["status" => "false", "message" => "Failed to create booking request: " . $stmt->error]);
}

// Close statement and connection
$stmt->close();
$conn->close();

?>
