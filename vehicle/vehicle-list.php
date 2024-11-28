<?php

header('Content-Type: application/json');
require './../database_connection.php';
require './../config.php';

$baseUrl = "http://30.0.2.112/odyssey_backend/uploads/vehicles/"; // Base URL for images

function formatVehicleData($vehicle, $baseUrl) {
    // Append the base URL to image paths
    foreach (['owner_image', 'main_image', 'front_image', 'back_image', 'left_image', 'interior_image', 'right_image'] as $imageField) {
        if (isset($vehicle[$imageField]) && !empty($vehicle[$imageField])) {
            $vehicle[$imageField] = $baseUrl . $vehicle[$imageField];
        }
    }
    return $vehicle;
}

// Check if vehicleId is provided in the query string
$vehicleId = isset($_GET['vehicleId']) ? intval($_GET['vehicleId']) : null;

if ($vehicleId !== null && $vehicleId <= 0) {
    echo json_encode(["status" => "false", "message" => "Invalid Vehicle ID."]);
    exit;
}

if ($vehicleId) {
    // Fetch a specific vehicle
    $query = "SELECT * FROM vehicles WHERE vehicle_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $vehicleId);
} else {
    // Fetch all vehicles
    $query = "SELECT * FROM vehicles";
    $stmt = mysqli_prepare($conn, $query);
}

if (!$stmt) {
    echo json_encode(["status" => "false", "message" => "Failed to prepare the query."]);
    exit;
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($vehicleId) {
    // Fetch single vehicle
    $vehicle = mysqli_fetch_assoc($result);
    if (!$vehicle) {
        echo json_encode(["status" => "false", "message" => "Vehicle not found."]);
    } else {
        $vehicle = formatVehicleData($vehicle, $baseUrl);
        echo json_encode(["status" => "true", "message" => "Vehicle details fetched successfully.", "data" => $vehicle]);
    }
} else {
    // Fetch all vehicles
    $vehicles = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $vehicles[] = formatVehicleData($row, $baseUrl);
    }

    if (empty($vehicles)) {
        echo json_encode(["status" => "false", "message" => "No vehicles found."]);
    } else {
        echo json_encode(["status" => "true", "message" => "Vehicles fetched successfully.", "data" => $vehicles]);
    }
}

$stmt->close();
$conn->close();

?>
