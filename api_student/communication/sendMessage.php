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

class SendMessage
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

    public function handelRequest()
    {

        $headers = getallheaders();
        
        $encryptedApiKey = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
         $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);

        

        if ($this->validateApiKey($decryptedApiKey)) {

            $method = $_SERVER['REQUEST_METHOD'];

            try {
                if ($method == "POST") {
                    $this->sendMessage();
                }
            } catch (Exception $e) {
                echo json_encode(['error' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['error' => 'Access denied. Invalid API key']);
        }
    }
    
     private function decryptData($data, $encryptionKey, $iv) {
        $plainText = openssl_decrypt($data, 'AES-256-CFB', $encryptionKey, 0, $iv);
        return $plainText;
    }
    
    

    public function sendMessage()
    {

        $sender_username = isset($_POST['sender_username']) ? $_POST['sender_username'] : '';
        $receiver_username = isset($_POST['receiver_username']) ? $_POST['receiver_username'] : '';
        $message = isset($_POST['message']) ? $_POST['message'] : '';

        if (!empty($sender_username) && !empty($receiver_username) && !empty($message)) {
            $sql = "INSERT INTO communication (sender_username, receiver_username, message, time_stamp) VALUES (:sender_username, :receiver_username, :message, CURRENT_TIMESTAMP)";
            $stmt = $this->conn->prepare($sql);

            $stmt->bindParam(':sender_username', $sender_username);
            $stmt->bindParam(':receiver_username', $receiver_username);
            $stmt->bindParam(':message', $message);

            if ($stmt->execute()) {
                $response = ['status' => 1, 'message' => 'message sent successfully'];
            } else {
                $response = ['status' => 0, 'message' => 'failed to send message'];
            }
        } else {
            $response = ['status' => 400, 'message' => 'Invalid Data'];
        }

        header('Content-Type: application/json');
        http_response_code($response['status']);
        echo json_encode($response);

    }

    private function validateApiKey($apiKey)
    {
        // Compare the extracted API key with the expected API key
        return $apiKey === $this->expectedApiKey;
    }
}

$controller = new SendMessage();
$controller->handelRequest();
?>









