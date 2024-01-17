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
        $objDb = new DbConnect;
        $this->conn = $objDb->connect();
         $this->expectedApiKey = $_ENV["CLASSLINK"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }

    public function handleRequest()
    {
        $headers = getallheaders();

        $encryptedApiKey =isset($headers['Authorization']) ? $headers['Authorization'] : '';

          $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);
          
        // assigning the functions according to request methods
        if ($this->validateApiKey($decryptedApiKey)) {
            $method = $_SERVER['REQUEST_METHOD'];

            try {
                if ($method === "GET") {
                    $this->getSchoolTeacher($_GET['school_id']);
                } else {
                    echo ("Method not allowed");
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


    // function to get teacher (returns all users in the table)

    public function getSchoolTeacher($school_id)
    {
        $sql = "SELECT DISTINCT class_id FROM users WHERE school_id = :school_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':school_id', $school_id);
        $stmt->execute();
        $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($teachers);
    }


}

$teacherController = new TeacherController();
$teacherController->handleRequest();
?>