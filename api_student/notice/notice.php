<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../DbConnect.php'; // Include your actual database connection code

//loading the environment variables
require_once  __DIR__. '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load();

class noticeController
{
   private $headers;
    private $encryptedApikey;
    private $expectedApiKey;

    private $conn;
    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv ; // 128 bits IV

    public function __construct()
    {
        // Use the existing database connection from DbConnect.php
        $objDb = new DbConnect;
        $this->conn = $objDb->connect();
        $this->expectedApiKey = $_ENV["NOTICE"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }

    public function handleRequest()
    {
        // Extract the API key from the request headers
        $headers = getallheaders();
        $encryptedApiKey = $headers['Authorization'];
        $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);


        // Check if the API key is valid
        if ($this->validateApiKey($decryptedApiKey)) {
            $method = $_SERVER['REQUEST_METHOD'];
            

            try {
                if ($method === "GET") {
                    $this->getNotice();
                } elseif ($method === "POST") {
                    $this->createNotice();
                }
            } catch (Exception $e) {
                // Handle exceptions here
                echo json_encode(['error' => $e->getMessage()]);
            }
        } else {
            // API key is invalid, deny access
            echo json_encode(['error' => 'Access denied. Invalid API key.']);
        }
    }
     private function decryptData($data, $encryptionKey, $iv) {
        $plainText = openssl_decrypt($data, 'AES-256-CFB', $encryptionKey, 0, $iv);
        return $plainText;
    }

    private function validateApiKey($apiKey)
    {
        // Compare the extracted API key with the expected API key
        return $apiKey === $this->expectedApiKey;
    }

    public function getNotice()
    {
        $school_id = isset($_GET['school_id']) ? $_GET['school_id'] : null;
        $class_id = isset($_GET['class_id']) ? $_GET['class_id'] : null;
        $student_username = isset($_GET['student_username']) ? $_GET['student_username'] : null;

        if ($school_id != null && $class_id != null && $student_username != null) {
            // Fetch notices based on school_id, class_id, and username
            $data = $this->fetchDataWithUsername($school_id, $class_id, $student_username);
            echo json_encode($data);
        } elseif ($school_id != null && $class_id != null) {
            // Fetch notices based on school_id and class_id only
            $data = $this->fetchDataWithClass($school_id, $class_id);
            echo json_encode($data);
        } elseif ($school_id != null) {
            // Fetch notices based on school_id only
            $data = $this->fetchDataWithoutClass($school_id);
            echo json_encode($data);
        } else {
            echo json_encode(["error" => "At least school_id or class_id is required."]);
        }
    }

    private function fetchDataWithUsername($school_id, $class_id, $student_username)
    {
        // Database retrieval logic using PDO with username filter
        try {
            $sql = "SELECT * FROM notice WHERE school_id = :school_id AND class_id = :class_id AND student_username = :student_username";

            // Prepare the SQL query
            $stmt = $this->conn->prepare($sql);

            // Bind parameters
            $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
            $stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
            $stmt->bindParam(':student_username', $student_username, PDO::PARAM_STR);

            // Execute the query
            $stmt->execute();

            // Fetch data as an associative array
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $data;
        } catch (PDOException $e) {
            // Handle any exceptions here
            echo "Error: " . $e->getMessage();
            return [];
        }
    }
 
 

   private function fetchDataWithClass($school_id, $class_id)
{
    // Database retrieval logic using PDO
    try {
        // Split the comma-separated class_id values into an array
        $class_ids = explode(',', $class_id);

        // Create placeholders for the class IDs
        $class_id_placeholders = implode(', ', array_fill(0, count($class_ids), '?'));

        // Build the SQL query with a single placeholder for all class_ids
        $sql = "SELECT * FROM notice WHERE school_id = ? AND class_id IN ($class_id_placeholders) ";

        // Prepare the SQL query
        $stmt = $this->conn->prepare($sql);

        // Bind school_id as a positional parameter
        $stmt->bindParam(1, $school_id, PDO::PARAM_INT);

        // Bind all class_id values as a single array parameter
        $stmt->execute(array_merge([1 => $school_id], $class_ids));

        // Fetch data as an associative array
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $data;
    } catch (PDOException $e) {
        // Handle any exceptions here
        echo $e->getMessage();
        return [];
    }
}



    private function fetchDataWithoutClass($school_id)
    {
        // Database retrieval logic using PDO
        try {
            $sql = "SELECT * FROM notice WHERE school_id = :school_id AND class_id=0";

            // Prepare the SQL query
            $stmt = $this->conn->prepare($sql);

            // Bind parameters
            $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);

            // Execute the query
            $stmt->execute();

            // Fetch data as an associative array
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $data;
        } catch (PDOException $e) {
            // Handle any exceptions here
            echo json_encode(["error" => "No notice found with the provided school_id"]);
            echo "Error: " . $e->getMessage();
        }
    }

    public function createNotice()
    {
        $notice = json_decode(file_get_contents('php://input'));

        // Trim and sanitize data
        $notice->school_id = trim($notice->school_id);
        $notice->class_id = trim($notice->class_id);
        $notice->message = trim($notice->message); // Added this line

        // Check if the school ID is available
        if (!$this->isSchoolIdAvailable($notice->school_id)) {
            $response = ['status' => 0, 'message' => 'School ID is not available.'];
        } else {
            // Corrected SQL query by adding a colon before 'message'
            $sql = "INSERT INTO notice (school_id, class_id, message, date_from, date_upto) VALUES (:school_id, :class_id, :message, :date_from, :date_upto)";
            $stmt = $this->conn->prepare($sql);

            // Fixed binding parameters for 'message', 'date_from', and 'date_upto'
            $stmt->bindParam(':school_id', $notice->school_id);
            $stmt->bindParam(':class_id', $notice->class_id);
            $stmt->bindParam(':message', $notice->message); // Updated to bind 'message'
            $stmt->bindParam(':date_from', $notice->date_from); // Assuming you have 'date_from' in $notice
            $stmt->bindParam(':date_upto', $notice->date_upto); // Assuming you have 'date_upto' in $notice

            if ($stmt->execute()) {
                $response = ['status' => 1, 'message' => 'Record created successfully.'];
            } else {
                $response = ['status' => 0, 'message' => 'Failed to create record.'];
            }
        }

        echo json_encode($response);
    }

    public function isSchoolIdAvailable($id)
    {
        try {
            $sql = "SELECT COUNT(*) FROM school WHERE id_school = :id"; // Use "id" as the column name
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_STR);
            $stmt->execute();

            // Fetch the result (number of rows with the given school ID)
            $result = $stmt->fetchColumn();

            // Return true if the school ID exists, false otherwise
            return $result > 0;
        } catch (PDOException $e) {
            // Handle school database connection errors here
            // You might want to log the error for debugging
            // For simplicity, we're returning false in case of an error
            return false;
        }
    }
}

// Usage
$controller = new noticeController();
$controller->handleRequest();
?>
