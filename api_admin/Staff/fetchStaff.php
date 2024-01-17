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
        $objDb = new DbConnect();
        $this->conn = $objDb->connect();
    }

    public function handleRequest()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        try {
            if ($method === "GET") {
                $school_id = isset($_GET['school_id']) ? $_GET['school_id'] : null;
                if ($school_id !== null) {
                    $this->getStaff($school_id);
                } else {
                    echo json_encode(['error' => "school_id parameter is required."]);
                }
            } else {
                echo json_encode(['error' => "Method not allowed"]);
            }
        } catch (Exception $e) {
            // Handle exceptions here
            echo json_encode(['error' => $e->getMessage()]);
        }
    
    }


    // Function to get staff members for a school
    public function getStaff($school_id)
    {
        $sql = "SELECT * FROM staff WHERE school_id = :school_id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
        $stmt->execute();
        $staffMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode($staffMembers);
    }
}

$teacherController = new StaffController();
$teacherController->handleRequest();
?>
