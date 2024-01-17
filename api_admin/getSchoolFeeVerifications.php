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
        $objDb = new DbConnect;
        $this->conn = $objDb->connect();
    }

    public function handleRequest()
    {
        // assigning the functions according to request methods
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === "GET") {
            // Use isset to check if the parameter is set
            if (isset($_GET['school_id'])) {
                $this->getFeeVerificationDetails($_GET['school_id']);
            } else {
                echo 'Invalid Request. Username parameter is missing.';
            }
        } else {
            echo 'Invalid Request Method';
        }
    }
 

    public function getFeeVerificationDetails($school_id)
{
    try {
        $sql = "SELECT fpv.*, f.title, f.plan FROM FeePaymentVerification fpv
                JOIN fees f ON fpv.fee_id = f.id
                WHERE fpv.school_id = :school_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
        $stmt->execute();
        $verificationDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($verificationDetails) {
            echo json_encode($verificationDetails);
        } else {
            echo json_encode(['error' => 'Fee for verification not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

}

$FeeController = new FeeController();
$FeeController->handleRequest();
?>
