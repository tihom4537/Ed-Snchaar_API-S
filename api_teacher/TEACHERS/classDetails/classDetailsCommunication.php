<?php
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../../DbConnect.php';

//loading the environment variables
require_once  __DIR__. '/../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..'); 
$dotenv->load();

class StudentAPI
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
        $this->expectedApiKey = $_ENV["COM"];
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
                    $teacher_username = isset($_GET['teacher_username']) ? $_GET['teacher_username'] : null;

                    if ($school_id !== null && $teacher_username !== null) {
                        $students = $this->getStudents($school_id, $teacher_username);
                        echo json_encode($students);
                    } else {
                        http_response_code(400); // Bad Request
                        echo json_encode(['error' => "school_id and teacher_username are required."]);
                    }
                } else {
                    http_response_code(405); // Method Not Allowed
                    echo json_encode(['error' => "Method not allowed"]);
                }
            } catch (Exception $e) {
                http_response_code(500); // Internal Server Error
                echo json_encode(['error' => 'An error occurred while processing the request n.']);
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

    public function getStudents($school_id, $teacher_username)
{
    try {
        if (!$this->conn) {
            throw new Exception("Database connection not established.");
        }

        // Fetch the teacher's class_ids
        $teacherSql = "SELECT class_id FROM teacher WHERE username = :teacher_username";
        $teacherStmt = $this->conn->prepare($teacherSql);
        $teacherStmt->bindValue(':teacher_username', $teacher_username, PDO::PARAM_STR);
        $teacherStmt->execute();
        $teacherData = $teacherStmt->fetch(PDO::FETCH_ASSOC);

        if (!$teacherData) {
            return ['error' => 'Teacher not found'];
        }

        $teacherClassIds = explode(', ', $teacherData['class_id']);
        $studentsByClass = []; // Create an array to store students by class.

        foreach ($teacherClassIds as $classId) {
            // Fetch students' usernames, names, and class_ids based on school_id and the current class
            $sql = "SELECT username, name, gender, class_id FROM users WHERE school_id = :school_id AND class_id = :class_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':school_id', $school_id, PDO::PARAM_STR);
            $stmt->bindValue(':class_id', $classId, PDO::PARAM_STR);
            $stmt->execute();

            if ($stmt) {
                $studentsByClass[$classId] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Handle SQL query execution error
                throw new Exception("Error executing SQL query.");
            }
        }

        return $studentsByClass;
    } catch (Exception $e) {
        error_log("Error in getStudents: " . $e->getMessage());
        return ['error' => "An error occurred while processing the request: " . $e->getMessage()];
    }
}


}

$studentAPI = new StudentAPI();
$studentAPI->handleRequest();
?>
