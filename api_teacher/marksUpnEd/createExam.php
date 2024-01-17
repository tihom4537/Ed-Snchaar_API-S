<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../DbConnect.php';

//loading the environment variables
require_once  __DIR__. '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load();

class FeeManagementController
{   private $headers;
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
        $this->expectedApiKey = $_ENV["SYLLABUS"];
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

            // assigning the function according to request methods
            if ($method == 'POST') {
                $this->createExam();
            } else {
                // Handle exceptions here
                http_response_code(405); // Method Not Allowed
                echo json_encode(['error' => "Method not allowed"]);
            }
        } else {
            // API key is invalid, deny access
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
        // Compare the extracted API key with the expected API key
        return $apiKey === $this->expectedApiKey;
    }

    public function createExam()
{
    // Retrieve values from $_POST or another source
    $class_id = isset($_POST['class_id']) ? $_POST['class_id'] : '';
    $school_id = isset($_POST['school_id']) ? ($_POST['school_id']) : '';
    $subject = isset($_POST['subject']) ? $_POST['subject'] : '';
    $maxMarks = isset($_POST['maxMarks']) ? intval($_POST['maxMarks']) : '';
    $ExamName = isset($_POST['ExamName']) ? $_POST['ExamName'] : '';

    try {
        // Insert exam record
        $sql = "INSERT INTO Exam (class_id, school_id, subject, maxMarks, ExamName) 
                VALUES (:class_id, :school_id, :subject, :maxMarks, :ExamName)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':class_id', $class_id, PDO::PARAM_STR);
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
        $stmt->bindParam(':subject', $subject, PDO::PARAM_STR);
        $stmt->bindParam(':maxMarks', $maxMarks, PDO::PARAM_INT);
        $stmt->bindParam(':ExamName', $ExamName, PDO::PARAM_STR);

        if ($stmt->execute()) {
            // Exam created successfully, now fetch the exam_id
            $exam_id = $this->conn->lastInsertId();

            // Fetch all usernames from the user table for the given class_id
            $usernames = $this->getAllUsernames($class_id);

            // Insert records into the marks table for each username
            foreach ($usernames as $username) {
                $this->insertMarksRecord($username, $exam_id, $subject, $maxMarks, $school_id, $class_id);
            }

            echo "Exam created successfully.";
        } else {
            echo "Error adding exam.";
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}


private function insertMarksRecord($student_id, $exam_id, $subject, $maxMarks, $school_id, $class_id)
{
    $marks_obtained = 0; // Default value

    $sql = "INSERT INTO marks (student_id, exam_id, subject, marks_obtained, max_marks, school_id, class_id) 
            VALUES (:student_id, :exam_id, :subject, :marks_obtained, :maxMarks, :school_id, :class_id)";
    $stmt = $this->conn->prepare($sql);
    $stmt->bindParam(':student_id', $student_id, PDO::PARAM_STR);
    $stmt->bindParam(':exam_id', $exam_id, PDO::PARAM_INT);
    $stmt->bindParam(':subject', $subject, PDO::PARAM_STR);
    $stmt->bindParam(':marks_obtained', $marks_obtained, PDO::PARAM_INT);
    $stmt->bindParam(':maxMarks', $maxMarks, PDO::PARAM_INT);
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
    $stmt->bindParam(':class_id', $class_id, PDO::PARAM_STR);
    $stmt->execute();
}



private function getAllUsernames($class_id)
{
    $usernames = [];
    
    try {
        // Assuming you have a column named `class_id` in the `users` table
        $sql = "SELECT username FROM users WHERE class_id = :class_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':class_id', $class_id, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($result as $row) {
            $usernames[] = $row['username'];
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }

    return $usernames;
}

}

// Usage
$controller = new FeeManagementController();
$controller->handleRequest();
?>
