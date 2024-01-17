<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include 'DbConnect.php';

//loading the environment variables
require_once  __DIR__. '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . ''); 
$dotenv->load();

class ApiHitsController {
   
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
        $this->expectedApiKey = $_ENV["ATTENDENCE"];
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
        if ($method == 'POST') {
            $this->updateApiHits();
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

    public function updateApiHits() {
        // Get inputs using $_POST
        $school_id = isset($_POST['school_id']) ? trim($_POST['school_id']) : null;
        $date = isset($_POST['date']) ? trim($_POST['date']) : null;

        // Check if required fields are provided
        if (empty($school_id) || empty($date)) {
            echo json_encode(['status' => 0, 'message' => 'Incomplete data. Please provide all required fields.']);
            return;
        }

        $sql = "SELECT * FROM api_hits WHERE school_id = :school_id AND date = :date";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
        $stmt->bindParam(':date', $date, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            // Record exists, update api_hits
            $api_hits = $result['api_hits'] + 1;
            $stmt = $this->conn->prepare("UPDATE api_hits SET api_hits = :api_hits WHERE id = :id");
            $stmt->bindParam(':api_hits', $api_hits, PDO::PARAM_INT);
            $stmt->bindParam(':id', $result['id'], PDO::PARAM_INT);
            $stmt->execute();
            $response = ['status' => 1, 'api_hits' => $api_hits];
        } else {
            // Record does not exist, create a new one
            $stmt = $this->conn->prepare("INSERT INTO api_hits (school_id, api_hits, date) VALUES (:school_id, 1, :date)");
            $stmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
            $stmt->bindParam(':date', $date, PDO::PARAM_STR);
            $stmt->execute();
            $response = ['status' => 1, 'api_hits' => 1];
        }

        // Send JSON response back to the client
        header('Content-Type: application/json');
        echo json_encode($response);
    }
}

// Usage
$controller = new ApiHitsController();
$controller->handleRequest();
?>
