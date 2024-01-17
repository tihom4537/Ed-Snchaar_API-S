<?php
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");
header("Content-Type: application/json");

include '../DbConnect.php';

class CarouselController
{
    private $conn;

    public function __construct()
    {
        $db = new DbConnect();
        $this->conn = $db->connect();
    }

    public function handleRequest()
    {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'POST') {
            $this->updateEvent();
        } else {
            // http_response_code(405); // Method Not Allowed
            echo json_encode(['data' => null, 'status' => 405, 'message' => 'Method Not Allowed']);
        }
    }

    public function updateEvent()
    {
        try {
            // Get JSON data from the request body
            $json_data = file_get_contents('php://input');
            $data = json_decode($json_data, true);

            // Trim and sanitize data
            $id = $data['id'] ?? null;
            $title = $data['title'] ?? null;
            $description = $data['description'] ?? null;

            // Validation (add more as needed)
            if (empty($id)) {
                throw new Exception('Event ID is required.');
            }

            $sql = "UPDATE school_events SET title = :title, description = :description WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(":id", $id);
            $stmt->bindParam(':description', $description);

            if ($stmt->execute()) {
                $response = ['status' => 200, 'message' => 'Event information updated successfully.'];
            } else {
                $response = ['status' => 500, 'message' => 'Failed to update event information.'];
            }
        } catch (Exception $e) {
            $response = ['status' => 400, 'message' => $e->getMessage()];
        }

        http_response_code($response['status']);
        echo json_encode($response);
    }
}

$controller = new CarouselController();
$controller->handleRequest();
?>
