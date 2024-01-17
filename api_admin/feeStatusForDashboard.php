<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include 'DbConnect.php';

// Load environment variables
require_once  __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

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
        if ($method == 'GET') {
            $response = $this->feeDetails();
            echo json_encode($response);
        } else {
            // Handle exceptions here
            // http_response_code(405); // Method Not Allowed
            echo json_encode(['error' => "Method not allowed"]);
        }
    }

    public function feeDetails()
    {
        $school_id = isset($_GET['school_id']) ? $_GET['school_id'] : null;

        // Check if school_id is provided
        if (!$school_id) {
            http_response_code(400); // Bad Request
            echo json_encode(['error' => "School ID is required"]);
            return;
        }



        // Fetch usernames from the users table based on school_id using parameterized query
        $usernamesQuery = "SELECT username FROM users WHERE school_id = :school_id";
        $usernamesStmt = $this->conn->prepare($usernamesQuery);
        $usernamesStmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
        $usernamesStmt->execute();
        $usernamesResult = $usernamesStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // echo json_encode($usernamesResult);
        
        // fetching data where due_date < current date
        
         $currentDate = date('Y-m-d');
        //  echo $currentDate;
        $feeDetails = [];

        foreach ($usernamesResult as $username) {
            $feeQuery = "SELECT username, due_date, total_fees, paid_status 
                         FROM fees 
                         WHERE username = :username 
                         AND due_date < :currentDate";

            $feeStmt = $this->conn->prepare($feeQuery);
            $feeStmt->bindParam(':username', $username, PDO::PARAM_STR);
            $feeStmt->bindParam(':currentDate', $currentDate, PDO::PARAM_STR);
            $feeStmt->execute();
            $feeDetails[$username] = $feeStmt->fetchAll(PDO::FETCH_ASSOC);
        }


        // echo json_encode($feeDetails); 

        // Check if there are any usernames 
        if (empty($usernamesResult)) {
            return [
                "total_paid" => 0,
                "total_unpaid" => 0,
            ];
        }


        // getting latet fee details 
        $latestFeeDetails = [];
        $totalPaidCount = 0;
        $totalUnpaidCount = 0;

        foreach ($feeDetails as $username => $fees) {
            // Check if $fees array is not empty
            if (!empty($fees)) {
                // Find the fee with the maximum due_date
                $maxDueDate = max(array_column($fees, 'due_date'));
                $maxDueDateFee = array_values(array_filter($fees, function ($fee) use ($maxDueDate) {
                    return $fee['due_date'] == $maxDueDate;
                }))[0];
        
                // Increment counters based on paid_status
                $totalPaidCount += $maxDueDateFee['paid_status'] == 1 ? 1 : 0;
                $totalUnpaidCount += $maxDueDateFee['paid_status'] == 0 ? 1 : 0;
        
                $latestFeeDetails[] = $maxDueDateFee;
            }
        }

        // Return the updated response
        return [
            "total_paid" => $totalPaidCount,
            // "total_paid_fees" => $totalPaidFees,
            "total_unpaid" => $totalUnpaidCount,
            // "details" => $latestFeeDetails,
        ];

    }
}

// Usage
$controller = new FeeController();
$controller->handleRequest();
?>
