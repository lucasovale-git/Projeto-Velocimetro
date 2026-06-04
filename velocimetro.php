<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telemetria ESP32 — Racing Dashboard</title>
    <meta name="description" content="Painel de telemetria em tempo real com velocímetro e RPM para ESP32">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;700;900&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>

    <style>
        /* ==========================================================
           1. CSS CUSTOM PROPERTIES — SINGLE SOURCE OF TRUTH
           ==========================================================
           --speed (0–200) e --rpm (0–8000) são as ÚNICAS variáveis
           que o JavaScript altera. Todo o restante é calc() puro.
        */
        :root {
            --speed: 0;
            --rpm: 0;

            /* Paleta de cores */
            --neon-red: #ff2d2d;
            --neon-cyan: #00f0ff;
            --neon-green: #00ff88;
            --neon-yellow: #ffdd00;
            --neon-orange: #ff6b2d;
            --dark-bg: #0a0a12;
            --road-surface: #2a2a2e;
            --grass-color: #0d4a0d;
        }

        /* ==========================================================
           2. RESET
        ========================================================== */
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Orbitron', sans-serif;
            overflow: hidden;
            height: 100vh;
            width: 100vw;
            background: var(--dark-bg);
            color: #fff;
        }

        /* ==========================================================
           3. RACING CONTAINER — recebe --speed e --rpm
        ========================================================== */
        .racing-container {
            position: relative;
            width: 100%;
            height: 100vh;
            overflow: hidden;
        }

        /* ==========================================================
           4. SCENE — leve screen-shake baseado na --speed
        ========================================================== */
        .scene {
            position: absolute;
            inset: 0;
            z-index: 1;
            animation: screen-shake 0.12s ease-in-out infinite;
        }

        @keyframes screen-shake {
            0%, 100% { transform: translate(0, 0); }
            25%  { transform: translate(calc(var(--speed) * 0.004 * 1px), calc(var(--speed) * -0.003 * 1px)); }
            50%  { transform: translate(calc(var(--speed) * -0.003 * 1px), calc(var(--speed) * 0.004 * 1px)); }
            75%  { transform: translate(calc(var(--speed) * 0.003 * 1px), calc(var(--speed) * 0.002 * 1px)); }
        }

        /* ==========================================================
           5. SKY — gradiente noturno com estrelas
        ========================================================== */
        .sky {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 32%;
            background: linear-gradient(180deg,
                #050510 0%,
                #0a0a22 40%,
                #151535 70%,
                #2d1045 90%,
                #401050 100%
            );
            z-index: 1;
        }

        /* Estrelas */
        .sky::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(1px 1px at 8% 12%,  rgba(255,255,255,0.9) 50%, transparent 100%),
                radial-gradient(1.5px 1.5px at 22% 30%, rgba(255,255,255,0.6) 50%, transparent 100%),
                radial-gradient(1px 1px at 38% 8%,  rgba(255,255,255,0.8) 50%, transparent 100%),
                radial-gradient(1px 1px at 52% 25%, rgba(255,255,255,0.5) 50%, transparent 100%),
                radial-gradient(1.5px 1.5px at 67% 15%, rgba(255,255,255,0.7) 50%, transparent 100%),
                radial-gradient(1px 1px at 78% 35%, rgba(255,255,255,0.6) 50%, transparent 100%),
                radial-gradient(1px 1px at 91% 10%, rgba(255,255,255,0.8) 50%, transparent 100%),
                radial-gradient(1px 1px at 5% 42%,  rgba(255,255,255,0.4) 50%, transparent 100%),
                radial-gradient(1.5px 1.5px at 60% 5%, rgba(255,255,255,0.7) 50%, transparent 100%),
                radial-gradient(1px 1px at 45% 38%, rgba(255,255,255,0.5) 50%, transparent 100%),
                radial-gradient(1px 1px at 15% 22%, rgba(255,255,255,0.6) 50%, transparent 100%),
                radial-gradient(1px 1px at 85% 28%, rgba(255,255,255,0.4) 50%, transparent 100%);
            animation: twinkle 4s ease-in-out infinite alternate;
        }

        @keyframes twinkle {
            0%   { opacity: 0.4; }
            100% { opacity: 1; }
        }

        /* Lua */
        .sky::after {
            content: '';
            position: absolute;
            top: 10%;
            right: 12%;
            width: 35px;
            height: 35px;
            background: radial-gradient(circle, #fffde8 0%, #fffde8 35%, transparent 65%);
            border-radius: 50%;
            box-shadow:
                0 0 25px 8px rgba(255,253,230,0.12),
                0 0 50px 15px rgba(255,253,230,0.06);
        }

        /* ==========================================================
           6. HORIZON GLOW — brilho quente no horizonte
        ========================================================== */
        .horizon-glow {
            position: absolute;
            top: 30%;
            left: 0;
            width: 100%;
            height: 4%;
            background: radial-gradient(ellipse at 50% 50%,
                rgba(255,80,40,0.15) 0%,
                rgba(255,120,60,0.06) 40%,
                transparent 70%
            );
            z-index: 2;
        }

        /* ==========================================================
           7. TREES PARALLAX — faixa de árvores com scroll
           animation-duration = calc(25s / (--speed * 0.1 + 0.2))
        ========================================================== */
        .trees-layer {
            position: absolute;
            top: 28%;
            left: 0;
            width: 200%;
            height: 6%;
            z-index: 3;
            background:
                /* Árvore grande (escura) */
                repeating-linear-gradient(90deg,
                    #063d06 0px, #063d06 12px,
                    #0a520a 12px, #0a520a 18px,
                    #074507 18px, #074507 28px,
                    #0b5e0b 28px, #0b5e0b 32px,
                    #054005 32px, #054005 45px,
                    #0a4f0a 45px, #0a4f0a 50px,
                    #074207 50px, #074207 60px
                ),
                /* Sombra no topo */
                linear-gradient(180deg, rgba(0,0,0,0.4) 0%, transparent 40%, transparent 100%);
            background-blend-mode: normal;
            animation: scroll-trees linear infinite;
            animation-duration: calc(25s / (var(--speed) * 0.1 + 0.2));
        }

        @keyframes scroll-trees {
            from { transform: translateX(0); }
            to   { transform: translateX(-50%); }
        }

        /* ==========================================================
           8. ROAD AREA — asfalto, faixas, marcações
        ========================================================== */
        .grass-top {
            position: absolute;
            top: 33%;
            left: 0;
            width: 100%;
            height: 3%;
            background: linear-gradient(180deg, #0a4a0a, #0d5a0d);
            z-index: 4;
        }

        .grass-bottom {
            position: absolute;
            top: 60%;
            left: 0;
            width: 100%;
            height: 3%;
            background: linear-gradient(180deg, #0d5a0d, #0a4a0a);
            z-index: 4;
        }

        .road {
            position: absolute;
            top: 35.5%;
            left: 0;
            width: 100%;
            height: 25%;
            background: var(--road-surface);
            z-index: 5;
            /* Textura sutil no asfalto */
            background-image:
                repeating-linear-gradient(90deg,
                    rgba(255,255,255,0.015) 0px, rgba(255,255,255,0.015) 1px,
                    transparent 1px, transparent 8px
                ),
                repeating-linear-gradient(180deg,
                    rgba(255,255,255,0.01) 0px, rgba(255,255,255,0.01) 1px,
                    transparent 1px, transparent 6px
                );
            background-color: var(--road-surface);
        }

        /* Bordas da pista (faixas contínuas brancas) */
        .road-edge-top,
        .road-edge-bottom {
            position: absolute;
            left: 0;
            width: 100%;
            height: 3px;
            background: rgba(255, 255, 255, 0.7);
            z-index: 6;
        }
        .road-edge-top    { top: 35.5%; }
        .road-edge-bottom { top: calc(35.5% + 25% - 3px); }

        /* Faixas tracejadas entre as pistas */
        .lane-marking {
            position: absolute;
            left: 0;
            width: 100%;
            height: 3px;
            z-index: 6;
            background: repeating-linear-gradient(90deg,
                rgba(255, 255, 255, 0.6) 0px,
                rgba(255, 255, 255, 0.6) 30px,
                transparent 30px,
                transparent 70px
            );
            animation: scroll-markings linear infinite;
            /* calc(): quanto maior --speed, menor o tempo → marcações mais rápidas */
            animation-duration: calc(20s / (var(--speed) * 0.15 + 0.3));
        }

        .lane-marking-1 { top: calc(35.5% + 25% / 3); }
        .lane-marking-2 { top: calc(35.5% + 25% * 2 / 3); }

        @keyframes scroll-markings {
            from { background-position-x: 0; }
            to   { background-position-x: -70px; }
        }

        /* Sombras na borda da pista para dar profundidade */
        .road::before,
        .road::after {
            content: '';
            position: absolute;
            top: 0;
            width: 8%;
            height: 100%;
            z-index: 1;
            pointer-events: none;
        }
        .road::before {
            left: 0;
            background: linear-gradient(90deg, rgba(0,0,0,0.25), transparent);
        }
        .road::after {
            right: 0;
            background: linear-gradient(270deg, rgba(0,0,0,0.25), transparent);
        }

        /* ==========================================================
           9. SPEED LINES — rastros de velocidade na pista
           Opacidade proporcional à --speed
        ========================================================== */
        .speed-lines {
            position: absolute;
            top: 35.5%;
            left: 0;
            width: 100%;
            height: 25%;
            z-index: 7;
            pointer-events: none;
            /* Linhas horizontais finas */
            background:
                linear-gradient(0deg, transparent 12%, rgba(255,255,255,0.06) 12.3%, transparent 12.6%) 0 0,
                linear-gradient(0deg, transparent 35%, rgba(255,255,255,0.04) 35.3%, transparent 35.6%) 0 0,
                linear-gradient(0deg, transparent 58%, rgba(255,255,255,0.07) 58.3%, transparent 58.6%) 0 0,
                linear-gradient(0deg, transparent 82%, rgba(255,255,255,0.05) 82.3%, transparent 82.6%) 0 0;
            /* Visível apenas em alta velocidade */
            opacity: calc(var(--speed) * 0.005);
        }

        /* ==========================================================
           10. PLAYER CAR — fixo à esquerda, vibração no eixo Y
           Amplitude da vibração = calc(--speed * fator)
        ========================================================== */
        .player-car {
            position: absolute;
            left: 12%;
            top: calc(35.5% + 25% / 2 - 2vh);
            z-index: 10;
            animation: vibrate 0.1s linear infinite;
        }

        @keyframes vibrate {
            0%, 100% { transform: translate(0, 0); }
            12%  { transform: translate(calc(var(--speed) * 0.005 * 1px), calc(var(--speed) * -0.025 * 1px)); }
            25%  { transform: translate(calc(var(--speed) * -0.004 * 1px), calc(var(--speed) * 0.018 * 1px)); }
            37%  { transform: translate(calc(var(--speed) * 0.006 * 1px), calc(var(--speed) * -0.022 * 1px)); }
            50%  { transform: translate(calc(var(--speed) * -0.003 * 1px), calc(var(--speed) * 0.015 * 1px)); }
            62%  { transform: translate(calc(var(--speed) * 0.005 * 1px), calc(var(--speed) * -0.028 * 1px)); }
            75%  { transform: translate(calc(var(--speed) * -0.004 * 1px), calc(var(--speed) * 0.020 * 1px)); }
            87%  { transform: translate(calc(var(--speed) * 0.003 * 1px), calc(var(--speed) * -0.016 * 1px)); }
        }

        /* Corpo do carro do jogador (vista de cima, apontando → direita) */
        .car-body {
            width: 9vh;
            height: 3.8vh;
            border-radius: 6px 22px 22px 6px;
            position: relative;
            box-shadow:
                0 3px 12px rgba(0, 0, 0, 0.6),
                0 0 8px rgba(0, 0, 0, 0.3);
        }

        .player-car .car-body {
            background: linear-gradient(180deg, #ff4444, #cc1111);
            border: 1px solid rgba(255,255,255,0.1);
        }

        /* Para-brisa */
        .car-body::before {
            content: '';
            position: absolute;
            right: 14%;
            top: 16%;
            width: 24%;
            height: 68%;
            background: linear-gradient(135deg, rgba(100,200,255,0.35), rgba(100,200,255,0.08));
            border-radius: 3px 8px 8px 3px;
            border: 1px solid rgba(255,255,255,0.12);
        }

        /* Faixa racing */
        .car-body::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 8%;
            right: 22%;
            height: 2px;
            background: rgba(255, 255, 255, 0.35);
            transform: translateY(-50%);
            border-radius: 2px;
        }

        /* Farol dianteiro — brilho proporcional à --speed */
        .headlight {
            position: absolute;
            right: -25px;
            top: 50%;
            transform: translateY(-50%);
            width: 50px;
            height: 22px;
            background: radial-gradient(ellipse at left center, rgba(255,220,100,0.5) 0%, transparent 70%);
            filter: blur(5px);
            /* Brilho cresce com a velocidade */
            opacity: calc(0.2 + var(--speed) * 0.004);
            pointer-events: none;
        }

        /* Luz traseira */
        .taillight {
            position: absolute;
            left: -8px;
            top: 50%;
            transform: translateY(-50%);
            width: 12px;
            height: 16px;
            background: radial-gradient(ellipse at right center, rgba(255,30,30,0.7) 0%, transparent 80%);
            filter: blur(3px);
            opacity: 0.7;
            pointer-events: none;
        }

        /* ==========================================================
           11. RIVAL CARS — cruzam da direita para a esquerda
           animation-duration = calc(3s + --speed * 0.06s)
           Inversamente proporcional: quanto maior a speed do jogador,
           mais LENTO o rival cruza a tela (jogador acompanha)
        ========================================================== */
        .rival-car {
            position: absolute;
            z-index: 9;
            animation: rival-pass linear infinite;
        }

        @keyframes rival-pass {
            from { transform: translateX(calc(100vw + 120px)); }
            to   { transform: translateX(-200px); }
        }

        /* Rival 1 — Azul, Pista 1 (topo) */
        .rival-1 {
            top: calc(35.5% + 25% / 6 - 1.9vh);
            animation-duration: calc(3s + var(--speed) * 0.06s);
            animation-delay: 0s;
        }
        .rival-1 .car-body {
            background: linear-gradient(180deg, #3388ff, #1155cc);
        }

        /* Rival 2 — Laranja, Pista 2 (meio — mesma do jogador!) */
        .rival-2 {
            top: calc(35.5% + 25% / 2 - 1.9vh);
            animation-duration: calc(4.5s + var(--speed) * 0.055s);
            animation-delay: -5s;
        }
        .rival-2 .car-body {
            background: linear-gradient(180deg, #ff8833, #cc5500);
        }

        /* Rival 3 — Verde, Pista 3 (baixo) */
        .rival-3 {
            top: calc(35.5% + 25% * 5 / 6 - 1.9vh);
            animation-duration: calc(2.8s + var(--speed) * 0.065s);
            animation-delay: -3s;
        }
        .rival-3 .car-body {
            background: linear-gradient(180deg, #33dd77, #119944);
        }

        /* Rival cars — tamanho levemente menor */
        .rival-car .car-body {
            width: 8vh;
            height: 3.4vh;
            border-radius: 6px 20px 20px 6px;
            border: 1px solid rgba(255,255,255,0.08);
        }

        .rival-car .car-body::after {
            right: 20%;
        }

        /* ==========================================================
           12. HUD OVERLAY — painel translúcido no rodapé
        ========================================================== */
        .hud {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 20;
            padding: 15px 30px 20px;
            background: linear-gradient(to bottom,
                transparent 0%,
                rgba(5, 5, 15, 0.75) 25%,
                rgba(5, 5, 15, 0.92) 100%
            );
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 40px;
            flex-wrap: wrap;
        }

        /* Painel individual (glassmorphism) */
        .hud-panel {
            background: rgba(12, 12, 30, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-radius: 16px;
            padding: 12px 18px;
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            box-shadow:
                0 4px 20px rgba(0, 0, 0, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.04);
        }

        .panel-label {
            font-size: 10px;
            color: #667;
            letter-spacing: 3px;
            text-align: center;
            margin-bottom: 6px;
        }

        /* ==========================================================
           13. SPEEDOMETER — gauge SVG com arco de 270°
           Ponteiro: rotate(calc(-135deg + --speed * 1.35deg))
           Arco: stroke-dashoffset(calc(377 - --speed * 1.885))
        ========================================================== */
        .speedometer-panel {
            width: 260px;
        }

        .gauge-svg {
            display: block;
            width: 100%;
            max-width: 230px;
            margin: 0 auto;
            overflow: visible;
            filter: drop-shadow(0 0 12px rgba(0, 240, 255, 0.08));
        }

        /* Arco de fundo */
        .gauge-bg {
            fill: none;
            stroke: #1a1a2e;
            stroke-width: 10;
            stroke-linecap: round;
        }

        /* Arco de velocidade — preenchimento progressivo */
        .gauge-fill {
            fill: none;
            stroke: url(#arcGradient);
            stroke-width: 10;
            stroke-linecap: round;
            stroke-dasharray: 377;
            /* calc(): quanto maior --speed, menor o offset → mais arco visível */
            stroke-dashoffset: calc(377px - var(--speed) * 1.885 * 1px);
            transition: stroke-dashoffset 0.3s ease-out;
        }

        /* Marcações maiores (a cada 40 km/h) */
        .tick-major {
            stroke: #778;
            stroke-width: 2.5;
            stroke-linecap: round;
        }

        /* Marcações menores (a cada 20 km/h) */
        .tick-minor {
            stroke: #445;
            stroke-width: 1.5;
            stroke-linecap: round;
        }

        /* Labels numéricos do gauge */
        .gauge-label {
            fill: #556;
            font-family: 'Orbitron', sans-serif;
            font-size: 8px;
            text-anchor: middle;
            dominant-baseline: central;
        }

        /* Ponteiro / Needle */
        .gauge-needle {
            stroke: var(--neon-red);
            stroke-width: 2.5;
            stroke-linecap: round;
            transform-origin: 100px 100px;
            /* calc(): ponteiro gira de -135° (0 km/h) a +135° (200 km/h) */
            transform: rotate(calc(-135deg + var(--speed) * 1.35deg));
            transition: transform 0.3s ease-out;
            filter: drop-shadow(0 0 6px rgba(255, 45, 45, 0.7));
        }

        /* Hub central */
        .gauge-hub-outer {
            fill: #111;
            stroke: #333;
            stroke-width: 2;
        }
        .gauge-hub-inner {
            fill: var(--neon-red);
            filter: drop-shadow(0 0 4px rgba(255, 45, 45, 0.6));
        }

        /* Leitura digital da velocidade */
        .speed-readout {
            text-align: center;
            margin-top: 4px;
        }

        .speed-value {
            font-size: 32px;
            font-weight: 900;
            color: #fff;
            /* Glow proporcional à velocidade */
            text-shadow:
                0 0 calc(4px + var(--speed) * 0.06 * 1px) rgba(255, 45, 45, 0.5),
                0 0 calc(8px + var(--speed) * 0.12 * 1px) rgba(255, 45, 45, 0.2);
            letter-spacing: 2px;
        }

        .speed-unit {
            font-size: 11px;
            color: var(--neon-red);
            font-weight: 700;
            margin-left: 4px;
            letter-spacing: 1px;
        }

        /* ==========================================================
           14. RPM BAR — barra horizontal com gradiente
           Width do fill = calc(--rpm / 8000 * 100%)
        ========================================================== */
        .rpm-panel {
            width: 260px;
        }

        .rpm-bar-container {
            margin-top: 8px;
        }

        .rpm-bar-track {
            width: 100%;
            height: 14px;
            background: #111;
            border-radius: 7px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.06);
        }

        .rpm-bar-fill {
            height: 100%;
            border-radius: 7px;
            background: linear-gradient(90deg,
                var(--neon-cyan) 0%,
                var(--neon-green) 30%,
                var(--neon-yellow) 60%,
                var(--neon-orange) 80%,
                var(--neon-red) 100%
            );
            /* calc(): largura proporcional ao RPM */
            width: calc(var(--rpm) / 8000 * 100%);
            transition: width 0.3s ease-out;
            box-shadow: 0 0 8px rgba(0, 240, 255, 0.2);
        }

        /* Zona de redline (últimos 25%) */
        .rpm-bar-track::after {
            content: '';
            position: absolute;
            right: 0;
            top: 0;
            width: 25%;
            height: 100%;
            background: rgba(255, 45, 45, 0.1);
            border-left: 1px solid rgba(255, 45, 45, 0.25);
            pointer-events: none;
        }

        /* Marcações de escala */
        .rpm-scale {
            display: flex;
            justify-content: space-between;
            margin-top: 4px;
            padding: 0 2px;
        }

        .rpm-scale span {
            font-size: 7px;
            color: #445;
            letter-spacing: 0.5px;
        }

        .rpm-scale span:last-child {
            color: var(--neon-red);
        }

        .rpm-readout {
            text-align: center;
            margin-top: 8px;
        }

        .rpm-value {
            font-size: 24px;
            font-weight: 700;
            color: #fff;
            letter-spacing: 2px;
        }

        .rpm-unit {
            font-size: 10px;
            color: var(--neon-cyan);
            font-weight: 700;
            margin-left: 4px;
        }

        /* Efeito de redline flash */
        .rpm-panel.redline .rpm-bar-fill {
            animation: rpm-flash 0.25s ease-in-out infinite alternate;
        }

        @keyframes rpm-flash {
            from { box-shadow: 0 0 8px rgba(255, 45, 45, 0.3); }
            to   { box-shadow: 0 0 18px rgba(255, 45, 45, 0.7), 0 0 30px rgba(255, 45, 45, 0.3); }
        }

        /* ==========================================================
           15. STATUS & TITLE
        ========================================================== */
        .hud-center {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .hud-title {
            font-size: 10px;
            letter-spacing: 4px;
            color: #445;
            text-transform: uppercase;
        }

        .status-indicator {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 8px;
            letter-spacing: 2px;
            color: #556;
        }

        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--neon-green);
            animation: pulse-dot 2s ease-in-out infinite;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 0.4; box-shadow: 0 0 4px rgba(0,255,136,0.3); }
            50%      { opacity: 1;   box-shadow: 0 0 8px rgba(0,255,136,0.6); }
        }

        /* ==========================================================
           16. TEST / DEMO CONTROLS
        ========================================================== */
        .test-controls {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 100;
            background: rgba(10, 10, 20, 0.8);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 10px 14px;
            backdrop-filter: blur(8px);
            font-size: 9px;
            color: #889;
            min-width: 180px;
        }

        .test-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .test-header span {
            letter-spacing: 2px;
            font-size: 8px;
        }

        .test-btn {
            background: rgba(0, 240, 255, 0.15);
            border: 1px solid rgba(0, 240, 255, 0.3);
            color: var(--neon-cyan);
            padding: 3px 10px;
            border-radius: 5px;
            font-family: 'Orbitron', sans-serif;
            font-size: 8px;
            cursor: pointer;
            letter-spacing: 1px;
            transition: all 0.2s ease;
        }

        .test-btn:hover {
            background: rgba(0, 240, 255, 0.25);
            box-shadow: 0 0 10px rgba(0, 240, 255, 0.2);
        }

        .test-btn.active {
            background: rgba(0, 240, 255, 0.3);
            border-color: var(--neon-cyan);
        }

        .test-sliders {
            display: none;
            margin-top: 8px;
        }

        .test-sliders.visible {
            display: block;
        }

        .test-sliders label {
            display: block;
            font-size: 8px;
            color: #667;
            margin: 6px 0 2px;
            letter-spacing: 1px;
        }

        .test-sliders input[type="range"] {
            width: 100%;
            height: 4px;
            -webkit-appearance: none;
            appearance: none;
            background: #222;
            border-radius: 2px;
            outline: none;
        }

        .test-sliders input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 12px;
            height: 12px;
            background: var(--neon-cyan);
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 0 6px rgba(0,240,255,0.4);
        }

        /* ==========================================================
           17. RESPONSIVE
        ========================================================== */
        @media (max-width: 700px) {
            .hud {
                flex-direction: column;
                align-items: center;
                gap: 15px;
                padding: 10px 15px 15px;
            }

            .speedometer-panel,
            .rpm-panel {
                width: 90%;
                max-width: 260px;
            }

            .speed-value {
                font-size: 24px;
            }

            .rpm-value {
                font-size: 18px;
            }

            .player-car .car-body {
                width: 7vh;
                height: 3vh;
            }

            .rival-car .car-body {
                width: 6vh;
                height: 2.6vh;
            }

            .test-controls {
                top: 5px;
                right: 5px;
                min-width: 150px;
            }
        }
    </style>
</head>
<body>

<div class="racing-container" id="racingContainer">

    <!-- ==================== SCENE ==================== -->
    <div class="scene">

        <!-- Céu noturno com estrelas e lua -->
        <div class="sky"></div>

        <!-- Brilho no horizonte -->
        <div class="horizon-glow"></div>

        <!-- Camada parallax de árvores -->
        <div class="trees-layer"></div>

        <!-- Grama (acima e abaixo da pista) -->
        <div class="grass-top"></div>
        <div class="grass-bottom"></div>

        <!-- Pista de asfalto -->
        <div class="road"></div>

        <!-- Bordas da pista -->
        <div class="road-edge-top"></div>
        <div class="road-edge-bottom"></div>

        <!-- Marcações tracejadas das faixas -->
        <div class="lane-marking lane-marking-1"></div>
        <div class="lane-marking lane-marking-2"></div>

        <!-- Linhas de velocidade (motion blur) -->
        <div class="speed-lines"></div>

        <!-- ===== CARRO DO JOGADOR ===== -->
        <div class="player-car" id="playerCar">
            <div class="car-body">
                <div class="headlight"></div>
                <div class="taillight"></div>
            </div>
        </div>

        <!-- ===== CARROS RIVAIS ===== -->
        <div class="rival-car rival-1">
            <div class="car-body"></div>
        </div>
        <div class="rival-car rival-2">
            <div class="car-body"></div>
        </div>
        <div class="rival-car rival-3">
            <div class="car-body"></div>
        </div>

    </div><!-- /.scene -->

    <!-- ==================== HUD OVERLAY ==================== -->
    <div class="hud">

        <!-- VELOCÍMETRO -->
        <div class="hud-panel speedometer-panel" id="speedometerPanel">
            <div class="panel-label">VELOCIDADE</div>

            <svg class="gauge-svg" viewBox="0 0 200 165">
                <defs>
                    <linearGradient id="arcGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%"   stop-color="#00f0ff"/>
                        <stop offset="35%"  stop-color="#00ff88"/>
                        <stop offset="65%"  stop-color="#ffdd00"/>
                        <stop offset="100%" stop-color="#ff2d2d"/>
                    </linearGradient>
                </defs>

                <!-- Arco de fundo (270° de 7:30 a 4:30) -->
                <path class="gauge-bg"
                      d="M 43.43 156.57 A 80 80 0 1 1 156.57 156.57"/>

                <!-- Arco de velocidade (preenchimento progressivo) -->
                <path class="gauge-fill"
                      d="M 43.43 156.57 A 80 80 0 1 1 156.57 156.57"/>

                <!-- Marcações maiores (a cada 40 km/h) -->
                <!-- 0 km/h -->
                <line class="tick-major" x1="44.85" y1="155.15" x2="52.78" y2="147.22"/>
                <!-- 40 km/h -->
                <line class="tick-major" x1="22.96" y1="87.80" x2="33.60" y2="89.50"/>
                <!-- 80 km/h -->
                <line class="tick-major" x1="64.59" y1="30.50" x2="69.50" y2="40.18"/>
                <!-- 120 km/h -->
                <line class="tick-major" x1="135.41" y1="30.50" x2="130.50" y2="40.18"/>
                <!-- 160 km/h -->
                <line class="tick-major" x1="177.04" y1="87.80" x2="166.40" y2="89.50"/>
                <!-- 200 km/h -->
                <line class="tick-major" x1="155.15" y1="155.15" x2="147.22" y2="147.22"/>

                <!-- Marcações menores (20, 60, 100, 140, 180 km/h) -->
                <line class="tick-minor" x1="25.81" y1="124.10" x2="33.89" y2="121.47"/>
                <line class="tick-minor" x1="36.90" y1="54.15" x2="44.06" y2="59.35"/>
                <line class="tick-minor" x1="100" y1="22" x2="100" y2="31"/>
                <line class="tick-minor" x1="163.10" y1="54.15" x2="155.94" y2="59.35"/>
                <line class="tick-minor" x1="174.19" y1="124.10" x2="166.11" y2="121.47"/>

                <!-- Labels numéricos -->
                <text class="gauge-label" x="56" y="143">0</text>
                <text class="gauge-label" x="33" y="90">40</text>
                <text class="gauge-label" x="68" y="45">80</text>
                <text class="gauge-label" x="132" y="45">120</text>
                <text class="gauge-label" x="168" y="90">160</text>
                <text class="gauge-label" x="144" y="143">200</text>

                <!-- Ponteiro (rotação via calc) -->
                <line class="gauge-needle" x1="100" y1="100" x2="100" y2="32"/>

                <!-- Hub central -->
                <circle class="gauge-hub-outer" cx="100" cy="100" r="8"/>
                <circle class="gauge-hub-inner" cx="100" cy="100" r="3.5"/>
            </svg>

            <div class="speed-readout">
                <span class="speed-value" id="velocidade">0.0</span>
                <span class="speed-unit">km/h</span>
            </div>
        </div>

        <!-- INFO CENTRAL -->
        <div class="hud-center">
            <div class="hud-title">PAINEL DE TELEMETRIA</div>
            <div class="status-indicator">
                <div class="status-dot"></div>
                <span>ESP32 ONLINE</span>
            </div>
        </div>

        <!-- RPM -->
        <div class="hud-panel rpm-panel" id="rpmPanel">
            <div class="panel-label">ROTAÇÃO</div>

            <div class="rpm-bar-container">
                <div class="rpm-bar-track">
                    <div class="rpm-bar-fill" id="rpmBarFill"></div>
                </div>
                <div class="rpm-scale">
                    <span>0</span>
                    <span>1k</span>
                    <span>2k</span>
                    <span>3k</span>
                    <span>4k</span>
                    <span>5k</span>
                    <span>6k</span>
                    <span>7k</span>
                    <span>8k</span>
                </div>
            </div>

            <div class="rpm-readout">
                <span class="rpm-value" id="rpm">0</span>
                <span class="rpm-unit">RPM</span>
            </div>
        </div>

    </div><!-- /.hud -->

    <!-- ==================== DEMO CONTROLS ==================== -->
    <div class="test-controls" id="testControls">
        <div class="test-header">
            <span>🎮 MODO DEMO</span>
            <button class="test-btn" id="toggleDemoBtn">ATIVAR</button>
        </div>
        <div class="test-sliders" id="testSliders">
            <label>Velocidade: <strong id="demoSpeedVal">0</strong> km/h</label>
            <input type="range" id="demoSpeed" min="0" max="200" value="0">
            <label>RPM: <strong id="demoRpmVal">0</strong></label>
            <input type="range" id="demoRpm" min="0" max="8000" value="0" step="100">
        </div>
    </div>

</div><!-- /.racing-container -->


<script>
    /* ==========================================================
       JAVASCRIPT MÍNIMO
       A ÚNICA responsabilidade do JS é:
       1. Buscar dados do backend (ou demo slider)
       2. Setar --speed e --rpm no container
       3. Atualizar os displays de texto
       Toda a animação visual é CSS puro via calc(var(--speed)).
    ========================================================== */

    const container = document.getElementById('racingContainer');
    const rpmPanel  = document.getElementById('rpmPanel');
    const painelVirtual = { velocidade: 0, rpm: 0 };
    let demoMode = false;

    /**
     * Aplica as variáveis CSS — a única ponte entre JS e as animações
     */
    function aplicarVariaveisCSS(speed, rpm) {
        container.style.setProperty('--speed', speed);
        container.style.setProperty('--rpm', rpm);

        // Toggle classe redline no painel RPM quando RPM > 6000
        rpmPanel.classList.toggle('redline', rpm > 6000);
    }

    /**
     * Busca dados do backend PHP e usa GSAP para interpolar suavemente
     */
    function atualizarDados() {
        if (demoMode) return;

        fetch('buscar_ultimo.php')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'ok') {
                    const novaVelocidade = Math.min(parseFloat(data.velocidade), 200);
                    const novoRpm = Math.min(parseFloat(data.rpm), 8000);

                    gsap.to(painelVirtual, {
                        duration: 0.5,
                        velocidade: novaVelocidade,
                        rpm: novoRpm,
                        ease: "power2.out",
                        onUpdate: function () {
                            // Seta as variáveis CSS (fonte da verdade)
                            aplicarVariaveisCSS(
                                painelVirtual.velocidade,
                                painelVirtual.rpm
                            );

                            // Atualiza displays de texto
                            document.getElementById('velocidade').innerText =
                                painelVirtual.velocidade.toFixed(1);
                            document.getElementById('rpm').innerText =
                                Math.round(painelVirtual.rpm);
                        }
                    });
                }
            })
            .catch(error => console.log("Erro na API:", error));
    }

    // Primeiro disparo + intervalo de 1 segundo
    atualizarDados();
    setInterval(atualizarDados, 1000);

    /* ==========================================================
       MODO DEMO — controles manuais via sliders
    ========================================================== */
    const toggleBtn    = document.getElementById('toggleDemoBtn');
    const testSliders  = document.getElementById('testSliders');
    const demoSpeedEl  = document.getElementById('demoSpeed');
    const demoRpmEl    = document.getElementById('demoRpm');
    const demoSpeedVal = document.getElementById('demoSpeedVal');
    const demoRpmVal   = document.getElementById('demoRpmVal');

    toggleBtn.addEventListener('click', () => {
        demoMode = !demoMode;
        toggleBtn.textContent = demoMode ? 'DESATIVAR' : 'ATIVAR';
        toggleBtn.classList.toggle('active', demoMode);
        testSliders.classList.toggle('visible', demoMode);

        if (!demoMode) {
            // Retorna ao modo live
            atualizarDados();
        }
    });

    demoSpeedEl.addEventListener('input', () => {
        if (!demoMode) return;
        const speed = parseFloat(demoSpeedEl.value);
        demoSpeedVal.textContent = speed;
        document.getElementById('velocidade').textContent = speed.toFixed(1);
        aplicarVariaveisCSS(speed, parseFloat(demoRpmEl.value));
    });

    demoRpmEl.addEventListener('input', () => {
        if (!demoMode) return;
        const rpm = parseFloat(demoRpmEl.value);
        demoRpmVal.textContent = rpm;
        document.getElementById('rpm').textContent = Math.round(rpm);
        aplicarVariaveisCSS(parseFloat(demoSpeedEl.value), rpm);
    });
</script>

</body>
</html>