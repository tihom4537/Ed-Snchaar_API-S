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


class MarksController
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
         $this->expectedApiKey = $_ENV["SYLLABUS"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }

    private function validateApiKey($apiKey)
    {
        // Compare the extracted API key with the expected API key
        return $apiKey === $this->expectedApiKey;
    }

    // Function to get teachers (returns all users in the table)
    public function handleRequest()
{
    
    $headers = getallheaders();
    $encryptedApiKey = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
     $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);

    if ($this->validateApiKey($decryptedApiKey)) {
        $method = $_SERVER['REQUEST_METHOD'];

        try {
            if ($method === "GET") {
                // $username = isset($_GET['username']) ? $_GET['username'] : null;
                $exam_id = isset($_GET['exam_id']) ? $_GET['exam_id'] : null;
                //  $subject = isset($_GET['subject']) ? $_GET['subject'] : null;

                if ($exam_id !== null) {
                    $this->getMarks($exam_id);
                } else {
                    echo json_encode(['error' => " username,subject and exam_id are required."]);
                }
            } else {
                echo json_encode(['error' => "Method not allowed"]);
            }
        } catch (Exception $e) {
            // Handle exceptions here
            echo json_encode(['error' => $e->getMessage()]);
        }
    } else {
        // API key is invalid, deny access
        echo json_encode(['error' => 'Access denied. Invalid API key.']);
    }
}
private function decryptData($data, $encryptionKey, $iv) {
        $plainText = openssl_decrypt($data, 'AES-256-CFB', $encryptionKey, 0, $iv);
        return $plainText;
    }


public function getMarks($exam_id)
{
    $sql = "SELECT m.*, u.name, u.roll_no FROM marks m
            JOIN users u ON m.student_id = u.username
            WHERE m.exam_id = :exam_id ";
    $stmt = $this->conn->prepare($sql);
    $stmt->bindParam(':exam_id', $exam_id, PDO::PARAM_INT);
    $stmt->execute();
    $marks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($marks);
}

}

$testController = new MarksController();
$testController->handleRequest();
?>
