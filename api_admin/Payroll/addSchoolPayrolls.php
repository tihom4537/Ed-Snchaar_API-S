<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../DbConnect.php';

class PayrollController
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
        if ($method == 'POST') {
            $this->addPayrollDetails();
        } else {
            // Handle exceptions here
            // http_response_code(405); // Method Not Allowed
            echo json_encode(['error' => "Method not allowed"]);
        }
    }

    public function addPayrollDetails()
    {
        // Get inputs using $_POST
        $payroll = json_decode(file_get_contents('php://input'));

        $school_id = $payroll->school_id ?? '';
        $employee_type = $payroll->employee_type ?? '';
        $employee_name = $payroll->employee_name ?? '';
        $employee_username = $payroll->employee_username ?? '';
        $year = $payroll->year ?? '';
        $month = $payroll->month ?? '';
        $base_salary = $payroll->base_salary ?? '';
        $bonus_salary = $payroll->bonus_salary ?? '';
        $additional_salary = $payroll->additional_salary ?? '';
        $paid_at = $payroll->paid_at ?? date('Y-m-d');
        $updated_at = date('Y-m-d H:i:s');

        // Check if the school ID is available

        // Corrected SQL query by adding proper column names
        $sql = "INSERT INTO payroll (school_id, employee_type, employee_name, employee_username, year, month, base_salary, bonus_salary, additional_salary, paid_at, updated_at) 
                VALUES (:school_id, :employee_type, :employee_name, :employee_username, :year, :month, :base_salary, :bonus_salary, :additional_salary, :paid_at, :updated_at)";
        $stmt = $this->conn->prepare($sql);

        // Fixed binding parameters for the new columns
        $stmt->bindParam(':school_id', $school_id);
        $stmt->bindParam(':employee_type', $employee_type);
        $stmt->bindParam(':employee_name', $employee_name);
        $stmt->bindParam(':employee_username', $employee_username);
        $stmt->bindParam(':year', $year);
        $stmt->bindParam(':month', $month);
        $stmt->bindParam(':base_salary', $base_salary);
        $stmt->bindParam(':bonus_salary', $bonus_salary);
        $stmt->bindParam(':additional_salary', $additional_salary);
        $stmt->bindParam(':paid_at', $paid_at);
        $stmt->bindParam(':updated_at', $updated_at);

        if ($stmt->execute()) {
            $response = ['status' => 1, 'message' => 'Record created successfully.'];
        } else {
            $response = ['status' => 0, 'message' => 'Failed to create record.'];
        }

        // Send the response back to the client
        header('Content-Type: application/json');
        echo json_encode($response);
    }
}

// Usage
$controller = new PayrollController();
$controller->handleRequest();
?>
