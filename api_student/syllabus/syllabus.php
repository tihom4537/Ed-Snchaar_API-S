<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");


//loading the environment variables
require_once  __DIR__. '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load();

include '../DbConnect.php';

class FeeManagementController
{   private $headers;
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
        $this->expectedApiKey = $_ENV["SYLLABUS"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }

    public function handleRequest()
    {
        $headers=getallheaders();
        $encryptedApiKey = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);


        
        
        if ($this->validateApiKey($decryptedApiKey)){
        $action = isset($_GET['action']) ? $_GET['action'] : '';

        // assigning the function according to request methods
        try{ switch ($action) {
            case 'classfees':
                $this->fetchSubject();
                break;
            case 'fetchSubjectSyllabus':
                $this->fetchSubjectTopic();
                break;
            case 'fetchSpeceficTopic':
                $this->fetchSpeceficTopic();
                break;
            case 'updateProgress':
                $this->updateProgress();
                break;
            case 'addSyllabus':
                $this->addSyllabus();
                break;
            default:
                echo "Invalid action.";
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
        return $apiKey = $this->expectedApiKey;
    }

    public function fetchSubject()
    // fetch fee for the whole class using clas id and school id 
    {
        $schoolId = isset($_GET['schoolid']) ? $_GET['schoolid'] : '';
        $classId = isset($_GET['classid']) ? $_GET['classid'] : '';

        if (empty($schoolId) || empty($classId)) {
            echo "Both schoolid and classid parameters are required.";
            return;
        }

        // Database retrieval logic for class fees using PDO
        try {
            $sql = "SELECT DISTINCT subject FROM syllabus WHERE school_id = :schoolId AND class_id = :classId";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':schoolId', $schoolId, PDO::PARAM_INT);
            $stmt->bindParam(':classId', $classId, PDO::PARAM_INT);
            $stmt->execute();

            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: application/json');
            echo json_encode($data);
        } catch (PDOException $e) {
            echo ("syllabus not found for school Id: $schoolId and class Id: $classId");
            echo "Error: " . $e->getMessage();
        }
    }

    public function fetchSubjectTopic()
    {
        $schoolId = isset($_GET['schoolid']) ? $_GET['schoolid'] : '';
        $classId = isset($_GET['classid']) ? $_GET['classid'] : '';
        $subject = isset($_GET['subject']) ? $_GET['subject'] : '';

        if (empty($schoolId)) {
            echo "schoolid parameter is required.";
            return;
        }

        // Database retrieval logic for school fees using PDO
        try {
            // $sql = "SELECT subject_content FROM syllabus WHERE school_id = :schoolId AND class_id = :classId AND subject = :subject";
            $sql = "SELECT * FROM syllabus WHERE school_id = :schoolId AND class_id = :classId AND subject = :subject";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':schoolId', $schoolId, PDO::PARAM_INT);
            $stmt->bindParam(':classId', $classId, PDO::PARAM_INT);
            $stmt->bindParam(':subject', $subject, PDO::PARAM_STR); // Use PDO::PARAM_STR for string parameters
            $stmt->execute();

            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: application/json');
            echo json_encode($data);
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
        }
    }
    public function fetchSpeceficTopic()
    {
        $schoolId = isset($_GET['schoolid']) ? $_GET['schoolid'] : '';
        $classId = isset($_GET['classid']) ? $_GET['classid'] : '';
        $subject = isset($_GET['subject']) ? $_GET['subject'] : '';
        $subject_content = isset($_GET['subject_content']) ? $_GET['subject_content'] : '';

        if (empty($schoolId)) {
            echo "schoolid parameter is required.";
            return;
        }

        // Database retrieval logic for school fees using PDO
        try {
            // $sql = "SELECT subject_content FROM syllabus WHERE school_id = :schoolId AND class_id = :classId AND subject = :subject";
            $sql = "SELECT * FROM syllabus WHERE school_id = :schoolId AND class_id = :classId AND subject = :subject AND subject_content = :subject_content";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':schoolId', $schoolId, PDO::PARAM_INT);
            $stmt->bindParam(':classId', $classId, PDO::PARAM_INT);
            $stmt->bindParam(':subject', $subject, PDO::PARAM_STR);
            $stmt->bindParam(':subject_content', $subject_content, PDO::PARAM_STR);
            $stmt->execute();

            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: application/json');
            echo json_encode($data);
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function updateProgress()
    {
        // must pass pass school ID as school_id, Class ID as class_id and status as status
        $class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : '';
        $school_id = isset($_GET['school_id']) ? intval($_GET['school_id']) : '';
        $subject = isset($_GET['subject']) ? $_GET['subject'] : '';
        $subject_content = isset($_GET['subject_content']) ? ($_GET['subject_content']) : '';
        $status = isset($_GET['status']) ? intval($_GET['status']) : '';

        // Database update logic using PDO
        try {
            $sql = "UPDATE syllabus 
                SET status = :status 
                WHERE subject_content = :subject_content 
                AND school_id = :school_id 
                AND class_id = :class_id 
                AND subject = :subject";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
            $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT); // Add ':' before school_id
            $stmt->bindParam(':subject', $subject, PDO::PARAM_STR);
            $stmt->bindParam(':subject_content', $subject_content, PDO::PARAM_STR);
            $stmt->bindParam(':status', $status, PDO::PARAM_INT);

            if ($stmt->execute()) {
                echo "Fee updated successfully.";
            } else {
                echo "Error updating fee.";
            }
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
        }
    }
    public function addSyllabus()
    {
        // must pass pass school ID as school_id, Class ID as class_id and status as status
        $class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : '';
        $school_id = isset($_GET['school_id']) ? intval($_GET['school_id']) : '';
        $subject = isset($_GET['subject']) ? $_GET['subject'] : '';
        $subject_content = isset($_GET['subject_content']) ? ($_GET['subject_content']) : '';
        $status = isset($_GET['status']) ? intval($_GET['status']) : '';


        try {
            $sql = "INSERT INTO syllabus (class_id, school_id, subject, subject_content, status) 
                    VALUES (:class_id, :school_id, :subject, :subject_content, :status )"; // Add ':' before school_id

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
            $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT); // Add ':' before school_id
            $stmt->bindParam(':subject', $subject, PDO::PARAM_STR);
            $stmt->bindParam(':subject_content', $subject_content, PDO::PARAM_STR);
            $stmt->bindParam(':status', $status, PDO::PARAM_INT);

            if ($stmt->execute()) {
                echo "Syllabus added successfully.";
            } else {
                echo "Error adding syllabus.";
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