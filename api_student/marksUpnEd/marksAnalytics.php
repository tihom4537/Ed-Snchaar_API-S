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


class MarksController
{
    //   $positionByMarks=0;
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
        $this->expectedApiKey = $_ENV["SYLLABUS"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }

    private function validateApiKey($apiKey)
    {
        // Compare the extracted API key with the expected API key
        return $apiKey === $this->expectedApiKey;
    }

    // Function to calculate statistics for marks
    public function calculateStatistics()
    {
        $headers = getallheaders();
        $encryptedApiKey = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);


        if ($this->validateApiKey($decryptedApiKey)) {
            $method = $_SERVER['REQUEST_METHOD'];

            try {
                if ($method === "GET") {
                    $class_id = isset($_GET['class_id']) ? $_GET['class_id'] : null;
                    $subject = isset($_GET['subject']) ? $_GET['subject'] : null;
                    $exam_id = isset($_GET['exam_id']) ? $_GET['exam_id'] : null;
                    $username = isset($_GET['username']) ? $_GET['username'] : null;
                    
                    if ($username !==null){
                        $trimmedUsername = trim($username);
                        
                    }
                    
                    
                    // echo $username;


                    if ($class_id !== null) {
                        $result = $this->getStatistics($class_id, $subject, $exam_id,$trimmedUsername );
                        echo json_encode($result);
                    } else {
                        echo json_encode(['error' => "Table name is required."]);
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
            echo json_encode(['error' => 'Access denied.Invalid API key.']);
        }
    }
    
     function trimUsername($username) {
    // Trim leading and trailing whitespaces
    $trimmedUsername = trim($username);
    
    return $trimmedUsername;
      }
    
    
     private function decryptData($data, $encryptionKey, $iv) {
        $plainText = openssl_decrypt($data, 'AES-256-CFB', $encryptionKey, 0, $iv);
        return $plainText;
    }
    
//     public function getStatistics($class_id, $subject, $exam_id, $trimmedUsername)  
//     {

//     $query = "SELECT marks_obtained, student_id FROM marks WHERE class_id = :class_id AND subject = :subject AND exam_id = :exam_id ORDER BY marks_obtained DESC";
//     $stmt = $this->conn->prepare($query);
//     if ($stmt === false) {
//         die(print_r($this->conn->errorInfo(), true));
//     }

//     $stmt->bindParam(':class_id', $class_id, PDO::PARAM_STR);
//     $stmt->bindParam(':subject', $subject, PDO::PARAM_STR);
//     $stmt->bindParam(':exam_id', $exam_id, PDO::PARAM_INT);
    
//     // var_dump( $trimmedUsername);
//     //  $positionByMarks=0;
//     if ($stmt->execute()) {
//         $marks = array();
//         $position = 1;
//         $highestMark = 0;
        
//         //  $positionByMarks;
    
//     while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
//     $mark = (int)$row["marks_obtained"];
//     $studentUsername = $row['student_id'];

//     if ($mark > $highestMark) {
//         $highestMark = $mark;
//     }

//     $marks[] = $mark;

//     if ($studentUsername === $trimmedUsername) {
//         $positionByMarks = $position; // Assign position if username matches
//     }

//     $position++;
// }

// $averageMarks = count($marks) > 0 ? array_sum($marks) / count($marks) : 0;
// $averageMarks = number_format($averageMarks, 2); // Format to 2 decimal places
// $highestMark = number_format($highestMark, 2); // Format to 2 decimal places

// return [
//     "average_marks" => $averageMarks,
//     "highest_marks" => $highestMark,
//     "position_by_marks" => $positionByMarks // Position based on descending marks
// ];

    
    
//     } else {
//         return ['error' => 'Failed to execute the query.'];
//     }
// }
public function getStatistics($class_id, $subject, $exam_id, $trimmedUsername)
{
    $distinctMarksQuery = "SELECT DISTINCT marks_obtained FROM marks WHERE class_id = :class_id AND subject = :subject AND exam_id = :exam_id ORDER BY marks_obtained DESC";
    $distinctMarksStmt = $this->conn->prepare($distinctMarksQuery);

    if ($distinctMarksStmt === false) {
        die(print_r($this->conn->errorInfo(), true));
    }

    $distinctMarksStmt->bindParam(':class_id', $class_id, PDO::PARAM_STR);
    $distinctMarksStmt->bindParam(':subject', $subject, PDO::PARAM_STR);
    $distinctMarksStmt->bindParam(':exam_id', $exam_id, PDO::PARAM_INT);

    if ($distinctMarksStmt->execute()) {
        $distinctMarks = $distinctMarksStmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        die(print_r($distinctMarksStmt->errorInfo(), true));
    }

    $studentMarksQuery = "SELECT marks_obtained FROM marks WHERE student_id = :student_id AND class_id = :class_id AND subject = :subject AND exam_id = :exam_id";
    $studentMarksStmt = $this->conn->prepare($studentMarksQuery);

    if ($studentMarksStmt === false) {
        die(print_r($this->conn->errorInfo(), true));
    }

    $studentMarksStmt->bindParam(':student_id', $trimmedUsername, PDO::PARAM_STR);
    $studentMarksStmt->bindParam(':class_id', $class_id, PDO::PARAM_STR);
    $studentMarksStmt->bindParam(':subject', $subject, PDO::PARAM_STR);
    $studentMarksStmt->bindParam(':exam_id', $exam_id, PDO::PARAM_INT);

    if ($studentMarksStmt->execute()) {
        $studentMarks = (int)$studentMarksStmt->fetchColumn();
    } else {
        die(print_r($studentMarksStmt->errorInfo(), true));
    }

    $positionByMarks = 0;
    foreach ($distinctMarks as $position => $mark) {
        if ($mark == $studentMarks) {
            $positionByMarks = $position + 1; // Positions are 1-based
            break;
        }
    }

    $averageMarksQuery = "SELECT AVG(marks_obtained) as average_marks FROM marks WHERE class_id = :class_id AND subject = :subject AND exam_id = :exam_id";
    $averageMarksStmt = $this->conn->prepare($averageMarksQuery);
    $averageMarksStmt->bindParam(':class_id', $class_id, PDO::PARAM_STR);
    $averageMarksStmt->bindParam(':subject', $subject, PDO::PARAM_STR);
    $averageMarksStmt->bindParam(':exam_id', $exam_id, PDO::PARAM_INT);

    if ($averageMarksStmt->execute()) {
        $averageMarks = (float)$averageMarksStmt->fetchColumn();
        $averageMarks = number_format($averageMarks, 2); // Format to 2 decimal places
    } else {
        die(print_r($averageMarksStmt->errorInfo(), true));
    }

    $highestMarkQuery = "SELECT MAX(marks_obtained) as highest_mark FROM marks WHERE class_id = :class_id AND subject = :subject AND exam_id = :exam_id";
    $highestMarkStmt = $this->conn->prepare($highestMarkQuery);
    $highestMarkStmt->bindParam(':class_id', $class_id, PDO::PARAM_STR);
    $highestMarkStmt->bindParam(':subject', $subject, PDO::PARAM_STR);
    $highestMarkStmt->bindParam(':exam_id', $exam_id, PDO::PARAM_INT);

    if ($highestMarkStmt->execute()) {
        $highestMark = (float)$highestMarkStmt->fetchColumn();
        $highestMark = number_format($highestMark, 2); // Format to 2 decimal places
    } else {
        die(print_r($highestMarkStmt->errorInfo(), true));
    }

    return [
        "average_marks" => $averageMarks,
        "highest_marks" => $highestMark,
        "position_by_marks" => $positionByMarks // Position based on descending marks
    ];
}





}

$marksController = new MarksController();
$marksController->calculateStatistics();
?>
