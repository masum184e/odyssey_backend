<?php
header('Content-Type: application/json');
require './../database_connection.php';
require './../config.php';
require './../middleware/user_authentication.php';

$user = userAuthentication();
$email = $user->email;
$role = $user->role;

$request = json_decode(file_get_contents('php://input'), true);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status" => "false", "message" => "Invalid Request."]);
    exit;
}

// Validate required fields
if (!isset($request['title'], $request['message'])) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit;
}

$title = $request['title'];
$message = $request['message'];
$document = "checked.pdf";

$table = ($role === "driver") ? "drivers" : "renters";
$id = ($role === "driver") ? "driver_id" : "renter_id";

// Get user ID
$query = "SELECT $id FROM $table WHERE email = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

if (!$row) {
    echo json_encode(["status" => "false", "message" => "User not found."]);
    exit;
}

$userId = $row[$id];

// Insert complaint into the complaints table
$insertQuery = "INSERT INTO complaints (user_type, message, document, $id) VALUES (?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $insertQuery);
mysqli_stmt_bind_param($stmt, "sssi", $role, $message, $document, $userId);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(["status" => "true", "message" => "Complaint Submitted"]);
} else {
    echo json_encode(["status" => "false", "message" => "Error: " . mysqli_error($conn)]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
