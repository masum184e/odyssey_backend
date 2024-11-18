<?php
    header('Content-Type: application/json');
    require './../database_connection.php';
    require './../config.php';
    require './../middleware/user_authentication.php';

    $user = userAuthentication();
    $email = $user->email;
    $role = $user->role;

    if ($_SERVER["REQUEST_METHOD"] !== "POST" || $role=="renter") {
        echo json_encode(["status" => "false", "message" => "Invalid Request."]);
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

    $licensePlate = $_POST['licensePlate'] ?? '';
    $mileage = $_POST['mileage'] ?? 0;
    $seats = $_POST['numberOfSeats'] ?? 0;
    $chasisNumber = $_POST['chasisNumber'] ?? '';
    $model = $_POST['model'] ?? '';
    $type = $_POST['type'] ?? '';
    $year = $_POST['year'] ?? '';
    $color = $_POST['color'] ?? '';
    $ownerMobile = $_POST['ownerMobileNumber'] ?? '';

    if (empty($licensePlate) || empty($mileage) || empty($seats) || empty($chasisNumber) || empty($model) || empty($type) || empty($year) || empty($color) || empty($ownerMobile)) {
        echo json_encode(["status" => "false", "message" => "All fields are required."]);
        exit;
    }

    $uploadDir = './../uploads/vehicles/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    function uploadVehicleImage($image, $imageName, $uploadDir) {
        $fileTmpName = $image['tmp_name'];
        $fileName = basename($image['name']);
        $fileSize = $image['size'];
        $fileError = $image['error'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedTypes = ['jpg', 'jpeg', 'png'];
        $fileType = mime_content_type($fileTmpName);

        if (!in_array($fileExtension, $allowedTypes) || !in_array($fileType, ['image/jpeg', 'image/png'])) {
            echo json_encode(["status" => "false", "message" => "$imageName: Only JPG, JPEG, PNG files are allowed."]);
            exit;
        }

        if ($fileSize > 2 * 1024 * 1024) {
            echo json_encode(["status" => "false", "message" => "$imageName: File size must be less than 2MB."]);
            exit;
        }

        if ($fileError !== 0) {
            echo json_encode(["status" => "false", "message" => "$imageName: File upload error: $fileError"]);
            exit;
        }

        $uniqueFileName = uniqid($imageName . '_', true) . '.' . $fileExtension;
        $targetFilePath = $uploadDir . $uniqueFileName;

        if (move_uploaded_file($fileTmpName, $targetFilePath)) {
            return $uniqueFileName;
        } else {
            echo json_encode(["status" => "false", "message" => "$imageName: Failed to move the uploaded file."]);
            exit;
        }
    }

    $mainImage = uploadVehicleImage($_FILES['mainImage'], 'Main Image', $uploadDir);
    $frontImage = uploadVehicleImage($_FILES['frontImage'], 'Front Image', $uploadDir);
    $backImage = uploadVehicleImage($_FILES['backImage'], 'Back Image', $uploadDir);
    $leftImage = uploadVehicleImage($_FILES['leftImage'], 'Left Image', $uploadDir);
    $interiorImage = uploadVehicleImage($_FILES['interiorImage'], 'Interior Image', $uploadDir);
    $rightImage = uploadVehicleImage($_FILES['rightImage'], 'Right Image', $uploadDir);

    $sql = "INSERT INTO vehicles (driver_id, license_plate_number, mileage, number_of_seats, chasis_number, model, type, year, color, owner_mobile_number, owner_image, main_image, front_image, back_image, left_image, interior_image, right_image) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isdisdssssssssss", $driverId, $licensePlate, $mileage, $seats, $chasisNumber, $model, $type, $year, $color, $ownerMobile, $mainImage, $frontImage, $backImage, $leftImage, $interiorImage, $rightImage);

    if ($stmt->execute()) {
        echo json_encode(["status" => "true", "message" => "Vehicle added successfully."]);
    } else {
        echo json_encode(["status" => "false", "message" => "Failed to insert vehicle details into the database."]);
    }

    $stmt->close();
    $conn->close();
?>
