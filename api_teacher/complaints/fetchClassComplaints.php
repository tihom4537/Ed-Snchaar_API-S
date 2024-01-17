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

class ComplaintFetch
{    private $conn;
    private $expectedApiKey;
    private $encryptionKey;
    private $iv;
    
    public function __construct()
    {
        // Use the existing database connection from DbConnect
        $objDb = new DbConnect();
        $this->conn = $objDb->connect();
        $this->expectedApiKey = $_ENV["FEEDBACK"];
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
            $this->fetchComplaints();
        } else {
            $this->sendErrorResponse(405, 'Method not allowed');
        }
    }else {
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

    public function fetchComplaints()
    {
        $schoolId = isset($_GET['school_id']) ? $_GET['school_id'] : '';
        $classId = isset($_GET['class_id']) ? $_GET['class_id'] : '';

        // Check if the school_id and class_id parameters are not provided
        if (empty($schoolId) || empty($classId)) {
            $this->sendErrorResponse(400, 'school_id and class_id parameters are required.');
        }

        // Use prepared statements to prevent SQL injection
        $complaintQuery = "SELECT * FROM complaints WHERE school_id = :school_id AND class_id = :class_id";
        $complaintStmt = $this->conn->prepare($complaintQuery);
        $complaintStmt->bindParam(':school_id', $schoolId, PDO::PARAM_STR);
        $complaintStmt->bindParam(':class_id', $classId, PDO::PARAM_STR);
        $complaintStmt->execute();
        $complaints = $complaintStmt->fetchAll(PDO::FETCH_ASSOC);

        // Check if complaints were found
        if (empty($complaints)) {
            $this->sendSuccessResponse([]);
        } else {
            // Return JSON response with complaints
            $this->sendSuccessResponse($complaints);
        }
    }

    private function sendErrorResponse($statusCode, $errorMessage)
    {
        http_response_code($statusCode);
        echo json_encode(['error' => $errorMessage]);
        exit;
    }

    private function sendSuccessResponse($data)
    {
        echo json_encode($data);
        exit;
    }
}

// Usage
$controller = new ComplaintFetch();
$controller->handleRequest();
?>