<?php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

$servername = "localhost";
$username = "root";
$password = ""; 
$dbname = "velodb";

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Erro na conexão com o banco"]);
    exit;
}

$velocidade = isset($_POST['velocidade']) ? floatval($_POST['velocidade']) : null;
$rpm = isset($_POST['rpm']) ? floatval($_POST['rpm']) : null;
$timestamp = date('Y-m-d H:i:s'); // Formato datetime do MySQL

if ($velocidade === null || $rpm === null) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Parâmetros incorretos"]);
    exit;
}

// Inserindo os dados reais do ESP32 no banco MySQL
$sql = "INSERT INTO velotabe (velocidade, rpm, timestamp) VALUES ('$velocidade', '$rpm', '$timestamp')";

if (mysqli_query($conn, $sql)) {
    echo json_encode(["status" => "ok", "message" => "Salvo no MySQL com sucesso"]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Erro ao inserir no banco"]);
}

mysqli_close($conn);
?>