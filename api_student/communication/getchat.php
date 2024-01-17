<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include("../DbConnect.php");

//loading the environment variables
require_once  __DIR__. '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load();

class ChatApi
{
    private $conn;
    private $expectedApiKey;
    private $encryptedApikey;
    
    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv ; // 128 bits IV

    public function __construct()
    {
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
         
         if ($this->validateApiKey($decryptedApiKey)){
        $method = $_SERVER['REQUEST_METHOD'];

        try {
            if ($method == "GET") {
                if (isset($_GET['school_id']) & isset($_GET['class_id'])) {
                    $this->getDistinctConversations();
                }
            }
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
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
    

    private function getDistinctConversations()
{
    $school_id = isset($_GET['school_id']) ? $_GET['school_id'] : '';
    $class_id = isset($_GET['class_id']) ? $_GET['class_id'] : '';

    if (!empty($school_id) && !empty($class_id)) {
        // Prepare the SQL query to fetch teachers
        $query = "SELECT * FROM teacher WHERE school_id = :school_id AND class_id LIKE :class_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':school_id', $school_id);
        // Modify the class_id value to include wildcards for LIKE
        $class_id = '%' . $class_id . '%';
        $stmt->bindParam(':class_id', $class_id);

        $stmt->execute();
        $distinctReceivers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode($distinctReceivers);
    } else {
        $response = ['status' => 400, 'message' => 'Invalid Data'];
        header('Content-Type: application/json');
        http_response_code($response['status']);
        echo json_encode($response);
    }
}


}

$controller = new ChatApi();
$controller->handleRequest();
?>
