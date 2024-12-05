<?php

header('Content-Type: application/json');
require './../database_connection.php';
require './../config.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status" => "false", "message" => "Invalid request."]);
    exit;
}

if (!$_GET || !isset($_GET['bookingId']) || !isset($_GET['tranId']) || !isset($_GET['amount'])) {
    echo json_encode(["status" => "false", "message" => "Invalid input data."]);
    exit;
}

$bookingId = $_GET['bookingId'];
$transactionId = $_GET['tranId'];
$amount = $_GET['amount'];


try {
    $updateBookingQuery = "UPDATE bookings SET booking_status = 'paid' WHERE booking_id = ?";
    $stmt = mysqli_prepare($conn, $updateBookingQuery);
    if (!$stmt) {
        throw new Exception("Failed to prepare query for updating booking status.");
    }
    mysqli_stmt_bind_param($stmt, "s", $bookingId);
    mysqli_stmt_execute($stmt);
    if (mysqli_stmt_affected_rows($stmt) <= 0) {
        throw new Exception("Failed to update booking status or no rows affected.");
    }

    // Step 2: Insert into `trip` table
    $insertTripQuery = "INSERT INTO trips (booking_id) VALUES (?)";
    $stmt = mysqli_prepare($conn, $insertTripQuery);
    if (!$stmt) {
        throw new Exception("Failed to prepare query for inserting into trip table.");
    }
    mysqli_stmt_bind_param($stmt, "s", $bookingId);
    mysqli_stmt_execute($stmt);

    // Get the generated `trip_id`
    $tripId = mysqli_insert_id($conn);
    if (!$tripId) {
        throw new Exception("Failed to retrieve trip ID.");
    }

    $insertPaymentQuery = "INSERT INTO payments (trip_id, amount, transaction_id) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $insertPaymentQuery);
    if (!$stmt) {
        throw new Exception("Failed to prepare query for inserting into payments table.");
    }
    mysqli_stmt_bind_param($stmt, "ids", $tripId, $amount, $transactionId);
    mysqli_stmt_execute($stmt);
    if (mysqli_stmt_affected_rows($stmt) <= 0) {
        throw new Exception("Failed to insert into payments table.");
    }

    mysqli_commit($conn);

    echo json_encode([
        "status" => "true",
        "message" => "Payment successfully recorded.",
        "bookingId" => $bookingId,
        "tripId" => $tripId,
        "transactionId" => $transactionId,
        "amount" => $amount
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(["status" => "false", "message" => $e->getMessage()]);
}

// Close connection
mysqli_close($conn);

?>
