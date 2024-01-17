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
        $objDb = new DbConnect;
        $this->conn = $objDb->connect();
    }

    public function handleRequest()
    {   
        // assigning the functions according to request methods
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === "GET") {
            // Use isset to check if the parameter is set
            if (isset($_GET['username'])) {
                $this->getTeacher($_GET['username']);
            } else {
                echo 'Invalid Request. Username parameter is missing.';
            }
        }
          
    }

    // function to get teacher (returns teacher where username = :username)
    public function getTeacher($username)
    {
        try {
            $sql = "SELECT * FROM staff WHERE username = :username";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($teacher) {
                echo json_encode($teacher);
            } else {
                echo json_encode(['error' => 'Staff not found']);
            }
        } catch (PDOException $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}

$teacherController = new StaffController();
$teacherController->handleRequest();
?>
