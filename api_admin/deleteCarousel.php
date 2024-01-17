<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include 'DbConnect.php';

// loading the environment variables
require_once  __DIR__. '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load();

class CarouselManager {
    private $headers;
    private $encryptedApiKey;
    private $expectedApiKey;

    private $conn;
    private $encryptionKey;
    private $iv;

    public function __construct() {
        $objDb = new DbConnect();
        $this->conn = $objDb->connect();
        $this->expectedApiKey = $_ENV["CAROUSEL"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }

    public function handleRequest() {
        $headers = getallheaders();
        $encryptedApiKey = $headers['Authorization'];
        $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);

        if ($this->validateApiKey($decryptedApiKey)) {
            $method = $_SERVER['REQUEST_METHOD'];

            if ($method === 'POST' && isset($_GET['id'])) {
                $this->deleteCarousel();
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['error' => "Method not allowed"]);
            }
        } else {
            echo json_encode(['error' => 'Access denied. Invalid API key.']);
        }
    }

    private function decryptData($data, $encryptionKey, $iv) {
        $plainText = openssl_decrypt($data, 'AES-256-CFB', $encryptionKey, 0, $iv);
        return $plainText;
    }

    private function validateApiKey($apiKey) {
        return $apiKey === $this->expectedApiKey;
    }
    
    public function deleteCarousel() {
    $carouselId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($carouselId === false || $carouselId === null) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => "Invalid or missing id parameter"]);
        return;
    }

    try {
        // Retrieve the file path before deleting the carousel
        $getFilePathQuery = "SELECT url FROM carousel WHERE id = :id";
        $getFilePathStmt = $this->conn->prepare($getFilePathQuery);
        $getFilePathStmt->bindParam(':id', $carouselId, PDO::PARAM_INT);
        $getFilePathStmt->execute();
        $result = $getFilePathStmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            http_response_code(404); // Not Found
            echo json_encode(['error' => "Carousel not found"]);
            return;
        }

        $filePath = $result['url'];

        // Delete the carousel record
        $deleteCarouselQuery = "DELETE FROM carousel WHERE id = :id";
        $deleteCarouselStmt = $this->conn->prepare($deleteCarouselQuery);
        $deleteCarouselStmt->bindParam(':id', $carouselId, PDO::PARAM_INT);
        $deleteCarouselStmt->execute();

        // Delete the associated file
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log("Error: " . $e->getMessage());
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => "Internal server error"]);
    }
}


}

$controller = new CarouselManager();
$controller->handleRequest();
?>
