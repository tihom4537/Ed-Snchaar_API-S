<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include 'DbConnect.php'; // Include your actual database connection code


class virtualclassController
{   
    private $conn;
    public function __construct()
    {
        // Use the existing database connection from DbConnect.php
        $objDb = new DbConnect;
        $this->conn = $objDb->connect();
    }

    public function handleRequest()
    {   
         $method = $_SERVER['REQUEST_METHOD'];

        if ($method === "GET") {
            $this->fetchLinks();
        } else {
            http_response_code(405); // Method Not Allowed
            echo json_encode(['error' => "Method not allowed"]);
        }
    }
    

    
    

    public function fetchLinks()
    {
        $school_id = isset($_GET['school_id']) ? $_GET['school_id'] : null;
        $username = isset($_GET['username']) ? $_GET['username'] : null;

        if ($school_id !== null && $username !== null) {
            $data = $this->fetchDataWithUsername($school_id, $username);
        } else {
            echo json_encode(["error" => "school_id and teacher username are required."]);
            return;
        }

        echo json_encode($data);
    }

    private function fetchDataWithUsername($school_id, $username)
    {
        // Database retrieval logic using PDO
        try {
            $sql = "SELECT * FROM virtualclass WHERE school_id = :school_id AND teacher_username = :username";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $data;
        } catch (PDOException $e) {
            echo json_encode(["error" => "Database error: " . $e->getMessage()]);
            return [];
        }
    }
}

// Usage
$controller = new virtualclassController();
$controller->handleRequest();
?>
