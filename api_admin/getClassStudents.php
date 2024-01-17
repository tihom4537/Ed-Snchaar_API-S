<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Allow cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

// Include the database connection class
include 'DbConnect.php';



class ClassStudentsFetch
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
         $headers = getallheaders();

        $method = $_SERVER['REQUEST_METHOD'];

        // Assigning the function according to request methods
        if ($method == 'GET') {
            $this->fetchClassStudents();
        } else {
            // Handle exceptions here
            // http_response_code(405); // Method Not Allowed
            echo json_encode(['error' => "Method not allowed"]);
        }
    
    }

    public function fetchClassStudents()
{
    $school_id = isset($_GET['school_id']) ? $_GET['school_id'] : '';
    $class_id = isset($_GET['class_id']) ? $_GET['class_id'] : '';

    // Check if the school_id parameter is not provided
    if (empty($school_id) || empty($class_id)) {
        echo json_encode(['error' => "school_id and Class_id parameters are required."]);
        return;
    }

    // Use prepared statements to prevent SQL injection
    $classQuery = "
        SELECT u.username, u.name, u.class_id, u.roll_no, u.boarder,
               f.plan, f.title, f.base_fee, f.miscellaneous_charges,f.total_fees, f.paid_status, f.id as fee_id
        FROM users u
        JOIN fees f ON u.username = f.username
        WHERE u.school_id = :school_id AND u.class_Id = :class_id";
    
    $classStmt = $this->conn->prepare($classQuery);
    $classStmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
    $classStmt->bindParam(':class_id', $class_id, PDO::PARAM_STR);

    $classStmt->execute();
    $students = $classStmt->fetchAll(PDO::FETCH_ASSOC);

    // Check if students were found
    if (empty($students)) {
        echo json_encode(['message' => "No students for specified class"]);
    } else {
        // Return JSON response with students and fees information
        echo json_encode($students);
    }
}


}

// Usage
$controller = new ClassStudentsFetch();
$controller->handleRequest();
?>
