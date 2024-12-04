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
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    echo json_encode(["status" => "false", "message" => "Invalid request method. Only GET is allowed."]);
    exit;
}

if (!in_array($role, ["driver", "renter"])) {
    echo json_encode(["status" => "false", "message" => "Unauthorized access. Role must be either 'driver' or 'renter'."]);
    exit;
}

// Retrieve user ID based on email and role
$table = $role === "driver" ? "drivers" : "renters";
$id = $role === "driver" ? "driver_id" : "renter_id";
$query = "SELECT $id FROM $table WHERE email = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$userData = mysqli_fetch_assoc($result);

if (!$userData) {
    echo json_encode(["status" => "false", "message" => "User not found."]);
    exit;
}

$userId = $userData[$id];
mysqli_stmt_close($stmt);

// Check if bookingId is provided in the query string
$bookingId = isset($_GET['bookingId']) ? intval($_GET['bookingId']) : null;

if ($bookingId !== null && $bookingId <= 0) {
    echo json_encode(["status" => "false", "message" => "Invalid Booking ID."]);
    exit;
}

if ($bookingId) {
    if ($role === "renter") {
        // Fetch driver name if the role is renter
        $query = "SELECT b.*, d.name 
                  FROM bookings b
                  LEFT JOIN drivers d ON b.driver_id = d.driver_id
                  WHERE b.booking_id = ? AND b.renter_id = ?";
    } else {
        // Fetch renter name if the role is driver
        $query = "SELECT b.*, r.name 
                  FROM bookings b
                  LEFT JOIN renters r ON b.renter_id = r.renter_id
                  WHERE b.booking_id = ? AND b.driver_id = ?";
    }

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $bookingId, $userId);
} else {
    // Fetch all bookings for the authorized user
    if ($role === "renter") {
        // Fetch driver name if the role is renter
        $query = "SELECT b.*, d.name 
                  FROM bookings b
                  LEFT JOIN drivers d ON b.driver_id = d.driver_id
                  WHERE b.renter_id = ?";
    } else {
        // Fetch renter name if the role is driver
        $query = "SELECT b.*, r.name 
                  FROM bookings b
                  LEFT JOIN renters r ON b.renter_id = r.renter_id
                  WHERE b.driver_id = ?";
    }
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $userId);
}

if (!$stmt) {
    echo json_encode(["status" => "false", "message" => "Failed to prepare the query."]);
    exit;
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($bookingId) {
    // Fetch single booking
    $booking = mysqli_fetch_assoc($result);
    if (!$booking) {
        echo json_encode(["status" => "false", "message" => "Booking not found or unauthorized access."]);
    } else {
        echo json_encode(["status" => "true", "message" => "Booking details fetched successfully.", "data" => $booking]);
    }
} else {
    // Fetch all bookings
    $bookings = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $bookings[] = $row;
    }

    if (empty($bookings)) {
        echo json_encode(["status" => "false", "message" => "No bookings found."]);
    } else {
        echo json_encode(["status" => "true", "message" => "Bookings fetched successfully.", "data" => $bookings]);
    }
}

mysqli_stmt_close($stmt);
$conn->close();

?>
