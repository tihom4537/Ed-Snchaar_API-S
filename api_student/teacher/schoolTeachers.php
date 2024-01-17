<?php
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../DbConnect.php';

//loading the environment variables
require_once  __DIR__. '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load();

class TeacherController
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
        $this->expectedApiKey = $_ENV["TEACHER"];
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

            try {
                if ($method === "GET") {
                    $school_id = isset($_GET['school_id']) ? $_GET['school_id'] : null;
                    $class_id = isset($_GET['class_id']) ? $_GET['class_id'] : null;

                    if ($school_id !== null && $class_id !== null) {
                        $this->getTeachers($school_id, $class_id);
                    } else {
                        echo json_encode(['error' => "Both school_id and class_id parameters are required."]);
                    }
                } else {
                    echo json_encode(['error' => "Method not allowed"]);
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

    // Function to get teachers (returns all users in the table)
    // Modify the getTeachers function
    
    
// public function getTeachers($school_id, $class_id)
// {
//     $sql = "SELECT * FROM teacher WHERE school_id = :school_id";
//     $stmt = $this->conn->prepare($sql);
//     $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
//     $stmt->execute();
//     $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

//     // Filter teachers based on the provided class_id
//     $filteredTeachers = array_filter($teachers, function ($teacher) use ($class_id) {
//         // Explode the class_id field to get an array of class IDs for the teacher
//         $teacherClassIds = explode(', ', $teacher['class_id']);
//         // Check if the provided class_id is present in the teacher's class IDs
//         return in_array($class_id, $teacherClassIds);
//     });

//     header('Content-Type: application/json');
//     echo json_encode($filteredTeachers);
// }

// Modify the getTeachers function
public function getTeachers($school_id, $class_id)
{
    $sql = "SELECT * FROM teacher WHERE school_id = :school_id";
    $stmt = $this->conn->prepare($sql);
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
    $stmt->execute();
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filter teachers based on the provided class_id
    $filteredTeachers = array_filter($teachers, function ($teacher) use ($class_id) {
        // Explode the class_id field to get an array of class IDs for the teacher
        $teacherClassIds = explode(', ', $teacher['class_id']);
        // Check if the provided class_id is present in the teacher's class IDs
        return in_array($class_id, $teacherClassIds);
    });

    // Extract subjects corresponding to the provided class_id
    $subjects = array_map(function ($teacher) use ($class_id) {
        // Explode the subject field to get an array of subjects for the teacher
        $teacherSubjects = explode(', ', $teacher['subject']);
        // Find the subject corresponding to the provided class_id
        $key = array_search($class_id, explode(', ', $teacher['class_id']));
        return $teacherSubjects[$key] ?? null;
    }, $filteredTeachers);

    // Combine the filtered teachers with the corresponding subjects
    $result = array_map(function ($teacher, $subject) use ($class_id){
        $teacher['subject'] = $subject;
        $teacher['class_id'] = $class_id;

        return $teacher;
    }, $filteredTeachers, $subjects);

    header('Content-Type: application/json');
    echo json_encode($result);
}


}

$teacherController = new TeacherController();
$teacherController->handleRequest();
?>