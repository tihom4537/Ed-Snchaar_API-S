<?php
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include 'DbConnect.php';

//loading the environment variables
require_once  __DIR__. '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load();

class ComplaintController {
     private $conn;
    private $expectedApiKey;
    private $encryptedApikey;
    
    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv ; // 128 bits IV

    public function __construct() {
        $db = new DbConnect();
        $this->conn = $db->connect();
         $this->expectedApiKey = $_ENV["SCHOOL"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }

    public function handleRequest() {
        $headers = getallheaders();

        $encryptedApiKey = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
         $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);

    if ($this->validateApiKey($decryptedApiKey)) {
        $method = $_SERVER['REQUEST_METHOD'];

        if($method === 'POST') {
            $this->updateComplaint();
        } else {
            http_response_code(405); // Method Not Allowed
            echo json_encode(['status' => 405, 'message' => 'Method Not Allowed']);
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
    
    public function updateComplaint() {
        try {
            // Trim and sanitize data
            $complaint_id = $_POST['complaint_id'] ?? null;
            $status = $_POST['status'] ?? null;
             $message = $_POST['message'] ?? null;
             

            if($status != 1 && $status != 2) {
                throw new Exception('Invalid Complaint Status');
            }


            // Now modify $status
            if($status == 1) {
                $status = "Reviewing";
            } else if($status == 2) {
                $status = "ActionTaken";
            }
            
             
            // Validation (add more as needed)
            if(empty($complaint_id) || empty($status)) {
                throw new Exception('Both, Updated Status and Complaint ID are required.');
            }

            $sql = "UPDATE complaints SET status = :status, response = :message WHERE complaint_id = :complaint_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':complaint_id', $complaint_id);
            $stmt->bindParam(':status', $status);
             $stmt->bindParam(':message', $message);

            if($stmt->execute()) {
                // Include a success message in the response
                echo json_encode(['status' => 204, 'message' => 'Complaint Status updated successfully.']);
                return;
            } else {
                throw new Exception('Failed to update Complaint Status.');
            }

        } catch (Exception $e) {
            error_log("Error: ".$e->getMessage());
            // http_response_code(500); // Internal Server Error
            echo json_encode(['status' => 500, 'message' => 'Internal Server Error']);
        }
    }
}

$controller = new ComplaintController();
$controller->handleRequest();
?>