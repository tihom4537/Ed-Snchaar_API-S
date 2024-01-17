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
        $employee_type = $_GET['employee_type'] ?? '';

        // Validate input
        if (empty($school_id) || empty($employee_type)) {
            echo json_encode(['error' => 'School ID and Employee Type are required']);
            return;
        }

        // Choose the table based on the employee type
        $tableName = ($employee_type == 'teacher') ? 'teacher' : 'staff';

        // Corrected SQL query by adding proper column names
        $sql = "SELECT * FROM $tableName WHERE school_id = :school_id";
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
