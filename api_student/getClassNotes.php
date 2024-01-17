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

class ClassLectureGetter
{
    private $conn;

    public function __construct()
    {
        // Use the existing database connection from DbConnect
        $objDb = new DbConnect();
        $this->conn = $objDb->connect();
    }

    public function handleRequest()
    {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method == 'GET') {
            // Assuming you provide the school_id, class_id, and subject
            $school_id = isset($_GET['school_id']) ? $_GET['school_id'] : '';
            $class_id = isset($_GET['class_id']) ? $_GET['class_id'] : '';
            $subject = isset($_GET['subject']) ? $_GET['subject'] : '';
            
            $this->getClassLectures($school_id, $class_id, $subject);
        } else {
            http_response_code(405); // Method Not Allowed
            echo json_encode(['error' => 'Method not allowed']);
        }
    }

    public function getClassLectures($school_id, $class_id, $subject)
    {
        // Get the class lectures for the specified school_id, class_id, and subject
        $lectureQuery = "SELECT * FROM class_lectures WHERE school_id = :school_id AND class_id = :class_id AND subject = :subject";
        $lectureStmt = $this->conn->prepare($lectureQuery);
        $lectureStmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
        $lectureStmt->bindParam(':class_id', $class_id, PDO::PARAM_STR);
        $lectureStmt->bindParam(':subject', $subject, PDO::PARAM_STR);
        $lectureStmt->execute();
        $lectureResult = $lectureStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($lectureResult) {
            echo json_encode(["status" => "success", "lectureList" => $lectureResult]);
        } else {
            echo json_encode(["status" => "error", "message" => "No lectures found for the specified criteria."]);
        }
    }
}

// Usage
$controller = new ClassLectureGetter();
$controller->handleRequest();
?>
