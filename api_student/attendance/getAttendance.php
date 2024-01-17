<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../DbConnect.php';

//loading the environment variables
require_once  __DIR__. '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load();

class AttendanceController
{ private $conn;
    private $expectedApiKey;
    private $encryptedApikey;
    
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


        if ($this->validateApiKey($decryptedApiKey)) {
            $method = $_SERVER['REQUEST_METHOD'];

            // Assigning the function according to request methods
            if ($method == 'GET') {
                $this->fetchAttendance();
            } else {
                // Handle exceptions here
                http_response_code(405); // Method Not Allowed
                echo json_encode(['error' => "Method not allowed"]);
            }
        } else {
            // API key is invalid, deny access
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
        // Compare the extracted API key with the expected API key
        return $apiKey === $this->expectedApiKey;
    }

    public function fetchAttendance()
    {
        $username = isset($_GET['username']) ? $_GET['username'] : '';
        $month = isset($_GET['month']) ? $_GET['month'] : '';
        $date = isset($_GET['date']) ? $_GET['date'] : '';

        if (empty($username) || empty($month) || empty($date)) {
            http_response_code(400); // Bad Request
            echo json_encode(['error' => "username, month, and date parameters are required."]);
            return;
        }

        try {
            $sql = "SELECT SUBSTRING(attendance_text, 1, :date) AS attendance FROM studentattendance WHERE username = :username AND month = :month";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':date', $date, PDO::PARAM_INT);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->bindParam(':month', $month, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $attendance = $result['attendance'];

            // Count the number of 1s (present), 0s (absent), and 2s (holidays) in the attendance string
            $presentCount = substr_count($attendance, '1');
            $absentCount = substr_count($attendance, '0');
            $holidaysCount = substr_count($attendance, '2');

            // Total attendance is the same as the length of the attendance string up to the i'th term minus holidays
            $totalAttendance = strlen($attendance) - $holidaysCount;

            // Extract attendance data up to the i'th date
            $attendanceData = substr($attendance, 0, $date);

            $response = [
                'total_attendance' => $totalAttendance,
                'present' => $presentCount,
                'absent' => $absentCount,
                'holidays' => $holidaysCount,
                'attendance_data' => $attendanceData
            ];

            header('Content-Type: application/json');
            echo json_encode($response);
        } catch (PDOException $e) {
            http_response_code(500); // Internal Server Error
            echo ("Error fetching attendance for username: $username, month: $month, and i: $date");
            echo "Error: " . $e->getMessage();
        }
    }

}

// Usage
$controller = new AttendanceController();
$controller->handleRequest();
?>