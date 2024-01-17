<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Allow cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

// Include the database connection class
include 'DbConnect.php';


//loading the environment variables
require_once  __DIR__. '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load();

class ComplaintStatusFetch
{
    private $conn;
    private $expectedApiKey;
    private $encryptedApikey;
    
    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv ; // 128 bits IV

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
        
         $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);

    if ($this->validateApiKey($decryptedApiKey)) {
        $method = $_SERVER['REQUEST_METHOD'];

        // Assigning the function according to request methods
        if ($method == 'GET') {
            $this->fetchComplaints(); // Corrected function name
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

    private function validateApiKey($decryptedApiKey)
    {
        // Compare the extracted API key with the expected API key
        return $decryptedApiKey === $this->expectedApiKey;
    }

    public function fetchComplaints()
{
    $school_id = isset($_GET['school_id']) ? $_GET['school_id'] : '';

    // Check if the school_id parameter is not provided
    if (empty($school_id)) {
        echo json_encode(['error' => "school_id parameter is required."]); // Return JSON error response
        return;
    }

    // Use prepared statements to prevent SQL injection
    $complaintQuery = "SELECT * FROM complaints WHERE school_id = :school_id AND class_id = 0"; // Modified query
    $complaintStmt = $this->conn->prepare($complaintQuery);
    $complaintStmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
    $complaintStmt->execute();
    $complaints = $complaintStmt->fetchAll(PDO::FETCH_ASSOC);

    // Check if complaints were found
    if (empty($complaints)) {
        echo json_encode(['message' => "No complaints raised for the specified school_id and class_id = 0."]);
    } else {
        // Return JSON response with complaints
        echo json_encode($complaints);
    }
}

}

// Usage
$controller = new ComplaintStatusFetch();
$controller->handleRequest();
?>
