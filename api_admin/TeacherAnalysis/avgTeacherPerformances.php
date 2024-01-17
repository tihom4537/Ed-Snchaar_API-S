<?php

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

                if ($school_id !== null) {
                    $this->getTeacherAverageMarks($school_id);
                } else {
                    echo json_encode(['error' => "school_id is required."]);
                }
            } else {
                echo json_encode(['error' => "Method not allowed"]);
            }
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

     public function getTeacherAverageMarks($school_id)
    {
        if ($school_id === null) {
            http_response_code(400);
            echo json_encode(['error' => "school_id is required."]);
            return;
        }

        try {
            $teacherData = $this->getTeacherData($school_id);

            if (empty($teacherData)) {
                http_response_code(404);
                echo json_encode(['error' => "No teachers found for the given school."]);
                return;
            }

            $teachersAverageMarks = [];

            foreach ($teacherData as $teacher) {
                $teacherUsername = $teacher['username'];
                $teacherName = $teacher['name'];

                $teacherAverage = $this->calculateTeacherAverageMarks($school_id, $teacherUsername);

                if ($teacherAverage !== false) {
                    $teachersAverageMarks[] = [
                        'teacher_username' => $teacherUsername,
                        'teacher_name' => $teacherName,
                        'teacher_average_marks' => $teacherAverage,
                    ];
                }
            }

            if (!empty($teachersAverageMarks)) {
                header('Content-Type: application/json');
                echo json_encode($teachersAverageMarks);
            } else {
                http_response_code(404);
                echo json_encode(['error' => "No data available to calculate average."]);
            }

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error fetching average marks: ' . $e->getMessage()]);
        }
    }

    private function getTeacherData($school_id)
    {
        $sql = "SELECT username, name FROM teacher WHERE school_id = :school_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
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

    
     private function calculateTeacherAverageMarks($school_id, $teacher_username)
    {
        try {
            $teacherSubjects = $this->getTeacherSubjects($school_id, $teacher_username);
            $teacherClasses = $this->getTeacherClasses($school_id, $teacher_username);

            if (empty($teacherSubjects) || empty($teacherClasses)) {
                return false; // Skip this teacher if no subjects or classes found
            }

            $teacherAverageMarks = 0;
            $totalAverages = 0;

            foreach (array_map(null, $teacherSubjects, $teacherClasses) as [$subject, $class]) {
                $subjects = explode(', ', $subject['subject']);
                $class_ids = explode(', ', $class['class_id']);

                foreach ($subjects as $index => $individualSubject) {
                    $class_id = $class_ids[$index];

                    $sql = "SELECT
                                SUM(m.marks_obtained )/SUM( m.max_marks) AS overall_average_marks
                            FROM
                                Exam e
                            JOIN
                                marks m ON e.ExamID = m.exam_id
                            WHERE
                                e.class_id = :class_id
                                AND e.subject = :subject
                                AND e.school_id = :school_id";

                    $stmt = $this->conn->prepare($sql);
                    $stmt->bindParam(':class_id', $class_id, PDO::PARAM_STR);
                    $stmt->bindParam(':subject', $individualSubject, PDO::PARAM_STR);
                    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
                    $stmt->execute();

                    $row = $stmt->fetch(PDO::FETCH_ASSOC);

                    $teacherAverageMarks += $row['overall_average_marks'];
                    $totalAverages++;
                }
            }

            if ($totalAverages > 0) {
                return ($teacherAverageMarks / $totalAverages);
            } else {
                return false; // Skip this teacher if no data available to calculate average
            }

        } catch (PDOException $e) {
            return false; // Skip this teacher if there is an error fetching data
        }
    }
    // Remaining functions are the same as before...
    

}

$testController = new TestController();
$testController->handleRequest();
?>
