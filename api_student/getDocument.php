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
            $document_id = isset($_GET['document_id']) ? $_GET['document_id'] : '';

            if (empty($document_id)) {
                throw new Exception("Document ID parameter is required.", 400);
            }

            $query = "SELECT school_id, document, status, request_date, document_link FROM document_requests WHERE id = :document_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':document_id', $document_id, PDO::PARAM_INT);
            $stmt->execute();

            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($documents)) {
                throw new Exception("No Document found with the specified ID.", 404);
            }

            echo json_encode(['status' => 1, 'message' => 'Document requested status successfully.', 'data' => $documents]);
        } catch (Exception $e) {
            http_response_code($e->getCode());
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}

$controller = new DocumentRequestStatus();
$controller->handleRequest();
?>
