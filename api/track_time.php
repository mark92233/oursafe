<?php
require_once __DIR__ . '/db_related/db_connect.php';

// Receives beacon pings from the frontend JS when the user closes the page or tab
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['time'])) {
    $id = (int)$_POST['id'];
    $time = (int)$_POST['time'];
    
    try {
        $stmt = $pdo->prepare("UPDATE visitor_logs SET time_spent_seconds = :time WHERE id = :id");
        $stmt->execute(['time' => $time, 'id' => $id]);
    } catch (PDOException $e) {
        error_log("Failed to update time tracking: " . $e->getMessage());
    }
}
?>