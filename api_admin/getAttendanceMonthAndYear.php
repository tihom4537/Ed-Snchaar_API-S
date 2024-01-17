<?php

ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include 'DbConnect.php';

//loading the environment variables
require_once  __DIR__. '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load();

class GetAttendanceMonths {
     private $conn;
    private $expectedApiKey;
    private $encryptedApikey;
    
    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv ; // 128 bits IV
   

    public function __construct() {
        $objDb = new DbConnect;
        $this->conn = $objDb->connect();
          $this->expectedApiKey = $_ENV["ATTENDENCE"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }

    public function handleRequest() {
        $headers = getallheaders();

        $encryptedApiKey = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);

        // assigning the functions according to request methods
        if($this->validateApiKey($decryptedApiKey)) {
            $method = $_SERVER['REQUEST_METHOD'];

            try {
                if($method === "GET") {
                    $this->getSchoolUsers($_GET['school_id']);
                } else {
                    echo json_encode(['error' => 'Method not allowed']);
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
    
    private function validateApiKey($apiKey) {
        // Compare the extracted API key with the expected API key
        return $apiKey === $this->expectedApiKey;
    }

    // function to get teacher (returns all users in the table)
    public function getSchoolUsers($school_id) {
        $sql = "SELECT username FROM users WHERE school_id = :schoolId";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':schoolId', $school_id, PDO::PARAM_INT);
        $stmt->execute();
        $usernames = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->getAttendanceMonths($usernames);
    }

    public function getAttendanceMonths($usernames) {
        $monthsByYear = [];

        foreach($usernames as $username) {
            $sql = "SELECT DISTINCT month, year FROM studentattendance WHERE username = :username";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':username', $username['username']);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach($result as $entry) {
                $year = $entry['year'];
                $month = $entry['month'];

                if(!isset($monthsByYear[$year])) {
                    $monthsByYear[$year] = ['year' => $year, 'months' => []];
                }

                // Check if the month is not already present for the given year
                if(!in_array($month, $monthsByYear[$year]['months'])) {
                    $monthsByYear[$year]['months'][] = $month;
                }
            }
        }

        // Convert associative array to indexed array
        $formattedMonthsByYear = array_values($monthsByYear);

        echo json_encode(['months' => $formattedMonthsByYear]);
    }


}

$GetAttendanceMonths = new GetAttendanceMonths();
$GetAttendanceMonths->handleRequest();
?>