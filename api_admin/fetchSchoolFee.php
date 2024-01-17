<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include 'DbConnect.php';


//loading the environment variables
require_once  __DIR__. '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load();

class SchoolMonthlyFee {
    private $conn;
    private $expectedApiKey;
    private $encryptedApikey;
    
    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv ; // 128 bits IV

    public function __construct() {
        // Use the existing database connection from DbConnect
        $objDb = new DbConnect();
        $this->conn = $objDb->connect();
          $this->expectedApiKey = $_ENV["FEES"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }

    public function handleRequest() {
        $headers = getallheaders();

        $encryptedApiKey = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
         $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);

    if ($this->validateApiKey($decryptedApiKey)) {
        $method = $_SERVER['REQUEST_METHOD'];

        // assigning the function according to request methods
        if($method == 'GET') {
            $this->fetchSchoolFees();
        } else {
            // Handle exceptions here
            http_response_code(405); // Method Not Allowed
            echo json_encode(['error' => "Method not allowed"]);
        }
    }else {
            // API key is invalid, deny access
            echo json_encode(['error' => 'Access denied. Invalid API key.']);
        }
    }

    private function decryptData($data, $encryptionKey, $iv) {
        $plainText = openssl_decrypt($data, 'AES-256-CFB', $encryptionKey, 0, $iv);
        return $plainText;
    }

    private function validateApiKey($decryptedApiKey)
    {
        // Compare the extracted API key with the expected API key
        return $decryptedApiKey === $this->expectedApiKey;
    }

    public function fetchSchoolFees() {
        $schoolId = isset($_GET['school_id']) ? $_GET['school_id'] : '';
        $monthString = isset($_GET['month']) ? $_GET['month'] : '';
        $classId = isset($_GET['class_id']) ? $_GET['class_id'] : '';

        if(empty($schoolId) || empty($monthString) || empty($classId)) {
            echo "Scool Id, month year and Class Id parameter is required.";
            return;
        }

        // Parse the month string to get the year and month
        $parsedMonth = date_parse($monthString);

        if(!$parsedMonth || !$parsedMonth['year'] || !$parsedMonth['month']) {
            echo "Invalid month parameter.";
            return;
        }

        $year = $parsedMonth['year'];
        $month = $parsedMonth['month'];
        $monthName = date("F", mktime(0, 0, 0, $month, 1, $year)); // Get month name from the numeric representation

        try {
            // Get usernames and class_id from the users table for the given school_id
            $usernamesQuery = "SELECT username, name, roll_no, class_id FROM users WHERE school_id = :school_id AND class_id = :class_id";
            $usernamesStmt = $this->conn->prepare($usernamesQuery);
            $usernamesStmt->bindParam(':school_id', $schoolId, PDO::PARAM_STR);
            $usernamesStmt->bindParam(':class_id', $classId, PDO::PARAM_STR);
            $usernamesStmt->execute();
            $usernamesData = $usernamesStmt->fetchAll(PDO::FETCH_ASSOC);

            // Get fees for each username
            $feesData = [];
            foreach($usernamesData as $userData) {
                $username = $userData['username'];
                $classId = $userData['class_id'];
                $name = $userData['name'];
                $roll_no = $userData['roll_no'];

                $feeQuery = "SELECT * FROM fees WHERE username = :username AND year = :year AND month = :monthName";
                $feeStmt = $this->conn->prepare($feeQuery);
                $feeStmt->bindParam(':username', $username, PDO::PARAM_STR);
                $feeStmt->bindParam(':year', $year, PDO::PARAM_INT);
                $feeStmt->bindParam(':monthName', $monthName, PDO::PARAM_STR);
                $feeStmt->execute();
                $feeData = $feeStmt->fetchAll(PDO::FETCH_ASSOC);

                // Combine username, class_id, and fee data
                $feesData[] = [
                'username' => $username,
                'name' => $name,
                'roll_no' => $roll_no,
                'class_id' => $classId,
                'fees' => $feeData,
            ];
            }

            header('Content-Type: application/json');
            echo json_encode($feesData);
        } catch (PDOException $e) {
            echo "Error: ".$e->getMessage();
        }
    }

}

// Usage
$controller = new SchoolMonthlyFee();
$controller->handleRequest();
?>