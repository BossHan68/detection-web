<?php
require '../config/db.php';

$stmt = $conn->query(
    "SELECT * FROM events ORDER BY created_at DESC"
);

echo json_encode($stmt->fetchAll());
