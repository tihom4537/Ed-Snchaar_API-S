<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Allow cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

// Include the database connection class
include '../DbConnect.php';

// Loading the environment variables
require_once  __DIR__. '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load();

class SchoolExtraFetch
{
    private $conn;
    private $expectedApiKey;
    private $encryptionKey;
    private $iv;

    public function __construct()
    {
        $objDb = new DbConnect();
        $this->conn = $objDb->connect();
        $this->expectedApiKey = $_ENV["CAROUSEL"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }

    public function handleRequest()
    {
        $headers = getallheaders();
        $encryptedApiKey = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        $decryptedApiKey = $this->decryptData($encryptedApiKey);

        if ($this->validateApiKey($decryptedApiKey)) {
            $method = $_SERVER['REQUEST_METHOD'];

            if ($method == 'GET') {
                $schoolId = isset($_GET['school_id']) ? $_GET['school_id'] : '';
                $classId = isset($_GET['class_id']) ? $_GET['class_id'] : '';
                $subject = isset($_GET['subject']) ? $_GET['subject'] : '';

                if (empty($schoolId) || empty($classId) || empty($subject)) {
                    $this->sendResponse(400, ['error' => 'school_id, class_id, and subject parameters are required.']);
                    return; // Stop further execution
                }

                $this->fetchSchoolAssignments($schoolId, $classId, $subject);
            } else {
                $this->sendResponse(405, ['error' => 'Method not allowed']);
            }
        } else {
            $this->sendResponse(403, ['error' => 'Access denied. Invalid API key.']);
        }
    }

    private function decryptData($data)
    {
        $plainText = openssl_decrypt($data, 'AES-256-CFB', $this->encryptionKey, 0, $this->iv);
        return $plainText;
    }

    private function validateApiKey($decryptedApiKey)
    {
        return $decryptedApiKey === $this->expectedApiKey;
    }

    public function fetchSchoolAssignments($schoolId, $classId, $subject)
{
    $assignmentsQuery = "
        SELECT assignments.id, assignments.school_id, assignments.class_id, assignments.subject, 
               assignments.title, assignments.assignment, assignments.submit_date, assignments.due_date, 
               teacher.name as teacher_name
        FROM assignments
        INNER JOIN teacher ON assignments.teacher_username COLLATE utf8mb4_unicode_ci = teacher.username COLLATE utf8mb4_unicode_ci
        WHERE assignments.school_id = :schoolId
        AND assignments.class_id = :classId
        AND assignments.subject = :subject
    ";

    $assignmentsStmt = $this->conn->prepare($assignmentsQuery);
    $assignmentsStmt->bindParam(':schoolId', $schoolId, PDO::PARAM_STR);
    $assignmentsStmt->bindParam(':classId', $classId, PDO::PARAM_STR);
    $assignmentsStmt->bindParam(':subject', $subject, PDO::PARAM_STR);
    $assignmentsStmt->execute();
    $schoolAssignments = $assignmentsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Check if assignments were found
    if (empty($schoolAssignments)) {
        $this->sendResponse(200, []); 
    } else {
        $this->sendResponse(200, $schoolAssignments);
    }
}



    private function sendResponse($statusCode, $data)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

// Usage
$controller = new SchoolExtraFetch();
$controller->handleRequest();
?>
