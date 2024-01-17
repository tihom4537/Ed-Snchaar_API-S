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
         $this->expectedApiKey = $_ENV["NOTICE"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }

    public function handleRequest() {


         $headers=getallheaders();

        $encryptedApiKey =isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        // var_dump($encryptedApiKey);
        
        $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);

 
        
        // Check if the API key is valid
    if ($this->validateApiKey($decryptedApiKey)) {
        $method = $_SERVER['REQUEST_METHOD'];

        // assigning the function according to request methods
        if($method == 'POST') {
            $this->addNotice();
        } else {
            // Handle exceptions here
            // http_response_code(405); // Method Not Allowed
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
    $notice = json_decode(file_get_contents('php://input'));

    $school_id = $notice->school_id ?? '';
    $class_id = 0;
    $date_upto = $notice->date_upto ?? ''; 
    $message = $notice->message ?? '';
    $date_from = $date_from ?? date('Y-m-d');
    
    // Check if the school ID is available

    // Corrected SQL query by adding a colon before 'message'
    $sql = "INSERT INTO notice (school_id, class_id, message, date_upto,date_from) VALUES (:school_id, :class_id, :message, :date_upto,:date_from)";
    $stmt = $this->conn->prepare($sql);

    // Fixed binding parameters for 'message', 'date_from', and 'date_upto'
    $stmt->bindParam(':school_id', $school_id);
    $stmt->bindParam(':class_id', $class_id);
    $stmt->bindParam(':message', $message); // Updated to bind 'message'
    $stmt->bindParam(':date_upto', $date_upto);
     $stmt->bindParam(':date_from', $date_from);


    if($stmt->execute()) {
        $response = ['status' => 1, 'message' => 'Record created successfully.'];
    } else {
        $response = ['status' => 0, 'message' => 'Failed to create record.'];
    }

    // Send the response back to the client
    header('Content-Type: application/json');
    echo json_encode($response);
}


}

// Usage
$controller = new CarouselController();
$controller->handleRequest();
?>