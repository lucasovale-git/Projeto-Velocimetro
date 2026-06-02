<!DOCTYPE html>
<html lang="pt-br">
<head>

<meta charset="UTF-8">
<title>Monitor ESP32</title>
<style>
body{ background: #111; color: #00ff00; font-family: Arial; text-align: center; margin-top: 100px; }
.valor{ font-size: 50px; margin: 20px; }
</style>
</head>
<body>
<h1>Dados da ESP32</h1>
<div class="valor">Velocidade: <span id="velocidade">0</span></div>
<div class="valor">RPM: <span id="rpm">0</span></div>

<script>
function atualizarDados() {
fetch('dados.php')
.then(response => response.json())
.then(data => {
document.getElementById('velocidade').innerText = data.velocidade;
document.getElementById('rpm').innerText = data.rpm;
})
.catch(error => console.log(error));
}
atualizarDados();
setInterval(atualizarDados, 1000);
</script>
</body>
</html>

<?php
//dados de conexão

if (isset($_POST['exemplo']) && isset($_POST['exemplo'])) {
$vel = $_POST['exemplo'];
$conn->close();
}


//dados de conexão com o banco e demais funções =>

//Trecho para enviar ao index.html
$result = $conn->query($sql);
if ($result->num_rows > 0) {
$row = $result->fetch_assoc();
echo json_encode([
"exemplo" => $row["exemplo"],
"exemplo" => $row["exemplo"],
"exemplo" => $row["exemplo"]
]);
}
$conn->close();
?>