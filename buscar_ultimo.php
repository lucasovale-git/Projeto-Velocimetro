<?php
header('Content-Type: application/json; charset=utf-8');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "velodb";

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    echo json_encode(["status" => "error", "message" => "Connection failed"]);
    exit;
}

// Busca apenas o último registro inserido
$sql = "SELECT velocidade, rpm, timestamp FROM velotabe ORDER BY id DESC LIMIT 1";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    echo json_encode([
        "status" => "ok",
        "velocidade" => $row['velocidade'],
        "rpm" => $row['rpm'],
        "timestamp" => $row['timestamp']
    ]);
} else {
    echo json_encode(["status" => "empty", "message" => "Nenhum dado na tabela"]);
}

mysqli_close($conn);
?>