<?php
header('Content-Type: application/json');
require './../database_connection.php';
require './../config.php';
require './../middleware/user_authentication.php';

$user = userAuthentication();
$email = $user->email;
$role = $user->role;

if ($role == 'renter') {
    echo json_encode(["status" => "false", "message" => "Unauthorized access."]);
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

$today = date('Y-m-d');
$nextSevenDays = date('Y-m-d', strtotime('+7 days'));
$query = "SELECT date FROM availability WHERE driver_id = ? AND date >= ? AND date <= ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "iss", $driverId, $today, $nextSevenDays);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo json_encode(["status" => "false", "message" => "No upcoming available dates found for this driver."]);
    exit;
}

$availableDates = [];
while ($row = mysqli_fetch_assoc($result)) {
    $availableDates[] = $row['date'];
}

echo json_encode(["status" => "true","message"=>"Date Fetched Successfully", "dates" => $availableDates]);

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
