<?php
header('Content-Type: application/json');
require './../database_connection.php';
require './../config.php';
require './../middleware/user_authentication.php';

try {
    $user = userAuthentication();
    $email = $user->email;
    $role = $user->role;

    $table = ($role === "driver") ? "drivers" : "renters";

    $query = "SELECT * FROM $table WHERE email = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        echo json_encode([
            "status" => "true",
            "message" => "Profile fetched successfully.",
            "data" => $row
        ]);
    } else {
        echo json_encode(["status" => "false", "message" => "Profile not found."]);
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
} catch (Exception $e) {
    echo json_encode(["status" => "false", "message" => "Error: " . $e->getMessage()]);
}
?>
