<?php
include "../DbConnect.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-headers:*");
header("Access-Control-Allow-Methods: *");

header("Content-Type: application/json");

// Load environment variables
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$expectedApiKey = $_ENV["LOGIN"];
$encryptionKey = $_ENV["ENCRYPT_KEY"];
$iv = $_ENV["INIT_VEC"];

function decryptData($data, $encryptionKey, $iv) {
    $plainText = openssl_decrypt($data, 'AES-256-CFB', $encryptionKey, 0, $iv);
    return $plainText;
}

$headers = getallheaders();

$decryptedApiKey = isset($headers['Authorization']) ? decryptData($headers['Authorization'], $encryptionKey, $iv) : '';

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the posted username and password with default values
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        // Create an instance of DbConnect and establish a database connection
        $objDb = new DbConnect;
        $connection = $objDb->connect();

        if ($connection) {
            if ($expectedApiKey == $decryptedApiKey) {
                // Use prepared statement to prevent SQL injection
                // $query = "SELECT * FROM login_teacher WHERE password = :password AND username = :username";
                $query = "SELECT * FROM login_teacher WHERE BINARY password = :password AND BINARY username = :username";

                $stmt = $connection->prepare($query);
                $stmt->bindValue(':password', $password);
                $stmt->bindValue(':username', $username);
                $stmt->execute();

                // Check for a single row result
                if ($stmt->rowCount() == 1) {
                    echo json_encode(array('success' => true, 'message' => 'Login successful'));
                } else {
                    echo json_encode(array('success' => false, 'message' => 'Username or Password is invalid'));
                }

                // Close the prepared statement and database connection
                $stmt = null;
                $connection = null;
            } else {
                // Invalid API key
                echo json_encode(array('error' => 'Invalid API key'));
            }
        } else {
            // Database connection failed
            echo json_encode(array('error' => 'Database connection error'));
        }
    } catch (Exception $e) {
        // Handle exceptions here
        echo json_encode(array('error' => 'An error occurred: ' . $e->getMessage()));
    }
} else {
    // Invalid request method
    http_response_code(405); // Method Not Allowed
    echo json_encode(array('error' => 'Invalid request method'));
}
?>
