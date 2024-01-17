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

class SchoolLogin {
    private $conn;
    private $expectedApiKey;
    private $encryptedApikey;
    
    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv ; // 128 bits IV

    public function __construct() {
        $objDb = new DbConnect();
        $this->conn = $objDb->connect();
        $this->expectedApiKey = $_ENV["LOGIN"];
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
            $this->fetchSchoolFees();
        } else {
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

    public function fetchSchoolFees() {
        try {
            
            
            $jsonData = file_get_contents("php://input");
        $data = json_decode($jsonData, true);

        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

            if (empty($username) || empty($password)) {
                // http_response_code(400); // Bad Request
                echo json_encode(['error' => 'Invalid request parameters']);
                return;
            }

            $query = "SELECT school_id, school_secure_key FROM login_school WHERE school_username = :username AND school_password = :password";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':password', $password);
            $stmt->execute();

            $schoolData = $stmt->fetch(PDO::FETCH_ASSOC); // Use fetch instead of fetchAll

            header('Content-Type: application/json'); // Move this line up

            if ($stmt->rowCount() == 1) {
                echo json_encode(['success' => true, 'data' => $schoolData]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Username or Password is invalid']);
            }
        } catch (PDOException $e) {
            http_response_code(500); // Internal Server Error
            echo json_encode(['error' => 'Internal Server Error']);
        }
    }
}

$controller = new SchoolLogin();
$controller->handleRequest();
?>
