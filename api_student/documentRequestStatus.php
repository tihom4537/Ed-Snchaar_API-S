<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../DbConnect.php';

class DocumentRequestStatus
{
    private $conn;

    public function __construct()
    {
        $dbConnect = new DbConnect();
        $this->conn = $dbConnect->connect();
    }

    public function handleRequest()
    {
        try {
            $method = $_SERVER['REQUEST_METHOD'];

            if ($method == 'GET') {
                $this->documentStatus();
            } else {
                throw new Exception("Method not allowed", 405);
            }
        } catch (Exception $e) {
            http_response_code($e->getCode());
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function documentStatus()
{
    try {
        $school_id = isset($_GET['school_id']) ? $_GET['school_id'] : '';
        $username = isset($_GET['username']) ? $_GET['username'] : '';

        if (empty($school_id) || empty($username)) {
            throw new Exception("Both school_id and username parameters are required.", 400);
        }

        $query = "SELECT id, school_id, student_username, document, status, request_date FROM document_requests WHERE school_id = :school_id AND student_username = :username";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();

        $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($complaints)) {
            throw new Exception("No Documents for the specified user.", 404);
        }

        // Remove the document_link field from each item in the result
        foreach ($complaints as &$complaint) {
            unset($complaint['document_link']);
        }

        echo json_encode(['status' => 1, 'message' => 'Document requested status successfully.', 'data' => $complaints]);
    } catch (Exception $e) {
        http_response_code($e->getCode());
        echo json_encode(['error' => $e->getMessage()]);
    }
}

}

$controller = new DocumentRequestStatus();
$controller->handleRequest();
?>