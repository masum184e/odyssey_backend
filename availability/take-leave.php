<?php
header('Content-Type: application/json');
require './../database_connection.php';
require './../config.php';
require './../middleware/user_authentication.php';

$user = userAuthentication();
$email = $user->email;
$role = $user->role;

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status" => "false", "message" => "Invalid Request."]);
    exit;
}

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
$data = json_decode(file_get_contents("php://input"), true);
$startDate = $data['start_date'] ?? null;
$endDate = $data['end_date'] ?? null;

if (!$startDate || !$endDate) {
    echo json_encode(["status" => "false", "message" => "All fields are required"]);
    exit;
}

if (!validateDate($startDate) || !validateDate($endDate)) {
    echo json_encode(["status" => "false", "message" => "Invalid date format"]);
    exit;
}

$today = date('Y-m-d');
if (strtotime($startDate) <= strtotime($today)) {
    echo json_encode(["status" => "false", "message" => "Start date must be greater than today's date."]);
    exit;
}

if (strtotime($startDate) > strtotime($endDate)) {
    echo json_encode(["status" => "false", "message" => "End date must be greater than start date"]);
    exit;
}

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

$dateInterval = new DatePeriod(
    new DateTime($startDate),
    new DateInterval('P1D'),
    (new DateTime($endDate))->modify('+1 day')
);

$dates = [];
foreach ($dateInterval as $date) {
    $dates[] = $date->format('Y-m-d');
}

// Insert dates into the availability table
$query = "INSERT INTO availability (driver_id, date) VALUES (?, ?)";
$stmt = mysqli_prepare($conn, $query);

if (!$stmt) {
    echo json_encode(["status" => "false", "message" => "Database error: " . $conn->error]);
    exit;
}

foreach ($dates as $availableDate) {
    // Check if the date already exists in the availability table
    $checkQuery = "SELECT * FROM availability WHERE driver_id = ? AND date = ?";
    $checkStmt = mysqli_prepare($conn, $checkQuery);
    mysqli_stmt_bind_param($checkStmt, "is", $driverId, $availableDate);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);
    
    if (mysqli_num_rows($checkResult) > 0) {
        // Skip if the date already exists
        continue;
    }

    // Insert date into the availability table
    $insertQuery = "INSERT INTO availability (driver_id, date) VALUES (?, ?)";
    $insertStmt = mysqli_prepare($conn, $insertQuery);
    mysqli_stmt_bind_param($insertStmt, "is", $driverId, $availableDate);

    if (!mysqli_stmt_execute($insertStmt)) {
        echo json_encode(["status" => "false", "message" => "Failed to insert date: $availableDate"]);
        exit;
    }
}

mysqli_stmt_close($checkStmt);
// mysqli_stmt_close($insertStmt);
mysqli_close($conn);

echo json_encode(["status" => "true", "message" => "Dates added successfully."]);

?>
