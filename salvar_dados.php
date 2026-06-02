<?php
header('Content-Type: application/json; charset=utf-8');

// salvar_dados.php
// Recebe POST com 'velocidade' e 'rpm' e grava em dados.json (último registro)

$velocidade = isset($_POST['velocidade']) ? floatval($_POST['velocidade']) : null;
$rpm = isset($_POST['rpm']) ? floatval($_POST['rpm']) : null;

if ($velocidade === null || $rpm === null) {
	http_response_code(400);
	echo json_encode(["status" => "error", "message" => "Parâmetros 'velocidade' e 'rpm' são obrigatórios"]);
	exit;
}

$file = __DIR__ . '/dados.json';
$data = [];
if (file_exists($file)) {
	$content = file_get_contents($file);
	$data = json_decode($content, true);
	if (!is_array($data)) $data = [];
}

$entry = [
	'timestamp' => date('c'),
	'velocidade' => $velocidade,
	'rpm' => $rpm
];

$data[] = $entry;

// opcional: manter apenas os últimos 100 registros
if (count($data) > 100) {
	$data = array_slice($data, -100);
}

if (file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
	http_response_code(500);
	echo json_encode(["status" => "error", "message" => "Falha ao escrever arquivo"]);
	exit;
}

echo json_encode(["status" => "ok", "entry" => $entry]);
?>