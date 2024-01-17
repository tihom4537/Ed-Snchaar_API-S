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

class TeacherController
{
   private $conn;
    private $expectedApiKey;
    private $encryptedApikey;
    
    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv ; // 128 bits IV


    public function __construct()
    {
        $objDb = new DbConnect();
        $this->conn = $objDb->connect();
         $this->expectedApiKey = $_ENV["SCHOOL"];
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
                if ($method === "GET") {
                    $school_id = isset($_GET['school_id']) ? $_GET['school_id'] : null;

                    if ($school_id !== null) {
                        $this->getStudents($school_id);
                    } else {
                        echo json_encode(['error' => "Both school_id and class_id parameters are required."]);
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

    private function validateApiKey($apiKey)
    {
        // Compare the extracted API key with the expected API key
        return $apiKey === $this->expectedApiKey;
    }

    // Function to get teachers (returns all users in the table)
    // Function to get students with login information
public function getStudents($school_id)
{
    $sql = "SELECT users.*, login.password
            FROM users
            JOIN login ON users.username = login.username
            WHERE users.school_id = :school_id";

    $stmt = $this->conn->prepare($sql);
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($students);
}

}

$teacherController = new TeacherController();
$teacherController->handleRequest();
?>
