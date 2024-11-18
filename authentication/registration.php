<?php
header('Content-Type: application/json');
require './../database_connection.php';
require './../config.php';
require './../jwt/create_jwt.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $inputData = json_decode(file_get_contents('php://input'), true);

    if (!$inputData) {
        echo json_encode(["status" => "false", "message" => "Invalid Input."]);
        exit;
    }

    $name = $inputData['name'];
    $email = $inputData['email'];
    $password = $inputData['password'];
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $mobileNumber = $inputData['mobileNumber'];
    $role = $inputData['role'];

    if($role=="driver"){
        $query = "SELECT * FROM drivers WHERE email = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) > 0) {
            echo json_encode(["status" => "false", "message" => "Email already exists."]);
            mysqli_stmt_close($stmt);
            mysqli_close($conn);
            exit;
        }
        mysqli_stmt_close($stmt);
        
        $insertQuery = "INSERT INTO drivers (name, mobile_number, password, email) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $insertQuery);
        mysqli_stmt_bind_param($stmt, "ssss", $name, $mobileNumber, $hashedPassword, $email);
    
        if (mysqli_stmt_execute($stmt)) {
            try {
                $token = create_jwt($email);
                echo json_encode(["status" => "true", "message" => "User registered successfully!", "token" => $token]);
            } catch (Exception $e) {
                echo json_encode(["status" => "false", "message" => "Error generating token."]);
            }
        } else {
            echo json_encode(["status" => "false", "message" => "Error: " . mysqli_error($conn)]);
        }
    }


    mysqli_stmt_close($stmt);
    mysqli_close($conn);
}else{
    echo json_encode(["status" => "false", "message" => "Invalid Request."]);
}
?>