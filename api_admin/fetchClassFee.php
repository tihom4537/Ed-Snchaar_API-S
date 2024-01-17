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

class ClassMonthlyFee
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
        $objDb = new DbConnect();
        $this->conn = $objDb->connect();
         $this->expectedApiKey = $_ENV["FEES"];
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

        if ($method == 'GET') {
            $this->fetchClassFees();
        } else {
            http_response_code(405);
            echo json_encode(['error' => "Method not allowed"]);
        }
    }else {
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


    public function fetchClassFees()
    {
        $schoolId = isset($_GET['school_id']) ? $_GET['school_id'] : '';
        $classId = isset($_GET['class_id']) ? $_GET['class_id'] : '';
        $month = isset($_GET['month']) ? $_GET['month'] : '';

        if (empty($schoolId) || empty($classId)) {
            echo "Both schoolid and classid parameters are required.";
            return;
        }

        // Extract year and month from the "yyyy-month" format
        list($year, $formattedMonth) = explode("-", $month);
        $month = date('F', strtotime($formattedMonth));

        try {
            $usernamesAndClass = $this->getUsernamesAndClass($schoolId, $classId);
            $feesData = $this->getFeesForUsernamesAndClass($usernamesAndClass, $year, $month);

            header('Content-Type: application/json');
            echo json_encode($feesData);
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    private function getUsernamesAndClass($schoolId, $classId)
    {
        $sql = "SELECT username, class_id FROM users WHERE school_id = :schoolId AND class_id = :classId";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':schoolId', $schoolId, PDO::PARAM_INT);
        $stmt->bindParam(':classId', $classId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getFeesForUsernamesAndClass($usernamesAndClass, $year, $month)
{
    $feesData = [];

    foreach ($usernamesAndClass as $userData) {
        $username = $userData['username'];
        $classId = $userData['class_id'];


        $feeQuery = "SELECT * FROM fees WHERE username = :username AND YEAR(month) = :year AND MONTH(month) = :month";
        $feeStmt = $this->conn->prepare($feeQuery);
        $feeStmt->bindParam(':username', $username, PDO::PARAM_STR);
        $feeStmt->bindParam(':year', $year, PDO::PARAM_INT);

        // Use the month directly without using MONTH() function
        $feeStmt->bindParam(':month', $month, PDO::PARAM_STR);

        $feeStmt->execute();
        $feeData = $feeStmt->fetchAll(PDO::FETCH_ASSOC);

        // Combine username, class_id, and fee data
        $feesData[] = [
            'username' => $username,
            'class_id' => $classId,
            'fees' => $feeData,
        ];
    }

    return $feesData;
}

}

$controller = new ClassMonthlyFee();
$controller->handleRequest();
?>
