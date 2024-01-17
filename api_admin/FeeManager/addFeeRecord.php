<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../DbConnect.php';

class FeeController
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

        // Assign the function according to request methods
        if ($method == 'POST') {
            $this->addFeeDetails();
        } else {
            // Handle exceptions here
            // http_response_code(405); // Method Not Allowed
            echo json_encode(['error' => "Method not allowed"]);
        }
    }

    public function addFeeDetails()
    {
        // Get inputs using $_POST
        $feeDetails = json_decode(file_get_contents('php://input'));

        $school_id = $feeDetails->school_id ?? '';
        $class_id = $feeDetails->class_id ?? '';
        $name = $feeDetails->name ?? '';
        $plan = $feeDetails->plan ?? '';
        $title = $feeDetails->title ?? '';
        $base_fee = $feeDetails->base_fee ?? '';
        $miscellaneous_charges = $feeDetails->miscellaneous_charges ?? '';
        $total_fee = $feeDetails->total_fee ?? '';
        $due_date = $feeDetails->due_date ?? date('Y-m-d');
        $roll_no = $feeDetails->roll_no ?? '';

        // Retrieve username from users table
        $usernameQuery = "SELECT username FROM users WHERE name = :name AND school_id = :school_id AND class_id = :class_id AND roll_no = :roll_no";
        $usernameStmt = $this->conn->prepare($usernameQuery);
        $usernameStmt->bindParam(':name', $name);
        $usernameStmt->bindParam(':school_id', $school_id);
        $usernameStmt->bindParam(':class_id', $class_id);
        $usernameStmt->bindParam(':roll_no', $roll_no);
        $usernameStmt->execute();

        $usernameResult = $usernameStmt->fetch(PDO::FETCH_ASSOC);
        $username = $usernameResult['username'] ?? '';

        // Check if the school ID is available
        if (empty($username)) {
            $response = ['status' => 0, 'message' => 'Failed to find username for the given conditions.'];
        } else {
            // Corrected SQL query by adding proper column names
            $sql = "INSERT INTO fees ( plan, title, base_fee, miscellaneous_charges, total_fees, due_date, username) 
                    VALUES ( :plan, :title, :base_fee, :miscellaneous_charges, :total_fee, :due_date, :username)";

            $stmt = $this->conn->prepare($sql);

            // Fixed binding parameters for the new columns
            $stmt->bindParam(':plan', $plan);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':base_fee', $base_fee);
            $stmt->bindParam(':miscellaneous_charges', $miscellaneous_charges);
            $stmt->bindParam(':total_fee', $total_fee);
            $stmt->bindParam(':due_date', $due_date);
            $stmt->bindParam(':username', $username);


            if ($stmt->execute()) {
                $response = ['status' => 1, 'message' => 'Record created successfully.'];
            } else {
                $response = ['status' => 0, 'message' => 'Failed to create record.'];
            }
        }

        // Send the response back to the client
        header('Content-Type: application/json');
        echo json_encode($response);
    }
}

// Usage
$controller = new FeeController();
$controller->handleRequest();
?>
