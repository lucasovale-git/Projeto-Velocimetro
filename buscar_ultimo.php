<?php
header('Content-Type: application/json; charset=utf-8');

// buscar_ultimo.php
// Retorna o último registro presente em dados.json

$file = __DIR__ . '/dados.json';
if (!file_exists($file)) {
	echo json_encode(["status" => "empty", "message" => "Nenhum dado encontrado"]);
	exit;
}

$content = file_get_contents($file);
$data = json_decode($content, true);
if (!is_array($data) || count($data) === 0) {
	echo json_encode(["status" => "empty", "message" => "Nenhum dado disponível"]);
	exit;
}

$last = end($data);
echo json_encode(["status" => "ok", "velocidade" => $last['velocidade'], "rpm" => $last['rpm'], "timestamp" => $last['timestamp']]);
?>