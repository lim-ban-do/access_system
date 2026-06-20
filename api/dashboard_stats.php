<?php
require_once __DIR__ . '/../includes/header.php';

$today = date('Y-m-d');

try {

    $present = $pdo->prepare("
        SELECT COUNT(*) FROM attendance 
        WHERE attendance_date=? AND status='Present'
    ");
    $present->execute([$today]);

    $absent = $pdo->prepare("
        SELECT COUNT(*) FROM attendance 
        WHERE attendance_date=? AND status='Absent'
    ");
    $absent->execute([$today]);

    $late = $pdo->prepare("
        SELECT COUNT(*) FROM attendance 
        WHERE attendance_date=? AND status='Late'
    ");
    $late->execute([$today]);

    echo json_encode([
        "present" => $present->fetchColumn(),
        "absent"  => $absent->fetchColumn(),
        "late"    => $late->fetchColumn()
    ]);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}