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

class NoticeApi
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
        $objDb = new DbConnect();
        $this->conn = $objDb->connect();
         $this->expectedApiKey = $_ENV["SYLLABUS"];
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

        if ($method == 'GET') {
            $this->fetchPaycheckDetails();
        } else {
            http_response_code(405);
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

    private function validateApiKey($apiKey)
    {
        // Compare the extracted API key with the expected API key
        return $apiKey === $this->expectedApiKey;
    }

    public function fetchPaycheckDetails()
    {
        $username = isset($_GET['username']) ? $_GET['username'] : '';
        $school_id = isset($_GET['school_id']) ? $_GET['school_id'] : '';

        if (empty($username) || empty($school_id)) {
            echo json_encode(['error' => "Both Username and School Id are required."]);
            return;
        }

        try {
            // Construct your SQL query as per your requirements
            $sql = "SELECT * FROM payroll WHERE employee_username = :username AND school_id = :school_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
            $stmt->execute();
            $response = $stmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: application/json');
            echo json_encode($response);
        } catch (PDOException $e) {
            echo json_encode(['error' => "Error: " . $e->getMessage()]);
        }
    }


}


$controller = new NoticeApi();
$controller->handleRequest();
?>