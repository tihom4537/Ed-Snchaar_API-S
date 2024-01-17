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

class ExamController {
    private $conn;
    private $expectedApiKey;
    private $encryptedApikey;
    
    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv ; // 128 bits IV


    public function __construct() {
        $objDb = new DbConnect();
        $this->conn = $objDb->connect();
            $this->expectedApiKey = $_ENV["SYLLABUS"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }

    public function handleRequest() {
        $headers = getallheaders();
        $encryptedApiKey = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
         $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);

        if($this->validateApiKey($decryptedApiKey)) {
            $method = $_SERVER['REQUEST_METHOD'];

            try {
                if($method === "GET") {
                    $school_id = isset($_GET['school_id']) ? $_GET['school_id'] : null;
                    $class_id = isset($_GET['class_id']) ? $_GET['class_id'] : null;

                    $this->getExams($school_id, $class_id);
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => "Method not allowed"]);
                }
            } catch (Exception $e) {
                error_log($e->getMessage());
                echo json_encode(['error' => "Internal Server Error"]);
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
    public function getExams($school_id, $class_id) {
        if($school_id === null || $class_id === null) {
            http_response_code(400);
            echo json_encode(['error' => "Both school_id and class_id are required."]);
            return;
        }
        try {
            $sql = "SELECT * FROM Exam WHERE school_id = :school_id AND class_id = :class_id";
          
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
            $stmt->bindParam(':class_id', $class_id, PDO::PARAM_STR);

            $stmt->execute();
            $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            header('Content-Type: application/json');
            echo json_encode($exams);

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error fetching exams.']);
        }


    }
}

$examController = new ExamController();
$examController->handleRequest();
?>