<?php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Allow cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

// Include the database connection class
include '../DbConnect.php';

class EventAdder
{
    private $conn;

    public function __construct()
    {
        // Use the existing database connection from DbConnect
        $objDb = new DbConnect();
        $this->conn = $objDb->connect();;
    }

    public function handleRequest()
    {
            $method = $_SERVER['REQUEST_METHOD'];

            // Assigning the function according to request methods
            if ($method == 'POST') {
                // Assuming you are sending title, description, school_id, date, and banner in the form data
                $title = isset($_POST['title']) ? $_POST['title'] : '';
                $description = isset($_POST['description']) ? $_POST['description'] : '';
                $school_id = isset($_POST['school_id']) ? $_POST['school_id'] : '';
                $date = isset($_POST['date']) ? $_POST['date'] : '';

                $this->addEvent($title, $description, $school_id, $date);
            } else {
                // http_response_code(405); // Method Not Allowed
                echo json_encode(['error' => 'Method not allowed']);
            }
    }


    public function addEvent($title, $description, $school_id, $date)
    {
        // Check if file is uploaded
        if (isset($_FILES['image'])) {
            $file = $_FILES['image'];
            

            // Handle file upload logic
            $target_dir = "event_images/"; // Save images inside the 'event_images' folder
            $imageFileType = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
            echo $imageFileType;

            // Generate a random 9-digit alphanumeric string as the new file name
            $newFileName = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 9) . '.' . $imageFileType;
            $target_file = $target_dir . $newFileName;

            // Check file size
            if ($file["size"] > 5000000) {
                echo json_encode(["status" => "error", "message" => "File is too large."]);
                exit();
            }

            // Allow certain file formats
           // Allow certain file formats
            // if (!in_array($imageFileType, ["jpg", "jpeg", "png"])) {
            //     echo json_encode(["status" => "error", "message" => "Only JPG, JPEG, PNG files are allowed."]);
            //     exit();
            // }



            // Move the uploaded file to the target directory
            if (!move_uploaded_file($file["tmp_name"], $target_file)) {
                echo json_encode(["status" => "error", "message" => "Error uploading file."]);
                exit();
            }

            // Insert information into the database
            $eventQuery = "INSERT INTO school_events (title, description, banner, date, school_id) VALUES (:title, :description, :banner, :date, :school_id)";
            $eventStmt = $this->conn->prepare($eventQuery);
            $eventStmt->bindParam(':title', $title, PDO::PARAM_STR);
            $eventStmt->bindParam(':description', $description, PDO::PARAM_STR);
            $eventStmt->bindParam(':banner', $target_file, PDO::PARAM_STR);
            $eventStmt->bindParam(':date', $date, PDO::PARAM_STR);
            $eventStmt->bindParam(':school_id', $school_id, PDO::PARAM_STR);

            if ($eventStmt->execute()) {
                echo json_encode(["status" => "success", "message" => "Event added successfully."]);
            } else {
                echo json_encode(["status" => "error", "message" => "Error adding event to the database."]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "No file uploaded."]);
        }
    }
}

// Usage
$controller = new EventAdder();
$controller->handleRequest();

?>
