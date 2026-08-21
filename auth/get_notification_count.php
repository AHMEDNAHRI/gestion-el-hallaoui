<?php
require_once __DIR__ . '/../db.php';

try {
    $nextWeek = date('Y-m-d', strtotime('+7 days'));
    $stmt = $conn->prepare("SELECT COUNT(*) FROM entrepreneur WHERE assurance_end <= ? AND assurance_end >= CURDATE()");
    $stmt->execute([$nextWeek]);
    $count = $stmt->fetchColumn();
    
    echo json_encode(['count' => $count]);
} catch (PDOException $e) {
    echo json_encode(['count' => 0]);
}
?>