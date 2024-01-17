<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include 'DbConnect.php';

class FeeDeleter {
    private $conn;

    public function __construct() {
        $objDb = new DbConnect();
        $this->conn = $objDb->connect();
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method == 'POST') {
            $this->deleteFeeRecord();
        } else {
            echo json_encode(['error' => "Method not allowed"]);
        }
    }

    public function deleteFeeRecord() {
        $feeIds = $_POST['fee_ids'] ?? null;
        
        if (empty($feeIds)) {
            http_response_code(400); // Bad Request
            echo json_encode(['error' => 'Invalid id format']);
            return;
        }

        try {
            // Use a placeholder in the SQL query
            $deleteFeeQuery = "DELETE FROM fees WHERE id = :feeId";
            $deleteFeeStmt = $this->conn->prepare($deleteFeeQuery);
            $deleteFeeStmt->bindParam(':feeId', $feeIds, PDO::PARAM_INT);
            $deleteFeeStmt->execute();

            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            error_log("Error: " . $e->getMessage());
            http_response_code(500); // Internal Server Error
            echo json_encode(['error' => "Internal server error"]);
        }
    }
}

$deleter = new FeeDeleter();
$deleter->handleRequest();
?>
