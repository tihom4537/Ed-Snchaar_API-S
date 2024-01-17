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
{   private $headers;
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
        $this->expectedApiKey = $_ENV["SYLLABUS"];
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

            // assigning the function according to request methods
            if ($method == 'POST') {
                $this->addSyllabus();
            } else {
                // Handle exceptions here
                http_response_code(405); // Method Not Allowed
                echo json_encode(['error' => "Method not allowed"]);
            }
        } else {
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

    public function addSyllabus()
    {
        // must pass pass school ID as school_id, Class ID as class_id and status as status
        $class_id = isset($_POST['class_id']) ? ($_POST['class_id']) : '';
        $school_id = isset($_POST['school_id']) ? ($_POST['school_id']) : '';
        $subject = isset($_POST['subject']) ? $_POST['subject'] : '';
        $subject_content = isset($_POST['subject_content']) ? ($_POST['subject_content']) : '';
        $status = isset($_POST['status']) ? intval($_POST['status']) : '';


        try {
            $sql = "INSERT INTO syllabus (class_id, school_id, subject, subject_content, status) 
                    VALUES (:class_id, :school_id, :subject, :subject_content, :status )";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':class_id', $class_id, PDO::PARAM_STR);
            $stmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
            $stmt->bindParam(':subject', $subject, PDO::PARAM_STR);
            $stmt->bindParam(':subject_content', $subject_content, PDO::PARAM_STR);
            $stmt->bindParam(':status', $status, PDO::PARAM_INT);

            if ($stmt->execute()) {
                echo "Syllabus added successfully.";
            } else {
                echo "Error adding syllabus.";
            }
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
        }
    }
}

// Usage
$controller = new FeeManagementController();
$controller->handleRequest();
?>
