<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

// Include your actual database connection code here
include '../../DbConnect.php';

//loading the environment variables
require_once  __DIR__. '/../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..'); 
$dotenv->load();

class DeleteNoticeController
{
    private $headers;
    private $encryptedApikey;
    private $expectedApiKey;

    private $conn;
    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv ; // 128 bits IV

   

    public function __construct()
    {
        // Use the existing database connection from DbConnect.php
        $objDb = new DbConnect;
        $this->conn = $objDb->connect();
         $this->expectedApiKey = $_ENV["NOTICE"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }

    public function handleRequest()
    {
        // Extract the API key from the request headers
        $headers = getallheaders();
        $encryptedApiKey = $headers['Authorization'];
        $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);

    if ($this->validateApiKey($decryptedApiKey)) {    
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method == "GET") {
            $id = isset($_GET['id']) ? $_GET['id'] : null;

            if ($id != null) {
                $response = $this->deleteNotice($id);
                echo json_encode($response);
            } else {
                echo json_encode(["error" => "Notice ID is required for deletion."]);
            }
        } else {
            echo json_encode(['error' => 'Invalid request method. Only DELETE requests are allowed.']);
        }
    }else {
            // API key is invalid, deny access
            echo json_encode(['error' => 'Access denied.COOL Invalid API key.']);
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
 
    private function deleteNotice($id)
    {
        try {
            $sql = "DELETE FROM notice WHERE id = :id";
            $stmt = $this->conn->prepare($sql);

            $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                return ['status' => 1, 'message' => 'Notice deleted successfully.'];
            } else {
                return ['status' => 0, 'message' => 'Failed to delete notice.'];
            }
        } catch (PDOException $e) {
            // Handle database errors here
            return ['status' => 0, 'error' => $e->getMessage()];
        }
    }
}

// Usage
$controller = new DeleteNoticeController();
$controller->handleRequest();
?>
