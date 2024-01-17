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

//loading the environment variables
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

class LectureUploader
{
    private $headers;
    private $expectedApiKey;
    private $encryptedApiKey;

    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv; // 128 bits IV

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
                // Assuming you are sending school_id, class_id, subject, topic, and pdf in the form data
                $school_id = isset($_POST['school_id']) ? $_POST['school_id'] : '';
                $class_id = isset($_POST['class_id']) ? $_POST['class_id'] : '';
                $subject = isset($_POST['subject']) ? $_POST['subject'] : '';
                $topic = isset($_POST['topic']) ? $_POST['topic'] : '';

                $this->uploadLecturePdf($school_id, $class_id, $subject, $topic);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['error' => 'Method not allowed']);
            }
    }

    public function uploadLecturePdf($school_id, $class_id, $subject, $topic)
    {
        // Check if file is uploaded
        if (isset($_FILES['pdf'])) {
            $file = $_FILES['pdf'];

            // Handle file upload logic
            $target_dir = "pdf/";  // Save PDFs inside the 'pdf' folder
            $pdfFileType = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

            // Generate a random 5-digit alphanumeric string as the new file name
            $newFileName = bin2hex(random_bytes(5)) . '.' . $pdfFileType;
            $target_file = $target_dir . $newFileName;


            // Check file size
            if ($file["size"] > 5000000) { // Adjust the file size limit as needed
                echo json_encode(["status" => "error", "message" => "File is too large."]);
                exit();
            }

            // Allow only PDF file format
            if ($pdfFileType != "pdf") {
                echo json_encode(["status" => "error", "message" => "Only PDF files are allowed."]);
                exit();
            }

            // Move the uploaded file to the target directory
            if (!move_uploaded_file($file["tmp_name"], $target_file)) {
                echo json_encode(["status" => "error", "message" => "Error uploading file."]);
                exit();
            }

            // Insert information into the database
            $lectureQuery = "INSERT INTO class_lectures (school_id, class_id, subject, topic, pdf, date) 
                             VALUES (:school_id, :class_id, :subject, :topic, :pdf_path, NOW())";
            $lectureStmt = $this->conn->prepare($lectureQuery);
            $lectureStmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
            $lectureStmt->bindParam(':class_id', $class_id, PDO::PARAM_STR);
            $lectureStmt->bindParam(':subject', $subject, PDO::PARAM_STR);
            $lectureStmt->bindParam(':topic', $topic, PDO::PARAM_STR);
            $lectureStmt->bindParam(':pdf_path', $target_file, PDO::PARAM_STR);

            if ($lectureStmt->execute()) {
                echo json_encode(["status" => "success", "message" => "Lecture PDF uploaded successfully."]);
            } else {
                echo json_encode(["status" => "error", "message" => "Error adding lecture to the database."]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "No file uploaded."]);
        }
    }
}

// Usage
$controller = new LectureUploader();
$controller->handleRequest();
?>
