<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../DbConnect.php';


class ComplaintsController
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

            // Assigning the function according to request methods
            if ($method == 'GET') {
                $this->fetchComplaints();
            } else {
                // Handle exceptions here
                // http_response_code(405); // Method Not Allowed
                echo json_encode(['error' => "Method not allowed"]);
            }
    }


    
    public function fetchComplaints()
    {
        $school_id = isset($_GET['school_id']) ? $_GET['school_id'] : '';

        if (empty($school_id)) {
            http_response_code(400); // Bad Request
            echo json_encode(['error' => "school_id parameter is required."]);
            return;
        }

        try {
            $sql = "
    SELECT
        t.username AS teacher_username,
        t.name AS teacher_name,
        t.class_teacher,
        COUNT(c.complaint_id) AS complaint_count
    FROM
        teacher t
    LEFT JOIN
        complaints c ON t.class_teacher = c.class_id AND t.school_id = c.school_id
    WHERE
        t.school_id = :school_id AND c.class_id != 0
    GROUP BY
        t.username, t.name, t.class_teacher;
";


            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
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
$controller = new ComplaintsController();
$controller->handleRequest();
?>
