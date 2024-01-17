<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include 'DbConnect.php';

class FeePaymentController
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

        if ($method == 'POST') {
            $this->notifyFeePayment();
        } else {
            echo json_encode(['error' => "Method not allowed"]);
        }
    }

    public function notifyFeePayment()
{
    
   $requestData = json_decode(file_get_contents("php://input"), true);

    $school_id = isset($requestData['school_id']) ? $requestData['school_id'] : '';
    $class_id = isset($requestData['class_id']) ? $requestData['class_id'] : '';
    $unpaid_usernames = isset($requestData['unpaid_usernames']) ? $requestData['unpaid_usernames'] : [];
    $message = isset($requestData['message']) ? $requestData['message'] : '';
    


    

    $teacher = $this->getTeacher($school_id, $class_id);

    if ($teacher) {
        foreach ($unpaid_usernames as $student_username) {
            $sql = "INSERT INTO communication (sender_username, receiver_username, message) VALUES (:sender_username, :receiver_username, :message)";
            $stmt = $this->conn->prepare($sql);

            $stmt->bindParam(':sender_username', $teacher['username']);
            $stmt->bindParam(':receiver_username', $student_username);
            $stmt->bindParam(':message', $message);



            if ($stmt->execute()) {
                $response = ['status' => 1, 'message' => 'Record created successfully.'];
            } else {
                $response = ['status' => 0, 'message' => 'Failed to create record.'];
            }
        }
    } else {
        $response = ['status' => 0, 'message' => 'Teacher not found.'];
    }

    echo json_encode($response);
}



    public function getTeacher($school_id, $class_id)
    {
        $sql = 'SELECT username FROM teacher WHERE school_id = :school_id AND class_teacher = :class_id';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':school_id', $school_id);
        $stmt->bindParam(':class_id', $class_id);

        $stmt->execute();
        $teacher_username = $stmt->fetch(PDO::FETCH_ASSOC);

        return $teacher_username;
    }
}

$controller = new FeePaymentController();
$controller->handleRequest();
?>