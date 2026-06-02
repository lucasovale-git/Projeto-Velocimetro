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
	fetch('buscar_ultimo.php')
		.then(response => response.json())
		.then(data => {
			if (data.status === 'ok') {
				document.getElementById('velocidade').innerText = parseFloat(data.velocidade).toFixed(2) + ' km/h';
				document.getElementById('rpm').innerText = parseFloat(data.rpm).toFixed(1) + ' rpm';
			}
		})
		.catch(error => console.log(error));
}
atualizarDados();
setInterval(atualizarDados, 1000);
</script>
</body>
</html>
