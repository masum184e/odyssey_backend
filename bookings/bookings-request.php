<?php

header('Content-Type: application/json');
require './../database_connection.php';
require './../config.php';
require './../middleware/user_authentication.php';

$user = userAuthentication();
$email = $user->email;
$role = $user->role;

// Check if the user is authorized and the request method is POST
if ($_SERVER["REQUEST_METHOD"] !== "POST"){
    echo json_encode(["status" => "false", "message" => "Invalid request method."]);
    exit;
}

if ($role !== "renter") {
    echo json_encode(["status" => "false", "message" => "Unauthorized access"]);
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

// Validate if the booking dates are in the future
$currentTimestamp = time();
$pickupTimestamp = strtotime($pickupDatetime);
$dropoffTimestamp = strtotime($dropoffDatetime);

// Calculate the maximum allowed pickup date (7 days from now)
$maxPickupTimestamp = strtotime("+7 days");

// Validate that the pickup date is within the next 7 days
if ($pickupTimestamp < $currentTimestamp || $pickupTimestamp > $maxPickupTimestamp) {
    echo json_encode([
        "status" => "false",
        "message" => "Pickup datetime must be within the next 7 days."
    ]);
    exit;
}

if ($pickupTimestamp < $currentTimestamp) {
    echo json_encode(["status" => "false", "message" => "Pickup datetime must be in the future."]);
    exit;
}

if ($dropoffTimestamp < $currentTimestamp) {
    echo json_encode(["status" => "false", "message" => "Dropoff datetime must be in the future."]);
    exit;
}

// Validate date range
if ($dropoffTimestamp <= $pickupTimestamp) {
    echo json_encode(["status" => "false", "message" => "Dropoff datetime must be later than the pickup datetime."]);
    exit;
}

// Validate the range between pickup and dropoff
$maxDurationInSeconds = 7 * 24 * 60 * 60; // 7 days in seconds
if (($dropoffTimestamp - $pickupTimestamp) > $maxDurationInSeconds) {
    echo json_encode([
        "status" => "false", 
        "message" => "The range between pickup and dropoff must not exceed 7 days."
    ]);
    exit;
}

$query = "SELECT booking_id FROM bookings 
          WHERE renter_id = ? 
          AND (
              (pickup_datetime <= NOW() AND dropoff_datetime >= NOW()) -- Ongoing booking
              OR 
              (pickup_datetime > NOW()) -- Upcoming booking
          )";
        //   MAKE CHANGES FOR BOOKING STATUS
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $renterId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(["status" => "false", "message" => "You already have an ongoing or upcoming booking."]);
    exit;
}

// Insert booking into the database
$query = "INSERT INTO bookings 
    (driver_id, renter_id, pickup_datetime, dropoff_datetime, pickup_location, dropoff_location, number_of_passengers, number_of_stoppages, booking_status) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";

$stmt = $conn->prepare($query);
$stmt->bind_param("iissssii", $driverId, $renterId, $pickupDatetime, $dropoffDatetime, $pickupLocation, $dropoffLocation, $numberOfPassengers, $numberOfStoppages);

if ($stmt->execute()) {
    echo json_encode(["status" => "true", "message" => "Booking request send successfully."]);
} else {
    echo json_encode(["status" => "false", "message" => "Failed to create booking request: " . $stmt->error]);
}

// Close statement and connection
$stmt->close();
$conn->close();

?>
