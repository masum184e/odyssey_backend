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
if (!isset($request['new_password']) || empty($request['new_password'])) {
    echo json_encode(['status' => 'false', 'message' => 'Password field is required.']);
    exit;
}

$newPassword = trim($request['new_password']);

// Validate password length
if (strlen($newPassword) < 6) {
    echo json_encode(['status' => 'false', 'message' => 'Password must be at least 6 characters long.']);
    exit;
}

$table = ($role === "driver") ? "drivers" : "renters";
$id = ($role === "driver") ? "driver_id" : "renter_id";

// Get user ID
$query = "SELECT $id FROM $table WHERE email = ?";
$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    echo json_encode(["status" => "false", "message" => "Database error."]);
    exit;
}

mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

if (!$row) {
    echo json_encode(["status" => "false", "message" => "User not found."]);
    exit;
}

$userId = $row[$id];

// Hash the new password
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

// Update the password in the database
$updateQuery = "UPDATE $table SET password = ? WHERE $id = ?";
$updateStmt = mysqli_prepare($conn, $updateQuery);

if (!$updateStmt) {
    echo json_encode(["status" => "false", "message" => "Failed to prepare update query."]);
    exit;
}

mysqli_stmt_bind_param($updateStmt, "si", $hashedPassword, $userId);
if (mysqli_stmt_execute($updateStmt)) {
    echo json_encode(["status" => "true", "message" => "Password updated successfully."]);
} else {
    echo json_encode(["status" => "false", "message" => "Failed to update password."]);
}

// Close statements and connection
mysqli_stmt_close($stmt);
mysqli_stmt_close($updateStmt);
mysqli_close($conn);
?>
