// ESP32 Firmware: Reads sensors, handles safety warning/danger rules, and uploads logs.
#include <WiFi.h>
#include <HTTPClient.h>
#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>
#include "DHT.h"
#include <Preferences.h>
#include <WebServer.h>
#include <DNSServer.h>
#include <WiFiManager.h>

// Setup constants for server URLs and unique device identification.
const char* deviceId    = "ESP32_SAFETY_01";
const char* serverUrl   = "http://canorcannot.com/Ken/sensor_fa/insert_log.php"; 
const char* controlUrl  = "http://canorcannot.com/Ken/sensor_fa/control.php";

String getSettingsUrl() {
  return "http://canorcannot.com/Ken/sensor_fa/get_settings.php?device_id=" + String(deviceId);
}

// Function to generate setup portal AP SSID dynamically from device ID.
String getAPSSID() {
  String idStr = String(deviceId);
  int idx = idStr.length() - 1;
  while (idx >= 0 && isDigit(idStr.charAt(idx))) {
    idx--;
  }
  String suffix = idStr.substring(idx + 1);
  if (suffix.length() > 0) {
    return "ESP32-Safety-Setup-" + suffix;
  }
  return "ESP32-Safety-Setup-" + idStr;
}

// Pin mapping configurations for sensors and actuator hardware.
#define DHTPIN 4          
#define DHTTYPE DHT22 
#define MQ_PIN 34        
#define FLAME_PIN 26      
#define PIR_PIN 15      
#define RESET_BTN_PIN 13  
#define MANUAL_BTN_PIN 32 
#define BUZZER_PIN 27     
#define RELAY_PIN 25     

#define SCREEN_WIDTH 128
#define SCREEN_HEIGHT 64
// Initialize SSD1306 OLED display and DHT22 temperature sensor objects.
Adafruit_SSD1306 display(SCREEN_WIDTH, SCREEN_HEIGHT, &Wire, -1);
DHT dht(DHTPIN, DHTTYPE);

// Set up non-volatile flash preferences and listen on port 8080.
Preferences preferences;
WebServer mainServer(8080);   

// Callback executed when entering captive portal configuration mode.
void configModeCallback(WiFiManager *myWiFiManager) {
  Serial.println("Entered Configuration Portal Mode!");
  Serial.print("AP IP Address: ");
  Serial.println(WiFi.softAPIP());

  display.clearDisplay();
  display.setCursor(0, 0);
  display.println("🔧 WIFI ONBOARDING");
  display.println("-------------------");
  display.println("Connect your phone to:");
  display.setTextColor(SSD1306_BLACK, SSD1306_WHITE); 
  display.println(" " + myWiFiManager->getConfigPortalSSID() + " ");
  display.setTextColor(SSD1306_WHITE, SSD1306_BLACK);
  display.println("\nPortal page will");
  display.print("open automatically.");
  display.display();
}

bool alarmMuted = false;
bool manualTrigger = false;
bool wasHazard = false;
bool hasHazard = false;
bool hasDanger = false;

// Define variables to track time intervals and non-blocking button states.
unsigned long lastUpdateMillis = 0;
bool lastResetState = HIGH;
bool lastManualState = HIGH;
unsigned long buttonPressStartTime = 0;

// Define safety limit parameters dynamically retrieved from database.
float threshold_1 = 250.0;
float threshold_2 = 400.0;
float threshold_2_temp = 45.0;
int upload_interval = 3;
int webAlarmState = 0;
int webFanState = 0;


// Trigger configuration mode, setup AP hotspot, and open local captive portal.
void startConfigMode() {
  String apSSID = getAPSSID();
  
  WiFiManager wm;
  wm.setAPCallback(configModeCallback);
  wm.setConfigPortalTimeout(180); 

  wm.setCustomHeadElement(R"rawhtml(
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Inter', system-ui, sans-serif;
    background: #f0f4f8;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px 16px;
  }

  .wrap, #main { all: unset; display: block; }

  form {
    background: #ffffff;
    border-radius: 20px;
    padding: 40px 32px 36px;
    max-width: 380px;
    width: 100%;
    box-shadow: 0 4px 24px rgba(0,0,0,0.09);
  }

  form::before {
    content: '';
    display: block;
    width: 56px; height: 56px;
    margin: 0 auto 20px;
    background: #1a73e8;
    border-radius: 16px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 12.55a11 11 0 0 1 14.08 0'/%3E%3Cpath d='M1.42 9a16 16 0 0 1 21.16 0'/%3E%3Cpath d='M8.53 16.11a6 6 0 0 1 6.95 0'/%3E%3Ccircle cx='12' cy='20' r='1'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: center;
    background-size: 28px 28px;
  }

  h1, h2, h3 {
    text-align: center;
    font-size: 20px;
    font-weight: 600;
    color: #1a1a2e;
    margin-bottom: 4px;
    letter-spacing: -0.3px;
  }

  h1 + p, h2 + p, h3 + p, .msg {
    text-align: center;
    font-size: 13.5px;
    color: #6b7280;
    margin-bottom: 28px;
    line-height: 1.5;
  }

  .navigation, br + a, a[href='/0wifi'], a[href='/info'], a[href='/close'], a[href='/update'] {
    display: none !important;
  }

  label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: #374151;
    margin-bottom: 6px;
  }

  input[type="text"],
  input[type="password"] {
    width: 100%;
    padding: 12px 14px;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    font-size: 15px;
    font-family: inherit;
    color: #1a1a2e;
    background: #f9fafb;
    outline: none;
    transition: border-color .2s, box-shadow .2s;
    margin-bottom: 18px;
  }
  input[type="text"]:focus,
  input[type="password"]:focus {
    border-color: #1a73e8;
    box-shadow: 0 0 0 3px rgba(26,115,232,0.12);
    background: #fff;
  }

  input[type="submit"] {
    width: 100%;
    padding: 13px;
    background: #1a73e8;
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    letter-spacing: 0.1px;
    transition: background .2s, transform .1s;
    margin-top: 4px;
  }
  input[type="submit"]:hover  { background: #1558c0; }
  input[type="submit"]:active { transform: scale(0.98); }

  form::after {
    content: 'Your credentials are sent directly to the device and never stored online.';
    display: block;
    text-align: center;
    font-size: 11.5px;
    color: #9ca3af;
    margin-top: 20px;
    line-height: 1.5;
  }
</style>
  )rawhtml");

  wm.setTitle("Smart Safety System");

  wm.setSaveConfigCallback([]() {
    Serial.println("WiFi Configuration Saved via Portal!");
    preferences.begin("wifi-creds", false);
    preferences.putString("ssid", WiFi.SSID());
    preferences.putString("pass", WiFi.psk());
    preferences.end();
  });
  
  wm.setWebServerCallback([&wm]() {
    wm.server->on("/", [&wm]() {
      String redirectHtml = "<html><head>"
                            "<meta http-equiv=\"refresh\" content=\"0;url=/wifi\">"
                            "</head><body>"
                            "<script>window.location.href='/wifi';</script>"
                            "<p>Redirecting to WiFi Configuration page...</p>"
                            "</body></html>";
      wm.server->send(200, "text/html", redirectHtml);
    });
  });

  if (!wm.startConfigPortal(apSSID.c_str())) {
    Serial.println("Portal timed out. Restarting...");
    display.clearDisplay();
    display.setCursor(0, 10);
    display.print("Setup Timeout.\nRestarting...");
    display.display();
    delay(3000);
    ESP.restart();
  }

  Serial.println("\n✨ Connected successfully!");
  display.clearDisplay();
  display.setCursor(0, 10);
  display.print("WiFi Connected!\nIP: ");
  display.print(WiFi.localIP());
  display.display();
  delay(2000);
  
  ESP.restart();
}

// Read saved credentials from non-volatile memory and connect to user's WiFi.
void connectWiFi() {
  
  preferences.begin("wifi-creds", true);
  String savedSSID = preferences.getString("ssid", ""); 
  String savedPass = preferences.getString("pass", "");
  preferences.end();

  if (savedSSID.length() == 0) {
    Serial.println("No saved WiFi credentials. Entering Setup...");
    display.clearDisplay();
    display.setCursor(0, 0);
    display.println("No WiFi Configured.");
    display.println("Entering Setup...");
    display.display();
    delay(2000);
    startConfigMode(); 
  }

  Serial.println("\n==========================================");
  Serial.print("Connecting WiFi SSID: "); Serial.println(savedSSID);
  Serial.print("WiFi Password:        "); Serial.println(savedPass);
  Serial.println("==========================================");

  display.clearDisplay();
  display.setCursor(0, 0);
  display.println("Connecting WiFi...");
  display.println(savedSSID);
  display.println("Pass: " + savedPass);
  display.display();

  WiFi.persistent(false);
  WiFi.disconnect(false); 
  WiFi.softAPdisconnect(true);
  delay(300);
  WiFi.mode(WIFI_STA);
  delay(100);

  WiFi.begin(savedSSID.c_str(), savedPass.c_str());

  unsigned long startAttempt = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - startAttempt < 60000) {
    delay(500);
    Serial.print(".");
    display.print(".");
    display.display();
  }

  int wifiStatus = WiFi.status();
  if (wifiStatus == WL_CONNECTED) {
    Serial.println("\n✨ Connected!");
    display.clearDisplay();
    display.setCursor(0, 0);
    display.println("WiFi Connected!");
    display.print("IP: ");
    display.println(WiFi.localIP());
    display.display();
    delay(2000);
  } else {
    
    Serial.print("\n❌ WiFi failed. Status code: "); 
    Serial.println(wifiStatus);
    if (wifiStatus == WL_NO_SSID_AVAIL) {
      Serial.println("Reason: SSID not found! Is the router 2.4GHz? Is it in range?");
    } else if (wifiStatus == WL_CONNECT_FAILED) {
      Serial.println("Reason: Connection failed! Check password spelling.");
    } else {
      Serial.println("Reason: Disconnected or Idle.");
    }
    
    display.clearDisplay();
    display.setCursor(0, 0);
    display.println("WiFi Failed!");
    display.print("Status Code: "); display.println(wifiStatus);
    display.println("Entering Setup...");
    display.display();
    delay(3000);
    startConfigMode(); 
  }
}

// Convert characters to HTML-safe parameters for network uploads.
String escapeParam(String val) {
  String escaped = "";
  for (int i = 0; i < val.length(); i++) {
    char c = val.charAt(i);
    if (c == ' ') {
      escaped += "%20";
    } else if (c == '&') {
      escaped += "%26";
    } else if (c == '=') {
      escaped += "%3D";
    } else if (c == '+') {
      escaped += "%2B";
    } else {
      escaped += c;
    }
  }
  return escaped;
}

// Retrieve key float parameters from database JSON responses.
float parseJsonFloat(String &json, String key) {
  int idx = json.indexOf("\"" + key + "\":");
  if (idx == -1) return -1;
  int startVal = idx + key.length() + 3;
  int endVal = json.indexOf(",", startVal);
  if (endVal == -1) endVal = json.indexOf("}", startVal);
  if (endVal == -1) return -1;
  return json.substring(startVal, endVal).toFloat();
}

int parseJsonInt(String &json, String key) {
  int idx = json.indexOf("\"" + key + "\":");
  if (idx == -1) return -1;
  int startVal = idx + key.length() + 3;
  int endVal = json.indexOf(",", startVal);
  if (endVal == -1) endVal = json.indexOf("}", startVal);
  if (endVal == -1) return -1;
  return json.substring(startVal, endVal).toInt();
}

String parseJsonString(String &json, String key) {
  int idx = json.indexOf("\"" + key + "\":\"");
  if (idx == -1) return "";
  int startVal = idx + key.length() + 4;
  int endVal = json.indexOf("\"", startVal);
  if (endVal == -1) return "";
  return json.substring(startVal, endVal);
}

// Download latest safety configuration and override commands from server.
void fetchSettings() {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(getSettingsUrl());
    int httpResponseCode = http.GET();
    if (httpResponseCode == 200) {
      String response = http.getString();
      Serial.println("Settings fetched successfully!");
      float t1 = parseJsonFloat(response, "threshold_1"); if (t1 != -1) threshold_1 = t1;
      float t2 = parseJsonFloat(response, "threshold_2"); if (t2 != -1) threshold_2 = t2;
      float t2t = parseJsonFloat(response, "threshold_2_temp"); if (t2t != -1) threshold_2_temp = t2t;
      int ui = parseJsonInt(response, "upload_interval"); if (ui != -1) upload_interval = ui;
      String actStatus = parseJsonString(response, "actuator_status");
      if (actStatus.length() >= 3) {
        webAlarmState = actStatus.charAt(0) - '0';
        webFanState   = actStatus.charAt(2) - '0';
      }
      int rm = parseJsonInt(response, "reboot_mode");
      if (rm == 1) {
        Serial.println("🔄 Remote reboot-into-config command received via settings poll!");
        display.clearDisplay();
        display.setCursor(0, 0);
        display.println("Remote config");
        display.println("request received.");
        display.println("Entering Setup...");
        display.display();
        
        preferences.begin("wifi-creds", false);
        preferences.clear();
        preferences.putBool("config-mode", true);
        preferences.end();

        WiFiManager wm;
        wm.resetSettings();

        delay(1000);
        ESP.restart();
      }
    }
    http.end();
  }
}

// Synchronize manual buzzer changes from ESP32 back to DB.
void setWebAlarm(int state) {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(controlUrl);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    String actionName = "alarm_off";
    if (state == 1) actionName = "alarm_on";
    else if (state == 2) actionName = "alarm_mute";
    String requestData = "device_id=" + String(deviceId) + "&action=" + actionName;
    http.POST(requestData);
    http.end();
  }
}

// Setup hardware pins, connect WiFi, and configure Web Server listeners.
void setup() {
  Serial.begin(115200);

  dht.begin();
  pinMode(FLAME_PIN, INPUT);
  pinMode(PIR_PIN, INPUT);
  pinMode(RESET_BTN_PIN, INPUT_PULLUP);
  pinMode(MANUAL_BTN_PIN, INPUT_PULLUP);
  pinMode(BUZZER_PIN, OUTPUT);
  pinMode(RELAY_PIN, OUTPUT);
  digitalWrite(BUZZER_PIN, LOW);
  digitalWrite(RELAY_PIN, LOW);

  if (!display.begin(SSD1306_SWITCHCAPVCC, 0x3C)) {
    Serial.println("OLED Failed");
  }
  display.clearDisplay();
  display.setTextColor(WHITE);
  display.setTextSize(1);

  preferences.begin("wifi-creds", false);
  bool forceConfig = preferences.getBool("config-mode", false);
  if (forceConfig) {
    preferences.putBool("config-mode", false); 
    preferences.end();
    display.setCursor(0, 0);
    display.println("Remote config");
    display.println("request received.");
    display.println("Entering Setup...");
    display.display();
    delay(1000);
    startConfigMode(); 
  }
  preferences.end();

  connectWiFi();
  fetchSettings();

  mainServer.on("/reboot-config", HTTP_POST, []() {
    mainServer.sendHeader("Access-Control-Allow-Origin", "*");
    mainServer.sendHeader("Access-Control-Allow-Methods", "POST, OPTIONS");
    mainServer.send(200, "text/plain", "ok");
    
    preferences.begin("wifi-creds", false);
    preferences.clear();
    preferences.putBool("config-mode", true); 
    preferences.end();

    WiFiManager wm;
    wm.resetSettings();
    
    delay(500);
    ESP.restart();
  });

  mainServer.on("/reboot-config", HTTP_OPTIONS, []() {
    mainServer.sendHeader("Access-Control-Allow-Origin", "*");
    mainServer.sendHeader("Access-Control-Allow-Methods", "POST, OPTIONS");
    mainServer.sendHeader("Access-Control-Allow-Headers", "Content-Type");
    mainServer.send(204);
  });

  mainServer.begin();
  Serial.println("Main server listening on port 8080");
}

// Loop: Handle controls, process inputs, read sensors, evaluate thresholds, and post logs.
void loop() {
  
  mainServer.handleClient();

  int resetPressed  = digitalRead(RESET_BTN_PIN);
  int manualPressed = digitalRead(MANUAL_BTN_PIN);

  // Check Button 1: Toggle mute state on short press, format Wi-Fi NVS on 3-second hold.
  if (resetPressed == LOW) {
    if (lastResetState == HIGH) {
      buttonPressStartTime = millis();
    }
    if (millis() - buttonPressStartTime > 3000) {
      Serial.println("⚠️ Button 1 held for 3 seconds! Resetting Wi-Fi memory...");
      display.clearDisplay();
      display.setCursor(0, 10);
      display.print("Wiping Wi-Fi...\nGoing to Setup Mode");
      display.display();
      delay(2000);
      
      WiFiManager wm;
      wm.resetSettings();
      
      preferences.begin("wifi-creds", false);
      preferences.clear();
      preferences.end();
      
      ESP.restart();
    }
  } 
  else if (resetPressed == HIGH && lastResetState == LOW) {
    if (millis() - buttonPressStartTime < 3000) {
      if (webAlarmState == 2) {
        Serial.println("🔊 Button 1: Unmuting alarm.");
        setWebAlarm(0);
        webAlarmState = 0;
      } else {
        Serial.println("🔕 Button 1: Muting alarm.");
        setWebAlarm(2);
        webAlarmState = 2;
      }
    }
  }
  lastResetState = resetPressed;

  // Check Button 2: Toggle manual override test alarm.
  if (manualPressed == LOW && lastManualState == HIGH) {
    if (webAlarmState == 1) {
      Serial.println("🔕 Button 2: Stopping manual alarm.");
      setWebAlarm(0);
      webAlarmState = 0;
    } else {
      Serial.println("🔔 Button 2: Starting manual alarm.");
      setWebAlarm(1);
      webAlarmState = 1;
    }
    delay(50);
  }
  lastManualState = manualPressed;
  manualTrigger = (webAlarmState == 1);

  // Core task loop: read parameters, compare warnings/danger levels, and upload payload.
  unsigned long currentMillis = millis();
  if (currentMillis - lastUpdateMillis >= (upload_interval * 1000)) {
    lastUpdateMillis = currentMillis;
    fetchSettings();

    float temp = dht.readTemperature();
    int gasValue = analogRead(MQ_PIN);
    int flameStatus = digitalRead(FLAME_PIN);
    int motionStatus = digitalRead(PIR_PIN);

    static float lastValidTemp = 28.0;
    if (isnan(temp) || temp > 80.0 || temp < -20.0) {
      temp = lastValidTemp;
    } else {
      lastValidTemp = temp;
    }

    hasDanger  = (gasValue > threshold_2 || temp > threshold_2_temp || flameStatus == 0);
    bool isWarning = (gasValue > threshold_1 && gasValue <= threshold_2);
    hasHazard = (hasDanger || isWarning);

    if (hasHazard && !wasHazard) {
      if (webAlarmState == 2) {
        Serial.println("🚨 New hazard! Resetting mute.");
        setWebAlarm(0);
        webAlarmState = 0;
      }
    } else if (!hasHazard && wasHazard) {
      if (webAlarmState == 2) {
        Serial.println("🟢 Hazard cleared. Resetting mute.");
        setWebAlarm(0);
        webAlarmState = 0;
      }
    }

    String systemStatus = "Safe";
    if (manualTrigger)        systemStatus = "Warning (Manual Test)";
    else if (hasDanger)        systemStatus = "Danger";
    else if (isWarning)       systemStatus = "Warning";
    else if (motionStatus == 1) systemStatus = "Warning (Motion)";
    else if (webAlarmState == 1 || webFanState == 1) systemStatus = "Manual Override";

    display.clearDisplay();
    display.setCursor(0, 0);
    display.print("SYS: "); display.println(systemStatus);
    display.print("Gas: "); display.print(gasValue); display.println(" ppm");
    display.print("Temp: "); display.print(temp, 1); display.println(" C");
    display.print("Flame: "); display.println(flameStatus == 0 ? "YES" : "NO");
    display.print("Motion: "); display.println(motionStatus == 1 ? "YES" : "NO");
    display.print("Muted: "); display.println(webAlarmState == 2 ? "YES" : "NO");
    display.print("Manual: "); display.println(manualTrigger ? "ON" : "OFF");
    display.display();

    if (WiFi.status() == WL_CONNECTED) {
      HTTPClient http;
      http.begin(serverUrl);
      http.addHeader("Content-Type", "application/x-www-form-urlencoded");
      String httpRequestData = String("device_id=") + String(deviceId)
                             + "&sensor_1=" + String(gasValue)
                             + "&sensor_2=" + String(temp)
                             + "&sensor_3=" + String(flameStatus == 0 ? 1 : 0)
                             + "&motion="   + String(motionStatus)
                             + "&status="   + escapeParam(systemStatus)
                             + "&device_ip=" + WiFi.localIP().toString()
                             + "&wifi_ssid=" + escapeParam(WiFi.SSID());
      http.POST(httpRequestData);
      http.end();
    }
    wasHazard = hasHazard;
  }

  // Set physical outputs: sound buzzer on hazard, start fan on danger level.
  bool buzzerOutput = false;
  if (webAlarmState == 1)      buzzerOutput = true;
  else if (webAlarmState == 2) buzzerOutput = false;
  else                         buzzerOutput = hasHazard;

  digitalWrite(BUZZER_PIN, buzzerOutput ? HIGH : LOW);
  digitalWrite(RELAY_PIN, (hasDanger || webFanState == 1 || manualTrigger) ? HIGH : LOW);
  delay(10);
}