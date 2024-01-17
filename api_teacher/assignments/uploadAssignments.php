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

// Loading the environment variables
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

class LectureUploader
{
    private $headers;
    private $expectedApiKey;
    private $encryptionKey;
    private $iv;

    private $conn;

    public function __construct()
    {
        // Use the existing database connection from DbConnect
        $objDb = new DbConnect();
        $this->conn = $objDb->connect();

        $this->expectedApiKey = $_ENV["SCHOOL"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }

    public function handleRequest()
    {
        $headers = getallheaders();
        $encryptedApiKey = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        $decryptedApiKey = $this->decryptData($encryptedApiKey);

        if ($this->validateApiKey($decryptedApiKey)) {
            $method = $_SERVER['REQUEST_METHOD'];

            // Assigning the function according to request methods
            if ($method == 'POST') {
                // Assuming you are sending school_id, class_id, subject, topic, teacher_username, due_date, and pdf in the form data
                $school_id = isset($_POST['school_id']) ? $_POST['school_id'] : '';
                $class_id = isset($_POST['class_id']) ? $_POST['class_id'] : '';
                $subject = isset($_POST['subject']) ? $_POST['subject'] : '';
                $topic = isset($_POST['topic']) ? $_POST['topic'] : '';
                $teacher_username = isset($_POST['teacher_username']) ? $_POST['teacher_username'] : '';
                $due_date = isset($_POST['due_date']) ? $_POST['due_date'] : '';
                $upload_date = date('Y-m-d');
                
                $this->uploadAssignment($school_id, $class_id, $subject, $topic, $teacher_username, $due_date, $upload_date);
            } else {
                $this->sendResponse(405, ['error' => 'Method not allowed']);
            }
        } else {
            $this->sendResponse(403, ['error' => 'Access denied. Invalid API key.']);
        }
    }

    public function uploadAssignment($school_id, $class_id, $subject, $topic, $teacher_username, $due_date, $upload_date)
    {
        // Check if file is uploaded
        if (isset($_FILES['pdf'])) {
            $file = $_FILES['pdf'];

            // Handle file upload logic
            $target_dir = "pdf/";  // Save PDFs inside the 'pdf' folder
            $pdfFileType = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

            $newFileName = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 9) . '.' . $pdfFileType;
            $target_file = $target_dir . $newFileName;

            // Move the uploaded file to the target directory
            if (!move_uploaded_file($file["tmp_name"], $target_file)) {
                $this->sendResponse(500, ["status" => "error", "message" => "Error uploading file."]);
                exit();
            }

            // Check file size
            if ($file["size"] > 5000000) { // Adjust the file size limit as needed
                $this->sendResponse(400, ["status" => "error", "message" => "File is too large."]);
                exit();
            }

            // Allow only PDF file format
            if ($pdfFileType != "pdf") {
                $this->sendResponse(400, ["status" => "error", "message" => "Only PDF files are allowed."]);
                exit();
            }

            // Insert information into the database
            $assignmentQuery = "INSERT INTO assignments (teacher_username, school_id, class_id, subject, title, assignment, submit_date, due_date) 
                               VALUES (:teacher_username, :school_id, :class_id, :subject, :title, :assignment, :submit_date, :due_date)";

            $assignmentStmt = $this->conn->prepare($assignmentQuery);
            $assignmentStmt->bindParam(':teacher_username', $teacher_username, PDO::PARAM_STR);
            $assignmentStmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
            $assignmentStmt->bindParam(':class_id', $class_id, PDO::PARAM_STR);
            $assignmentStmt->bindParam(':subject', $subject, PDO::PARAM_STR);
            $assignmentStmt->bindParam(':title', $topic, PDO::PARAM_STR); // Assuming 'topic' corresponds to 'title'
            $assignmentStmt->bindParam(':assignment', $target_file, PDO::PARAM_STR);
            $assignmentStmt->bindParam(':submit_date', $upload_date, PDO::PARAM_STR);
            $assignmentStmt->bindParam(':due_date', $due_date, PDO::PARAM_STR);

            if ($assignmentStmt->execute()) {
                $this->sendResponse(200, ["status" => "success", "message" => "Assignment PDF uploaded successfully."]);
            } else {
                $this->sendResponse(500, ["status" => "error", "message" => "Error adding assignment to the database."]);
            }
        } else {
            $this->sendResponse(400, ["status" => "error", "message" => "No file uploaded."]);
        }
    }

    private function decryptData($data)
    {
        $plainText = openssl_decrypt($data, 'AES-256-CFB', $this->encryptionKey, 0, $this->iv);
        return $plainText;
    }

    private function validateApiKey($decryptedApiKey)
    {
        return $decryptedApiKey === $this->expectedApiKey;
    }

    private function sendResponse($statusCode, $data)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

// Usage
$controller = new LectureUploader();
$controller->handleRequest();
?>
