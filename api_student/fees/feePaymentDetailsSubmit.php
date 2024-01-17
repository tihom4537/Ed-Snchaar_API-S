<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Allow cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

// Include the database connection class
include '../DbConnect.php';

class FeePaymentUploader
{
    private $conn;

    public function __construct()
    {
        // Use the existing database connection from DbConnect
        $objDb = new DbConnect();
        $this->conn = $objDb->connect();
    }

    public function handleRequest()
    {
        $method = $_SERVER['REQUEST_METHOD'];

        // Assigning the function according to request methods
        if ($method == 'POST') {
            // Assuming you are sending fee_id, name, class_id, transaction_screenshot in the form data
            $school_id = isset($_POST['school_id']) ? $_POST['school_id'] : '';
            $class_id = isset($_POST['class_id']) ? $_POST['class_id'] : '';
            $fee_id = isset($_POST['fee_id']) ? $_POST['fee_id'] : '';
            $name = isset($_POST['name']) ? $_POST['name'] : '';
            $username = isset($_POST['username']) ? $_POST['username'] : '';
            $message = isset($_POST['message']) ? $_POST['message'] : '';


            // Call the function to upload fee payment details
            $this->uploadFeePaymentDetails($fee_id, $name, $class_id, $school_id, $username, $message);
        } else {
            http_response_code(405); // Method Not Allowed
            echo json_encode(['error' => 'Method not allowed']);
        }
    }

    public function uploadFeePaymentDetails($fee_id, $name, $class_id, $school_id, $username, $message)
{
    // Check if base64-encoded image is provided
    if (isset($_POST['transaction_screenshot'])) {
        $base64Data = $_POST['transaction_screenshot'];

        // Decode base64 data
        $decodedImage = base64_decode($base64Data);

        // Handle image upload logic
        $target_file = $this->handleBase64ImageUpload($decodedImage);

        // Insert information into the database
        $paymentQuery = "INSERT INTO FeePaymentVerification (fee_id, name, class_id, transaction_screenshot, username, school_id, message, verification_status, request_date) 
             VALUES (:fee_id, :name, :class_id, :screenshot_path, :username, :school_id, :message, 'pending', NOW())";

        $paymentStmt = $this->conn->prepare($paymentQuery);
        $paymentStmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
        $paymentStmt->bindParam(':name', $name, PDO::PARAM_STR);
        $paymentStmt->bindParam(':username', $username, PDO::PARAM_STR);
        $paymentStmt->bindParam(':class_id', $class_id, PDO::PARAM_STR);
        $paymentStmt->bindParam(':fee_id', $fee_id, PDO::PARAM_STR);
        $paymentStmt->bindParam(':message', $message, PDO::PARAM_STR);
        $paymentStmt->bindParam(':screenshot_path', $target_file, PDO::PARAM_STR);

        if ($paymentStmt->execute()) {
            // Update the verification_status in the Fee table
            $updateFeeQuery = "UPDATE fees SET verification_status = 1 WHERE id = :fee_id";
            $updateFeeStmt = $this->conn->prepare($updateFeeQuery);
            $updateFeeStmt->bindParam(':fee_id', $fee_id, PDO::PARAM_STR);

            if ($updateFeeStmt->execute()) {
                echo json_encode(["status" => "success", "message" => "Fee payment details uploaded successfully."]);
            } else {
                echo json_encode(["status" => "error", "message" => "Error updating fee verification status."]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Error adding fee payment details to the database."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "No base64-encoded image provided."]);
    }
}

private function handleBase64ImageUpload($decodedImage)
{
    $target_dir = "Screenshots/";
    $imageFileType = "png";  // You may need to determine the file type based on your requirements
    $newFileName = substr(bin2hex(random_bytes(5)), 0, 9) . '.' . $imageFileType;
    $target_file = $target_dir . $newFileName;

    // Move the decoded image data to the target directory
    if (file_put_contents($target_file, $decodedImage)) {
        return $target_file;
    } else {
        echo json_encode(["status" => "error", "message" => "Error saving decoded image data."]);
        exit();
    }
}
}

// Usage
$feeController = new FeePaymentUploader();
$feeController->handleRequest();
?>
