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
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function getAverageMarks($school_id, $teacher_username)
{
    if ($school_id === null || $teacher_username === null) {
        http_response_code(400);
        echo json_encode(['error' => "Both school_id and teacher_username are required."]);
        return;
    }

    try {
        $teacherSubjects = $this->getTeacherSubjects($school_id, $teacher_username);
        $teacherClasses = $this->getTeacherClasses($school_id, $teacher_username);

        if (empty($teacherSubjects) || empty($teacherClasses)) {
            http_response_code(404);
            echo json_encode(['error' => "No subjects or classes found for the given teacher."]);
            return;
        }

        $averageMarksData = [];

        foreach (array_map(null, $teacherSubjects, $teacherClasses) as [$subject, $class]) {
            $subjects = explode(', ', $subject['subject']);
            $class_ids = explode(', ', $class['class_id']);

            foreach ($subjects as $index => $individualSubject) {
                $class_id = $class_ids[$index];

                $sql = "SELECT
                            SUM(m.marks_obtained) / SUM(m.max_marks) AS average_marks,
                            e.date
                        FROM
                            Exam e
                        JOIN
                            marks m ON e.ExamID = m.exam_id
                        WHERE
                            e.class_id = :class_id
                            AND e.subject = :subject
                            AND e.school_id = :school_id
                        GROUP BY
                            e.ExamID, m.subject, e.date
                        ORDER BY
                            e.date ASC";

                $stmt = $this->conn->prepare($sql);
                $stmt->bindParam(':class_id', $class_id, PDO::PARAM_STR);
                $stmt->bindParam(':subject', $individualSubject, PDO::PARAM_STR);
                $stmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);
                $stmt->execute();

                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $averageMarksData[] = [
                        'average_marks' => $row['average_marks'],
                        'date' => $row['date'],
                    ];
                }
            }
        }

        // Sort the data by date in ascending order
        usort($averageMarksData, function ($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        header('Content-Type: application/json');
        echo json_encode($averageMarksData);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error fetching average marks: ' . $e->getMessage()]);
    }
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
