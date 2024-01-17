<?php
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../DbConnect.php';

class AddStaffController
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
            $this->insertStaff();
        } else {
            echo json_encode(['status' => 405, 'message' => 'Method Not Allowed']);
        }
    }

    public function insertStaff()
    {
        try {
            $staff = json_decode(file_get_contents('php://input'));
            
            // echo json_encode($staff);

            // Check if required properties are present and have valid data types
            if (
                !isset($staff->school_id, $staff->name, $staff->roll_no, $staff->gender, $staff->dob)
            ) {
                http_response_code(400);
                echo json_encode(['status' => 400, 'message' => 'Invalid or incomplete data provided']);
                return;
            }

            $nameParts = explode(' ', $staff->name);
            $nameInitials = '';
            foreach ($nameParts as $part) {
                $nameInitials .= mb_convert_case(mb_substr($part, 0, 1), MB_CASE_UPPER, 'UTF-8');
            }

            // Extract first 3 initials of the school ID
            $schoolIdPrefix = mb_convert_case(mb_substr($staff->school_id, 0, 3), MB_CASE_UPPER, 'UTF-8');

            // Generate a 3-digit random number
            $randomNumber = sprintf('%03d', mt_rand(0, 999));

            // Combine the components to create the staff username
            $username = $nameInitials . $schoolIdPrefix . $staff->roll_no . $randomNumber;
            
            $school_id = $staff->school_id;
            $roll_no = $staff->roll_no;
            $role = $staff->role;
            $name = $staff->name;
            $gender = $staff->gender;
            $dob = $staff->dob;
            

            $sql = "INSERT INTO staff (username, school_id, roll_no, role, name, gender, dob) VALUES (:username, :school_id, :roll_no, :role, :name, :gender, :dob)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':school_id', $school_id);
            $stmt->bindParam(':roll_no', $roll_no);
            $stmt->bindParam(':role', $role);

            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':gender', $gender);
            $stmt->bindParam(':dob', $dob);

            if ($stmt->execute()) {
                http_response_code(201);
                echo json_encode(['status' => 201, 'message' => 'Staff member enrolled successfully']);
            } else {
                http_response_code(400);
                echo json_encode(['status' => 400, 'message' => 'Failed to enroll staff member']);
            }
        } catch (Exception $e) {
            error_log("Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['status' => 500, 'message' => 'Internal Server Error']);
        }
    }
}

$controller = new AddStaffController();
$controller->handleRequest();
?>
