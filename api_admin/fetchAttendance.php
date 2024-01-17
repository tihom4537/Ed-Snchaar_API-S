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

class AttendanceController
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
        // Use the existing database connection from DbConnect
        $objDb = new DbConnect();
        $this->conn = $objDb->connect();
         $this->expectedApiKey = $_ENV["ATTENDENCE"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }

    public function handleRequest()
    {

        $headers = getallheaders();
        $encryptedApiKey = isset($headers['Authorization']) ? $headers['Authorization'] : '';
         
        $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);


        // if ($this->validateApiKey($apiKey)) {
         if ($this->validateApiKey($decryptedApiKey)) {
            $method = $_SERVER['REQUEST_METHOD'];

            // Assigning the function according to request methods
            // if ($method == 'GET') {
                $classId = isset($_GET['class_id']) ? $_GET['class_id'] : '';
                // $date = isset($_GET['date']) ? $_GET['date'] : '';
                $month = isset($_GET['month']) ? $_GET['month'] : '';
                $year = isset($_GET['year']) ? $_GET['year'] : '';

                if (empty($classId) || empty($month) || empty($year)) {
                    http_response_code(400); // Bad Request
                    echo json_encode(['error' => "class_id,  month, and year parameters are required."]);
                    return;
                }

                $usernames = $this->fetchUsernamesFromClass($classId);

                $attendanceData = [];

                foreach ($usernames as $username) {
                    $attendance = $this->fetchAttendance($username,  $month, $year);
                    $attendanceData[$username] = $attendance;
                }

                header('Content-Type: application/json');
                echo json_encode(['attendance' => $attendanceData]);
            // } else {
            //     // Handle exceptions here
            //     http_response_code(405); // Method Not Allowed
            //     echo json_encode(['error' => "Method not allowed"]);
            // }
        // } else {
        //     // API key is invalid, deny access
        //     http_response_code(403); // Forbidden
        //     echo json_encode(['error' => 'Access denied. Invalid API key.']);
        // }
        }else {
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

    private function fetchUsernamesFromClass($classId)
    {
        try {
            $sql = "SELECT username FROM users WHERE class_id = :class_id";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':class_id', $classId, PDO::PARAM_STR);
            $stmt->execute();

            $usernames = $stmt->fetchAll(PDO::FETCH_COLUMN, 0); // Fetch usernames as an array

            return $usernames;
        } catch (PDOException $e) {
            return ['error' => 'Error fetching usernames: ' . $e->getMessage()];
        }
    }

    // public function fetchAttendance($username, $month, $year)
    // {
    //     try {
    //     // Construct a date in the format 'YYYY-MM-DD'
    //     // $formattedDate = "$year-$month-$date";

    //     $sql = "SELECT attendance_text FROM studentattendance WHERE username = :username AND month = :month";

    //     $stmt = $this->conn->prepare($sql);
    //     $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    //     $stmt->bindParam(':month', $month, PDO::PARAM_STR);
    //     $stmt->execute();

    //     $result = $stmt->fetch(PDO::FETCH_ASSOC);

    //     if ($result) {
    //         $attendanceText = $result['attendance_text'];

    //         // // Extract the attendance status for the specified date
    //         // $attendance = $attendanceText[$date - 1];

    //         return $attendanceText;
    //     } else {
    //         return ['error' => 'Attendance data not found for the specified username and date.'];
    //     }
    // } catch (PDOException $e) {
    //     return ['error' => 'Error fetching attendance data: ' . $e->getMessage()];
    // }
    // }
    
    public function fetchAttendance($username, $month, $year)
{
    try {
        // Construct a date in the format 'YYYY-MM-DD'
        // $formattedDate = "$year-$month-$date";

        $sql = "SELECT attendance_text FROM studentattendance WHERE username = :username AND month = :month";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->bindParam(':month', $month, PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $attendanceText = $result['attendance_text'];

            // Fetch additional information (name and roll_no) from the 'users' table
            $userInfo = $this->fetchUserInfo($username);

            // Return an array containing attendance and user information
            return [
                'attendanceText' => $attendanceText,
                'userInfo' => $userInfo,
            ];
        } else {
            return ['error' => 'Attendance data not found for the specified username and date.'];
        }
    } catch (PDOException $e) {
        return ['error' => 'Error fetching attendance data: ' . $e->getMessage()];
    }
}

private function fetchUserInfo($username)
{
    try {
        $sql = "SELECT name, roll_no FROM users WHERE username = :username";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();

        $userInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        return $userInfo;
    } catch (PDOException $e) {
        return ['error' => 'Error fetching user information: ' . $e->getMessage()];
    }
}



}

// Usage
$controller = new AttendanceController();
$controller->handleRequest();
?>