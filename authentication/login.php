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
    if (!$inputData || !isset($inputData['email']) || !isset($inputData['password'])) {
        echo json_encode(["status" => "false", "message" => "Invalid Input. Email and password are required."]);
        exit;
    }

    $email = $inputData['email'];
    $password = $inputData['password'];

    $query = "SELECT 'driver' AS role, password FROM drivers WHERE email = ?
              UNION 
              SELECT 'renter' AS role, password FROM renters WHERE email = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ss", $email, $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) === 0) {
        echo json_encode(["status" => "false", "message" => "Invalid email or password."]);
        exit;
    }

    $row = mysqli_fetch_assoc($result);
    $hashedPassword = $row['password'];
    $role = $row['role'];

    if (!password_verify($password, $hashedPassword)) {
        echo json_encode(["status" => "false", "message" => "Invalid email or password."]);
        exit;
    }

    try {
        $token = create_jwt($email, $role);
        echo json_encode([
            "status" => "true",
            "message" => "Login successful.",
            "token" => $token,
            "role" => $role
        ]);
    } catch (Exception $e) {
        echo json_encode(["status" => "false", "message" => "Error generating token."]);
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
?>
