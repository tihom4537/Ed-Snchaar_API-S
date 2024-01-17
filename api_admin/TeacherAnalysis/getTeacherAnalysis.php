<?php
// ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../DbConnect.php';


class TestController
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
                    $school_id = isset($_GET['school_id']) ? $_GET['school_id'] : null;
                    $teacher_username = isset($_GET['teacher_username']) ? $_GET['teacher_username'] : null;

                    if ($school_id !== null && $teacher_username !== null) {
                        $this->getAverageMarks($school_id, $teacher_username);
                    } else {
                        echo json_encode(['error' => "Both school_id and teacher_username are required."]);
                    }
                } else {
                    echo json_encode(['error' => "Method not allowed"]);
                }
            } catch (Exception $e) {
                // Handle exceptions here
                echo json_encode(['error' => $e->getMessage()]);
            }
        
    }
    




public function getAverageMarks($school_id, $teacher_username)
{
    if ($school_id === null || $teacher_username === null) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => "Both school_id and teacher_username are required."]);
        return;
    }

    try {
        // Fetch the subjects and classes associated with the teacher
        $teacherSubjects = $this->getTeacherSubjects($school_id, $teacher_username);
        $teacherClasses = $this->getTeacherClasses($school_id, $teacher_username);

        if (empty($teacherSubjects) || empty($teacherClasses)) {
            http_response_code(404); // Not Found
            echo json_encode(['error' => "No subjects or classes found for the given teacher."]);
            return;
        }

        // Prepare an array to store consolidated data
        $consolidatedData = [];

        // Iterate through each subject and class
foreach (array_map(null, $teacherSubjects, $teacherClasses) as [$subject, $class]) {
    $subjects = explode(', ', $subject['subject']);
    $class_ids = explode(', ', $class['class_id']);

    foreach ($subjects as $index => $individualSubject) {
        $class_id = $class_ids[$index];
        $classSubjectKey = $class_id . '_' . $individualSubject;

                    // Check if entry for class and subject exists in consolidatedData
                    if (!isset($consolidatedData[$classSubjectKey])) {
                        $consolidatedData[$classSubjectKey] = [
                            'school_id' => $school_id,
                            'class_id' => $class_id,
                            'marks' => [],
                        ];
                    }

                    // Calculate average marks for the current subject and class ID
                    $sql = "SELECT
                                subject, SUM(marks_obtained)/SUM(max_marks) AS average_marks
                            FROM
                                marks
                            WHERE
                                school_id = :school_id
                                AND subject = :subject
                                AND class_id = :class_id
                            GROUP BY
                                subject";
                    $stmt = $this->conn->prepare($sql);
                    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
                    $stmt->bindParam(':subject', $individualSubject, PDO::PARAM_STR);
                    $stmt->bindParam(':class_id', $class_id, PDO::PARAM_STR);
                    $stmt->execute();
                    $averageMarks = $stmt->fetch(PDO::FETCH_ASSOC);

                    // Update average_marks in consolidatedData
                    $consolidatedData[$classSubjectKey]['marks'][$individualSubject] = $averageMarks['average_marks'];

                    // Now, calculate average marks for other distinct subjects
                    $sqlOtherSubjects = "SELECT
                                            subject, SUM(marks_obtained)/SUM(max_marks) AS average_marks
                                        FROM
                                            marks
                                        WHERE
                                            school_id = :school_id
                                            AND class_id = :class_id
                                            AND subject != :subject
                                        GROUP BY
                                            subject";
                    $stmtOtherSubjects = $this->conn->prepare($sqlOtherSubjects);
                    $stmtOtherSubjects->bindParam(':school_id', $school_id, PDO::PARAM_STR);
                    $stmtOtherSubjects->bindParam(':subject', $individualSubject, PDO::PARAM_STR);
                    $stmtOtherSubjects->bindParam(':class_id', $class_id, PDO::PARAM_STR);
                    $stmtOtherSubjects->execute();
                    $averageMarksOtherSubjects = $stmtOtherSubjects->fetchAll(PDO::FETCH_ASSOC);

                    // Add average marks for other distinct subjects to consolidatedData
                    foreach ($averageMarksOtherSubjects as $avgMark) {
                        $consolidatedData[$classSubjectKey]['marks']['dist_' . $avgMark['subject']] = $avgMark['average_marks'];
                    }
                }
            }

        // Convert the associative array to a numerically indexed array
        $consolidatedData = array_values($consolidatedData);

        header('Content-Type: application/json');
        echo json_encode($consolidatedData);

    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Error fetching average marks: ' . $e->getMessage()]);
    }
}




// Function to get other distinct subjects' average marks
private function getOtherSubjects($school_id, $class_id, $excludeSubject)
{
    $sql = "SELECT
                subject,
                AVG(marks_obtained / max_marks) AS average_marks
            FROM
                marks
            WHERE
                school_id = :school_id
                AND class_id = :class_id
                AND subject != :exclude_subject
            GROUP BY
                subject";
    $stmt = $this->conn->prepare($sql);
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
    $stmt->bindParam(':class_id', $class_id, PDO::PARAM_STR);
    $stmt->bindParam(':exclude_subject', $excludeSubject, PDO::PARAM_STR);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



    // Function to get distinct subjects taught by a teacher
    private function getTeacherSubjects($school_id, $teacher_username)
    {
        $sql = "SELECT DISTINCT subject FROM teacher WHERE school_id = :school_id AND username = :teacher_username";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
        $stmt->bindParam(':teacher_username', $teacher_username, PDO::PARAM_STR);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Function to get distinct classes taught by a teacher
    private function getTeacherClasses($school_id, $teacher_username)
    {
        $sql = "SELECT DISTINCT class_id FROM teacher WHERE school_id = :school_id AND username = :teacher_username";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
        $stmt->bindParam(':teacher_username', $teacher_username, PDO::PARAM_STR);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$testController = new TestController();
$testController->handleRequest();
?>
