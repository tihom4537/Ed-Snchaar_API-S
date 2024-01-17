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
        $this->expectedApiKey = $_ENV["FEES"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }


    
    public function handleRequest()
{
    $headers = getallheaders();
    // $apiKey = $headers['authorization'];
        $encryptedApiKey = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
    $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);

    if ($this->validateApiKey($decryptedApiKey)) {
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        // $action = $_GET['action'] ?? '';
        
        try {
            switch ($action) {
                
                case 'classfees':
                    $this->fetchClassFees();
                    break;
                case 'schoolfees':
                    $this->fetchSchoolFees();
                    break;
                case 'updatefee':
                    $this->updateFee();
                    break;
                default:
                    echo "Invalid action.";
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

    public function fetchClassFees()
    {
        $schoolId = isset($_GET['schoolid']) ? $_GET['schoolid'] : '';
        $classId = isset($_GET['classid']) ? $_GET['classid'] : '';

        if (empty($schoolId) || empty($classId)) {
            echo "Both schoolid and classid parameters are required.";
            return;
        }

        // Database retrieval logic for class fees using PDO
        try {
            $sql = "SELECT * FROM fees WHERE school_id = :schoolId AND class_id = :classId";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':schoolId', $schoolId, PDO::PARAM_INT);
            $stmt->bindParam(':classId', $classId, PDO::PARAM_INT);
            $stmt->execute();

            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: application/json');
            echo json_encode($data);
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function fetchSchoolFees()
    {
        $schoolId = isset($_GET['schoolid']) ? $_GET['schoolid'] : '';

        if (empty($schoolId)) {
            echo "schoolid parameter is required.";
            return;
        }

        // Database retrieval logic for school fees using PDO
        try {
            $sql = "SELECT * FROM fees WHERE school_id = :schoolId";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':schoolId', $schoolId, PDO::PARAM_INT);
            $stmt->execute();

            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: application/json');
            echo json_encode($data);
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function updateFee()
    {
        $username = isset($_GET['username']) ? intval($_GET['username']) : '';
        $month = isset($_GET['month']) ? $_GET['month'] : '';
        $paidStatus = isset($_GET['paid_status']) ? intval($_GET['paid_status']) : '';

        // Database update logic using PDO
        try {
            $sql = "UPDATE fees SET paid_status = :paidStatus WHERE username = :username AND month = :month";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':username', $username, PDO::PARAM_INT);
            $stmt->bindParam(':month', $month, PDO::PARAM_STR);
            $stmt->bindParam(':paidStatus', $paidStatus, PDO::PARAM_INT);

            if ($stmt->execute()) {
                echo "Fee updated successfully.";
            } else {
                echo "Error updating fee.";
            }
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
        }
    }
}

// Usage
$controller = new FeeManagementController();
$controller->handleRequest();

?>