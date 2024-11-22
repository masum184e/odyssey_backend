<?php

header('Content-Type: application/json');
require './../database_connection.php';
require './../config.php';
require './../middleware/user_authentication.php';

// User authentication
$user = userAuthentication();
$email = $user->email; // Assuming the authenticated user ID is the renter_id

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status" => "false", "message" => "Invalid request method."]);
    exit;
}

$inputData = json_decode(file_get_contents('php://input'), true);
if (!$inputData) {
    echo json_encode(["status" => "false", "message" => "Invalid Input."]);
    exit;
}

$query = "SELECT driver_id FROM drivers WHERE email = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

if (!$row) {
    echo json_encode(["status" => "false", "message" => "Driver not found."]);
    exit;
}

$driverId = $row['driver_id'];

// Retrieve and validate inputs
$driverId = isset($row['driver_id']) ? intval($row['driver_id']) : null;
$pickupDatetime = $inputData['pickup_datetime'] ?? '';
$dropoffDatetime = $inputData['dropoff_datetime'] ?? '';
$pickupLocation = $inputData['pickup_location'] ?? '';
$dropoffLocation = $inputData['dropoff_location'] ?? '';
$numberOfPassengers = isset($inputData['number_of_passengers']) ? intval($inputData['number_of_passengers']) : null;
$numberOfStoppages = isset($inputData['number_of_stoppages']) ? intval($inputData['number_of_stoppages']) : null;


// Validate required fields
if (!$driverId || empty($pickupDatetime) || empty($dropoffDatetime) || empty($pickupLocation) || 
    empty($dropoffLocation) || !$numberOfPassengers || !$numberOfStoppages) {
    echo json_encode(["status" => "false", "message" => "All fields are required."]);
    exit;
}

// Check if the pickup and dropoff datetime are valid
if (strtotime($dropoffDatetime) <= strtotime($pickupDatetime)) {
    echo json_encode(["status" => "false", "message" => "Dropoff datetime must be after pickup datetime."]);
    exit;
}

// Insert the booking into the database
$query = "INSERT INTO bookings 
    (driver_id, renter_id, pickup_datetime, dropoff_datetime, pickup_location, dropoff_location, number_of_passengers, number_of_stoppages, booking_status) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";
$stmt = $conn->prepare($query);
$stmt->bind_param("iissssii", $driverId, $renterId, $pickupDatetime, $dropoffDatetime, $pickupLocation, $dropoffLocation, $numberOfPassengers, $numberOfStoppages);

if ($stmt->execute()) {
    echo json_encode(["status" => "true", "message" => "Booking request created successfully.", "booking_id" => $stmt->insert_id]);
} else {
    echo json_encode(["status" => "false", "message" => "Failed to create booking request."]);
}

// Close statement and connection
$stmt->close();
$conn->close();

?>
