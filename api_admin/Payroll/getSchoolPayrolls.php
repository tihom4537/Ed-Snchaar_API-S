<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../DbConnect.php';

class GetPayrollController
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

        // assigning the function according to request methods
        if ($method == 'GET') {
            $this->getPayrolls();
        } else {
            // Handle exceptions here
            // http_response_code(405); // Method Not Allowed
            echo json_encode(['error' => "Method not allowed"]);
        }
    }

    public function getPayrolls()
    {
        // Get inputs using $_GET
        $school_id = $_GET['school_id'] ?? '';

        // Validate input
        if (empty($school_id)) {
            echo json_encode(['error' => 'School ID is required']);
            return;
        }

        // Corrected SQL query by adding proper column names
        $sql = "SELECT * FROM payroll WHERE school_id = :school_id";
        $stmt = $this->conn->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':school_id', $school_id);

        // Execute the query
        $stmt->execute();

        // Fetch the results
        $payrolls = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Send the response back to the client
        header('Content-Type: application/json');
        echo json_encode($payrolls);
    }
}

// Usage
$controller = new GetPayrollController();
$controller->handleRequest();
?>
