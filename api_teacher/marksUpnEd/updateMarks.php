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

class EditMarksController
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
                $this->updateMarks();
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

    public function updateMarks()
    {
        // echo ($_POST['student_ids']);
        // echo ($_POST['new_marks']);
        // echo ($_POST['exam_id']);
        // echo ($_POST['subject']);
        
        $student_ids = isset($_POST['student_ids']) ? explode(',', $_POST['student_ids']) : [];
        $new_marks = isset($_POST['new_marks']) ? explode(',', $_POST['new_marks']) : [];
        $exam_id = isset($_POST['exam_id']) ? intval($_POST['exam_id']) : '';
        $subject = isset($_POST['subject']) ? $_POST['subject'] : '';
        
        // echo json_encode($student_ids);
        // echo json_encode($new_marks);
        // echo json_encode($exam_id);
        // echo json_encode($subject);
        

        try {
            // Check if marks for this student, exam, and subject exist
            $checkMarksQuery = "SELECT COUNT(*) as count FROM marks WHERE student_id = :student_id AND exam_id = :exam_id AND subject = :subject";

            $checkMarksStmt = $this->conn->prepare($checkMarksQuery);

            $response = [];

            for ($i = 0; $i < count($student_ids); $i++) {
                $checkMarksStmt->bindParam(':student_id', $student_ids[$i], PDO::PARAM_STR);
                $checkMarksStmt->bindParam(':exam_id', $exam_id, PDO::PARAM_INT);
                $checkMarksStmt->bindParam(':subject', $subject, PDO::PARAM_STR);
                $checkMarksStmt->execute();
                $result = $checkMarksStmt->fetch(PDO::FETCH_ASSOC);

                if ($result['count'] == 0) {
                    $response[] = ['error' => "Marks for student ID: " . $student_ids[$i] . " with subject $subject and exam ID $exam_id do not exist."];
                } else {
                    // Update marks for the specified student, subject, and exam_id
                    $updateMarksQuery = "UPDATE marks SET marks_obtained = :new_marks WHERE student_id = :student_id AND exam_id = :exam_id AND subject = :subject";
                    $updateMarksStmt = $this->conn->prepare($updateMarksQuery);

                    $updateMarksStmt->bindParam(':student_id', $student_ids[$i], PDO::PARAM_STR);
                    $updateMarksStmt->bindParam(':exam_id', $exam_id, PDO::PARAM_INT);
                    $updateMarksStmt->bindParam(':subject', $subject, PDO::PARAM_STR);
                    $updateMarksStmt->bindParam(':new_marks', $new_marks[$i], PDO::PARAM_INT);

                    // Execute the statement inside the loop
                    if ($updateMarksStmt->execute()) {
                        $response[] = ['success' => true, 'message' => "Marks updated successfully for student ID: " . $student_ids[$i]];
                    } else {
                        $response[] = ['error' => "Error updating marks for student ID: " . $student_ids[$i]];
                    }
                }
            }

            // Return the JSON response after the loop
            echo json_encode($response);
        } catch (PDOException $e) {
            echo json_encode(['error' => "Error: " . $e->getMessage()]);
        }
    }
}

// Usage
$controller = new EditMarksController();
$controller->handleRequest();
?>
