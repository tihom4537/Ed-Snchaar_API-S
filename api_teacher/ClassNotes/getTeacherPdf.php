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


class TeacherPdfGetter
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
                // Assuming you provide the teacher's username
                $username = isset($_GET['username']) ? $_GET['username'] : '';
                $this->getTeacherPdfList($username);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['error' => 'Method not allowed']);
            }
    }

    public function getTeacherPdfList($username)
{
    // Assuming you have a table named 'teachers'
    $teacherQuery = "SELECT class_id, subject, school_id FROM teacher WHERE username = :username";
    $teacherStmt = $this->conn->prepare($teacherQuery);
    $teacherStmt->bindParam(':username', $username, PDO::PARAM_STR);
    $teacherStmt->execute();
    $teacherResult = $teacherStmt->fetch(PDO::FETCH_ASSOC);

    if ($teacherResult) {
        // Retrieve class_id and subject as comma-separated values
        $school_id = $teacherResult['school_id'];


        $class_ids = explode(', ', $teacherResult['class_id']);
        $subjects = explode(', ', $teacherResult['subject']);

        // Ensure that both arrays have the same size
        if (count($class_ids) === count($subjects)) {
            $pdfList = [];

            // Iterate over the arrays to get PDFs for each class_id and subject
            for ($i = 0; $i < count($class_ids); $i++) {
                $class_id = $class_ids[$i];
                $subject = $subjects[$i];

                // Get the uploaded PDFs for the current class_id and subject
                $pdfQuery = "SELECT * FROM class_lectures WHERE class_id = :class_id AND subject = :subject AND school_id = :school_id";
                $pdfStmt = $this->conn->prepare($pdfQuery);
                $pdfStmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
                $pdfStmt->bindParam(':class_id', $class_id, PDO::PARAM_STR);
                $pdfStmt->bindParam(':subject', $subject, PDO::PARAM_STR);
                $pdfStmt->execute();
                $pdfResult = $pdfStmt->fetchAll(PDO::FETCH_ASSOC);

                if ($pdfResult) {
                    $pdfList[] = ["class_id" => $class_id, "subject" => $subject, "pdfList" => $pdfResult];
                }
            }

            if ($pdfList) {
                echo json_encode(["status" => "success", "pdfList" => $pdfList]);
            } else {
                echo json_encode(["status" => "error", "message" => "No PDFs found for the teacher's classes."]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Mismatch in the size of class_id and subject arrays."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Teacher not found."]);
    }
}

}

// Usage
$controller = new TeacherPdfGetter();
$controller->handleRequest();
?>
