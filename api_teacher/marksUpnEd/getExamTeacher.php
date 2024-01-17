<?php
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../DbConnect.php';

//loading the environment variables
require_once  __DIR__. '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load();


class TestController
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

            try {
                if ($method === "GET") {
                    $school_id = isset($_GET['school_id']) ? $_GET['school_id'] : null;
                    $teacher_username = isset($_GET['teacher_username']) ? $_GET['teacher_username'] : null;

                    if ($school_id !== null && $teacher_username !== null) {
                        $this->getTests($school_id, $teacher_username);
                    } else {
                        echo json_encode(['error' => "Both school_id and teacher_username are required."]);
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

    // Function to get exams for all subjects and classes taught by a teacher
    public function getTests($school_id, $teacher_username)
{
    if ($school_id === null || $teacher_username === null) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => "Both school_id and teacher_username are required."]);
        return;
    }

    try {
        // Fetch the subjects and classes associated with the teacher
        $teacherSubjects = $this->getTeacherSubjects($school_id, $teacher_username);
        $teacherClasses = $this->getTeacherClasses($school_id, $teacher_username);
        
        // echo json_encode($teacherSubjects);
        // echo json_encode($teacherClasses);

        if (empty($teacherSubjects) || empty($teacherClasses)) {
            http_response_code(404); // Not Found
            echo json_encode(['error' => "No subjects or classes found for the given teacher."]);
            return;
        }

        // Prepare an array to store exams for all subjects and classes
        $allExams = [];

        // Iterate through each subject and class and fetch exams
        // Iterate through each subject and class and fetch exams
        
        
        foreach ($teacherSubjects as $subject) {
    $subjects = explode(', ', $subject['subject']); // Split comma-separated subjects

    // Iterate through each class and fetch exams for the corresponding subject
    foreach ($teacherClasses as $class) {
        $class_ids = explode(', ', $class['class_id']); // Split comma-separated class IDs

        // Fetch exams for the current subject and its corresponding class ID
        foreach ($subjects as $index => $individualSubject) {
            $class_id = $class_ids[$index];

            // Only fetch exams for the current subject and class ID
            $sql = "SELECT * FROM Exam
                    WHERE school_id = :school_id
                    AND subject = :subject
                    AND class_id = :class_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
            $stmt->bindParam(':subject', $individualSubject, PDO::PARAM_STR);
            $stmt->bindParam(':class_id', $class_id, PDO::PARAM_STR);
            $stmt->execute();
            $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
            

            // Add each exam to the result array
            foreach ($exams as $exam) {
                $allExams[] = $exam;
            }
        }
    }
}


header('Content-Type: application/json');
echo json_encode($allExams);

    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Error fetching exams: ' . $e->getMessage()]);
    }
}


    // Function to get distinct subjects taught by a teacher
    private function getTeacherSubjects($school_id, $teacher_username)
    {
        $sql = "SELECT DISTINCT subject FROM teacher WHERE school_id = :school_id AND username = :teacher_username";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
        $stmt->bindParam(':teacher_username', $teacher_username, PDO::PARAM_STR);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Function to get distinct classes taught by a teacher
    private function getTeacherClasses($school_id, $teacher_username)
    {
        $sql = "SELECT DISTINCT class_id FROM teacher WHERE school_id = :school_id AND username = :teacher_username";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
        $stmt->bindParam(':teacher_username', $teacher_username, PDO::PARAM_STR);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$testController = new TestController();
$testController->handleRequest();
?>
