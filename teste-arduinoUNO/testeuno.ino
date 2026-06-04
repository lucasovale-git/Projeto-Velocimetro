const int pinoPotenciometro = A0;

void setup() {
  Serial.begin(115200);
}

void loop() {
  long somaLeituras = 0;
  
  // Faz 20 leituras rápidas para criar uma média estável
  for(int i = 0; i < 20; i++) {
    somaLeituras += analogRead(pinoPotenciometro);
    delay(5); // Pequena pausa entre as leituras
  }
  
  // Divide a soma por 20 para ter o valor real e limpo
  int leituraLimpa = somaLeituras / 20;
  
  // Converte a leitura limpa para o nosso painel
  float velocidade = map(leituraLimpa, 0, 1023, 0, 180);
  float rpm = map(leituraLimpa, 0, 1023, 0, 8000);
  
  // Envia pela porta USB
  Serial.print(velocidade);
  Serial.print(",");
  Serial.println(rpm);
  
  // Delay de 900ms (pois já gastamos 100ms no loop das leituras)
  delay(900); 
}