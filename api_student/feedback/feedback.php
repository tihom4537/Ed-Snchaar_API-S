<?php
include '../DbConnect.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

//loading the environment variables
require_once  __DIR__. '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load();

class FeedbackController {
    private $conn;
    private $expectedApiKey;
    private $encryptedApikey;
    
    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv ; // 128 bits IV

    public function __construct() {
        $objDb = new DbConnect;
        $this->conn = $objDb->connect();
        $this->expectedApiKey = $_ENV["FEEDBACK"];
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
                if ($method === "POST") {
                    $this->sendFeedback();
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

    private function validateApiKey($decryptedApiKey)
    {
        // Compare the extracted API key with the expected API key
        return $decryptedApiKey === $this->expectedApiKey;
    }

    public function sendFeedback() {
        try {
            // Retrieve form data
            $username = isset($_POST['username']) ? $_POST['username'] : '';
            $message = isset($_POST['message']) ? $_POST['message'] : '';

            // Check if username and message are provided in the form data
            if (!empty($username) && !empty($message)) {
                $sql = "INSERT INTO feedback (username, message) VALUES (:username, :message)";
                $stmt = $this->conn->prepare($sql);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':message', $message);

                if ($stmt->execute()) {
                    $response = ['status' => 201, 'message' => 'Feedback sent successfully.'];
                } else {
                    $response = ['status' => 400, 'message' => 'Failed to send feedback.'];
                }
            } else {
                $response = ['status' => 400, 'message' => 'Invalid data.'];
            }
        } catch (PDOException $e) {
            $response = ['status' => 500, 'message' => 'Internal Server Error: ' . $e->getMessage()];
        }

        header('Content-Type: application/json');
        http_response_code($response['status']);
        echo json_encode($response);
    }
}

$feedbackController = new FeedbackController();
$feedbackController->handleRequest();
?>
