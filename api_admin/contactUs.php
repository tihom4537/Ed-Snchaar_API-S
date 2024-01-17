<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include 'DbConnect.php';


//loading the environment variables
require_once  __DIR__. '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load();


class CarouselController {
      private $headers;
    private $encryptedApikey;
    private $expectedApiKey;

    private $conn;
    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv ; // 128 bits IV

    public function __construct() {
        // Use the existing database connection from DbConnect
        $objDb = new DbConnect();
        $this->conn = $objDb->connect();
        $this->expectedApiKey = $_ENV["FEEDBACK"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }

    public function handleRequest() {

        $headers=getallheaders();

        $encryptedApiKey = $headers['Authorization'];
        
        $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);

 
        
        // Check if the API key is valid
    if ($this->validateApiKey($decryptedApiKey)) {

        $method = $_SERVER['REQUEST_METHOD'];

        // assigning the function according to request methods
        if($method == 'POST') {
            $this->addNotice();
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
    
    private function decryptData($data, $encryptionKey, $iv) {
        $plainText = openssl_decrypt($data, 'AES-256-CFB', $encryptionKey, 0, $iv);
        return $plainText;
    }

    private function validateApiKey($apiKey)
    {
        // Compare the extracted API key with the expected API key
        return $apiKey === $this->expectedApiKey;
    }

    public function addNotice() {
        // Get inputs using $_POST
        
        $jsonData = file_get_contents("php://input");
        $data = json_decode($jsonData, true);

        $school_id = isset($data['school_id']) ? trim($data['school_id']) : null;
        $inquiry_type = isset($data['inquiry_type']) ? trim($data['inquiry_type']) : null;
        $subject = isset($data['subject']) ? trim($data['subject']) : null;
        $message = isset($data['message']) ? trim($data['message']) : null;

  
        // Check if required fields are provided
        if(empty($school_id) || empty($inquiry_type) || empty($subject)) {
            echo json_encode(['status' => 0, 'message' => 'Incomplete data. Please provide all required fields.']);
            return;
        }

        // Additional validation for "Write to us" inquiry type
        if($inquiry_type == "WriteToUs" && empty($message)) {
            echo json_encode(['status' => 0, 'message' => 'There must be a message for Write to us inquiry.']);
            return;
        }

        $sql = "INSERT INTO contactus (school_id, inquiry_type, subject, message) VALUES (:school_id, :inquiry_type, :subject, :message)";
        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(':school_id', $school_id);
        $stmt->bindParam(':inquiry_type', $inquiry_type);
        $stmt->bindParam(':subject', $subject);
        $stmt->bindParam(':message', $message);

        if($stmt->execute()) {
            http_response_code(201);
            $response = ['status' => 1, 'message' => 'Record created successfully.'];
        } else {
            $response = ['status' => 0, 'message' => 'Failed to create record.'];
        }

        // Send JSON response back to the client
        header('Content-Type: application/json');
        echo json_encode($response);
    }



}

// Usage
$controller = new CarouselController();
$controller->handleRequest();
?>