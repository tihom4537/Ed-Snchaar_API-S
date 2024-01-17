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
require_once  __DIR__. '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load();

class BirthdayFetch
{    
    private $conn;
    private $expectedApiKey;
    private $encryptionKey;
    private $iv;

    public function __construct()
    {
        // Use the existing database connection from DbConnect
        $objDb = new DbConnect();
        $this->conn = $objDb->connect();
        $this->expectedApiKey = $_ENV["TEACHER"];
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
            if ($method == 'GET') {
                $this->fetchBirthdayStudents();
            } else {
                $this->sendErrorResponse(405, 'Method not allowed');
            }
        } else {
            $this->sendErrorResponse(403, 'Access denied. Invalid API key.');
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

      public function fetchBirthdayStudents()
    {
        $schoolId = isset($_GET['school_id']) ? $_GET['school_id'] : '';
        $classId = isset($_GET['class_id']) ? $_GET['class_id'] : '';

        // Check if the school_id and class_id parameters are not provided
        if (empty($schoolId) || empty($classId)) {
            $this->sendErrorResponse(400, 'school_id and class_id parameters are required.');
        }

        // Use prepared statements to prevent SQL injection
        $birthdayQuery = "SELECT name, DOB FROM users WHERE MONTH(DOB) = MONTH(CURRENT_DATE()) AND school_id = :school_id AND class_id = :class_id";
        $birthdayStmt = $this->conn->prepare($birthdayQuery);
        $birthdayStmt->bindParam(':school_id', $schoolId, PDO::PARAM_STR);
        $birthdayStmt->bindParam(':class_id', $classId, PDO::PARAM_STR);
        $birthdayStmt->execute();
        $birthdayStudents = $birthdayStmt->fetchAll(PDO::FETCH_ASSOC);

        // Check if birthday students were found
        // if (empty($birthdayStudents)) {
        //     $this->sendErrorResponse(404, 'No birthday students found for the current month');
        // } else {
        //     // Return JSON response with birthday students
            echo json_encode($birthdayStudents);
        //     exit;
        // }
    }

    private function sendErrorResponse($statusCode, $errorMessage)
    {
        http_response_code($statusCode);
        echo json_encode(['error' => $errorMessage]);
        exit;
    }
}

// Usage
$controller = new BirthdayFetch();
$controller->handleRequest();
?>
