#include <WiFi.h>
#include <PubSubClient.h>

// konfigurasi wifi (Wokwi pakai WiFi simulasi)
const char* ssid = "Wokwi-GUEST";
const char* password = "";

// konfigurasi mqtt broker
// Ganti dengan IP/domain broker Mosquitto
const char* mqtt_server = "103.147.92.135"; // ip server
const int   mqtt_port   = 1813;

// konfigurasi bus
const int BUS_ID = 1;

// Titik awal koordinat (contoh: area Bandung)
double currentLat = -6.9022;
double currentLng = 107.6058;

// Kecepatan saat ini
int currentSpeed = 40;

WiFiClient espClient;
PubSubClient client(espClient);

unsigned long lastPublish = 0;
const long publishInterval = 4000; // 4 detik (sesuai range 3-5 detik)

// setup wifi
void setupWifi() {
  Serial.print("Connecting to WiFi");
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nWiFi connected!");
  Serial.println(WiFi.localIP());
}

// reconnect mqtt jika putus
void reconnectMqtt() {
  while (!client.connected()) {
    Serial.print("Connecting to MQTT...");
    String clientId = "ESP32TrafficBus-" + String(BUS_ID);
    if (client.connect(clientId.c_str())) {
      Serial.println("connected!");
    } else {
      Serial.print("failed, rc=");
      Serial.print(client.state());
      Serial.println(" retry in 2 seconds");
      delay(2000);
    }
  }
}

// simulasi pergerakan bus
void simulateMovement() {
  // Koordinat bergerak sedikit setiap publish (simulasi jalan)
  currentLat += random(-10, 10) / 100000.0;
  currentLng += random(-10, 10) / 100000.0;

  // Speed random 20-70 km/h
  currentSpeed = random(20, 71);
}

// publish gps
void publishGPS() {
  String payload = "{";
  payload += "\"bus_id\":" + String(BUS_ID) + ",";
  payload += "\"latitude\":" + String(currentLat, 6) + ",";
  payload += "\"longitude\":" + String(currentLng, 6);
  payload += "}";

  client.publish("smarttransit/traffic/gps", payload.c_str());
  Serial.println("Published GPS: " + payload);
}

// publish speed
void publishSpeed() {
  String payload = "{";
  payload += "\"bus_id\":" + String(BUS_ID) + ",";
  payload += "\"speed\":" + String(currentSpeed);
  payload += "}";

  client.publish("smarttransit/traffic/speed", payload.c_str());
  Serial.println("Published Speed: " + payload);
}

// setup
void setup() {
  Serial.begin(115200);
  setupWifi();
  client.setServer(mqtt_server, mqtt_port);

  randomSeed(analogRead(0)); // seed random biar variatif
}

// loop
void loop() {
  if (!client.connected()) {
    reconnectMqtt();
  }
  client.loop();

  unsigned long now = millis();
  if (now - lastPublish > publishInterval) {
    lastPublish = now;

    simulateMovement();
    publishGPS();
    publishSpeed();
  }
}