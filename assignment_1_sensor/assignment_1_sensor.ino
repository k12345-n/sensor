#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>
#include <DHT.h>
#include <WiFi.h>
#include <WebServer.h>

#define MQ135_PIN 34
#define DHTPIN 4
#define DHTTYPE DHT11
#define SCREEN_WIDTH 128
#define SCREEN_HEIGHT 64

// WiFi Credentials
const char* ssid = "Galaxy A53 5G E658";
const char* password = "pjni2567";

Adafruit_SSD1306 display(SCREEN_WIDTH, SCREEN_HEIGHT, &Wire, -1);
DHT dht(DHTPIN, DHTTYPE);
WebServer server(80);

int gasValue = 0;
float temp = 0;

void handleRoot() {
  String status = (gasValue > 1000 || temp > 40) ? "DANGER!" : "SAFE";
  String color = (status == "DANGER!") ? "red" : "green";

  String html = "<!DOCTYPE html><html>";
  html += "<head><meta http-equiv='refresh' content='2'>"; 
  html += "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
  html += "<style>body { font-family: Arial; text-align: center; background-color: #f4f4f4; }";
  html += ".card { background: white; padding: 20px; margin: 20px auto; width: 80%; border-radius: 10px; box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2); }";
  html += ".status { font-size: 30px; font-weight: bold; color: " + color + "; }</style></head>";
  html += "<body>";
  html += "<h1>Kitchen Guard</h1>";
  html += "<div class='card'>";
  html += "<h2>Air Value: " + String(gasValue) + "</h2>";
  html += "<h2>Temp: " + String(temp) + " &deg;C</h2>";
  html += "<p class='status'>" + status + "</p>";
  html += "</div>";
  html += "</body></html>";

  server.send(200, "text/html", html);
}

void setup() {
  Serial.begin(115200);
  dht.begin();
  
  if (!display.begin(SSD1306_SWITCHCAPVCC, 0x3C)) {
    while (true); 
  }

  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) { delay(500); }
  
  Serial.println(WiFi.localIP());

  server.on("/", handleRoot);
  server.begin();

  display.clearDisplay();
  display.setTextColor(WHITE);
}

void loop() {
  server.handleClient();

  gasValue = analogRead(MQ135_PIN);
  temp = dht.readTemperature();

  // OLED Display Logic
  display.clearDisplay();
  display.setTextSize(1);
  display.setCursor(0, 0);
  display.println("KITCHEN GUARD");

  display.setCursor(0, 20);
  display.print("Temp: "); display.print(temp); display.println(" C");
  display.print("Air: "); display.println(gasValue);

  display.setTextSize(2);
  display.setCursor(0, 45);
  
  if (gasValue > 1000 || temp > 40) {
    display.println("DANGER!");
  } else {
    display.println("SAFE");
  }
  
  display.display();
  delay(1000);
}