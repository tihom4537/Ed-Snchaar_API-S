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
                // Use $_GET to get the exam_id from the URL
                $exam_id = isset($_GET['exam_id']) ? $_GET['exam_id'] : null;

                if (empty($exam_id)) {
                    http_response_code(400); // Bad Request
                    echo json_encode(['error' => "Exam_id is required for deletion."]);
                    return;
                }

                $result = $this->deleteExamRecord($exam_id);
                
                if ($result) {
                    echo json_encode(['message' => "Exam and associated marks deleted successfully."]);
                } else {
                    http_response_code(404); // Not Found
                    echo json_encode(['error' => "Unable to find exam with provided exam_id."]);
                }
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

    private function deleteExamRecord($exam_id)
{
    try {
        // Delete exam record
        $sql = "DELETE FROM Exam WHERE ExamId = :exam_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':exam_id', $exam_id, PDO::PARAM_INT);

        $stmt->execute();

        // Check if any rows were affected
        if ($stmt->rowCount() > 0) {
            // Rows affected, delete associated marks records
            $this->deleteMarks($exam_id);
            return true;
        } else {
            // No rows affected, exam not found
            return false;
        }
    } catch (PDOException $e) {
        // Log or handle the exception as needed
        return false;
    }
}


    private function deleteMarks($exam_id)
    {
        try {
            // Delete marks records associated with the given exam_id
            $sql = "DELETE FROM marks WHERE exam_id = :exam_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':exam_id', $exam_id, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            // Handle the error as needed
        }
    }

}

// Usage
$controller = new FeeManagementController();
$controller->handleRequest();
?>
