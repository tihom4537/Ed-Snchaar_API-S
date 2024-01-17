<?php
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../../DbConnect.php';


//loading the environment variables
require_once  __DIR__. '/../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..'); 
$dotenv->load();

class TestController
{
    private $headers;
    private $expectedApiKey;
    private $encryptedApikey;
    
    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv ; // 128 bits IV

    public function __construct()
    {
        $objDb = new DbConnect();
        $this->conn = $objDb->connect();
        $this->expectedApiKey = $_ENV["S_N_S"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }

    public function handleRequest()
    {
        $headers = getallheaders();
        $encryptedApiKey = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);


        if ($this->validateApiKey($decryptedApiKey)) {
            $method = $_SERVER['REQUEST_METHOD'];

            try {
                if ($method === "GET") {
                    $school_id = isset($_GET['school_id']) ? $_GET['school_id'] : null;
                    $class_id = isset($_GET['class_id']) ? $_GET['class_id'] : null;

                    if ($school_id !== null) {
                        $students = $this->getStudents($school_id, $class_id);
                        echo json_encode($students);
                    } else {
                        echo json_encode(['error' => "school_id is required."]);
                    }
                } else {
                    http_response_code(405); // Method Not Allowed
                    echo json_encode(['error' => "Method not allowed"]);
                }
            } catch (Exception $e) {
                http_response_code(500); // Internal Server Error
                echo json_encode(['error' => $e->getMessage()]);
            }
        } else {
            http_response_code(403); // Forbidden
            echo json_encode(['error' => 'Access denied. Invalid API key.']);
        }
    }
     private function decryptData($data, $encryptionKey, $iv) {
        $plainText = openssl_decrypt($data, 'AES-256-CFB', $encryptionKey, 0, $iv);
        return $plainText;
    }

    private function validateApiKey($apiKey)
    {
        return $apiKey === $this->expectedApiKey;
    }

    public function getStudents($school_id, $class_id)
{
    try {
        if (!$this->conn) {
            throw new Exception("Database connection not established.");
        }

        // Add a validation step to ensure class_id is a valid format (e.g., alphanumeric).
        if (!preg_match('/^[A-Za-z0-9]+$/', $class_id)) {
            throw new Exception("Invalid class_id format.");
        }

        $sql = "SELECT gender, COUNT(*) as count FROM users WHERE school_id = :school_id AND class_id = :class_id GROUP BY gender";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':school_id', $school_id, PDO::PARAM_INT);
        $stmt->bindValue(':class_id', $class_id, PDO::PARAM_STR); // Use PDO::PARAM_STR for string values
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $maleCount = 0;
        $femaleCount = 0;

        foreach ($data as $row) {
            if ($row['gender'] == 0) {
                $maleCount = $row['count'];
            } elseif ($row['gender'] == 1) {
                $femaleCount = $row['count'];
            }
        }

        $ratio = ($femaleCount > 0) ? $maleCount . ':' . $femaleCount : '0:' . $maleCount;

        $studentDetails = $this->fetchStudentDetails($school_id, $class_id);

        return [
            'male_count' => $maleCount,
            'female_count' => $femaleCount,
            'male_female_ratio' => $ratio,
            'student_details' => $studentDetails,
        ];
    } catch (Exception $e) {
        error_log("Error in getStudents: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

// Add a method to fetch student details
private function fetchStudentDetails($school_id, $class_id)
{
    try {
        if (!$this->conn) {
            throw new Exception("Database connection not established.");
        }

        // Add a validation step to ensure class_id is a valid format (e.g., alphanumeric).
        if (!preg_match('/^[A-Za-z0-9]+$/', $class_id)) {
            throw new Exception("Invalid class_id format.");
        }

        // Modify the SQL query to filter by both school_id and class_id.
        $sql = "SELECT * FROM users WHERE school_id = :school_id AND class_id = :class_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':school_id', $school_id, PDO::PARAM_INT);
        $stmt->bindValue(':class_id', $class_id, PDO::PARAM_STR); // Use PDO::PARAM_STR for string values
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error in fetchStudentDetails: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}




}

$testController = new TestController();
$testController->handleRequest();
?>
