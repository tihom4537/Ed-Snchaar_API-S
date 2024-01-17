<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../DbConnect.php';

class StaffController
{
    private $conn;

    public function __construct()
    {
        $objDb = new DbConnect();
        $this->conn = $objDb->connect();
    }

    public function handleRequest()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'POST' && isset($_GET['username'])) {
            $this->deleteUser($_GET['username']);
        } else {
            // http_response_code(405); // Method Not Allowed
            echo json_encode(['error' => "Method not allowed"]);
        }
    }

    public function deleteUser($username)
    {
        try {
            $this->conn->beginTransaction();

            // Delete from the 'staff' table using prepared statement
            $deleteUserQuery = "DELETE FROM staff WHERE username = :username";
            $deleteUserStmt = $this->conn->prepare($deleteUserQuery);
            $deleteUserStmt->bindParam(':username', $username, PDO::PARAM_STR);
            $deleteUserStmt->execute();

            $this->conn->commit();

            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            $this->conn->rollBack();

            error_log("Error: " . $e->getMessage());
            http_response_code(500); // Internal Server Error
            echo json_encode(['error' => "Internal server error"]);
        }
    }
}

$controller = new StaffController();
$controller->handleRequest();
?>
