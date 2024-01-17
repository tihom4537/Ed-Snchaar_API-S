<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../DbConnect.php';

// loading the environment variables

class PayrollManager
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

            if ($method == 'DELETE') {
                $this->deletePayrollRecord();
            } else {
                // http_response_code(405); // Method Not Allowed
                echo json_encode(['error' => "Method not allowed"]);
            }
    }

    public function deletePayrollRecord()
{
    $payrollId = $_GET['payroll_id'] ?? null;

    if ($payrollId === null) {  // Use triple equals for strict comparison
        http_response_code(400); // Bad Request
        echo json_encode(['error' => "Invalid or missing payroll_id parameter"]);
        return;
    }

    try {
        $deletePayrollQuery = "DELETE FROM payroll WHERE id = :payroll_id";
        $deletePayrollStmt = $this->conn->prepare($deletePayrollQuery);
        $deletePayrollStmt->bindParam(':payroll_id', $payrollId, PDO::PARAM_INT);
        $deletePayrollStmt->execute();

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log("Error: " . $e->getMessage());
        echo json_encode(['error' => "Internal server error"]);
    }
}

}

$controller = new PayrollManager();
$controller->handleRequest();
?>
