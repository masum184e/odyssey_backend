<?php
    header('Content-Type: application/json');
    require './../database_connection.php';
    require './../config.php';
    require './../jwt/create_jwt.php';

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["status" => "false", "message" => "Invalid Request."]);
        exit;
    }

    $inputData = json_decode(file_get_contents('php://input'), true);
    if (!$inputData) {
        echo json_encode(["status" => "false", "message" => "Invalid Input."]);
        exit;
    }

    $name = $inputData['name'];
    $email = $inputData['email'];
    $password = password_hash($inputData['password'], PASSWORD_DEFAULT);
    $mobileNumber = $inputData['mobileNumber'];
    $role = $inputData['role'];

    $table = $role === "driver" ? "drivers" : "renters";

    $query = "SELECT email FROM drivers WHERE email = ? 
              UNION 
              SELECT email FROM renters WHERE email = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ss", $email, $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) > 0) {
        echo json_encode(["status" => "false", "message" => "Email already exists."]);
        exit;
    }

    mysqli_stmt_close($stmt);

    $insertQuery = "INSERT INTO $table (name, mobile_number, password, email) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $insertQuery);
    mysqli_stmt_bind_param($stmt, "ssss", $name, $mobileNumber, $password, $email);

    if (mysqli_stmt_execute($stmt)) {
        try {
            $token = create_jwt($email, $role);
            echo json_encode([
                "status" => "true", 
                "message" => "Account created successfully!",
                 "token" => $token,
                 "role" => $role
            ]);
        } catch (Exception $e) {
            echo json_encode(["status" => "false", "message" => "Error generating token."]);
        }
    } else {
        echo json_encode(["status" => "false", "message" => "Error: " . mysqli_error($conn)]);
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
?>
