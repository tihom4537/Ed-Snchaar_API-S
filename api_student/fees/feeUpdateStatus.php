<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../DbConnect.php';

class FeeManagementController
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
        // $action = $_GET['action'] ?? '';
        try {
            if ($method === "GET") {
                $this->fetchFees();
            }
        } catch (Exception $e) {
            // Handle exceptions here
            echo json_encode(['error' => $e->getMessage()]);
        }
    
}



   public function fetchFees()
{
    $username = isset($_GET['username']) ? $_GET['username'] : '';

    if (empty($username)) {
        echo json_encode(['error' => 'Username parameter is required.']);
        return;
    }

    try {
        $sql = "SELECT * FROM FeePaymentVerification WHERE username = :username";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($data)) {
            echo json_encode(['message' => 'No data submitted for verification.']);
        } else {
            header('Content-Type: application/json');
            echo json_encode($data);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
    }
}

}

// Usage
$controller = new FeeManagementController();
$controller->handleRequest();

?>