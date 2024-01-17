<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../DbConnect.php'; // Include your actual database connection code

//loading the environment variables
require_once  __DIR__. '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load();

class GetMessageController
{    private $conn;
    private $expectedApiKey;
    private $encryptedApikey;
    
    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv ; // 128 bits IV
    

    public function __construct()
    {
        // Use the existing database connection from DbConnect.php
        $objDb = new DbConnect;
        $this->conn = $objDb->connect();
        $this->expectedApiKey = $_ENV["COM"];
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

            try {
                if ($method === "GET") {
                    $this->getMessages();
                } else {
                    echo json_encode(['error' => 'Invalid HTTP method. Only GET is allowed.']);
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

    public function getMessages()
    {
        try {
            $sender_username = $_GET['sender_username'] ?? null;
            $receiver_username = $_GET['receiver_username'] ?? null;

            if ($sender_username === null || $receiver_username === null) {
                echo json_encode(['error' => 'Both sender_username and receiver_username must be provided.']);
                return;
            }

            // $sql = "SELECT * FROM communication WHERE sender_username = :sender_username  AND receiver_username = :receiver_username";
            
            $sql ="(
  (SELECT * FROM communication WHERE sender_username = :sender_username AND receiver_username = :receiver_username)
  UNION
  (SELECT * FROM communication WHERE sender_username = :receiver_username AND receiver_username = :sender_username)
)
ORDER BY id ASC";




            // Prepare the SQL query
            $stmt = $this->conn->prepare($sql);

            // Bind parameters
            $stmt->bindParam(':sender_username', $sender_username);
            $stmt->bindParam(':receiver_username', $receiver_username);

            // Execute the query
            $stmt->execute();

            // Fetch data as an associative array
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Close the database connection
            $this->conn = null;

            // Return data as JSON
            echo json_encode($data);
        } catch (PDOException $e) {
            // Handle any exceptions here
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
    }
}

// Usage
$controller = new GetMessageController();
$controller->handleRequest();
?>
