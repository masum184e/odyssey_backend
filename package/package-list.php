<?php

header('Content-Type: application/json');
require './../database_connection.php';
require './../config.php';

// Check if packageId is provided in the query string
$packageId = isset($_GET['packageId']) ? intval($_GET['packageId']) : null;

if ($packageId !== null && $packageId <= 0) {
    echo json_encode(["status" => "false", "message" => "Invalid Package ID."]);
    exit;
}

if ($packageId) {
    // Fetch a specific package
    $query = "SELECT * FROM packages WHERE package_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $packageId);
} else {
    // Fetch all packages
    $query = "SELECT * FROM packages";
    $stmt = mysqli_prepare($conn, $query);
}

if (!$stmt) {
    echo json_encode(["status" => "false", "message" => "Failed to prepare the query."]);
    exit;
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($packageId) {
    // Fetch single package
    $package = mysqli_fetch_assoc($result);
    if (!$package) {
        echo json_encode(["status" => "false", "message" => "Package not found."]);
    } else {
        echo json_encode(["status" => "true", "message" => "Package details fetched successfully.", "data" => $package]);
    }
} else {
    // Fetch all packages
    $packages = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $packages[] = $row;
    }

    if (empty($packages)) {
        echo json_encode(["status" => "false", "message" => "No packages found."]);
    } else {
        echo json_encode(["status" => "true", "message" => "Packages fetched successfully.", "data" => $packages]);
    }
}

$stmt->close();
$conn->close();

?>
