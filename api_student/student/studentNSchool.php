<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../DbConnect.php'; // Replace with your actual database connection code

//loading environment variables
require_once  __DIR__. '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load();


 // Include your controller class

class UserController
{
    private $headers;
    private $expectedApiKey;
    private $encryptedApikey;
    
    private $conn;
    
    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv ; // 128 bits IV

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
    $encryptedApiKey = $headers['Authorization'];
    
    $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);


    if ($this->validateApiKey($decryptedApiKey)) {
        $method = $_SERVER['REQUEST_METHOD'];

        try {
            if ($method === "GET") {
                $student = $this->getStudent();

                if (isset($student['school_id'])) {
                    $school_id = $student['school_id'];
                    $class_id = $student['class_id'];
                    
                    $school = $this->getSchool($school_id);
                    $teacher = $this->getTeacher($school_id, $class_id);

                    // Combine the data from all three functions
                    $response = [
                        'student' => $student,
                        'school' => $school,
                        'teacher' => $teacher,
                    ];

                    http_response_code(200); // OK
                    echo json_encode($response);
                } else {
                    http_response_code(404); // Not Found
                    echo json_encode(['error' => 'Student not found or school_id not available.']);
                }
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


private function decryptData($data, $encryptionKey, $iv) {
        $plainText = openssl_decrypt($data, 'AES-256-CFB', $encryptionKey, 0, $iv);
        return $plainText;
    }

public function getStudent()
{
    $username = isset($_GET['username']) ? $_GET['username'] : null;

    if ($username) {
        $username = trim($username);
        $sql = "SELECT * FROM users WHERE username = :username";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        return $student;
    } else {
        return ['error' => 'Invalid input data.'];
    }
}

public function getSchool($school_id)
{
    $school_id = trim($school_id);
    $sql = "SELECT school_name FROM school WHERE id_school = :school_id";

    $stmt = $this->conn->prepare($sql);
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
    $stmt->execute();
    $school = $stmt->fetch(PDO::FETCH_ASSOC);

    return $school;
}

public function getTeacher($school_id, $class_id)
{
    $school_id = trim($school_id);
    $class_id = trim($class_id);
    
    // Assuming you have a 'teacher' table with appropriate columns
    $sql = "SELECT * FROM teacher WHERE school_id = :school_id AND class_teacher = :class_id";

    $stmt = $this->conn->prepare($sql);
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
    $stmt->bindParam(':class_id', $class_id, PDO::PARAM_STR);
    $stmt->execute();
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    return $teacher;
}


    
    private function validateApiKey($apiKey)
    {
        // Compare the extracted API key with the expected API key
        // echo ($this->expectedApiKey);
        // echo ($apiKey);
    
        return $apiKey === $this->expectedApiKey;
    
    }
    
}

$userController = new UserController();
$userController->handleRequest();
?>