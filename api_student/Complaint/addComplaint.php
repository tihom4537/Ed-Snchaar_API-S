<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../DbConnect.php';

// Loading the environment variables
require_once  __DIR__. '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load();

class AddComplaintApi
{
    private $conn;
    private $expectedApiKey;
    private $encryptionKey;
    private $iv;

    public function __construct()
    {
        $objDb = new DbConnect();
        $this->conn = $objDb->connect();
        $this->expectedApiKey = $_ENV["FEEDBACK"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }

    public function handleRequest()
    {
        $headers = getallheaders();
        $encryptedApiKey = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        $decryptedApiKey = $this->decryptData($encryptedApiKey);

        if ($this->validateApiKey($decryptedApiKey)) {
            $method = $_SERVER['REQUEST_METHOD'];

            if ($method == 'POST') {
                $this->addComplaint();
            } else {
                $this->sendErrorResponse(405, 'Method not allowed');
            }
        } else {
            $this->sendErrorResponse(403, 'Access denied. Invalid API key.');
        }
    }

    private function decryptData($data)
    {
        $plainText = openssl_decrypt($data, 'AES-256-CFB', $this->encryptionKey, 0, $this->iv);
        return $plainText;
    }

    private function validateApiKey($decryptedApiKey)
    {
        return $decryptedApiKey === $this->expectedApiKey;
    }

    private function sendErrorResponse($statusCode, $errorMessage)
    {
        http_response_code($statusCode);
        echo json_encode(['error' => $errorMessage]);
        exit;
    }

    public function addComplaint()
    {
        $requiredParams = ['school_id', 'username', 'subject', 'message', 'class_id'];

        $schoolId = $_POST['school_id'];
        $username = $_POST['username'];
        $subject = $_POST['subject'];
        $message = $_POST['message'];
        $classId = $_POST['class_id'];
        
        $requiredParams = ['school_id', 'username', 'subject', 'message', 'class_id'];

        // Check if all required parameters are present in the POST request
        foreach ($requiredParams as $param) {
    if (!isset($_POST[$param]) || $_POST[$param] === '') {
        echo json_encode(['status' => 0, 'message' => 'All parameters are required']);
        return;
    }
}

        $sql = "INSERT INTO complaints (school_id, class_id, username, subject, message, status) VALUES (:schoolId, :classId, :username, :subject, :message, 'Submitted')";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':schoolId', $schoolId, PDO::PARAM_STR);
        $stmt->bindParam(':classId', $classId, PDO::PARAM_STR);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->bindParam(':subject', $subject, PDO::PARAM_STR);
        $stmt->bindParam(':message', $message, PDO::PARAM_STR);
        $stmt->execute();

        echo json_encode(['status' => 1, 'message' => 'Complaint added successfully']);
    }
}

$controller = new AddComplaintApi();
$controller->handleRequest();
?>
