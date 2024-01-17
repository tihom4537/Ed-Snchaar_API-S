<?php
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../DbConnect.php';

class StaffController
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
            $this->updateStaff();
        } else {
            echo json_encode(['status' => 405, 'message' => 'Method Not Allowed']);
        }
    
    }


    public function updateStaff()
    {
        $staff = json_decode(file_get_contents('php://input'));
        
        // echo json_encode($staff);

        $username = $staff->username ?? null;
        $school_id = $staff->school_id ?? null;
        $roll_no = $staff->roll_no ?? null;
        $name = $staff->name ?? null;
        $role = $staff->role ?? null;
        $dob = $staff->dob ?? null;
        

        $sql = "UPDATE staff SET roll_no = :roll_no, name = :name, role = :role, dob = :dob WHERE username = :username AND school_id = :school_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":school_id", $school_id);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':roll_no', $roll_no);
        $stmt->bindParam('dob', $dob);
        $stmt->bindParam(':role', $role);

        $stmt->bindParam(':name', $name);

        if ($stmt->execute()) {
            $response = ['status' => 1, 'message' => 'Staff information updated successfully.'];
        } else {
            $response = ['status' => 0, 'message' => 'Failed to update staff information.'];
        }
        echo json_encode($response);
    }
}

$controller = new StaffController();
$controller->handleRequest();
?>
