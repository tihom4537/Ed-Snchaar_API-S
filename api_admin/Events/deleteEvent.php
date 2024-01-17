<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");

include '../DbConnect.php';

class EventDelete {
    private $conn;

    public function __construct() {
        $objDb = new DbConnect();
        $this->conn = $objDb->connect();
    }

    public function handleRequest() {
            $method = $_SERVER['REQUEST_METHOD'];

            if ($method === 'POST' && isset($_GET['id'])) {
                $this->deleteEvent();
            } else {
                // http_response_code(405); // Method Not Allowed
                echo json_encode(['error' => "Method not allowed"]);
            }
    }
    
public function deleteEvent() {
    $carouselId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($carouselId === false || $carouselId === null) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => "Invalid or missing id parameter"]);
        return;
    }

    try {
        // Retrieve the file path before deleting the carousel
        $getFilePathQuery = "SELECT banner FROM school_events WHERE id = :id";
        $getFilePathStmt = $this->conn->prepare($getFilePathQuery);
        $getFilePathStmt->bindParam(':id', $carouselId, PDO::PARAM_INT);
        $getFilePathStmt->execute();
        $result = $getFilePathStmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            http_response_code(404); // Not Found
            echo json_encode(['error' => "Event not found"]);
            return;
        }

        $filePath = $result['banner'];  // Change 'url' to 'banner'

        // Delete the carousel record
        $deleteCarouselQuery = "DELETE FROM school_events WHERE id = :id";
        $deleteCarouselStmt = $this->conn->prepare($deleteCarouselQuery);
        $deleteCarouselStmt->bindParam(':id', $carouselId, PDO::PARAM_INT);
        $deleteCarouselStmt->execute();

        // Delete the associated file
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log("Error: " . $e->getMessage());
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => "Internal server error"]);
    }
}


}

$controller = new EventDelete();
$controller->handleRequest();
?>
