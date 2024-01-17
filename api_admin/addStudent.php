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


class CarouselController
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
        $db = new DbConnect();
        $this->conn = $db->connect();
        $this->expectedApiKey = $_ENV["S_N_S"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }

    public function handleRequest()
    {
          $headers=getallheaders();

        $encryptedApiKey = $headers['Authorization'];
        
        $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);

 
        
        // Check if the API key is valid
    if ($this->validateApiKey($decryptedApiKey)) {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'POST') {
            $this->createUser();
        } else {
            echo json_encode(['status' => 405, 'message' => 'Method Not Allowed']);
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
    
    public function createUser()
    {
        $input = file_get_contents("php://input");
        if (empty($input)) {
            http_response_code(400); // Bad Request
            echo json_encode(['status' => 400, 'message' => 'Invalid data.']);
            return;
        }

        $user = json_decode($input);

        $requiredFields = ['name', 'school_id', 'roll_no', 'father_name', 'mother_name', 'email', 'mobile', 'phone', 'class_id', 'gender', 'Address', 'birthDate'];
        foreach ($requiredFields as $field) {
            if (!isset($user->{$field})) {
                http_response_code(400); // Bad Request
                echo json_encode(['status' => 400, 'message' => "Missing required field: $field"]);
                return;
            }
        }

        $user->mobile = trim($user->mobile);
        $user->phone = trim($user->phone);

        // Extract first initials of the name
        $nameParts = explode(' ', $user->name);
        $nameInitials = '';
        foreach ($nameParts as $part) {
            $nameInitials .= mb_convert_case(mb_substr($part, 0, 1), MB_CASE_UPPER, 'UTF-8');
        }

        // Extract first 3 initials of the school ID
        $schoolIdPrefix = mb_convert_case(mb_substr($user->school_id, 0, 3), MB_CASE_UPPER, 'UTF-8');

        // Generate a 3-digit random number
        $randomNumber = sprintf('%03d', mt_rand(0, 999));

        // Combine the components to create the username
        $username = $nameInitials . $schoolIdPrefix . $user->roll_no . $randomNumber;

        $sql = "INSERT INTO users (username, name, school_id, class_id, roll_no, father_name, mother_name, email, mobile, phone, gender, address, DOB, created_at) VALUES (:username, :name, :school_id, :class_id, :roll_no, :father_name, :mother_name, :email, :mobile, :phone, :gender, :address, :DOB, :created_at)";
        $stmt = $this->conn->prepare($sql);
        $created_at = date('Y-m-d');
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':name', $user->name);
        $stmt->bindParam(':school_id', $user->school_id);
        $stmt->bindParam(':class_id', $user->class_id);
        $stmt->bindParam(':roll_no', $user->roll_no);
        $stmt->bindParam(':father_name', $user->father_name);
        $stmt->bindParam(':mother_name', $user->mother_name);
        $stmt->bindParam(':email', $user->email);
        $stmt->bindParam(':mobile', $user->mobile);
        $stmt->bindParam(':phone', $user->phone);
        $stmt->bindParam(':address', $user->Address);
        $stmt->bindParam(':DOB', $user->birthDate);
        $stmt->bindParam(':gender', $user->gender);
        $stmt->bindParam(':created_at', $created_at);

        if ($stmt->execute()) {
            // If the user is successfully inserted, proceed to insert into the login table
            $password = $this->generateRandomAlphanumericPassword(8); // Generate a random 8-digit alphanumeric password

            $sqlLogin = "INSERT INTO login (username, password) VALUES (:username, :password)";
            $stmtLogin = $this->conn->prepare($sqlLogin);
            $stmtLogin->bindParam(':username', $username);
            $stmtLogin->bindParam(':password', $password); // Store hashed password

            // Insert into login table
            if ($stmtLogin->execute()) {
                $response = ['status' => 1, 'message' => 'Record created successfully.'];
            } else {
                $response = ['status' => 0, 'message' => 'Failed to create login record.'];
            }
        } else {
            $response = ['status' => 0, 'message' => 'Failed to create user record.'];
        }

        echo json_encode($response);
    }

    private function generateRandomAlphanumericPassword($length)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $password;
    }
}

$controller = new CarouselController();
$controller->handleRequest();
?>
