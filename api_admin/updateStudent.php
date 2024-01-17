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
      private $conn;
    private $expectedApiKey;
    private $encryptedApikey;
    
    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv ; // 128 bits IV

    public function __construct()
    {
        $db = new DbConnect();
        $this->conn = $db->connect();
         $this->expectedApiKey = $_ENV["CLASSLINK"];
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

        if ($method === 'POST') {
            $this->updateStudent();
        } else {
            // http_response_code(405); // Method Not Allowed
            echo json_encode(['data' => null, 'status' => 405, 'message' => 'Method Not Allowed']);
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

    public function updateStudent()
    {
        try {
            $student = json_decode(file_get_contents('php://input'));
            
            var_dump($student);
            
            // Trim and sanitize data
            $school_id = $student->school_id ?? null;
            $username = $student->username ?? null;
            $roll_no = $student->roll_no ?? null;
            $name = $student->name ?? null;
            $father_name = $student->father_name ?? null;
            $mother_name = $student->mother_name ?? null;
            $email = $student->email ?? null;
            $mobile = $student->mobile ?? null;
            $phone = $student->phone ?? null;

            var_dump( $username);
            echo $username;

            // Validation (add more as needed)
            if (empty($username) || empty($school_id)) {
                throw new Exception('Username and school ID are required.');
            }

            $sql = "UPDATE users SET roll_no = :roll_no, name = :name, father_name = :father_name, mother_name = :mother_name, email = :email, mobile = :mobile, phone = :phone WHERE username = :username AND school_id = :school_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(":school_id", $school_id);
            $stmt->bindParam(':roll_no', $roll_no);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':father_name', $father_name);
            $stmt->bindParam(':mother_name', $mother_name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':mobile', $mobile);
            $stmt->bindParam(':phone', $phone);

            if ($stmt->execute()) {
                $response = ['data' => null, 'status' => 200, 'message' => 'Student information updated successfully.'];
            } else {
                $response = ['data' => null, 'status' => 500, 'message' => 'Failed to update student information.'];
            }

        } catch (Exception $e) {
            $response = ['data' => null, 'status' => 400, 'message' => $e->getMessage()];
        }

        http_response_code($response['status']);
        echo json_encode($response);
    }
}

$controller = new CarouselController();
$controller->handleRequest();
?>
