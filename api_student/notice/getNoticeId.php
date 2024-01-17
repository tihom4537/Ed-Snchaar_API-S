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

class NoticeApi
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
        $objDb = new DbConnect();
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


        // Check if the API key is valid
    if ($this->validateApiKey($decryptedApiKey)) {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method == 'GET') {
            $this->fetchNotices();
        } else {
            http_response_code(405);
            echo json_encode(['error' => "Method not allowed"]);
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

    private function validateApiKey($apiKey)
    {
        // Compare the extracted API key with the expected API key
        return $apiKey === $this->expectedApiKey;
    }

    public function fetchNotices()
    {
        $username = isset($_GET['username']) ? $_GET['username'] : '';

        if (empty($username)) {
            echo "Username parameter is required.";
            return;
        }

        try {
            // Fetch school_id and class_id for the given username
            $userData = $this->getUserData($username);

            if (!$userData) {
                echo json_encode(['error' => "User not found"]);
                return;
            }

            $schoolId = $userData['school_id'];
            $classId = $userData['class_id'];

            // Fetch the last inserted id for the given school_id and class_id
            $lastNoticeId = $this->getLastNoticeId($schoolId, $classId);

            $response = [
                'username' => $username,
                'school_id' => $schoolId,
                'class_id' => $classId,
                'last_notice_id' => $lastNoticeId,
            ];

            header('Content-Type: application/json');
            echo json_encode($response);
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    private function getLastNoticeId($schoolId, $classId)
    {
        $sql = "SELECT MAX(id) as last_notice_id FROM notice WHERE school_id = :schoolId AND (class_id = '0' OR class_id = :classId)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':schoolId', $schoolId, PDO::PARAM_INT);
        $stmt->bindParam(':classId', $classId, PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['last_notice_id'];
    }
    private function getUserData($username)
    {
        $sql = "SELECT school_id, class_id FROM users WHERE username = :username";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

}


$controller = new NoticeApi();
$controller->handleRequest();
?>