<?php
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");
header("Content-Type: application/json");

include '../DbConnect.php';

class PayrollDetailsController
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
            $this->updatePayrollDetails();
        } else {
            // http_response_code(405); // Method Not Allowed
            echo json_encode(['data' => null, 'status' => 405, 'message' => 'Method Not Allowed']);
        }
    }

    public function updatePayrollDetails()
{
    try {
        // Get JSON data from the request body
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);

        // Trim and sanitize data
        $id = $data['id'] ?? null;
        $school_id = $data['school_id'] ?? null;
        $year = $data['year'] ?? null;
        $month = $data['month'] ?? null;
        $base_salary = $data['base_salary'] ?? null;
        $bonus_salary = $data['bonus_salary'] ?? null;
        $additional_salary = $data['additional_salary'] ?? null;
        $paid_at = $data['paid_at'] ?? null;

        // Validation (add more as needed)
        if (empty($id) || empty($school_id)) {
            throw new Exception('Payroll ID and School ID are required.');
        }

        $sql = "UPDATE payroll SET 
                year = :year,
                month = :month,
                base_salary = :base_salary,
                bonus_salary = :bonus_salary,
                additional_salary = :additional_salary,
                paid_at = :paid_at
                WHERE id = :id AND school_id = :school_id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':year', $year);
        $stmt->bindParam(':month', $month);
        $stmt->bindParam(':base_salary', $base_salary);
        $stmt->bindParam(':bonus_salary', $bonus_salary);
        $stmt->bindParam(':additional_salary', $additional_salary);
        $stmt->bindParam(':paid_at', $paid_at);
        $stmt->bindParam(":id", $id);
        $stmt->bindParam(':school_id', $school_id);

        if ($stmt->execute()) {
            $response = ['status' => 200, 'message' => 'Payroll details updated successfully.'];
        } else {
            $response = ['status' => 500, 'message' => 'Failed to update payroll details.'];
        }
    } catch (Exception $e) {
        $response = ['status' => 400, 'message' => $e->getMessage()];
    }

    http_response_code($response['status']);
    echo json_encode($response);
}

}

$controller = new PayrollDetailsController();
$controller->handleRequest();
?>
