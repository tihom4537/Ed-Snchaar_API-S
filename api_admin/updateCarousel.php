<?php
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include 'DbConnect.php';

//loading the environment variables
require_once  __DIR__. '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load();

class CarouselController
{
   private $conn;
    private $expectedApiKey;
    private $encryptedApikey;
    
    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv ; // 128 bits IV

    public function __construct()
    {
        $db = new DbConnect();
        $this->conn = $db->connect();
           $this->expectedApiKey = $_ENV["CAROUSEL"];
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

        if ($method === 'POST') {
            $this->updateCarousel();
        } else {
            // http_response_code(405); // Method Not Allowed
            echo json_encode(['data' => null, 'status' => 405, 'message' => 'Method Not Allowed']);
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

    public function updateCarousel()
    {
        try {

            // Trim and sanitize data
            $carousel_id = $_POST['carousel_id'] ?? null;
            $title = $_POST['title'] ?? null;
            $description = $_POST['description'] ?? null;
            

            echo $carousel_id;
            echo $title;

            // Validation (add more as needed)
            if (empty($carousel_id)) {
                throw new Exception('carousel ID is required.');
            }

            $sql = "UPDATE carousel SET  title = :title, description = :description WHERE ID = :carousel_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(":carousel_id", $carousel_id);
            $stmt->bindParam(':description', $description);

            if ($stmt->execute()) {
                $response = ['status' => 200, 'message' => 'Carousel information updated successfully.'];
            } else {
                $response = ['status' => 500, 'message' => 'Failed to update carousel information.'];
            }

        } catch (Exception $e) {
            $response = ['status' => 400, 'message' => $e->getMessage()];
        }

        http_response_code($response['status']);
        echo json_encode($response);
    }
}

$controller = new CarouselController();
$controller->handleRequest();
?>
