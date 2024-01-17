<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Allow cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

// Include the database connection class
include 'DbConnect.php';

//loading the environment variables
require_once  __DIR__. '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load();


class CarouselAdder
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
        // Use the existing database connection from DbConnect
        $objDb = new DbConnect();
        $this->conn = $objDb->connect();
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

            // Assigning the function according to request methods
            if ($method == 'POST') {
                // Assuming you are sending title, description, school_id in the form data
                $title = isset($_POST['title']) ? $_POST['title'] : '';
                $description = isset($_POST['description']) ? $_POST['description'] : '';
                $school_id = isset($_POST['school_id']) ? $_POST['school_id'] : '';
        
            $this->addCarousel($title, $description, $school_id);
        }
         else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['error' => 'Method not allowed']);
            }
        } else {
            http_response_code(403); // Forbidden
            echo json_encode(['error' => 'Access denied. Invalid API key.']);
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

    public function addCarousel($title, $description, $school_id)
    {
        // Check if file is uploaded
        if (isset($_FILES['image'])) {
            $file = $_FILES['image'];
    
            // Handle file upload logic
            $target_dir = "img/";  // Save images inside the 'image' folder
            $imageFileType = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    
            // Generate a random 5-digit alphanumeric string as the new file name
            $newFileName = bin2hex(random_bytes(2)) . '.' . $imageFileType;
            $target_file = $target_dir . $newFileName;
    
            // Check file size
            if ($file["size"] > 500000) {
                echo json_encode(["status" => "error", "message" => "File is too large."]);
                exit();
            }
    
            // Allow certain file formats
            if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg") {
                echo json_encode(["status" => "error", "message" => "Only JPG, JPEG, PNG & GIF files are allowed."]);
                exit();
            }
    
            // Move the uploaded file to the target directory
            if (!move_uploaded_file($file["tmp_name"], $target_file)) {
                echo json_encode(["status" => "error", "message" => "Error uploading file."]);
                exit();
            }
    
            // Insert information into the database
            $carouselQuery = "INSERT INTO carousel (title, description, url, school_id) VALUES (:title, :description,     :image_path, :school_id)";
            $carouselStmt = $this->conn->prepare($carouselQuery);
            $carouselStmt->bindParam(':title', $title, PDO::PARAM_STR);
            $carouselStmt->bindParam(':description', $description, PDO::PARAM_STR);
            $carouselStmt->bindParam(':image_path', $target_file, PDO::PARAM_STR);
            $carouselStmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
    
            if ($carouselStmt->execute()) {
                echo json_encode(["status" => "success", "message" => "Carousel added successfully."]);
            } else {
                echo json_encode(["status" => "error", "message" => "Error adding carousel to the database."]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "No file uploaded."]);
        }
    }
}

// Usage
$controller = new CarouselAdder();
$controller->handleRequest();
?>