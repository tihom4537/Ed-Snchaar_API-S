<?php
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../../DbConnect.php';

//loading the environment variables
require_once  __DIR__. '/../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..'); 
$dotenv->load();

class TestController
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
        $objDb = new DbConnect();
        $this->conn = $objDb->connect();
        $this->expectedApiKey = $_ENV["UPDATESTUDENT"];
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
                $username = isset($_GET['username']) ? $_GET['username'] : null;

                if ($username !== null) {
                    // You can continue using $_GET for other parameters here
                    $name = isset($_GET['name']) ? $_GET['name'] : null;
                    $class_id = isset($_GET['class_id']) ? $_GET['class_id'] : null;
                    $roll_no = isset($_GET['roll_no']) ? $_GET['roll_no'] : null;
                    $father_name = isset($_GET['father_name']) ? $_GET['father_name'] : null;
                    $mother_name = isset($_GET['mother_name']) ? $_GET['mother_name'] : null;
                    $email = isset($_GET['email']) ? $_GET['email'] : null;
                    $mobile = isset($_GET['mobile']) ? $_GET['mobile'] : null;
                    $phone = isset($_GET['phone']) ? $_GET['phone'] : null;

                    $result = $this->updateStudent($username, $name, $class_id, $roll_no, $father_name, $mother_name, $email, $mobile, $phone);
                    $this->sendJsonResponse($result);
                } else {
                    $this->sendErrorResponse("Username is required for the update.", 400);
                }
            } else {
                $this->sendErrorResponse("Invalid HTTP Method.", 405);
            }
        } catch (Exception $e) {
            $this->sendErrorResponse("Internal Server Error: " . $e->getMessage(), 500);
        }
    } else {
        $this->sendErrorResponse('Access denied. Invalid API key.', 403);
    }
}

private function updateStudent($username, $name, $class_id, $roll_no, $father_name, $mother_name, $email, $mobile, $phone)
{
    try {
        if (!$username) {
            throw new Exception("Username is required for the update.");
        }

        if (!$this->conn) {
            throw new Exception("Database connection not established.");
        }

        $sql = "UPDATE users SET 
            name = :name, 
            class_id = :class_id, 
            roll_no = :roll_no, 
            father_name = :father_name, 
            mother_name = :mother_name, 
            email = :email, 
            mobile = :mobile, 
            phone = :phone 
            WHERE username = :username";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':username', $username, PDO::PARAM_STR);

        // Bind parameters securely
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':class_id', $class_id, PDO::PARAM_STR);
        $stmt->bindValue(':roll_no', $roll_no, PDO::PARAM_INT);
        $stmt->bindValue(':father_name', $father_name, PDO::PARAM_STR);
        $stmt->bindValue(':mother_name', $mother_name, PDO::PARAM_STR);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':mobile', $mobile, PDO::PARAM_INT);
        $stmt->bindValue(':phone', $phone, PDO::PARAM_INT);

        $stmt->execute();

        return ['message' => "Student with username $username updated successfully."];
    } catch (Exception $e) {
        error_log("Error in updateStudent: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

   private function decryptData($data, $encryptionKey, $iv) {
        $plainText = openssl_decrypt($data, 'AES-256-CFB', $encryptionKey, 0, $iv);
        return $plainText;
    }

    private function validateApiKey($apiKey)
    {
        return $apiKey === $this->expectedApiKey;
    }

    

    private function bindIfSet($stmt, $data, $field, $paramType)
    {
        if (isset($data[$field])) {
            $stmt->bindValue(':' . $field, $data[$field], $paramType);
        }
    }

    private function sendJsonResponse($data, $statusCode = 200)
    {
        header("Content-Type: application/json");
        http_response_code($statusCode);
        echo json_encode($data);
    }

    private function sendErrorResponse($message, $statusCode)
    {
        $this->sendJsonResponse(['error' => $message], $statusCode);
    }
}

$testController = new TestController();
$testController->handleRequest();
?>
