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


class SchoolMonthlyFee {
    
    private $headers;
    private $expectedApiKey;
    private $encryptedApikey;
    
    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv ; // 128 bits IV
    
    private $conn;


    public function __construct() {
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
 

        if ($method == 'POST') {
            $this->deleteOCLink();
        } else {
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
    

    public function deleteOCLink() {
        $OCLink = $_POST['link_id'];

        if ($OCLink === false || $OCLink === null) {
            http_response_code(400); // Bad Request
            echo json_encode(['error' => "Invalid or missing OCLink parameter"]);
            return;
        }

        try {
            $deleteOCLink = "DELETE FROM virtualclass WHERE id = :OCLink";
            $deleteOCLinkStmt = $this->conn->prepare($deleteOCLink);
            $deleteOCLinkStmt->bindParam(':OCLink', $OCLink, PDO::PARAM_INT);
            $deleteOCLinkStmt->execute();

            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            error_log("Error: " . $e->getMessage());
            http_response_code(500); // Internal Server Error
            echo json_encode(['error' => "Internal server error"]);
        }
    }
}

$controller = new SchoolMonthlyFee();
$controller->handleRequest();
?>
