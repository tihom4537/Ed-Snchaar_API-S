<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include 'DbConnect.php';

//loading the environment variables


class ClassComplaintController
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

        // assigning the function according to request methods
        if ($method == 'GET') {
            $this->fetchClassComplaintStatus();
        } else {
            // Handle exceptions here
            // http_response_code(405); // Method Not Allowed
            echo json_encode(['error' => "Method not allowed"]);
        }
    }

    public function fetchClassComplaintStatus()
    {
        $school_id = isset($_GET['school_id']) ? $_GET['school_id'] : '';

        if (empty($school_id)) {
            http_response_code(400); // Bad Request
            echo json_encode(['error' => 'school_id parameter is required.']);
            return;
        }

        try {
            $sql = "SELECT * FROM complaints WHERE school_id = :school_id AND class_id != '0'";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
            $stmt->execute();

            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: application/json');
            echo json_encode($data);
        } catch (PDOException $e) {
            http_response_code(500); // Internal Server Error
            echo "Error: " . $e->getMessage();
        }
    }

}

// Usage
$controller = new ClassComplaintController();
$controller->handleRequest();
?>