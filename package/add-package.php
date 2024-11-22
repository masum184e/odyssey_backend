<?php
header('Content-Type: application/json');
require './../database_connection.php';
require './../config.php';
require './../middleware/user_authentication.php';

// // User authentication
// $user = userAuthentication();
// $role = $user->role;

// // Authorization: Allow only authorized roles
// if ($_SERVER["REQUEST_METHOD"] !== "POST" || $role !== "admin") {
//     echo json_encode(["status" => "false", "message" => "Unauthorized or invalid request."]);
//     exit;
// }

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status" => "false", "message" => "Unauthorized or invalid request."]);
    exit;
}

$inputData = json_decode(file_get_contents('php://input'), true);
if (!$inputData) {
    echo json_encode(["status" => "false", "message" => "Invalid Input."]);
    exit;
}

$type = $inputData['type'] ?? '';
$basePrice = $inputData['base_price'] ?? 0;
$category = $inputData['category'] ?? '';
$hasAC = $inputData['has_ac'] ?? "false";
$perKmCost = $inputData['per_km_cost'] ?? 0;
$perHourCost = $inputData['per_hour_cost'] ?? 0;
$approximateSeats = $inputData['approximate_seats'] ?? 0;

if (empty($type) || empty($category) || !is_numeric($basePrice) || !($hasAC=="true" || $hasAC=="false") ||
    !is_numeric($perKmCost) || !is_numeric($perHourCost) || !is_numeric($approximateSeats)) {
    echo json_encode(["status" => "false", "message" => "All fields are required."]);
    exit;
}

if ($basePrice <= 0 || $perKmCost <= 0 || $perHourCost <= 0 || $approximateSeats <= 0) {
    echo json_encode(["status" => "false", "message" => "Numeric values must be greater than zero."]);
    exit;
}

$query = "INSERT INTO packages (type, base_price, category, has_ac, per_km_cost, per_hour_cost, approximate_seats) 
          VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($query);
$stmt->bind_param("sdsidii", $type, $basePrice, $category, $hasAC, $perKmCost, $perHourCost, $approximateSeats);

if ($stmt->execute()) {
    echo json_encode(["status" => "true", "message" => "Package added successfully."]);
} else {
    echo json_encode(["status" => "false", "message" => "Failed to insert package details."]);
}

$stmt->close();
$conn->close();
?>
