#include <Arduino.h>
#include <WiFi.h>
#include <HTTPClient.h>

const char* nomeRede = "Sobrado 138";
const char* senhaRede = "12345678";
const String urlServidor = "http://192.168.100.6/Projeto-Velocimetro/salvar_dados.php";

#define PINO_SENSOR_HALL 4

volatile unsigned long tempoUltimoPulso = 0;
volatile unsigned long intervaloPulsos = 0;
volatile bool primeiroPulso = true;

float diametroRoda = 0.10; //editar diametro
float circunferenciaRoda;
float velocidadeKmh = 0;
float rpm = 0;

unsigned long tempoUltimoEnvio = 0;
const unsigned long intervaloEnvio = 1000;
void IRAM_ATTR detectarIma() {
unsigned long tempoAtual = micros();

if (tempoAtual - tempoUltimoPulso > 5000) {
    if (!primeiroPulso) {
      intervaloPulsos = tempoAtual - tempoUltimoPulso;
        } else {
        primeiroPulso = false;
        }
    tempoUltimoPulso = tempoAtual;
  }
}

void setup() {
Serial.begin(115200);
WiFi.begin(nomeRede, senhaRede);

while (WiFi.status() != WL_CONNECTED) {
delay(500);
Serial.print(".");
}
pinMode(PINO_SENSOR_HALL, INPUT_PULLUP);
circunferenciaRoda = diametroRoda * 3.141592;
attachInterrupt(digitalPinToInterrupt(PINO_SENSOR_HALL), detectarIma, FALLING);
}


void loop() {

noInterrupts();
unsigned long intervalo = intervaloPulsos;
unsigned long tempoDesdeUltimoPulso = micros() - tempoUltimoPulso;
interrupts();

if (tempoDesdeUltimoPulso > 2000000 || primeiroPulso) {
velocidadeKmh = 0;
rpm = 0;
noInterrupts();
intervaloPulsos = 0;
primeiroPulso = true;
interrupts();
}

else if (intervalo > 0) {
float pulsosPorSegundo = 1000000.0 / intervalo;
rpm = pulsosPorSegundo * 60.0;
velocidadeKmh = (circunferenciaRoda * pulsosPorSegundo) * 3.6;
}

if (millis() - tempoUltimoEnvio > intervaloEnvio) {
tempoUltimoEnvio = millis();

if (WiFi.status() == WL_CONNECTED) {
HTTPClient clienteHttp;
 clienteHttp.begin(urlServidor);
 clienteHttp.addHeader("Content-Type", "application/x-www-form-urlencoded");
 String dadosRequisicao = "velocidade=" + String(velocidadeKmh, 2) + "&rpm=" + String(rpm, 2);
 int codigoRespostaHttp = clienteHttp.POST(dadosRequisicao);
if (codigoRespostaHttp > 0) {
String respostaServidor = clienteHttp.getString();
Serial.println("Resposta: " + respostaServidor);
}

clienteHttp.end();
}
}
// reconectar WiFi se desconectado (opcional)
if (WiFi.status() != WL_CONNECTED) {
  static unsigned long lastTry = 0;
  if (millis() - lastTry > 5000) {
    lastTry = millis();
    Serial.println("WiFi desconectado, tentando reconectar...");
    WiFi.disconnect();
    WiFi.begin(nomeRede, senhaRede);
  }
}

}
