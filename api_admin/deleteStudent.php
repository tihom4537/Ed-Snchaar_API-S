<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include 'DbConnect.php';

// loading the environment variables
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

class SchoolMonthlyFee
{
    private $headers;
    private $encryptedApikey;
    private $expectedApiKey;

    private $conn;
    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv; // 128 bits IV

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
        $headers = getallheaders();
        $encryptedApiKey = $headers['Authorization'];
        $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);

        // Check if the API key is valid
        if ($this->validateApiKey($decryptedApiKey)) {
            $method = $_SERVER['REQUEST_METHOD'];

            if ($method === 'POST' && isset($_GET['username'])) {
                $this->deleteUser($_GET['username']);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['error' => "Method not allowed"]);
            }
        } else {
            // API key is invalid, deny access
            echo json_encode(['error' => 'Access denied. Invalid API key.']);
        }
    }

    private function decryptData($data, $encryptionKey, $iv)
    {
        $plainText = openssl_decrypt($data, 'AES-256-CFB', $encryptionKey, 0, $iv);
        return $plainText;
    }

    private function validateApiKey($apiKey)
    {
        // Compare the extracted API key with the expected API key
        return $apiKey === $this->expectedApiKey;
    }

    public function deleteUser($username)
{
    // Sanitize or validate $username as needed
    $username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

    if ($username === false || $username === null) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => "Invalid or missing username parameter"]);
        return;
    }

    try {
        $this->conn->beginTransaction();

        // Delete from the 'users' table
        $deleteUserQuery = "DELETE FROM users WHERE username = :username";
        $deleteUserStmt = $this->conn->prepare($deleteUserQuery);
        $deleteUserStmt->bindParam(':username', $username, PDO::PARAM_STR);
        $deleteUserStmt->execute();

        // Delete from the 'login' table
        $deleteLoginQuery = "DELETE FROM login WHERE username = :username";
        $deleteLoginStmt = $this->conn->prepare($deleteLoginQuery);
        $deleteLoginStmt->bindParam(':username', $username, PDO::PARAM_STR);
        $deleteLoginStmt->execute();

        $this->conn->commit();

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        $this->conn->rollBack();

        error_log("Error: " . $e->getMessage());
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => "Internal server error"]);
    }
}

}

$controller = new SchoolMonthlyFee();
$controller->handleRequest();
?>
