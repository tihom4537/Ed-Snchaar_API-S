<?php
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../DbConnect.php';

//loading the environment variables
require_once  __DIR__. '/../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..'); 
$dotenv->load();

class AddTeacherController
{   
    
    private $headers;
    private $encryptedApikey;
    private $expectedApiKey;

    private $conn;
    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv ; // 128 bits IV
   

    public function __construct()
    {
        $db = new DbConnect();
        $this->conn = $db->connect();
        $this->expectedApiKey = $_ENV["TEACHER"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }

    public function handleRequest()
    {  
        $headers=getallheaders();

        $encryptedApiKey = $headers['Authorization'];

        $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);

 
        
        // Check if the API key is valid
    if ($this->validateApiKey($decryptedApiKey)) {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'POST') {
            $this->insertTeacher();
        } else {
            // http_response_code(405); // Method Not Allowed
            echo json_encode(['status' => 405, 'message' => 'Method Not Allowed']);
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

    private function validateApiKey($apiKey)
    {
        // Compare the extracted API key with the expected API key
        return $apiKey === $this->expectedApiKey;
    }

     public function insertTeacher()
    {
        try {
            $teacher = json_decode(file_get_contents('php://input'));

            $username = strtolower(str_replace(' ', '', $teacher->name) . $teacher->school_id . $teacher->roll_no);

            $school_id = $teacher->school_id ?? null;
            $roll_no = $teacher->roll_no ?? null;
            $name = $teacher->name ?? null;
            $qualification = $teacher->qualification ?? null;
            $subject = $teacher->subject ?? null;
            $gender = $teacher->gender ?? null;
            $contact = $teacher->contact ?? null;
            $class_teacher = $teacher->class_teacher ?? null;
            $class_id = $teacher->class_id ?? null;

            
            $sql = "INSERT INTO teacher (username, school_id, roll_no, name, qualification, subject, gender, contact, class_teacher, class_id) VALUES (:username, :school_id, :roll_no, :name, :qualification, :subject, :gender, :contact, :class_teacher, :class_id)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':school_id', $school_id);
            $stmt->bindParam(':roll_no', $roll_no);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':gender', $gender);
            $stmt->bindParam(':qualification', $qualification);
            $stmt->bindParam(':subject', $subject);
            $stmt->bindParam(':contact', $contact);
            $stmt->bindParam(':class_teacher', $class_teacher);
            $stmt->bindParam(':class_id', $class_id);

            if ($stmt->execute()) {
                http_response_code(201);
                echo json_encode(['status' => 201, 'message' => 'Teacher enrolled successfully']);
                // If the teacher is successfully inserted, proceed to create login record
                $this->createTeacherLogin($username);
            } else {
                http_response_code(400);
                echo json_encode(['status' => 400, 'message' => 'Failed to enroll teacher']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 500, 'message' => 'Internal Server Error: ' . $e->getMessage()]);
        }
    }

    private function createTeacherLogin($username)
    {
        try {
            // Generate a random 8-character alphanumeric password
            $password = $this->generateRandomAlphanumericPassword(8);

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $sqlLogin = "INSERT INTO login_teacher (username, password) VALUES (:username, :password)";
            $stmtLogin = $this->conn->prepare($sqlLogin);
            $stmtLogin->bindParam(':username', $username);
            $stmtLogin->bindParam(':password', $hashedPassword);

            if ($stmtLogin->execute()) {
                http_response_code(201);
                echo json_encode(['status' => 201, 'message' => 'Teacher login created successfully', 'password' => $password]);
            } else {
                http_response_code(400);
                echo json_encode(['status' => 400, 'message' => 'Failed to create teacher login']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 500, 'message' => 'Internal Server Error: ' . $e->getMessage()]);
        }
    }

    private function generateRandomAlphanumericPassword($length)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $password;
    }
}

$controller = new AddTeacherController();
$controller->handleRequest();
?>
