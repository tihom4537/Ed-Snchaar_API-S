<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../../DbConnect.php'; // Include your actual database connection code

//loading the environment variables
require_once  __DIR__. '/../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..'); 
$dotenv->load();


class CommunicationController
{
    
    private $conn;
    private $expectedApiKey;
    private $encryptedApikey;
    
    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv ; // 128 bits IV

    public function __construct()
    {
        // Use the existing database connection from DbConnect.php
        $objDb = new DbConnect;
        $this->conn = $objDb->connect();
        $this->expectedApiKey = $_ENV["COM"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }

    public function handleRequest()
    {
         $headers = getallheaders();

        $encryptedApiKey = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
         $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);
         
    if ($this->validateApiKey($decryptedApiKey)){
        $method = $_SERVER['REQUEST_METHOD'];


        if ($method === "GET") {
            $this->getChats();
        } else {
            // Handle unsupported HTTP methods
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
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

    public function getChats()
{
    $teacher_username = isset($_GET['teacher_username']) ? $_GET['teacher_username'] : '';

    try {
        $sql = "SELECT chat_partners.chat_partner, u.class_id, u.name as student_name, u.gender
        FROM (
            SELECT DISTINCT sender_username AS chat_partner
            FROM communication
            WHERE receiver_username = :teacher_username
            UNION
            SELECT DISTINCT receiver_username AS chat_partner
            FROM communication
            WHERE sender_username = :teacher_username
        ) AS chat_partners
        LEFT JOIN users u ON chat_partners.chat_partner = u.username
        WHERE u.username IS NOT NULL
        ORDER BY (
            SELECT MAX(time_stamp) 
            FROM communication 
            WHERE (sender_username = :teacher_username OR receiver_username = :teacher_username) 
            AND (sender_username = chat_partners.chat_partner OR receiver_username = chat_partners.chat_partner)
        ) DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':teacher_username', $teacher_username);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Extract the chat partner names, usernames, and class_ids
        $response = [];
        foreach ($data as $row) {
    $response[] = [
        'student_username' => $row['chat_partner'],
        'student_name' => $row['student_name'],
        'class_id' => $row['class_id'],
        'gender' => $row['gender']
    ];
}

        header('Content-Type: application/json');
        echo json_encode($response);
    } catch (PDOException $e) {
        // Handle database errors
        return [];
    }
}




}

// Usage
$controller = new CommunicationController();
$controller->handleRequest();
?>