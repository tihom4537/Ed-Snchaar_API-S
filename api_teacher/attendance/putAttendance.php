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


class FeeManagementController
{
   private $headers;
    private $expectedApiKey;
    private $encryptedApikey;
    
    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv ; // 128 bits IV
    
    private $conn;
    

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

            // assigning the function according to request methods
            if ($method == 'POST') {
                $this->updateAttendance();
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
public function updateAttendance()
{
    $usernames = isset($_POST['usernames']) ? $_POST['usernames'] : '';
    $month = isset($_POST['month']) ? $_POST['month'] : '';
    $date = isset($_POST['date']) ? $_POST['date'] : '';
    $values = isset($_POST['values']) ? $_POST['values'] : '';

    if (empty($usernames) || empty($month) || empty($date) || empty($values)) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => "usernames, month, date, and values parameters are required."]);
        return;
    }

    $usernamesArray = explode(', ', $usernames);
    $valuesArray = explode(', ', $values);

    if (count($usernamesArray) !== count($valuesArray)) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => "Number of usernames and values should be the same."]);
        return;
    }

    try {
        foreach ($usernamesArray as $index => $username) {
            $value = $valuesArray[$index];

            // Fetch the current attendance data for the given username and month
            $sql = "SELECT attendance_text FROM studentattendance WHERE username = :username AND month = :month";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->bindParam(':month', $month, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $attendance = $result['attendance_text'];

            // Update the attendance data at the i'th position with the provided value
            $i = (int) $date;
            if ($i >= 1 && $i <= strlen($attendance)) {
                // Use substr_replace to replace the character at position $i
                $attendance = substr_replace($attendance, $value, $i - 1, 1);
            } else {
                http_response_code(400); // Bad Request
                echo json_encode(['error' => "Invalid i value. It should be between 1 and " . strlen($attendance)]);
                return;
            }

            // Update the attendance in the database
            $updateSql = "UPDATE studentattendance SET attendance_text = :attendance WHERE username = :username AND month = :month";
            $updateStmt = $this->conn->prepare($updateSql);
            $updateStmt->bindParam(':attendance', $attendance, PDO::PARAM_STR);
            $updateStmt->bindParam(':username', $username, PDO::PARAM_STR);
            $updateStmt->bindParam(':month', $month, PDO::PARAM_STR);
            $updateStmt->execute();
        }

        header('Content-Type: application/json');
        echo json_encode(['message' => 'Attendance updated successfully']);
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo "Error updating attendance for usernames: $usernames, month: $month, and date: $date";
        echo "Error: " . $e->getMessage();
    }
}

}

// Usage
$controller = new FeeManagementController();
$controller->handleRequest();
?>
