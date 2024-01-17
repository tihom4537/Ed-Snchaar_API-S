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
                $this->updateFee();
            } else {
                // http_response_code(405); // Method Not Allowed
                echo json_encode(['status' => 405, 'message' => 'Method Not Allowed']);
            }
    }

   public function updateFee()
{
    $id = $_POST['fee_id'] ?? null;
    $transaction_id = $_POST['transaction_id'] ?? null;
    $receipt_no = $_POST['receipt_no'] ?? 0;

    try {
        $this->conn->beginTransaction();

        // Update fees table
        $sqlFees = "UPDATE fees SET paid_status = 1, receipt_no = :receipt_no, transaction_id = :transaction_id WHERE id = :id";
        $stmtFees = $this->conn->prepare($sqlFees);
        $stmtFees->bindParam(':id', $id);
        $stmtFees->bindParam(':receipt_no', $receipt_no);
        $stmtFees->bindParam(':transaction_id', $transaction_id);



        if (!$stmtFees->execute()) {
            throw new Exception('Failed to update fees table.');
        }

        // Update FeePaymentVerification table
        $resolved_date = date("Y-m-d H:i:s"); // Current date and time
        $sqlVerification = "UPDATE FeePaymentVerification SET verification_status = 1, resolved_date = :resolved_date WHERE fee_id = :fee_id";
        $stmtVerification = $this->conn->prepare($sqlVerification);
        $stmtVerification->bindParam(':fee_id', $id);
        $stmtVerification->bindParam(':resolved_date', $resolved_date);

        if (!$stmtVerification->execute()) {
            throw new Exception('Failed to update FeePaymentVerification table.');
        }

        // If both updates are successful, commit the transaction
        $this->conn->commit();

        $response = ['status' => 1, 'message' => 'Fee information updated successfully.'];
    } catch (Exception $e) {
        // If any error occurs, rollback the transaction
        $this->conn->rollBack();

        // Log the error for debugging
        error_log("Exception in updateFee: " . $e->getMessage(), 0);

        $response = ['status' => 0, 'message' => 'Database error.'];
    }

    echo json_encode($response);
}



}

$controller = new FeeController();
$controller->handleRequest();
?>
