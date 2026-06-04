<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Telemetria ESP32</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    
    <style>
        /* Trazendo uma fonte com estilo mais tecnológico/digital */
        @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&display=swap');
        
        body {
            background-color: #0d0d0d;
            color: #ffffff;
            font-family: 'Orbitron', sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            background-image: radial-gradient(circle at center, #1a1a1a 0%, #000000 100%);
        }

        h1 {
            color: #ff3333;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-bottom: 50px;
            text-shadow: 0 0 10px rgba(255, 51, 51, 0.5);
        }

        .dashboard {
            display: flex;
            gap: 40px;
        }

        .gauge-card {
            background: linear-gradient(145deg, #1a1a1a, #111111);
            border: 2px solid #333;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            box-shadow: 10px 10px 20px #050505, -10px -10px 20px #1b1b1b;
            width: 280px;
            transition: all 0.3s ease;
        }

        .gauge-card:hover {
            border-color: #ff3333;
            box-shadow: 0 0 20px rgba(255, 51, 51, 0.2);
        }

        .label {
            font-size: 16px;
            color: #888;
            letter-spacing: 2px;
            margin-bottom: 15px;
        }

        .value-container {
            display: flex;
            align-items: baseline;
            justify-content: center;
            gap: 8px;
        }

        .value {
            font-size: 65px;
            font-weight: bold;
            color: #fff;
        }

        .unit {
            font-size: 20px;
            color: #ff3333;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <h1>Painel de Telemetria</h1>
    
    <div class="dashboard">
        <div class="gauge-card">
            <div class="label">VELOCIDADE</div>
            <div class="value-container">
                <span class="value" id="velocidade">0.0</span>
                <span class="unit">km/h</span>
            </div>
        </div>

        <div class="gauge-card">
            <div class="label">ROTAÇÃO</div>
            <div class="value-container">
                <span class="value" id="rpm">0</span>
                <span class="unit">RPM</span>
            </div>
        </div>
    </div>

    <script>
        // Objeto virtual que o GSAP vai usar para interpolar os valores
        const painelVirtual = { velocidade: 0, rpm: 0 };

        function atualizarDados() {
            fetch('buscar_ultimo.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'ok') {
                        let novaVelocidade = parseFloat(data.velocidade);
                        let novoRpm = parseFloat(data.rpm);

                        // O GSAP cria a transição suave do número antigo para o novo em 0.5 segundos
                        gsap.to(painelVirtual, {
                            duration: 0.5,
                            velocidade: novaVelocidade,
                            rpm: novoRpm,
                            ease: "power2.out",
                            onUpdate: function() {
                                document.getElementById('velocidade').innerText = painelVirtual.velocidade.toFixed(1);
                                document.getElementById('rpm').innerText = Math.round(painelVirtual.rpm);
                            }
                        });
                    }
                })
                .catch(error => console.log("Erro na API:", error));
        }

        // Dá o primeiro disparo e depois atualiza a cada 1 segundo
        atualizarDados();
        setInterval(atualizarDados, 1000);
    </script>
</body>
</html>