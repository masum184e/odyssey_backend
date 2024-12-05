<?php
    header('Content-Type: application/json');
    require './../database_connection.php';
    require './../config.php';
    require './../middleware/user_authentication.php';

    $user = userAuthentication();
    $email = $user->email;
    $role = $user->role;

    if ($_SERVER["REQUEST_METHOD"] !== "POST" || $role == "renter") {
        echo json_encode(["status" => "false", "message" => "Invalid Request."]);
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

    $inputData = json_decode(file_get_contents('php://input'), true);
    if (!$inputData) {
        echo json_encode(["status" => "false", "message" => "Invalid input."]);
        exit;
    }

    $bookingId = $inputData['booking_id'];
    $newStatus = $inputData['new_status'];

    $validStatuses = ['pending', 'progress', 'paid', 'cancelled'];
    if (!in_array($newStatus, $validStatuses)) {
        echo json_encode(["status" => "false", "message" => "Invalid status."]);
        exit;
    }

    $updateQuery = "UPDATE bookings SET booking_status = ? WHERE booking_id = ? AND driver_id = ?";
    $updateStmt = mysqli_prepare($conn, $updateQuery);
    mysqli_stmt_bind_param($updateStmt, "sii", $newStatus, $bookingId, $driverId);
    
    if (mysqli_stmt_execute($updateStmt)) {
        echo json_encode(["status" => "true", "message" => "Booking status updated successfully."]);
    } else {
        echo json_encode(["status" => "false", "message" => "Failed to update booking status."]);
    }
?>
