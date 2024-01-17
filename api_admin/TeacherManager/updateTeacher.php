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
         $this->expectedApiKey = $_ENV["TEACHER"];
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
            $this->updateTeacher();
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

    private function validateApiKey($decryptedApiKey)
    {
        // Compare the extracted API key with the expected API key
        return $decryptedApiKey === $this->expectedApiKey;
    }


    public function updateTeacher()
    {
        $teacher = json_decode(file_get_contents('php://input'));

        // Trim and sanitize data
        $username = $teacher->username ?? null;
        $school_id = $teacher->school_id ?? null;
        $roll_no = $teacher->roll_no ?? null;
        $name = $teacher->name ?? null;
        $subject = $teacher->subject ?? null;
        $contact = $teacher->contact ?? null;
        $class_teacher = $teacher->class_teacher ?? null;
        $class_id = $teacher->class_id ?? null;

        echo $username;

        echo $school_id;




        $sql = "UPDATE teacher SET roll_no = :roll_no, name = :name, contact = :contact, class_teacher = :class_teacher, class_id = :class_id, subject = :subject WHERE username = :username AND school_id = :school_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":school_id", $school_id);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':roll_no', $roll_no);
        $stmt->bindParam(':subject', $subject);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':contact', $contact);
        $stmt->bindParam(':class_teacher', $class_teacher);
        $stmt->bindParam(':class_id', $class_id);

        if ($stmt->execute()) {
            $response = ['status' => 1, 'message' => 'Teacher information updated successfully.'];
        } else {
            $response = ['status' => 0, 'message' => 'Failed to update teacher information.'];
        }
        echo json_encode($response);
    }

}

$controller = new CarouselController();
$controller->handleRequest();
?>