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

class virtualclassController
{   
    private $conn;
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
        $this->expectedApiKey = $_ENV["CLASSLINK"];
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

        if ($method === "GET") {
            $this->fetchLinks();
        } else {
            http_response_code(405); // Method Not Allowed
            echo json_encode(['error' => "Method not allowed"]);
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

    private function validateApiKey($decryptedApiKey)
    {
        // Compare the extracted API key with the expected API key
        return $decryptedApiKey === $this->expectedApiKey;
    }
    
    

    public function fetchLinks()
    {
        $school_id = isset($_GET['school_id']) ? $_GET['school_id'] : null;
        $class_id = isset($_GET['class_id']) ? $_GET['class_id'] : null;

        if ($school_id !== null && $class_id !== null) {
            $data = $this->fetchDataWithClass($school_id, $class_id);
        } elseif ($school_id !== null && $class_id === null) {
            $data = $this->fetchDataWithoutClass($school_id);
        } else {
            echo json_encode(["error" => "At least school_id or class_id is required."]);
            return;
        }

        echo json_encode($data);
    }

    private function fetchDataWithClass($school_id, $class_id)
{
    // Database retrieval logic using PDO
    try {
        $sql = "SELECT vc.id, vc.school_id, vc.class_id, vc.teacher_username, t.name as teacher_name, vc.subject, vc.link 
                FROM virtualclass vc 
                INNER JOIN teacher t ON vc.teacher_username = t.username
                WHERE vc.school_id = :school_id AND vc.class_id = :class_id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
        $stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $data;
    } catch (PDOException $e) {
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
        return [];
    }
    
}

    private function fetchDataWithoutClass($school_id)
    {
        // Database retrieval logic using PDO
        try {
            $sql = "SELECT * FROM virtualclass WHERE school_id = :school_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $data;
        } catch (PDOException $e) {
            echo json_encode(["error" => "Database error: " . $e->getMessage()]);
            return [];
        }
    }
}

// Usage
$controller = new virtualclassController();
$controller->handleRequest();
?>
