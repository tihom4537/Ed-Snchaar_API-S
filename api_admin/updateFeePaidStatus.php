<?php

error_reporting(E_ALL);
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
                $this->updateFee();
            } else {
                // http_response_code(405); // Method Not Allowed
                echo json_encode(['status' => 405, 'message' => 'Method Not Allowed']);
            }
    }

   public function updateFee()
{
    $fee_id = $_POST['fee_id'] ?? null;
    $transaction_id = $_POST['transaction_id'] ?? null;
    $total_paid = $_POST['total_paid'] ?? null;
    $receipt_no = $_POST['receipt_no'] ?? 0;
    $paid_status = 1;

    try {
        // Fetch existing fee details
        $selectSql = "SELECT * FROM fees WHERE id = :id";
        $selectStmt = $this->conn->prepare($selectSql);
        $selectStmt->bindParam(':id', $fee_id);
        $selectStmt->execute();
        $existingFee = $selectStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingFee) {
            $response = ['status' => 0, 'message' => 'Fee not found.'];
        } else {
            // Calculate due amount
        $due = $existingFee['total_fees'] - $total_paid;
        
        // Adjust miscellaneous charges and base_fee based on due
        $miscellaneous_charges = max(0, $existingFee['miscellaneous_charges'] - $due);
        $remaining_due = max(0, $due - $existingFee['miscellaneous_charges']); // Remaining due after adjusting miscellaneous_charges
        $base_fee = max(0, $existingFee['base_fee'] - $remaining_due);

        $total_fees = $base_fee + $miscellaneous_charges;

        // Update existing fee
        $updateSql = "UPDATE fees SET paid_status = :paid_status, receipt_no = :receipt_no, transaction_id = :transaction_id, total_paid = :total_paid, miscellaneous_charges = :miscellaneous_charges, base_fee = :base_fee, total_fees = :total_fees WHERE id = :id";
        $updateStmt = $this->conn->prepare($updateSql);
        $updateStmt->bindParam(':id', $fee_id);
        $updateStmt->bindParam(':receipt_no', $receipt_no);
        $updateStmt->bindParam(':transaction_id', $transaction_id);
        $updateStmt->bindParam(':total_paid', $total_paid);
        $updateStmt->bindParam(':paid_status', $paid_status);
        $updateStmt->bindParam(':miscellaneous_charges', $miscellaneous_charges);
        $updateStmt->bindParam(':base_fee', $base_fee);
        $updateStmt->bindParam(':total_fees', $total_fees);
        $updateStmt->execute();

            // Create new fee record if due is greater than 0
           if ($due > 0) {
        $dueTitle = "Due - " . $existingFee['title'];
        $dueSql = "INSERT INTO fees (username, plan, title, base_fee, miscellaneous_charges, total_fees, total_paid, transaction_id, receipt_no, due_date, paid_status, verification_status) VALUES (:username, :plan, :title, 0, 0, :total_fees, 0, 0, 0, :due_date, 0, 0)";
        $dueStmt = $this->conn->prepare($dueSql);
        $dueStmt->bindParam(':username', $existingFee['username']);
        $dueStmt->bindParam(':plan', $existingFee['plan']);
        $dueStmt->bindParam(':due_date', $existingFee['due_date']);
        $dueStmt->bindParam(':title', $dueTitle);
        $dueStmt->bindParam(':total_fees', $due);
        // $dueStmt->bindParam(':transaction_id', $transaction_id);
        $dueStmt->execute();
    }
        $response = ['status' => 1, 'message' => 'Fee information updated successfully.'];
        }
    } catch (PDOException $e) {
    // Log the error for debugging
    error_log("PDOException in updateFee: " . $e->getMessage(), 0);
    $response = ['status' => 0, 'message' => 'Database error: ' . $e->getMessage()];
}


    echo json_encode($response);
}



}

$controller = new FeeController();
$controller->handleRequest();
?>
