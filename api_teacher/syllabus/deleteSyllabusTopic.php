<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../DbConnect.php';

//loading the environment variables
require_once  __DIR__. '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load();


class FeeManagementController
{   
     private $headers;
    private $expectedApiKey;
    private $encryptedApikey;
    
    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv ; // 128 bits IV
    
    private $conn;

    public function __construct()
    {
        // Use the existing database connection from DbConnect
        $objDb = new DbConnect();
        $this->conn = $objDb->connect();
         $this->expectedApiKey = $_ENV["SYLLABUS"];
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

        // assigning the function according to request methods
        if ($method == 'POST') {
            // Use $_GET to get the syllabus_id from the URL
            $syllabus_id = isset($_GET['syllabus_id']) ? $_GET['syllabus_id'] : null;

            if (empty($syllabus_id)) {
                http_response_code(400); // Bad Request
                echo json_encode(['error' => "syllabus_id is required for deletion."]);
                return;
            }

            $result = $this->deleteMarks($syllabus_id);

            if ($result) {
                echo json_encode(['message' => "Topic associated with syllabus deleted successfully."]);
            } else {
                http_response_code(404); // Not Found
                echo json_encode(['error' => "Unable to find syllabus with provided syllabus_id."]);
            }
        } else {
            // Handle exceptions here
            http_response_code(405); // Method Not Allowed
            echo json_encode(['error' => "Method not allowed"]);
        }
    }else {
            // API key is invalid, deny access
            http_response_code(403); // Forbidden
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

    private function deleteMarks($syllabus_id)
    {
        try {
            // Delete marks records associated with the given syllabus_id
            $sql = "DELETE FROM syllabus WHERE id = :syllabus_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':syllabus_id', $syllabus_id, PDO::PARAM_INT);
            $stmt->execute();

            // Check how many rows were affected by the DELETE query
            $rowCount = $stmt->rowCount();

            // If at least one row was affected, consider it a successful deletion
            if ($rowCount > 0) {
                return true;
            } else {
                return false;
            }
        } catch (PDOException $e) {
            echo $e->getMessage();
            return false; // Return false in case of an exception
        }
    }
}

// Usage
$controller = new FeeManagementController();
$controller->handleRequest();
?>