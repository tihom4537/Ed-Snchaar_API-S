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

class SchoolExtraFetch
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
                $this->fetchSchool();
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

    public function fetchSchool()
    {
        $schoolId = isset($_GET['school_id']) ? $_GET['school_id'] : '';

        if (empty($schoolId)) {
            $this->sendErrorResponse(400, 'school_id parameter is required.');
            return; // Stop further execution
        }

        $schoolQuery = "SELECT school_name, website, email, facebook, instagram, linkedin, other FROM school WHERE id_school = :school_id";
        $schoolStmt = $this->conn->prepare($schoolQuery);
        $schoolStmt->bindParam(':school_id', $schoolId, PDO::PARAM_STR);
        $schoolStmt->execute();
        $schoolDetails = $schoolStmt->fetchAll(PDO::FETCH_ASSOC);

        // Check if school details were found
        if (empty($schoolDetails)) {
            $this->sendSuccessResponse(['message' => 'No school details found for the given school_id']);
            return; // Stop further execution
        }

        // Return JSON response with school details
        $this->sendSuccessResponse($schoolDetails);
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
$controller = new SchoolExtraFetch();
$controller->handleRequest();
?>
