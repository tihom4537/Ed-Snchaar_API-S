<?php
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include 'DbConnect.php';

class MarksController
{
    
    private $conn;
    

    public function __construct()
    {
        $objDb = new DbConnect();
        $this->conn = $objDb->connect();
    }


public function handleRequest()
{
    $method = $_SERVER['REQUEST_METHOD'];

    try {
        if ($method === "GET") {
            $exam_ids = isset($_GET['exam_id']) ? $_GET['exam_id'] : [];

            if (!empty($exam_ids)) {
                $this->getMarks($exam_ids);
            } else {
                echo json_encode(['error' => "At least one exam_id is required."]);
            }
        } else {
            echo json_encode(['error' => "Method not allowed"]);
        }
    } catch (Exception $e) {
        // Handle exceptions here
        echo json_encode(['error' => $e->getMessage()]);
    }
}

//   public function getMarks($exam_ids)
// {
//     // Convert the comma-separated string to an array
//     $exam_ids_array = explode(',', $exam_ids);

//     // Use the IN clause in the SQL query to fetch data for multiple exam_ids
//     $sql = "SELECT m.*, u.name, u.roll_no FROM marks m
//             JOIN users u ON m.student_id = u.username
//             WHERE m.exam_id IN (" . implode(',', array_map('intval', $exam_ids_array)) . ")";
    
//     $stmt = $this->conn->prepare($sql);
//     $stmt->execute();
//     $marks = $stmt->fetchAll(PDO::FETCH_ASSOC);

//     $result = []; // This array will store the final result

//     foreach ($marks as $mark) {
//         $examId = $mark['exam_id'];
//         $subject = $mark['subject'];
//         $maxMarks = $mark['max_marks'];
//         $schoolId = $mark['school_id'];
//         $classId = $mark['class_id'];

//         // Check if the examId is already a key in the result array
//         if (!isset($result[$examId])) {
//             // If not, add the common fields for this exam
//             $result[$examId] = [
//                 'exam_id' => $examId,
//                 'subject' => $subject,
//                 'max_marks' => $maxMarks,
//                 'school_id' => $schoolId,
//                 'class_id' => $classId,
//                 'students' => [], // This array will store individual student data
//             ];
//         }

//         // Add individual student data
//         $result[$examId]['students'][] = [
//             'student_id' => $mark['student_id'],
//             'id' => $mark['id'],
//             'marks_obtained' => $mark['marks_obtained'],
//             'name' => $mark['name'],
//             'roll_no' => $mark['roll_no'],
//         ];
//     }

//     // Convert the result array to JSON
//     header('Content-Type: application/json');
//     echo json_encode(array_values($result));
// }


public function getMarks($exam_ids)
{
    // Convert the comma-separated string to an array
    $exam_ids_array = explode(',', $exam_ids);

    // Use the IN clause in the SQL query to fetch data for multiple exam_ids
    $sql = "SELECT m.*, u.name, u.roll_no, e.ExamName as exam_name FROM marks m
            JOIN users u ON m.student_id = u.username
            JOIN Exam e ON m.exam_id = e.ExamID
            WHERE m.exam_id IN (" . implode(',', array_map('intval', $exam_ids_array)) . ")";
    
    $stmt = $this->conn->prepare($sql);
    $stmt->execute();
    $marks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = []; // This array will store the final result

    foreach ($marks as $mark) {
        $examId = $mark['exam_id'];
        $subject = $mark['subject'];
        $maxMarks = $mark['max_marks'];
        $schoolId = $mark['school_id'];
        $classId = $mark['class_id'];
        $examName = $mark['exam_name']; // Include exam_name in the result

        // Check if the examId is already a key in the result array
        if (!isset($result[$examId])) {
            // If not, add the common fields for this exam
            $result[$examId] = [
                'exam_id' => $examId,
                'exam_name' => $examName,
                'subject' => $subject,
                'max_marks' => $maxMarks,
                'school_id' => $schoolId,
                'class_id' => $classId,
                'students' => [], // This array will store individual student data
            ];
        }

        // Add individual student data
        $result[$examId]['students'][] = [
            'student_id' => $mark['student_id'],
            'id' => $mark['id'],
            'marks_obtained' => $mark['marks_obtained'],
            'name' => $mark['name'],
            'roll_no' => $mark['roll_no'],
        ];
    }

    // Convert the result array to JSON
    header('Content-Type: application/json');
    echo json_encode(array_values($result));
}





}

$testController = new MarksController();
$testController->handleRequest();
?>
