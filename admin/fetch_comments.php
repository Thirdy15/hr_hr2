<?php
session_start();
include '../db/db_conn.php'; // Include your database connection

if (!isset($_SESSION['a_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $employeeId = $data['employee_id'];

    // Fetch comments from the database
    $sql = "SELECT username, comment, created_at FROM employee_comments WHERE employee_id = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $comments = [];

    while ($row = $result->fetch_assoc()) {
        $comments[] = [
            'username' => $row['username'],
            'comment' => $row['comment'],
            'comment_time' => $row['created_at'] // Include the timestamp
        ];
    }

    echo json_encode(['status' => 'success', 'comments' => $comments]);

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>
