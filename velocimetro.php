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
        /* ==========================================================
           7. RETRO SUN & PARALLAX CITY SKYLINE
        ========================================================== */
        .synth-sun {
            position: absolute;
            bottom: 2%;
            left: 50%;
            transform: translateX(-50%);
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: linear-gradient(180deg, #ff007f 0%, #ff5500 60%, #ffaa00 100%);
            box-shadow: 0 0 50px rgba(255, 0, 127, 0.5), 0 0 100px rgba(255, 85, 0, 0.25);
            z-index: 1;
            overflow: hidden;
        }

        .synth-sun::after {
            content: '';
            position: absolute;
            inset: 0;
            background: repeating-linear-gradient(180deg,
                transparent 0px, transparent 8px,
                #0a0a22 8px, #0a0a22 12px
            );
        }

        .skyline-wrapper {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 68%;
            z-index: 2;
            overflow: hidden;
            pointer-events: none;
        }

        .skyline-track {
            display: flex;
            width: 200%;
            height: 100%;
            will-change: transform;
        }

        .skyline-group {
            display: flex;
            align-items: flex-end;
            justify-content: space-around;
            width: 50%;
            height: 100%;
            padding: 0 20px;
        }

        .building {
            position: relative;
            background: linear-gradient(180deg, #120e26 0%, #070512 100%);
            border: 1px solid rgba(255, 255, 255, 0.03);
            border-bottom: none;
            box-shadow: inset 0 2px 4px rgba(255, 255, 255, 0.02);
        }

        .building::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
        }

        .b1 { width: 45px; height: 75%; border-left: 2px solid var(--neon-cyan); }
        .b1::after { background: var(--neon-cyan); box-shadow: 0 0 8px var(--neon-cyan); }

        .b2 { width: 60px; height: 50%; border-top: 1px solid var(--neon-orange); }
        .b2::after { background: var(--neon-orange); box-shadow: 0 0 8px var(--neon-orange); }

        .b3 { width: 50px; height: 85%; border-right: 2px solid var(--neon-yellow); }
        .b3::after { background: var(--neon-yellow); box-shadow: 0 0 8px var(--neon-yellow); }

        .b4 { width: 35px; height: 60%; background: linear-gradient(180deg, #180d35 0%, #070714 100%); }
        .b4::after { background: var(--neon-red); box-shadow: 0 0 8px var(--neon-red); }

        .b5 { width: 70px; height: 40%; border-top: 2px solid var(--neon-green); }
        .b5::after { background: var(--neon-green); box-shadow: 0 0 8px var(--neon-green); }

        .b6 { width: 40px; height: 90%; border-left: 1px solid var(--neon-cyan); }
        .b6::after { background: var(--neon-cyan); box-shadow: 0 0 8px var(--neon-cyan); }

        .antenna {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            width: 2px;
            height: 15px;
            background: #333;
        }

        .antenna::after {
            content: '';
            position: absolute;
            top: 0;
            left: -2px;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--neon-red);
            animation: antenna-blink 1s infinite alternate;
        }

        @keyframes antenna-blink {
            0% { opacity: 0.3; }
            100% { opacity: 1; box-shadow: 0 0 6px var(--neon-red); }
        }

        .windows {
            position: absolute;
            inset: 8px;
            background-image: 
                radial-gradient(circle, rgba(255, 230, 100, 0.18) 1px, transparent 1px),
                radial-gradient(circle, rgba(0, 240, 255, 0.12) 1px, transparent 1px);
            background-size: 8px 12px;
            background-position: 0 0, 4px 6px;
            opacity: 0.65;
        }



        /* ==========================================================
           8. ROAD AREA, STREETLIGHTS & PARALLAX GRID
        ========================================================== */
        .streetlights-wrapper {
            position: absolute;
            top: 31%;
            left: 0;
            width: 100%;
            height: 29%;
            z-index: 4;
            overflow: hidden;
            pointer-events: none;
        }

        .streetlights-track {
            display: flex;
            width: 200%;
            height: 100%;
            will-change: transform;
        }

        .streetlight-group {
            display: flex;
            justify-content: space-around;
            width: 50%;
            height: 100%;
        }

        .streetlight {
            position: relative;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, #222 0%, #0c0c14 100%);
            margin-left: 20%;
        }

        .streetlight::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 16px;
            height: 3px;
            background: #444;
            border-radius: 2px 0 0 2px;
        }

        .streetlight::after {
            content: '';
            position: absolute;
            top: 0;
            right: 12px;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #ffdd00;
            box-shadow: 0 0 10px #ffdd00;
        }

        .light-cone {
            position: absolute;
            top: 0;
            right: 15px;
            width: 130px;
            height: calc(100vh * 0.28);
            background: linear-gradient(135deg, rgba(255, 221, 0, 0.1) 0%, rgba(255, 221, 0, 0) 70%);
            clip-path: polygon(0 0, 100% 0, 100% 100%, 30% 100%);
            pointer-events: none;
        }



        .grass-top {
            position: absolute;
            top: 33%;
            left: 0;
            width: 100%;
            height: 3%;
            background: linear-gradient(180deg, #050510, #0c0822);
            border-bottom: 1px solid rgba(0, 240, 255, 0.25);
            z-index: 4;
        }

        .grass-bottom {
            position: absolute;
            top: 60%;
            left: 0;
            width: 100%;
            height: 3%;
            background: linear-gradient(180deg, #0c0822, #050510);
            border-top: 1px solid rgba(0, 240, 255, 0.25);
            z-index: 4;
        }

        .road {
            position: absolute;
            top: 35.5%;
            left: 0;
            width: 100%;
            height: 25.5%;
            background: #11111a;
            z-index: 5;
            /* Grid no asfalto (Estilo Retro Cyberpunk) */
            background-image:
                linear-gradient(90deg, rgba(255, 0, 127, 0.08) 1px, transparent 1px),
                linear-gradient(0deg, rgba(255, 0, 127, 0.04) 1px, transparent 1px);
            background-size: 40px 10px;
        }

        /* Bordas da pista (neon ciano) */
        .road-edge-top,
        .road-edge-bottom {
            position: absolute;
            left: 0;
            width: 100%;
            height: 4px;
            z-index: 6;
            box-shadow: 0 0 10px rgba(0, 240, 255, 0.6);
        }
        .road-edge-top    { top: 35.5%; background: var(--neon-cyan); }
        .road-edge-bottom { top: calc(35.5% + 25.5% - 4px); background: var(--neon-cyan); }

        /* Faixas tracejadas neon magenta */
        .lane-marking {
            position: absolute;
            left: 0;
            width: calc(100% + 90px);
            height: 2px;
            z-index: 6;
            background: repeating-linear-gradient(90deg,
                #ff007f 0px,
                #ff007f 40px,
                transparent 40px,
                transparent 90px
            );
            opacity: 0.8;
            box-shadow: 0 0 5px #ff007f;
            will-change: transform;
        }

        .lane-marking-1 { top: calc(35.5% + 25.5% / 3); }
        .lane-marking-2 { top: calc(35.5% + 25.5% * 2 / 3); }



        /* Sombras na borda da pista */
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
            background: linear-gradient(90deg, rgba(0,0,0,0.4), transparent);
        }
        .road::after {
            right: 0;
            background: linear-gradient(270deg, rgba(0,0,0,0.4), transparent);
        }

        /* ==========================================================
           9. SPEED LINES
        ========================================================== */
        .speed-lines {
            position: absolute;
            top: 35.5%;
            left: 0;
            width: 100%;
            height: 25.5%;
            z-index: 7;
            pointer-events: none;
            background:
                linear-gradient(0deg, transparent 12%, rgba(255,255,255,0.08) 12.3%, transparent 12.6%) 0 0,
                linear-gradient(0deg, transparent 35%, rgba(255,255,255,0.05) 35.3%, transparent 35.6%) 0 0,
                linear-gradient(0deg, transparent 58%, rgba(255,255,255,0.09) 58.3%, transparent 58.6%) 0 0,
                linear-gradient(0deg, transparent 82%, rgba(255,255,255,0.06) 82.3%, transparent 82.6%) 0 0;
            opacity: calc(var(--speed) * 0.0055);
        }

        /* ==========================================================
           10. PLAYER CAR (Vista Superior Premium)
        ========================================================== */
        .player-car {
            position: absolute;
            left: 15%;
            top: calc(35.5% + 25.5% / 2 - 2.1vh);
            z-index: 10;
            animation: vibrate 0.12s linear infinite;
            transition: top 0.22s cubic-bezier(0.25, 1, 0.5, 1);
            will-change: top;
        }

        @keyframes vibrate {
            0%, 100% { transform: translate(0, 0); }
            12%  { transform: translate(calc(var(--speed) * 0.004 * 1px), calc(var(--speed) * -0.02 * 1px)); }
            25%  { transform: translate(calc(var(--speed) * -0.003 * 1px), calc(var(--speed) * 0.015 * 1px)); }
            37%  { transform: translate(calc(var(--speed) * 0.005 * 1px), calc(var(--speed) * -0.018 * 1px)); }
            50%  { transform: translate(calc(var(--speed) * -0.002 * 1px), calc(var(--speed) * 0.012 * 1px)); }
            62%  { transform: translate(calc(var(--speed) * 0.004 * 1px), calc(var(--speed) * -0.022 * 1px)); }
            75%  { transform: translate(calc(var(--speed) * -0.003 * 1px), calc(var(--speed) * 0.016 * 1px)); }
            87%  { transform: translate(calc(var(--speed) * 0.002 * 1px), calc(var(--speed) * -0.013 * 1px)); }
        }

        .car-body-wrapper {
            position: relative;
            width: 10vh;
            height: 4.2vh;
        }

        .car-underglow {
            position: absolute;
            inset: -4px -8px;
            border-radius: 14px;
            filter: blur(10px);
            z-index: -1;
        }

        .player-car .car-underglow {
            background: rgba(255, 45, 45, 0.85);
            box-shadow: 0 0 16px rgba(255, 45, 45, 0.65);
            animation: underglow-flicker 0.15s infinite alternate;
        }

        @keyframes underglow-flicker {
            0% { opacity: 0.75; }
            100% { opacity: 1; }
        }

        /* Rodas */
        .wheel {
            position: absolute;
            width: 2.2vh;
            height: 0.9vh;
            background: #181818;
            border-radius: 2px;
            border: 1px solid #3a3a3a;
            z-index: 1;
            background-image: repeating-linear-gradient(90deg, #111 0px, #111 2px, #222 2px, #222 4px);
        }
        .fl { top: -0.6vh; right: 1.8vh; }
        .fr { bottom: -0.6vh; right: 1.8vh; }
        .rl { top: -0.6vh; left: 1.2vh; }
        .rr { bottom: -0.6vh; left: 1.2vh; }

        /* Chassis */
        .car-chassis {
            position: absolute;
            inset: 0;
            border-radius: 6px 20px 20px 6px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.5);
            z-index: 2;
        }

        .player-car .car-chassis {
            background: linear-gradient(180deg, #ff3b30 0%, #8f0e0e 50%, #d32f2f 100%);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        /* Faixas esportivas de fibra de carbono */
        .car-stripes {
            position: absolute;
            top: 32%;
            bottom: 32%;
            left: 12%;
            right: 32%;
            background: linear-gradient(180deg, #111 0%, #222 100%);
            opacity: 0.85;
            border-radius: 1px;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        }

        /* Cockpit e Para-brisa */
        .car-cockpit {
            position: absolute;
            right: 18%;
            top: 15%;
            width: 25%;
            height: 70%;
            background: linear-gradient(135deg, rgba(200, 240, 255, 0.85) 0%, rgba(100, 180, 255, 0.3) 50%, rgba(200, 240, 255, 0.05) 100%);
            border-radius: 4px 10px 10px 4px;
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: inset 0 2px 4px rgba(255,255,255,0.25);
        }

        /* Aerofólio (Spoiler) */
        .car-spoiler {
            position: absolute;
            left: -2px;
            top: -0.5vh;
            width: 0.8vh;
            height: 5.2vh;
            background: #111;
            border-radius: 2px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.5);
            border: 1px solid #333;
        }
        .car-spoiler::before,
        .car-spoiler::after {
            content: '';
            position: absolute;
            width: 4px;
            height: 6px;
            background: #111;
            left: -1px;
        }
        .car-spoiler::before { top: 0; }
        .car-spoiler::after { bottom: 0; }

        /* Faróis (Projeção real de luz) */
        .headlight-beam {
            position: absolute;
            right: -180px;
            top: -49px;
            width: 180px;
            height: 140px;
            background: linear-gradient(90deg, rgba(255, 230, 150, 0.35) 0%, rgba(255, 230, 150, 0) 80%);
            clip-path: polygon(0 35%, 100% 0, 100% 100%, 0 65%);
            filter: blur(4px);
            opacity: calc(0.2 + var(--speed) * 0.004);
            pointer-events: none;
        }

        /* Lanternas traseiras */
        .taillight-glow {
            position: absolute;
            left: -6px;
            top: 15%;
            bottom: 15%;
            width: 6px;
            background: #ff2d2d;
            box-shadow: 0 0 12px #ff2d2d;
            border-radius: 1px;
        }

        /* Chamas de escapamento (RPM alto) */
        .exhaust-flame {
            position: absolute;
            left: -22px;
            top: 30%;
            height: 40%;
            width: 22px;
            background: linear-gradient(270deg, #ffdd00 0%, #ff5500 50%, rgba(255, 0, 0, 0) 100%);
            transform-origin: right center;
            animation: flame-burn 0.05s infinite alternate;
            opacity: calc(var(--rpm) * 0.00018 - 0.2);
            filter: drop-shadow(0 0 6px #ff5500);
            pointer-events: none;
        }

        @keyframes flame-burn {
            0% { transform: scaleY(0.8) scaleX(0.85) skewY(-2deg); }
            100% { transform: scaleY(1.2) scaleX(1.15) skewY(2deg); }
        }

        /* ==========================================================
           11. RIVAL CARS (Atualização por JS e Estilos Diferenciados)
        ========================================================== */
        .rival-car {
            position: absolute;
            z-index: 9;
            transform: translate3d(120vw, 0, 0); /* Controlado dinamicamente via JS */
        }

        .rival-car .car-body-wrapper {
            position: relative;
            width: 9.5vh;
            height: 4vh;
        }

        /* Rival 1 — Azul Coupe Esportivo */
        .rival-1 {
            top: calc(35.5% + 25.5% / 6 - 2vh);
        }
        .rival-1 .car-chassis {
            background: linear-gradient(180deg, #007aff 0%, #003a8c 50%, #0056b3 100%);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        .rival-1 .car-underglow {
            background: rgba(0, 240, 255, 0.85);
            box-shadow: 0 0 15px rgba(0, 240, 255, 0.65);
        }
        .rival-1 .headlight-beam {
            background: linear-gradient(90deg, rgba(0, 240, 255, 0.3) 0%, rgba(0, 240, 255, 0) 80%);
        }

        /* Rival 2 — Laranja Muscle Retro */
        .rival-2 {
            top: calc(35.5% + 25.5% / 2 - 2vh);
        }
        .rival-2 .car-chassis {
            background: linear-gradient(180deg, #ff9500 0%, #b35900 50%, #ff7f00 100%);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        .rival-2 .car-underglow {
            background: rgba(255, 127, 0, 0.85);
            box-shadow: 0 0 15px rgba(255, 127, 0, 0.65);
        }
        .rival-2 .headlight-beam {
            background: linear-gradient(90deg, rgba(255, 150, 0, 0.3) 0%, rgba(255, 150, 0, 0) 80%);
        }

        /* Rival 3 — Verde Hypercar Aerodinâmico */
        .rival-3 {
            top: calc(35.5% + 25.5% * 5 / 6 - 2vh);
        }
        .rival-3 .car-chassis {
            background: linear-gradient(180deg, #34c759 0%, #146428 50%, #24b044 100%);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        .rival-3 .car-underglow {
            background: rgba(52, 199, 89, 0.85);
            box-shadow: 0 0 15px rgba(52, 199, 89, 0.65);
        }
        .rival-3 .headlight-beam {
            background: linear-gradient(90deg, rgba(52, 199, 89, 0.3) 0%, rgba(52, 199, 89, 0) 80%);
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
           14. RPM CIRCULAR — gauge SVG circular de 270°
           Ponteiro: rotate(calc(-135deg + var(--rpm) * 0.03375deg))
           Arco: stroke-dashoffset(calc(377 - var(--rpm) * 0.047125))
        ========================================================== */
        .rpm-panel {
            width: 260px;
        }

        .rpm-gauge-fill {
            fill: none;
            stroke: url(#rpmArcGradient);
            stroke-width: 10;
            stroke-linecap: round;
            stroke-dasharray: 377;
            /* calc(): preenchimento progressivo do RPM */
            stroke-dashoffset: calc(377px - var(--rpm) * 0.047125 * 1px);
            transition: stroke-dashoffset 0.15s ease-out;
        }

        .rpm-gauge-needle {
            stroke: var(--neon-red);
            stroke-width: 2.5;
            stroke-linecap: round;
            transform-origin: 100px 100px;
            /* calc(): ponteiro gira de -135° (0 RPM) a +135° (8000 RPM) */
            transform: rotate(calc(-135deg + var(--rpm) * 0.03375deg));
            transition: transform 0.15s ease-out;
            filter: drop-shadow(0 0 6px rgba(255, 45, 45, 0.7));
        }

        .rpm-value {
            font-size: 32px;
            font-weight: 900;
            color: #fff;
            /* Glow proporcional ao RPM */
            text-shadow:
                0 0 calc(4px + var(--rpm) * 0.0015 * 1px) rgba(0, 240, 255, 0.5),
                0 0 calc(8px + var(--rpm) * 0.003 * 1px) rgba(0, 240, 255, 0.2);
            letter-spacing: 2px;
        }

        .rpm-unit {
            font-size: 11px;
            color: var(--neon-cyan);
            font-weight: 700;
            margin-left: 4px;
            letter-spacing: 1px;
        }

        /* Efeito de redline flash no ponteiro e arco do RPM circular */
        .rpm-panel.redline .rpm-gauge-needle {
            animation: rpm-needle-flash 0.15s ease-in-out infinite alternate;
        }
        .rpm-panel.redline .rpm-gauge-fill {
            animation: rpm-fill-flash 0.15s ease-in-out infinite alternate;
        }

        @keyframes rpm-needle-flash {
            from { filter: drop-shadow(0 0 6px rgba(255, 45, 45, 0.7)); }
            to   { filter: drop-shadow(0 0 16px rgba(255, 45, 45, 1)) brightness(1.5); }
        }
        @keyframes rpm-fill-flash {
            from { opacity: 0.85; }
            to   { opacity: 1; filter: drop-shadow(0 0 8px rgba(255, 45, 45, 0.8)); }
        }

        /* ==========================================================
           14A. COLISÃO, EXPLOSÃO & CRASH EFFECT
        ========================================================== */
        /* Carbonização do carro do jogador */
        .player-car.crashed .car-chassis {
            filter: brightness(0.12) contrast(1.3) grayscale(0.8);
            transition: filter 0.15s ease-in;
        }

        /* Nuvem de explosão no centro do carro */
        .explosion-cloud {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0);
            width: 14vh;
            height: 14vh;
            border-radius: 50%;
            background: radial-gradient(circle, #ffffff 0%, #ffdd00 25%, #ff5500 55%, #ff0000 80%, transparent 100%);
            opacity: 0;
            z-index: 99;
            pointer-events: none;
            filter: blur(1px);
            will-change: transform, opacity;
        }

        .player-car.crashed .explosion-cloud {
            animation: explode-anim 0.8s cubic-bezier(0.1, 0.8, 0.3, 1) forwards;
        }

        @keyframes explode-anim {
            0% { opacity: 1; transform: translate(-50%, -50%) scale(0.2); }
            30% { opacity: 1; transform: translate(-50%, -50%) scale(1.6); filter: blur(3px); }
            70% { opacity: 0.8; transform: translate(-50%, -50%) scale(2.2); filter: blur(6px); }
            100% { opacity: 0; transform: translate(-50%, -50%) scale(2.6); }
        }

        /* Tremedeira da tela no impacto */
        .scene.crashed-shake {
            animation: scene-crash-shake 0.5s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
        }

        @keyframes scene-crash-shake {
            10%, 90% { transform: translate3d(-6px, 4px, 0) rotate(1.2deg); }
            20%, 80% { transform: translate3d(8px, -5px, 0) rotate(-1.7deg); }
            30%, 50%, 70% { transform: translate3d(-10px, 8px, 0) rotate(2.4deg); }
            40%, 60% { transform: translate3d(10px, -8px, 0) rotate(-2.4deg); }
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

            .player-car .car-body-wrapper {
                width: 8vh;
                height: 3.4vh;
            }

            .rival-car .car-body-wrapper {
                width: 7.6vh;
                height: 3.2vh;
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

        <!-- Céu noturno com estrelas, lua e sol retro neon -->
        <div class="sky">
            <!-- Sol Synthwave Retro -->
            <div class="synth-sun"></div>
            <!-- Parallax Skyline da Cidade -->
            <div class="skyline-wrapper">
                <div class="skyline-track">
                    <div class="skyline-group">
                        <div class="building b1"><div class="antenna"></div><div class="windows"></div></div>
                        <div class="building b2"><div class="windows"></div></div>
                        <div class="building b3"><div class="windows"></div></div>
                        <div class="building b4"><div class="antenna"></div><div class="windows"></div></div>
                        <div class="building b5"><div class="windows"></div></div>
                        <div class="building b6"><div class="windows"></div></div>
                    </div>
                    <div class="skyline-group">
                        <div class="building b1"><div class="antenna"></div><div class="windows"></div></div>
                        <div class="building b2"><div class="windows"></div></div>
                        <div class="building b3"><div class="windows"></div></div>
                        <div class="building b4"><div class="antenna"></div><div class="windows"></div></div>
                        <div class="building b5"><div class="windows"></div></div>
                        <div class="building b6"><div class="windows"></div></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Brilho no horizonte -->
        <div class="horizon-glow"></div>

        <!-- Postes de luz dinâmicos (streetlights) -->
        <div class="streetlights-wrapper">
            <div class="streetlights-track">
                <div class="streetlight-group">
                    <div class="streetlight"><div class="light-cone"></div></div>
                    <div class="streetlight"><div class="light-cone"></div></div>
                    <div class="streetlight"><div class="light-cone"></div></div>
                </div>
                <div class="streetlight-group">
                    <div class="streetlight"><div class="light-cone"></div></div>
                    <div class="streetlight"><div class="light-cone"></div></div>
                    <div class="streetlight"><div class="light-cone"></div></div>
                </div>
            </div>
        </div>

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
            <div class="car-body-wrapper">
                <div class="explosion-cloud"></div>
                <div class="car-underglow"></div>
                <div class="wheel fl"></div>
                <div class="wheel fr"></div>
                <div class="wheel rl"></div>
                <div class="wheel rr"></div>
                <div class="car-chassis">
                    <div class="car-cockpit"></div>
                    <div class="car-stripes"></div>
                    <div class="car-spoiler"></div>
                </div>
                <div class="headlight-beam"></div>
                <div class="taillight-glow"></div>
                <div class="exhaust-flame"></div>
            </div>
        </div>

        <!-- ===== CARROS RIVAIS ===== -->
        <div class="rival-car rival-1">
            <div class="car-body-wrapper">
                <div class="car-underglow"></div>
                <div class="wheel fl"></div>
                <div class="wheel fr"></div>
                <div class="wheel rl"></div>
                <div class="wheel rr"></div>
                <div class="car-chassis">
                    <div class="car-cockpit"></div>
                    <div class="car-spoiler"></div>
                </div>
                <div class="headlight-beam"></div>
                <div class="taillight-glow"></div>
            </div>
        </div>
        <div class="rival-car rival-2">
            <div class="car-body-wrapper">
                <div class="car-underglow"></div>
                <div class="wheel fl"></div>
                <div class="wheel fr"></div>
                <div class="wheel rl"></div>
                <div class="wheel rr"></div>
                <div class="car-chassis">
                    <div class="car-cockpit"></div>
                    <div class="car-spoiler"></div>
                </div>
                <div class="headlight-beam"></div>
                <div class="taillight-glow"></div>
            </div>
        </div>
        <div class="rival-car rival-3">
            <div class="car-body-wrapper">
                <div class="car-underglow"></div>
                <div class="wheel fl"></div>
                <div class="wheel fr"></div>
                <div class="wheel rl"></div>
                <div class="wheel rr"></div>
                <div class="car-chassis">
                    <div class="car-cockpit"></div>
                    <div class="car-spoiler"></div>
                </div>
                <div class="headlight-beam"></div>
                <div class="taillight-glow"></div>
            </div>
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

        <!-- RPM CIRCULAR -->
        <div class="hud-panel rpm-panel" id="rpmPanel">
            <div class="panel-label">ROTAÇÃO</div>

            <svg class="gauge-svg" viewBox="0 0 200 165">
                <defs>
                    <linearGradient id="rpmArcGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%"   stop-color="#00f0ff"/>
                        <stop offset="40%"  stop-color="#00ff88"/>
                        <stop offset="70%"  stop-color="#ffdd00"/>
                        <stop offset="85%"  stop-color="#ff6b2d"/>
                        <stop offset="100%" stop-color="#ff2d2d"/>
                    </linearGradient>
                </defs>

                <!-- Arco de fundo (270° de 7:30 a 4:30) -->
                <path class="gauge-bg"
                      d="M 43.43 156.57 A 80 80 0 1 1 156.57 156.57"/>

                <!-- Arco de RPM (preenchimento progressivo) -->
                <path class="gauge-fill rpm-gauge-fill"
                      d="M 43.43 156.57 A 80 80 0 1 1 156.57 156.57"/>

                <!-- Marcações maiores (a cada 1000 RPM, marcadas como 0, 2, 4, 6, 8) -->
                <!-- 0 -->
                <line class="tick-major" x1="44.85" y1="155.15" x2="52.78" y2="147.22"/>
                <!-- 1 -->
                <line class="tick-minor" x1="25.81" y1="124.10" x2="33.89" y2="121.47"/>
                <!-- 2 -->
                <line class="tick-major" x1="22.96" y1="87.80" x2="33.60" y2="89.50"/>
                <!-- 3 -->
                <line class="tick-minor" x1="36.90" y1="54.15" x2="44.06" y2="59.35"/>
                <!-- 4 -->
                <line class="tick-major" x1="64.59" y1="30.50" x2="69.50" y2="40.18"/>
                <!-- 5 -->
                <line class="tick-minor" x1="100" y1="22" x2="100" y2="31"/>
                <!-- 6 (Redline) -->
                <line class="tick-major redline-tick" x1="135.41" y1="30.50" x2="130.50" y2="40.18"/>
                <!-- 7 -->
                <line class="tick-minor redline-tick" x1="163.10" y1="54.15" x2="155.94" y2="59.35"/>
                <!-- 8 -->
                <line class="tick-major redline-tick" x1="177.04" y1="87.80" x2="166.40" y2="89.50"/>

                <!-- Labels numéricos -->
                <text class="gauge-label" x="56" y="143">0</text>
                <text class="gauge-label" x="33" y="90">2</text>
                <text class="gauge-label" x="68" y="45">4</text>
                <text class="gauge-label" x="132" y="45">6</text>
                <text class="gauge-label" x="168" y="90">8</text>

                <!-- Ponteiro (rotação via calc) -->
                <line class="gauge-needle rpm-gauge-needle" x1="100" y1="100" x2="100" y2="32"/>

                <!-- Hub central -->
                <circle class="gauge-hub-outer" cx="100" cy="100" r="8"/>
                <circle class="gauge-hub-inner" cx="100" cy="100" r="3.5"/>
            </svg>

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

    /* ==========================================================
       JOGABILIDADE — CONTROLE DE FAIXAS E COLISÕES
    ========================================================== */
    const playerCar = document.getElementById('playerCar');
    const scene = document.querySelector('.scene');
    const skylineTrack = document.querySelector('.skyline-track');
    const streetlightsTrack = document.querySelector('.streetlights-track');
    const laneMarkings = document.querySelectorAll('.lane-marking');

    // Definição das coordenadas Y de cada faixa (topo, meio, baixo)
    const lanesY = [
        "calc(35.5% + 25.5% / 6 - 2.1vh)", // Lane 1 (topo)
        "calc(35.5% + 25.5% / 2 - 2.1vh)", // Lane 2 (meio - padrão)
        "calc(35.5% + 25.5% * 5 / 6 - 2.1vh)" // Lane 3 (baixo)
    ];

    let playerLane = 1; // Começa no meio (Lane 2)
    let crashed = false; // Estado de colisão/explosão
    let crashTime = 0; // Registro do timestamp da colisão

    // Escuta do teclado para mudar de faixa
    window.addEventListener('keydown', (e) => {
        if (crashed) return; // Desativa comandos se o carro estiver explodido

        if (e.key === 'ArrowUp') {
            playerLane = Math.max(0, playerLane - 1);
            playerCar.style.top = lanesY[playerLane];
        } else if (e.key === 'ArrowDown') {
            playerLane = Math.min(2, playerLane + 1);
            playerCar.style.top = lanesY[playerLane];
        }
    });

    const rivalCars = [
        {
            element: document.querySelector('.rival-1'),
            baseSpeed: 75,
            speed: 75,
            x: 120, // Posição X inicial (fora da tela, à direita)
            lane: 1 // Faixa 1 (topo, playerLane = 0)
        },
        {
            element: document.querySelector('.rival-2'),
            baseSpeed: 95,
            speed: 95,
            x: 150,
            lane: 2 // Faixa 2 (meio, playerLane = 1)
        },
        {
            element: document.querySelector('.rival-3'),
            baseSpeed: 60,
            speed: 60,
            x: 180,
            lane: 3 // Faixa 3 (baixo, playerLane = 2)
        }
    ];

    let lastTime = performance.now();

    // Variáveis suavizadas para renderização visual da cena e do carro
    let smoothedSpeed = 0;
    let smoothedRpm = 0;

    // Acumuladores de offset para scroll infinito do cenário
    let skylineOffset = 0;
    let streetlightsOffset = 0;
    let laneMarkingOffset = 0;

    function atualizarFisicaECenario(now) {
        requestAnimationFrame(atualizarFisicaECenario);

        const dt = (now - lastTime) / 1000;
        lastTime = now;

        // Evita saltos gigantes caso a janela perca foco (clamped dt)
        const dtLimitado = Math.min(dt, 0.1);

        // Velocidades alvo instantâneas (do ESP32 ou do slider demo)
        let playerSpeedAlvo = demoMode ? parseFloat(demoSpeedEl.value) : painelVirtual.velocidade;
        let playerRpmAlvo = demoMode ? parseFloat(demoRpmEl.value) : painelVirtual.rpm;

        // ==========================================================
        // LÓGICA DE CRASH E RECUPERAÇÃO
        // ==========================================================
        if (crashed) {
            // Força a velocidade visual e do painel para zero
            playerSpeedAlvo = 0;
            playerRpmAlvo = 800; // RPM de marcha lenta

            // Finaliza o estado de explosão após 2.5 segundos
            if (now - crashTime > 2500) {
                crashed = false;
                playerCar.classList.remove('crashed');
                scene.classList.remove('crashed-shake');

                // Reposiciona todos os rivais para longe para evitar colisões imediatas
                rivalCars.forEach(rival => {
                    rival.x = 120 + Math.random() * 45;
                });

                // Retorna o jogador para a faixa do meio de forma segura
                playerLane = 1;
                playerCar.style.top = lanesY[playerLane];
            }
        } else {
            // Se NÃO estiver colidido, realiza detecção de colisão com o rival da mesma faixa
            // activeRival corresponde diretamente a rivalCars[playerLane]
            const activeRival = rivalCars[playerLane];
            if (activeRival) {
                // Checa overlap de proximidade no eixo X (player fixo em 15vw)
                // Colisão ativada se a traseira/frente do rival encostar no player
                if (activeRival.x > 8.5 && activeRival.x < 22.0) {
                    crashed = true;
                    crashTime = now;
                    playerCar.classList.add('crashed');
                    scene.classList.add('crashed-shake');
                }
            }
        }

        // Interpolação suave (lerp) baseada no tempo delta
        const fatorSuavizacao = 1 - Math.exp(-7.5 * dtLimitado);
        smoothedSpeed += (playerSpeedAlvo - smoothedSpeed) * fatorSuavizacao;
        smoothedRpm += (playerRpmAlvo - smoothedRpm) * fatorSuavizacao;

        // Aplica as variáveis suavizadas APENAS na árvore da cena (carro, vibração, faróis, escapamento)
        scene.style.setProperty('--speed', smoothedSpeed);
        scene.style.setProperty('--rpm', smoothedRpm);

        // ==========================================================
        // SCROLL SUAVE DO CENÁRIO
        // ==========================================================
        
        // 1. Skyline de prédios (deslocamento de 0.045vw por km/h por segundo)
        skylineOffset -= smoothedSpeed * dtLimitado * 0.045;
        if (skylineOffset <= -100) skylineOffset += 100;
        skylineTrack.style.transform = `translate3d(${skylineOffset}vw, 0, 0)`;

        // 2. Postes de luz (deslocamento de 0.77vw por km/h por segundo)
        streetlightsOffset -= smoothedSpeed * dtLimitado * 0.77;
        if (streetlightsOffset <= -100) streetlightsOffset += 100;
        streetlightsTrack.style.transform = `translate3d(${streetlightsOffset}vw, 0, 0)`;

        // 3. Faixas da pista (deslocamento de 15px por km/h por segundo)
        laneMarkingOffset -= smoothedSpeed * dtLimitado * 15.0;
        if (laneMarkingOffset <= -90) laneMarkingOffset += 90;
        laneMarkings.forEach(el => {
            el.style.transform = `translate3d(${laneMarkingOffset}px, 0, 0)`;
        });

        // ==========================================================
        // FÍSICA DOS CARROS RIVAIS (Usando velocidade suavizada)
        // ==========================================================
        rivalCars.forEach(rival => {
            // Flutuação natural de velocidade para simular tráfego real (+-4 km/h)
            rival.speed = rival.baseSpeed + Math.sin(now * 0.0012 + rival.lane * 15) * 4;

            // Velocidade relativa
            const deltaV = smoothedSpeed - rival.speed;

            // Fator de deslocamento em tela
            const fatorDeslocamento = 0.45;
            const dx = -deltaV * fatorDeslocamento * dtLimitado;

            rival.x += dx;

            // Lógica de Overtake e Respawn
            // 1. Jogador está mais rápido e o rival ficou para trás (esquerda)
            if (rival.x < -25) {
                if (smoothedSpeed > rival.speed) {
                    rival.x = 120 + Math.random() * 40;
                    // Define velocidade menor que a do jogador para ser ultrapassado de novo
                    rival.baseSpeed = Math.max(40, smoothedSpeed - (20 + Math.random() * 35));
                } else {
                    rival.x = 125;
                }
            }
            // 2. Jogador está mais lento e o rival se afastou à frente (direita)
            else if (rival.x > 140) {
                if (smoothedSpeed < rival.speed) {
                    rival.x = -25 - Math.random() * 40;
                    // Define velocidade maior que a do jogador para conseguir ultrapassar
                    rival.baseSpeed = Math.min(180, smoothedSpeed + (15 + Math.random() * 35));
                } else {
                    rival.x = -25;
                }
            }

            // Aplica a posição calculada nos rivais
            rival.element.style.transform = `translate3d(${rival.x}vw, 0, 0)`;
        });
    }

    // Inicializa o motor físico e animações de scroll
    requestAnimationFrame(atualizarFisicaECenario);
</script>

</body>
</html>