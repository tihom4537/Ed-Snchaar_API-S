<?php

// Allow cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

// Include the database connection class
include '../DbConnect.php';

// Loading the environment variables
require_once  __DIR__. '/../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..'); 
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
        $this->expectedApiKey = $_ENV["NOTICE"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }

    public function handleRequest()
    {
         $headers = getallheaders();

        $encryptedApiKey = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
         $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);

    if ($this->validateApiKey($decryptedApiKey)) {
        $method = $_SERVER['REQUEST_METHOD'];

        // Assigning the function according to request methods
        if ($method == 'GET') {
            $this->fetchSchoolEvents(); // Corrected function name
        } else {
            // Handle exceptions here
            http_response_code(405); // Method Not Allowed
            echo json_encode(['error' => "Method not allowed"]);
        }
    }else {
            // API key is invalid, deny access
            echo json_encode(['error' => 'Access denied. Invalid API key.']);
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

    public function fetchSchoolEvents()
    {
        $schoolId = $_GET['school_id'] ?? null;
        $eventsQuery = "SELECT * FROM school_events WHERE school_id = :schoolId";
        $eventsStmt = $this->conn->prepare($eventsQuery);
        $eventsStmt->bindParam(':schoolId', $schoolId, PDO::PARAM_STR);
        $eventsStmt->execute();
        $schoolEvents = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Check if events were found
        if (empty($schoolEvents)) {
            $this->sendResponse(200, []);
        } else {
            $this->sendResponse(200, $schoolEvents);
        }
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
$controller = new SchoolExtraFetch();
$controller->handleRequest();
?>
