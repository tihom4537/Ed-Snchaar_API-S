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

class CommunicationUpdateApi
{
     private $conn;
    private $expectedApiKey;
    private $encryptedApikey;
    
    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv ; // 128 bits IV


    public function __construct()
    {
        $objDb = new DbConnect();
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

        if ($method == 'GET') {
            $this->fetchCommunicationUpdate();
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
    
    private function validateApiKey($decryptedApiKey)
    {
        // Compare the extracted API key with the expected API key
        return $decryptedApiKey === $this->expectedApiKey;
    }
    

    public function fetchCommunicationUpdate()
{
    $username = isset($_GET['username']) ? $_GET['username'] : '';

    if (empty($username)) {
        echo "Username parameter is required.";
        return;
    }

    try {
        // Fetch distinct sender usernames with names
        $senderUsernames = $this->getSenderUsernamesWithNames($username);

        $messageCounts = [];
        foreach ($senderUsernames as $senderInfo) {
            $messageCount = $this->getMessageCount($username, $senderInfo['sender_username']);
            $messageCounts[] = [
                'sender_username' => $senderInfo['sender_username'],
                'sender_name' => $senderInfo['name'],
                'message_count' => $messageCount,
            ];
        }

        $response = [
            'username' => $username,
            'message_counts' => $messageCounts,
        ];

        header('Content-Type: application/json');
        echo json_encode($response);
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}

private function getSenderUsernamesWithNames($receiverUsername)
{
    $sql = "SELECT DISTINCT c.sender_username, u.name 
            FROM communication c
            JOIN users u ON c.sender_username = u.username
            WHERE c.receiver_username = :username";

    $stmt = $this->conn->prepare($sql);
    $stmt->bindParam(':username', $receiverUsername, PDO::PARAM_STR);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


    private function getMessageCount($receiverUsername, $senderUsername)
    {
        $sql = "SELECT COUNT(*) as message_count FROM communication WHERE sender_username = :sender AND receiver_username = :receiver";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':sender', $senderUsername, PDO::PARAM_STR);
        $stmt->bindParam(':receiver', $receiverUsername, PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['message_count'];
    }
}

$controller = new CommunicationUpdateApi();
$controller->handleRequest();
?>
