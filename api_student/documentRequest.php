<?php
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../DbConnect.php';

class DocumentRequest
{
    private $conn;
    public function __construct()
    {
        $db = new DbConnect();
        $this->conn = $db->connect();
    }
    public function handleRequest()
    {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'POST') {
            $this->requestDocument();
        } else {
            echo json_encode(['status' => 405, 'message' => 'Method Not Allowed']);
        }
    }

    public function requestDocument()
    {
        $school_id = $_POST['school_id'] ?? null;
        $student_username = $_POST['student_username'] ?? null;
        $document = $_POST['document'] ?? null;
        // $cost = $_POST['cost'] ?? null;
        $status = '0';

        if ($school_id == null || $student_username == null || $document == null ) {
            echo json_encode(['status' => 0, 'message' => 'schoolId, student username, document or cost can\'t be null']);
            return;
        }

        $sql = "INSERT INTO document_requests (student_username, school_id, document, status) VALUES (:student_username, :school_id, :document, :status)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':student_username', $student_username);
        $stmt->bindParam(':school_id', $school_id);
        $stmt->bindParam(':document', $document);
        // $stmt->bindParam(':cost', $cost);
        $stmt->bindParam(':status', $status);

        if ($stmt->execute()) {
            $response = ['status' => 1, 'message' => 'Document requested successfully.'];
        } else {
            $response = ['status' => 0, 'message' => 'Failed to request document.'];
        }

        echo json_encode($response);
    }
}

$controller = new DocumentRequest();
$controller->handleRequest();
?>