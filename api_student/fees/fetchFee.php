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
        $this->expectedApiKey = $_ENV["FEES"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }


    
    public function handleRequest()
{
    $headers = getallheaders();
    // $apiKey = $headers['authorization'];
    $encryptedApiKey = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
    $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);

    if ($this->validateApiKey($decryptedApiKey)) {
        $method = $_SERVER['REQUEST_METHOD'];
        // $action = $_GET['action'] ?? '';
        try {
            if ($method === "GET") {
                $this->fetchFees();
            }
        } catch (Exception $e) {
            // Handle exceptions here
            echo json_encode(['error' => $e->getMessage()]);
        }
    } else {
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


    public function fetchFees()
    {
        $username = isset($_GET['username']) ? $_GET['username'] : '';

        if (empty($username)) {
            echo "Username parameter is required.";
            return;
        }

        try {
            $sql = "SELECT * FROM fees WHERE username = :username";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();

            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: application/json');
            echo json_encode($data);
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
        }
    }
}

// Usage
$controller = new FeeManagementController();
$controller->handleRequest();

?>