<?php
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include 'DbConnect.php';

class FeeController
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
            $this->updateFeeDetails();
        } else {
            echo json_encode(['data' => null, 'status' => 405, 'message' => 'Method Not Allowed']);
        }
    }

    public function updateFeeDetails()
{
    $response = []; // Initialize $response before the try-catch block

    try {
        // Trim and sanitize data
        $feeIdsJson = $_POST['fee_ids'] ?? null;
        $title = $_POST['title'] ?? null;
        $base_fee = $_POST['baseFee'] ?? null;
        $miscellaneous_charges = $_POST['miscellaneousCharges'] ?? null;
        $total_fees = $_POST['totalFee'] ?? null;

        // Get the usernames as an array
        $feeIdsArray = json_decode($feeIdsJson, true);

        // Validation
        if (empty($feeIdsArray) || !is_array($feeIdsArray)) {
            throw new Exception('Invalid usernames format');
        }

        // Loop through each username in the array
        foreach ($feeIdsArray as $id) {
            // SQL Query
            $sql = "UPDATE fees SET base_fee = :base_fee, miscellaneous_charges = :miscellaneous_charges, total_fees = :total_fees";

            // Check if title is present before updating
            if (!empty($title)) {
                $sql .= ", title = :title";
            }

            $sql .= " WHERE id = :id";

            // Prepare and execute the SQL statement
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':base_fee', $base_fee);
            $stmt->bindParam(':miscellaneous_charges', $miscellaneous_charges);
            $stmt->bindParam(':total_fees', $total_fees);

            // Bind title only if it is present
            if (!empty($title)) {
                $stmt->bindParam(':title', $title);
            }

            $stmt->bindParam(':id', $id);

            if ($stmt->execute()) {
                $affectedRows = $stmt->rowCount();
                if ($affectedRows > 0) {
                    $response = ['status' => 200, 'message' => 'Fee updated successfully.'];
                } else {
                    $response = ['status' => 200, 'message' => 'No changes were made.'];
                }
            } else {
                $errorInfo = $stmt->errorInfo();
                $errorMessage = $errorInfo[2];
                $response = ['status' => 500, 'message' => "Failed to update Fee details. Error: $errorMessage"];
            }
        }
    } catch (Exception $e) {
        $response = ['status' => 400, 'message' => $e->getMessage()];
    }

    http_response_code($response['status']);
    echo json_encode($response);
}

}

$controller = new FeeController();
$controller->handleRequest();
?>