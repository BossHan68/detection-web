<?php
require '../config/db.php';

$stmt = $conn->query(
    "SELECT * FROM events ORDER BY created_at DESC LIMIT 1"
);

echo json_encode($stmt->fetch());
