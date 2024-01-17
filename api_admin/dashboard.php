<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include 'DbConnect.php';

 //loading the environment variables
require_once  __DIR__. '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load();

class Dashboard {

    private $headers;
    private $encryptedApikey;
    private $expectedApiKey;

    private $conn;
    // Generate a random encryption key and initialization vector (IV)
    private $encryptionKey; // 256 bits key
    private $iv ; // 128 bits IV


    public function __construct() {
        // Use the existing database connection from DbConnect.php
        $objDb = new DbConnect;
        $this->conn = $objDb->connect();
        $this->expectedApiKey = $_ENV["SCHOOL"];
        $this->encryptionKey = $_ENV["ENCRYPT_KEY"];
        $this->iv = $_ENV["INIT_VEC"];
    }

    public function handleRequest() {
        
         $headers=getallheaders();

        $encryptedApiKey = $headers['Authorization'];
        
        $decryptedApiKey = $this->decryptData($encryptedApiKey, $this->encryptionKey, $this->iv);

 
        
        // Check if the API key is valid
    if ($this->validateApiKey($decryptedApiKey)) {
        $method = $_SERVER['REQUEST_METHOD'];

        if($method === "GET") {
            $school_id = isset($_GET['school_id']) ? $_GET['school_id'] : null;
            $this->schoolDetails($school_id);
        } else {
            http_response_code(405); // Method Not Allowed
            echo json_encode(array("message" => "Method not allowed."));
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

    // In schoolDetailsAndSubscription function
    public function schoolDetailsAndSubscription($school_id) {
        $sql = "SELECT * FROM school WHERE id_school = :school_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':school_id', $school_id, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    // In getTotalStudents function
    public function getTotalStudents($school_id) {
        $sql = "SELECT username, class_id, gender FROM users WHERE school_id = :school_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':school_id', $school_id, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Debugging: Log the raw data from the database
        error_log("Raw data from the database: ".print_r($result, true));

        // Calculate total number of students
        $totalStudents = count($result);

        // Debugging: Log total students
        error_log("Total students: ".$totalStudents);

        // Calculate male and female counts
        $maleCount = 0;
        $femaleCount = 0;

        foreach($result as $student) {
            $gender = $student['gender'];
            if($gender == '0') {
                $maleCount++;
            } elseif($gender == '1') {
                $femaleCount++;
            }
        }

        // Debugging: Log male and female counts
        error_log("Male count: ".$maleCount);
        error_log("Female count: ".$femaleCount);

        // Calculate the gender ratio
        $genderRatio = ($femaleCount === 0) ? "0:{$maleCount}" : "{$maleCount}:{$femaleCount}";

        return array(
            "students" => $result,
            "total_students" => $totalStudents,
            "male_count" => $maleCount,
            "female_count" => $femaleCount,
            "gender_ratio" => $genderRatio,
        );
    }


    // In schoolAttendance function
    public function schoolAttendance($school_id, $year, $month, $date) {
        try {
            // Fetch usernames from the users table for the given school_id
            $usernames = $this->getUsernames($school_id);

            $totalPresent = 0;
            $totalAbsent = 0;
            $totalHolidays = 0;
            $totalAttendance = 0;

            foreach($usernames as $username) {
                // Construct a date in the format 'YYYY-MM-DD'
                $formattedDate = "$year-$month-$date";
                

                $sql = "SELECT attendance_text FROM studentattendance WHERE username = :username AND month = :month";

                $stmt = $this->conn->prepare($sql);
                $stmt->bindParam(':username', $username, PDO::PARAM_STR);
                $stmt->bindParam(':month', $month, PDO::PARAM_STR);
                $stmt->execute();

                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                // echo json_encode($result);




                if($result) {
                    $attendanceText = $result['attendance_text'];

                    // Extract the attendance status for the specified date
                    $attendance = $attendanceText[$date - 1];

                    // Count the number of 1s (present), 0s (absent), and 2s (holidays) in the attendance string
                    $presentCount = substr_count($attendance, '1');
                    $absentCount = substr_count($attendance, '0');
                    $holidaysCount = substr_count($attendance, '2');

                    // Update total counts
                    $totalPresent += $presentCount;
                    $totalAbsent += $absentCount;
                    $totalHolidays += $holidaysCount;

                    // Total attendance is the same as the length of the attendance string up to the i'th term
                    $totalAttendance += strlen($attendance);
                }
            }

            $response = [
                'total_attendance' => $totalAttendance,
                'total_present' => $totalPresent,
                'total_absent' => $totalAbsent,
                'total_holidays' => $totalHolidays,
            ];

            return $response;
        } catch (PDOException $e) {
            return ['error' => 'Error fetching school attendance data: '.$e->getMessage()];
        }
    }

    // Function to get usernames from the users table for a given school_id
    private function getUsernames($school_id) {
        $usernames = [];

        $sql = "SELECT username FROM users WHERE school_id = :school_id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
        $stmt->execute();

        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $usernames[] = $row['username'];
        }

        return $usernames;
    }





    // teacher student Ratio
    public function teacherStudentRatio($school_id) {
        // Fetch total number of students
        $sqlStudents = "SELECT COUNT(*) as total_students FROM users WHERE school_id = :school_id";
        $stmtStudents = $this->conn->prepare($sqlStudents);
        $stmtStudents->bindValue(':school_id', $school_id, PDO::PARAM_STR);
        $stmtStudents->execute();
        $resultStudents = $stmtStudents->fetch(PDO::FETCH_ASSOC);
        $totalStudents = $resultStudents['total_students'];

        // Fetch total number of teachers
        $sqlTeachers = "SELECT COUNT(*) as total_teachers FROM teacher WHERE school_id = :school_id";
        $stmtTeachers = $this->conn->prepare($sqlTeachers);
        $stmtTeachers->bindValue(':school_id', $school_id, PDO::PARAM_STR);
        $stmtTeachers->execute();
        $resultTeachers = $stmtTeachers->fetch(PDO::FETCH_ASSOC);
        $totalTeachers = $resultTeachers['total_teachers'];

        // Calculate the greatest common divisor (gcd) to simplify the ratio
        $gcd = function ($a, $b) use (&$gcd) {
            return ($b === 0) ? $a : $gcd($b, $a % $b);
        };

        // Calculate the greatest common divisor
        $divisor = $gcd($totalTeachers, $totalStudents);

        // Calculate the simplified ratio
        $teacherRatio = $totalTeachers / $divisor;
        $studentRatio = $totalStudents / $divisor;

        return array(
            "total_students" => $totalStudents,
            "total_teachers" => $totalTeachers,
            "teacher_student_ratio" => "{$teacherRatio}:{$studentRatio}",
        );
    }


    // class ratio 
    // In classPercentage function
    public function classPercentage($school_id) {
        // Fetch class distribution
        $sqlClasses = "SELECT DISTINCT SUBSTRING(class_id, 1, LENGTH(class_id) - 1) AS class FROM users WHERE school_id = :school_id";
        $stmtClasses = $this->conn->prepare($sqlClasses);
        $stmtClasses->bindValue(':school_id', $school_id, PDO::PARAM_STR);
        $stmtClasses->execute();
        $classes = $stmtClasses->fetchAll(PDO::FETCH_COLUMN);

        // Fetch total number of students
        $sqlTotalStudents = "SELECT COUNT(*) as total_students FROM users WHERE school_id = :school_id";
        $stmtTotalStudents = $this->conn->prepare($sqlTotalStudents);
        $stmtTotalStudents->bindValue(':school_id', $school_id, PDO::PARAM_STR);
        $stmtTotalStudents->execute();
        $totalStudents = $stmtTotalStudents->fetchColumn();

        // Fetch number of students in each class
        $classData = [];
        foreach($classes as $class) {
            $sqlClassCount = "SELECT COUNT(*) as class_count FROM users WHERE school_id = :school_id AND SUBSTRING(class_id, 1, LENGTH(class_id) - 1) = :class";
            $stmtClassCount = $this->conn->prepare($sqlClassCount);
            $stmtClassCount->bindValue(':school_id', $school_id, PDO::PARAM_STR);
            $stmtClassCount->bindValue(':class', $class, PDO::PARAM_STR); // Change to PARAM_STR
            $stmtClassCount->execute();
            $classCount = $stmtClassCount->fetchColumn();

            // Calculate percentage
            $percentage = ($classCount / $totalStudents) * 100;

            $classData[] = array(
                "class" => $class,
                "students" => $classCount,
                "percentage" => $percentage,
            );
        }

        return array(
            "total_students" => $totalStudents,
            "class_data" => $classData,
        );
    }

    // In feeDetails function
public function feeDetails($school_id, $month) {
    // Fetch usernames from the users table based on school_id
    $usernamesQuery = "SELECT username FROM users WHERE school_id = :school_id";
    $usernamesStmt = $this->conn->prepare($usernamesQuery);
    $usernamesStmt->bindValue(':school_id', $school_id, PDO::PARAM_STR);
    $usernamesStmt->execute();
    $usernamesResult = $usernamesStmt->fetchAll(PDO::FETCH_COLUMN);

    // Check if there are any usernames before proceeding
    if (empty($usernamesResult)) {
        return array(
            "total_monthly_fee" => 0,
            "total_paid" => 0,
            "total_paid_fees" => 0,
            "total_unpaid" => 0,
            "details" => array()
        );
    }

    // Fetch fee details based on the fetched usernames and current date
    $currentDate = date('Y-m-d');

    // Subquery to get the latest due_date for each username
    $latestDueDateQuery = "SELECT username, MAX(due_date) AS latest_due_date 
                           FROM fees 
                           WHERE username IN (:usernames) 
                           GROUP BY username";

    $latestDueDateStmt = $this->conn->prepare($latestDueDateQuery);
    $latestDueDateStmt->bindValue(':usernames', implode(',', $usernamesResult), PDO::PARAM_STR);
    $latestDueDateStmt->execute();
    $latestDueDates = $latestDueDateStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch fee details using the latest due_date
    $placeholders = implode(',', array_map(function ($username) {
        return ":username_$username";
    }, $usernamesResult));

    $feeQuery = "SELECT f.username, f.month, f.total_fees, f.due_date, f.paid_status 
                 FROM fees f
                 INNER JOIN (
                     SELECT username, MAX(due_date) AS latest_due_date 
                     FROM fees 
                     WHERE username IN ($placeholders) 
                     GROUP BY username
                 ) latest ON f.username = latest.username AND f.due_date = latest.latest_due_date
                 WHERE f.month = :month AND f.due_date < :currentDate";

    // Add the placeholders to the SQL query
    $feeQuery = str_replace(':username_', ':username_', $feeQuery);

    $feeStmt = $this->conn->prepare($feeQuery);

    // Bind values for usernames
    foreach ($usernamesResult as $username) {
        $paramName = ":username_$username";
        $feeStmt->bindValue($paramName, $username, PDO::PARAM_STR);
    }

    // Bind month parameter
    $feeStmt->bindParam(':month', $month, PDO::PARAM_STR);
    // Bind current date parameter
    $feeStmt->bindParam(':currentDate', $currentDate, PDO::PARAM_STR);

    $feeStmt->execute();
    $result = $feeStmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total monthly fee
    $totalMonthlyFee = array_sum(array_column($result, 'total_fees'));

    // Calculate total paid and unpaid fees
    $totalPaidCount = count(array_filter($result, function ($row) {
        return $row['paid_status'] == 1;
    }));

    $totalPaidFees = array_sum(array_column(array_filter($result, function ($row) {
        return $row['paid_status'] == 1;
    }), 'total_fees'));

    $totalUnpaidCount = count(array_filter($result, function ($row) {
        return $row['paid_status'] == 0;
    }));

    return array(
        "total_monthly_fee" => $totalMonthlyFee,
        "total_paid" => $totalPaidCount,
        "total_paid_fees" => $totalPaidFees,
        "total_unpaid" => $totalUnpaidCount,
        "details" => $result
    );
}


    // avg marks of school 

    // public function schoolAvgMarks($school_id) {
    //     $sql = "SELECT marks_obtained, max_marks, subject FROM marks WHERE school_id = :school_id";
    //     $stmt = $this->conn->prepare($sql);
    //     $stmt->bindValue(':school_id', $school_id, PDO::PARAM_INT);
    //     $stmt->execute();
    //     $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    //     // Debugging: Log the raw data from the database
    //     error_log("Raw data from the database: " . print_r($result, true));

    //     // Check if there are any results
    //     if (empty($result)) {
    //         return array("error" => "No data found for the specified school.");
    //     }

    //     // Initialize an array to store total marks and student count per subject
    //     $subjectData = array();

    //     foreach ($result as $marks) {
    //         $subject = $marks['subject'];
    //         $marksObtained = $marks['marks_obtained'];

    //         // Check if the subject is already in the array
    //         if (!isset($subjectData[$subject])) {
    //             $subjectData[$subject] = array(
    //                 'total_marks' => $marksObtained,
    //                 'student_count' => 1,
    //             );
    //         } else {
    //             // Update total marks and student count for the existing subject
    //             $subjectData[$subject]['total_marks'] += $marksObtained;
    //             $subjectData[$subject]['student_count']++;
    //         }
    //     }

    //     // Calculate average marks per subject
    //     $averageMarksPerSubject = array();
    //     $totalStudents = 0; // To calculate the overall average
    //     $totalOverallMarks = 0;

    //     foreach ($subjectData as $subject => $data) {
    //         $averageMarks = $data['total_marks'] / $data['student_count'];
    //         $averageMarksPerSubject[$subject] = $averageMarks;

    //         // Add to overall total
    //         $totalStudents += $data['student_count'];
    //         $totalOverallMarks += $data['total_marks'];
    //     }

    //     // Calculate overall average marks
    //     $overallAverageMarks = $totalOverallMarks / $totalStudents;

    //     // Debugging: Log average marks per subject and overall average marks
    //     error_log("Average marks per subject: " . print_r($averageMarksPerSubject, true));
    //     error_log("Overall Average marks: " . $overallAverageMarks);

    //     return array(
    //         "average_marks_per_subject" => $averageMarksPerSubject,
    //         "overall_average_marks" => $overallAverageMarks,
    //     );
    // }


    public function schoolDetails($school_id) {
        // total number of students 
        $studentsData = $this->getTotalStudents($school_id);

        $schoolData = $this->schoolDetailsAndSubscription($school_id);

        $responseData = [];

        if(isset($studentsData['total_students']) && $studentsData['total_students'] > 0) {
            $usernamesAndClass = array("students" => $studentsData['students']);

            // Current date, month, and year
            $date = date('j'); // Current day of the month
            $month = date('F'); // Current month (full month name)
            $year = date('Y'); // Current year

            // today's attendance
            $attendance = $this->schoolAttendance($school_id, $year, $month, $date);

            // teacher student ratio
            $teacherStudentRatio = $this->teacherStudentRatio($school_id);

            // class ratio
            $classRatio = $this->classPercentage($school_id);

            // fee details
            // $month = date('F');
            // $feeDetails = $this->feeDetails($school_id, $month);

            // $schoolAverage = $this->schoolAvgMarks($school_id);

            // Add all data to the response array
            $responseData['school_details'] = $schoolData;
            $responseData['students_data'] = $studentsData;
            $responseData['attendance'] = $attendance;
            $responseData['teacher_student_ratio'] = $teacherStudentRatio;
            $responseData['class_ratio'] = $classRatio;
            $responseData['fee_details'] = $feeDetails;
            // $responseData['school_average'] = $schoolAverage;
        } else {
            $responseData['error'] = 'No students found.';
        }

        // Encode the array into a single JSON response
        echo json_encode($responseData);
    }

}

$dashboard = new Dashboard();
$dashboard->handleRequest();
?>