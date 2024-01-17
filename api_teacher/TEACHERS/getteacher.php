<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../DbConnect.php'; // Replace with your actual database connection code

// Loading environment variables
require_once  __DIR__. '/../vendor/autoload.php';
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Include your controller class
class TeacherController
{
    private $expectedApiKey;
    private $encryptionKey;
    private $iv;
    private $conn;

    public function __construct()
    {
        $objDb = new DbConnect;
        $this->conn = $objDb->connect();
        $this->expectedApiKey = $_ENV["S_N_S"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }

    public function handleRequest()
    {
        $headers = getallheaders();
        $encryptedApiKey = $headers['Authorization']; // Note: 'Authorization' is case-sensitive

        $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);

        if ($this->validateApiKey($decryptedApiKey)) {
            $method = $_SERVER['REQUEST_METHOD'];

            try {
                if ($method === "GET") {
                    echo $this->getTeacher();
                } else {
                    http_response_code(405); // Method Not Allowed
                    echo json_encode(['error' => 'Method not allowed.']);
                }
            } catch (Exception $e) {
                http_response_code(500); // Internal Server Error
                echo json_encode(['error' => $e->getMessage()]);
            }
        } else {
            http_response_code(401); // Unauthorized
            echo json_encode(['error' => 'Access denied. Invalid API key.']);
        }
    }

    private function decryptData($data, $encryptionKey, $iv)
    {
        $plainText = openssl_decrypt($data, 'AES-256-CFB', $encryptionKey, 0, $iv);
        return $plainText;
    }

    public function getTeacher()
    {
        $username = isset($_GET['username']) ? $_GET['username'] : null;

        if ($username) {
            $username = trim($username);
$sql = "SELECT * FROM teacher WHERE username = :username";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

            return json_encode($teacher);
        } else {
            return json_encode(['error' => 'Invalid input data.']);
        }
    }

    private function validateApiKey($apiKey)
    {
        // Compare the extracted API key with the expected API key
        return $apiKey === $this->expectedApiKey;
    }
}

$TeacherController = new TeacherController();
$TeacherController->handleRequest();
?>
