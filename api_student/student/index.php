<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../DbConnect.php'; // Replace with your actual database connection code

// include './school/school.php'; // Include your controller class

class UserController
{
    private $headers;
    private $expectedApiKey;
    private $encryptedApikey;
    
    private $conn;
    
    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv ; // 128 bits IV

    public function __construct()
    {
        $objDb = new DbConnect;
        $this->conn = $objDb->connect();
        $this->expectedApiKey = $_ENV["API"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }

    public function handleRequest()
    {

        $headers = getallheaders();
        $encryptedApiKey = $headers['Cookies'];
    
        $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);
    


    // foreach (apache_request_headers() as $name => $value) {
    //     echo "$name: $value\n";
    // }
    
    // $data = json_encode($apiKey);
    // echo $data;
    // return $data;

    if ($this->validateApiKey($decryptedApiKey)){
        
        $method = $_SERVER['REQUEST_METHOD'];

       try{ if ($method === "GET") {
            $this->getUsers();
        } elseif ($method === "POST") {
            $this->createUser();
        } elseif ($method === "PUT") {
            $this->updateUser();
        } elseif ($method === "DELETE") {
            $this->deleteUser();
        }
         }catch (Exception $e) {
        // Handle exceptions here
         echo json_encode(['error' => $e->getMessage()]);
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
        // echo ($this->expectedApiKey);
        // echo ($apiKey);
        return $apiKey === $this->expectedApiKey;
    }

    public function getUsers()
    {
        $sql = "SELECT * FROM users";
        $path = explode('/', $_SERVER['REQUEST_URI']);
        if (isset($path[3]) && is_numeric($path[3])) {
            $sql .= " WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $path[3]);
            $stmt->execute();
            $users = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode($users);

    }

    public function createUser()
    {
        $user = json_decode(file_get_contents('php://input'));

        // Trim and sanitize data
        $user->mobile = trim($user->mobile);
        $user->phone = trim($user->phone);

        // Check if the school ID is available
        if (!$this->isSchoolIdAvailable($user->school_id)) {
            $response = ['status' => 0, 'message' => 'School ID is not available.'];
        } else {
            $sql = "INSERT INTO users (name, school_id, roll_no, father_name, mother_name, email, mobile, phone, created_at) VALUES (:name, :school_id, :roll_no, :father_name, :mother_name, :email, :mobile, :phone, :created_at)";
            $stmt = $this->conn->prepare($sql);
            $created_at = date('Y-m-d');
            $stmt->bindParam(':name', $user->name);
            $stmt->bindParam(':school_id', $user->school_id);
            $stmt->bindParam(':roll_no', $user->roll_no);
            $stmt->bindParam(':father_name', $user->father_name);
            $stmt->bindParam(':mother_name', $user->mother_name);
            $stmt->bindParam(':email', $user->email);
            $stmt->bindParam(':mobile', $user->mobile);
            $stmt->bindParam(':phone', $user->phone);
            $stmt->bindParam(':created_at', $created_at);

            if ($stmt->execute()) {
                $response = ['status' => 1, 'message' => 'Record created successfully.'];
            } else {
                $response = ['status' => 0, 'message' => 'Failed to create record.'];
            }
        }

        echo json_encode($response);
    }


    public function isSchoolIdAvailable($id)
    {
        try {
            $sql = "SELECT COUNT(*) FROM school WHERE id_school = :id"; // Use "id" as the column name
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_STR);
            $stmt->execute();

            // Fetch the result (number of rows with the given school ID)
            $result = $stmt->fetchColumn();

            // Return true if the school ID exists, false otherwise
            return $result > 0;
        } catch (PDOException $e) {
            // Handle school database connection errors here
            // You might want to log the error for debugging
            // For simplicity, we're returning false in case of an error
            return false;
        }
    }

    public function updateUser()
    {
        $user = json_decode(file_get_contents('php://input'));

        // Trim and sanitize data
        $user->mobile = trim($user->mobile);
        $user->phone = trim($user->phone);

        $sql = "UPDATE users SET name = :name, school_id = :school_id, roll_no = :roll_no, father_name = :father_name, mother_name = :mother_name, email = :email, mobile = :mobile, phone = :phone, updated_at = :updated_at WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $updated_at = date('Y-m-d');
        $stmt->bindParam(':id', $user->id);
        $stmt->bindParam(':name', $user->name);
        $stmt->bindParam(':school_id', $user->school_id);
        $stmt->bindParam(':roll_no', $user->roll_no);
        $stmt->bindParam(':father_name', $user->father_name);
        $stmt->bindParam(':mother_name', $user->mother_name);
        $stmt->bindParam(':email', $user->email);
        $stmt->bindParam(':mobile', $user->mobile);
        $stmt->bindParam(':phone', $user->phone);
        $stmt->bindParam(':updated_at', $updated_at);

        if ($stmt->execute()) {
            $response = ['status' => 1, 'message' => 'Record updated successfully.'];
        } else {
            $response = ['status' => 0, 'message' => 'Failed to update record.'];
        }
        echo json_encode($response);
    }

    

     

    public function deleteUser()
    {
        $path = explode('/', $_SERVER['REQUEST_URI']);
        $sql = "DELETE FROM users WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $path[3]);

        if ($stmt->execute()) {
            $response = ['status' => 1, 'message' => 'Record deleted successfully.'];
        } else {
            $response = ['status' => 0, 'message' => 'Failed to delete record.'];
        }
        echo json_encode($response);
    }
}

$userController = new UserController();
$userController->handleRequest();
?>