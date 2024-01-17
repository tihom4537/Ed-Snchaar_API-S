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

class SchoolHolidayController {
    
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
          $this->expectedApiKey = $_ENV["ATTENDENCE"];
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
        if($method == 'POST') {
            $classIds = isset($_POST['class_ids']) ? $_POST['class_ids'] : null;
            $usernames = $this->usernamesFromClassIds($classIds); // Fetch usernames for multiple class IDs
            $this->updateAttendance($usernames);
        } else {
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

    public function usernamesFromClassIds($classIds) {
        // Convert comma-separated string to an array of class IDs
        $classIds = explode(',', $classIds);

        $usernames = [];

        try {
            foreach($classIds as $classId) {
                $sql = "SELECT username FROM users WHERE school_id = :schoolId AND class_id = :classId";

                $stmt = $this->conn->prepare($sql);
                $stmt->bindParam(':schoolId', $_POST['school_id'], PDO::PARAM_INT);
                $stmt->bindParam(':classId', $classId, PDO::PARAM_STR);
                $stmt->execute();

                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if($result) {
                    $usernames = array_merge($usernames, array_column($result, 'username'));
                }
            }

            return $usernames;
        } catch (PDOException $e) {
            // Handle database-related errors
            http_response_code(500); // Internal Server Error
            echo json_encode(['error' => "Database error: ".$e->getMessage()]);
            return [];
        }
    }

    public function updateAttendance($usernames) {

        // echo json_encode($usernames);

        $year = isset($_POST['year']) ? $_POST['year'] : '';
        $month = isset($_POST['month']) ? $_POST['month'] : '';
        $date = isset($_POST['date']) ? $_POST['date'] : '';
        $value = isset($_POST['value']) ? $_POST['value'] : '';

        if(empty($usernames) || empty($year) || empty($month) || empty($date) || $value !== '2') {
            http_response_code(400); // Bad Request
            echo json_encode(['error' => "usernames, year, month, date, and value parameters are required."]);
            return;
        }

        try {
            // $totalUsernames = count($usernames);

            // for ($i = 0; $i < $totalUsernames - 1; $i++) {
                // $username = $usernames[$i];
            foreach($usernames as $username) {
                $sql = "SELECT attendance_text FROM studentattendance WHERE username = :username AND month = :month and year = :year";
                $stmt = $this->conn->prepare($sql);
                $stmt->bindParam(':username', $username, PDO::PARAM_STR);
                $stmt->bindParam(':year', $year, PDO::PARAM_STR);
                $stmt->bindParam(':month', $month, PDO::PARAM_STR);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $attendance = $result ? $result['attendance_text'] : '';
                
               

                $i = (int)$date;
                
                if($i >= 1 && $i <= strlen($attendance)) {
                    $attendance[$i - 1] = $value;
                } else {
                    http_response_code(400); // Bad Request
                    echo json_encode(['error' => "Invalid i value. It should be between 1 and ".strlen($attendance)]);
                    return;
                }

                $updateSql = "UPDATE studentattendance SET attendance_text = :attendance WHERE username = :username AND month = :month and year = :year";
                $updateStmt = $this->conn->prepare($updateSql);
                $updateStmt->bindParam(':attendance', $attendance, PDO::PARAM_STR);
                $updateStmt->bindParam(':year', $year, PDO::PARAM_STR);
                $updateStmt->bindParam(':username', $username, PDO::PARAM_STR);
                $updateStmt->bindParam(':month', $month, PDO::PARAM_STR);
                $updateStmt->execute();
            }

            // header('Content-Type: application/json');
            echo json_encode(['message' => 'Attendance updated successfully']);
        } catch (PDOException $e) {
            http_response_code(500); // Internal Server Error
            echo json_encode(['error' => "Error updating attendance: ".$e->getMessage()]);
        }
    }
}

// Usage
$controller = new SchoolHolidayController();
$controller->handleRequest();
?>