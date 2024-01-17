<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

// Include your actual database connection code here
include '../../DbConnect.php';

//loading the environment variables
require_once  __DIR__. '/../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..'); 
$dotenv->load();

class UploadNoticeController
{
    private $headers;
    private $encryptedApikey;
    private $expectedApiKey;

    private $conn;
    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv ; // 128 bits IV

    public function __construct()
    {
        // Use the existing database connection from DbConnect.php
        $objDb = new DbConnect;
        $this->conn = $objDb->connect();
         $this->expectedApiKey = $_ENV["NOTICE"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }

    public function handleRequest()
    {
        
        $headers = getallheaders();
        $encryptedApiKey = $headers['Authorization'];
        $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);


        // Check if the API key is valid
    if ($this->validateApiKey($decryptedApiKey)) {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === "POST") {
            if (isset($_POST['student_username'])) {
                $this->userNotice();
            } else {
                $this->schoolNClassNotice();
            }
        } else {
            echo json_encode(['error' => 'Invalid request method. Only POST requests are allowed.']);
        }
    }else {
            // API key is invalid, deny access
            echo json_encode(['error' => 'Access denied. Invalid API key.']);
        }
    }
    
    private function decryptData($data, $encryptionKey, $iv) {
        $plainText = openssl_decrypt($data, 'AES-256-CFB', $encryptionKey, 0, $iv);
        return $plainText;
    }

    private function validateApiKey($apiKey)
    {
        // Compare the extracted API key with the expected API key
        return $apiKey === $this->expectedApiKey;
    }


    public function schoolNClassNotice()
    {
        $school_id = isset($_POST['school_id']) ? $_POST['school_id'] : null;
        $class_id = isset($_POST['class_id']) ? $_POST['class_id'] : null;
        $message = isset($_POST['message']) ? $_POST['message'] : null;
        $date_upto = isset($_POST['date_upto']) ? $_POST['date_upto'] : null;

        if ($school_id != null && $message != null) {
            $response = $this->insertNotice($school_id, $class_id, null, $message, $date_upto);
            echo json_encode($response);
        } else {
            echo json_encode(["error" => "school_id and message are required."]);
        }
    }

    public function userNotice()
    {
        $school_id = isset($_POST['school_id']) ? $_POST['school_id'] : null;
        $class_id = isset($_POST['class_id']) ? $_POST['class_id'] : null;
        $student_username = isset($_POST['student_username']) ? $_POST['student_username'] : null;
        $message = isset($_POST['message']) ? $_POST['message'] : null;
        $date_upto = isset($_POST['date_upto']) ? $_POST['date_upto'] : null;

        if ($school_id != null && $student_username != null && $message != null) {
            $response = $this->insertNotice($school_id, $class_id, $student_username, $message, $date_upto);
            echo json_encode($response);
        } else {
            echo json_encode(["error" => "school_id, student_username, and message are required."]);
        }
    }

    private function insertNotice($school_id, $class_id, $student_username, $message, $date_upto)
    {
        // Convert the date_upto to the 'Y-m-d' format
        $date_upto_formatted = DateTime::createFromFormat('d-m-Y', $date_upto)->format('Y-m-d');

        // Get the current date in 'Y-m-d' format
        $currentDate = date("Y-m-d");

        try {
            $sql = "INSERT INTO notice (school_id, class_id, student_username, message, date_from, date_upto) VALUES (:school_id, :class_id, :student_username, :message, :date_from, :date_upto)";
            $stmt = $this->conn->prepare($sql);

            $stmt->bindParam(':school_id', $school_id);
            $stmt->bindParam(':class_id', $class_id);
            $stmt->bindParam(':student_username', $student_username);
            $stmt->bindParam(':message', $message);

            // Bind the current date
            $stmt->bindParam(':date_from', $currentDate, PDO::PARAM_STR);

            // Create a separate variable for date_upto
            $date_upto_param = $date_upto_formatted;
            $stmt->bindParam(':date_upto', $date_upto_param, PDO::PARAM_STR);

            if ($stmt->execute()) {
                return ['status' => 1, 'message' => 'Notice created successfully.'];
            } else {
                return ['status' => 0, 'message' => 'Failed to create notice.'];
            }
        } catch (PDOException $e) {
            // Handle database errors here
            return ['status' => 0, 'error' => $e->getMessage()];
        }
    }
}

// Usage
$controller = new UploadNoticeController();
$controller->handleRequest();
?>
