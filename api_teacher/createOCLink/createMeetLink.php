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


class ClassLinkController {
   
     private $headers;
    private $expectedApiKey;
    private $encryptedApikey;
    
    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv ; // 128 bits IV
    
    private $conn;

    public function __construct() {
        // Use the existing database connection from DbConnect
        $objDb = new DbConnect();
        $this->conn = $objDb->connect();
        $this->expectedApiKey = $_ENV["COM"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }

    public function handleRequest() {

         $headers = getallheaders();
        $encryptedApiKey = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);


        if ($this->validateApiKey($decryptedApiKey)) {

        $method = $_SERVER['REQUEST_METHOD'];

        // assigning the function according to request methods
        if($method == 'POST') {
            $this->addVirtualClassLink();
        } else {
            // Handle exceptions here
            // http_response_code(405); // Method Not Allowed
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


    public function addVirtualClassLink() {
        // Get inputs using $_POST
        $school_id = isset($_POST['school_id']) ? trim($_POST['school_id']) : '';
        $class_id = isset($_POST['class_id']) ? trim($_POST['class_id']) : '';
        $username = isset($_POST['teacher_username']) ? trim($_POST['teacher_username']) : '';
        $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
        $link = isset($_POST['link']) ? trim($_POST['link']) : '';

        // Check if the school ID is available

        // Corrected SQL query by adding a colon before 'message'
        $sql = "INSERT INTO virtualclass (school_id, class_id, teacher_username, subject, link) VALUES (:school_id, :class_id, :username, :subject, :link)";
        $stmt = $this->conn->prepare($sql);

        $stmt->bindParam(':school_id', $school_id);
        $stmt->bindParam(':class_id', $class_id);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':subject', $subject);
        $stmt->bindParam(':link', $link);

        if($stmt->execute()) {
            $response = ['status' => 1, 'message' => 'Record created successfully.'];
        } else {
            $response = ['status' => 0, 'message' => 'Failed to create record.'];
        }

        // Echo the JSON-encoded response
        echo json_encode($response);
    }


}

// Usage
$controller = new ClassLinkController();
$controller->handleRequest();
?>