import serial
import requests
import time

# --- CONFIGURAÇÕES ---
PORTA_SERIAL = 'COM6'  # Mude para a porta do seu Arduino (ex: COM4, COM5)
BAUD_RATE = 115200
URL_PHP = 'http://localhost/Projeto-Velocimetro/salvar_dados.php'

try:
    arduino = serial.Serial(PORTA_SERIAL, BAUD_RATE)
    print(f"Conectado ao Arduino na porta {PORTA_SERIAL}!")
    print("Aguardando dados para enviar ao XAMPP...\n")
except Exception as e:
    print(f"Erro ao conectar na porta serial. O Monitor Serial do Arduino está fechado? Erro: {e}")
    exit()

while True:
    try:
        # Verifica se o Arduino mandou alguma coisa pelo cabo
        if arduino.in_waiting > 0:
            linha = arduino.readline().decode('utf-8').strip()
            dados = linha.split(',')
            
            if len(dados) == 2:
                velocidade, rpm = dados
                
                # Monta o pacote igualzinho o ESP32 fazia
                payload = {
                    'velocidade': velocidade,
                    'rpm': rpm
                }
                
                # Envia para o XAMPP
                resposta = requests.post(URL_PHP, data=payload)
                print(f"Dashboard Atualizado -> Vel: {velocidade} km/h | RPM: {rpm} | Servidor: {resposta.text}")
                
    except Exception as e:
        print(f"Erro durante a execução: {e}")
        time.sleep(1)