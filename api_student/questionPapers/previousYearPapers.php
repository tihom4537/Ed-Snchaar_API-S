<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Allow cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

// Include the database connection class
include '../DbConnect.php';

// Loading the environment variables
require_once  __DIR__. '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load();

class SchoolExtraFetch
{
    private $conn;
    private $expectedApiKey;
    private $encryptionKey;
    private $iv;

    public function __construct()
    {
        $objDb = new DbConnect();
        $this->conn = $objDb->connect();
        $this->expectedApiKey = $_ENV["NOTICE"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }

    public function handleRequest()
    {
        $headers = getallheaders();
        $encryptedApiKey = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        $decryptedApiKey = $this->decryptData($encryptedApiKey);

        if ($this->validateApiKey($decryptedApiKey)) {
            $method = $_SERVER['REQUEST_METHOD'];

            if ($method == 'GET') {
                $schoolId = isset($_GET['school_id']) ? $_GET['school_id'] : '';
                $class = isset($_GET['class']) ? $_GET['class'] : '';
                $subject = isset($_GET['subject']) ? $_GET['subject'] : '';

                if (empty($schoolId) || empty($class) || empty($subject)) {
                    $this->sendResponse(400, ['error' => 'school_id, class, and subject parameters are required.']);
                    return;
                }

                $this->fetchSchoolPapers($schoolId, $class, $subject);
            } else {
                $this->sendResponse(405, ['error' => 'Method not allowed']);
            }
        } else {
            $this->sendResponse(403, ['error' => 'Access denied. Invalid API key.']);
        }
    }

    private function decryptData($data)
    {
        $plainText = openssl_decrypt($data, 'AES-256-CFB', $this->encryptionKey, 0, $this->iv);
        return $plainText;
    }

    private function validateApiKey($decryptedApiKey)
    {
        return $decryptedApiKey === $this->expectedApiKey;
    }

    public function fetchSchoolPapers($schoolId, $class, $subject)
    {
        $papersQuery = "SELECT * FROM question_papers WHERE school_id = :schoolId AND class = :class AND subject = :subject";
        $papersStmt = $this->conn->prepare($papersQuery);
        $papersStmt->bindParam(':schoolId', $schoolId, PDO::PARAM_STR);
        $papersStmt->bindParam(':class', $class, PDO::PARAM_STR);
        $papersStmt->bindParam(':subject', $subject, PDO::PARAM_STR);
        $papersStmt->execute();
        $schoolPapers = $papersStmt->fetchAll(PDO::FETCH_ASSOC);

        // Check if papers were found
        if (empty($schoolPapers)) {
            $this->sendResponse(200, []);
        } else {
            $this->sendResponse(200, $schoolPapers);
        }
    }

    private function sendResponse($statusCode, $data)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

// Usage
$controller = new SchoolExtraFetch();
$controller->handleRequest();
?>
