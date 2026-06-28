/*
 * SmartTransit — Environment Bus Simulator (Melva)
 *
 * Simulates three cabin sensors:
 *   1. Passenger Counter  → smarttransit/environment/passenger
 *   2. Temperature        → smarttransit/environment/temperature
 *   3. Air Quality (CO2)  → smarttransit/environment/air
 *
 * Alert thresholds (from spec):
 *   passenger_count > 40  → overcrowded
 *   temperature     > 32  → overheating
 *   co2_ppm         > 1000 → poor air quality
 *
 * Publishes every 5 seconds to broker.hivemq.com:1883
 * Mosquitto bridges these into the local Docker network automatically.
 *
 * Paste this into: https://wokwi.com
 * Board: ESP32
 * Libraries: PubSubClient (knolleary), ArduinoJson
 */

#include <WiFi.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>

// ── WiFi (Wokwi built-in simulation network) ─────────────────────────────────
const char* WIFI_SSID     = "Wokwi-GUEST";
const char* WIFI_PASSWORD = "";

// ── MQTT broker ───────────────────────────────────────────────────────────────
// HiveMQ public broker — Mosquitto bridges this into your local Docker network.
// Do NOT change this unless you have a public IP for your own broker.
const char* MQTT_SERVER = "103.147.92.135";
const int   MQTT_PORT   = 1813;

// ── Bus identity ──────────────────────────────────────────────────────────────
const int BUS_ID = 1;

// ── MQTT topics ───────────────────────────────────────────────────────────────
const char* TOPIC_PASSENGER   = "smarttransit/environment/passenger";
const char* TOPIC_TEMPERATURE = "smarttransit/environment/temperature";
const char* TOPIC_AIR         = "smarttransit/environment/air";

// ── Sensor state ──────────────────────────────────────────────────────────────
int   passengerCount = 20;   // starts at 20 passengers
float temperature    = 28.0; // starts at comfortable 28°C
int   co2Ppm         = 700;  // starts at normal CO2

// ── Publish interval ──────────────────────────────────────────────────────────
const long PUBLISH_INTERVAL_MS = 5000; // every 5 seconds
unsigned long lastPublish = 0;

WiFiClient   espClient;
PubSubClient mqtt(espClient);

// ── WiFi setup ────────────────────────────────────────────────────────────────
void setupWifi() {
  Serial.print("[WiFi] Connecting");
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\n[WiFi] Connected! IP: " + WiFi.localIP().toString());
}

// ── MQTT reconnect ────────────────────────────────────────────────────────────
void reconnectMqtt() {
  while (!mqtt.connected()) {
    Serial.print("[MQTT] Connecting...");
    String clientId = "ESP32EnvBus-" + String(BUS_ID) + "-" + String(random(1000));
    if (mqtt.connect(clientId.c_str())) {
      Serial.println(" connected!");
    } else {
      Serial.println(" failed (rc=" + String(mqtt.state()) + "), retry in 3s");
      delay(3000);
    }
  }
}

// ── Sensor simulation ─────────────────────────────────────────────────────────
void simulateSensors() {
  // Passenger count: random boarding/alighting each stop
  // Biased slightly upward to reach alert threshold sometimes
  int delta = random(-5, 8);
  passengerCount = constrain(passengerCount + delta, 0, 55);

  // Temperature: gradual drift with small noise
  // Occasionally spikes above 32°C threshold to trigger overcrowding alert
  float tempDrift = (random(0, 100) < 10) ? random(2, 5) : random(-10, 15) / 10.0;
  temperature = constrain(temperature + tempDrift, 24.0, 38.0);

  // CO2: rises when bus is crowded, drops when passengers leave
  // More passengers → faster CO2 rise
  int co2Delta = (passengerCount > 35) ? random(20, 80) : random(-30, 30);
  co2Ppm = constrain(co2Ppm + co2Delta, 400, 2200);
}

// ── Alert status helpers (for serial monitor readability) ─────────────────────
String passengerStatus() { return passengerCount > 40 ? " ⚠ OVERCROWDED" : ""; }
String temperatureStatus() { return temperature > 32.0 ? " ⚠ OVERHEATING" : ""; }
String co2Status() { return co2Ppm > 1000 ? " ⚠ POOR AIR QUALITY" : ""; }

// ── Publish all three sensor readings ─────────────────────────────────────────
void publishSensors() {
  StaticJsonDocument<128> doc;
  char buf[128];

  // 1. Passenger count
  doc.clear();
  doc["bus_id"]          = BUS_ID;
  doc["passenger_count"] = passengerCount;
  serializeJson(doc, buf);
  mqtt.publish(TOPIC_PASSENGER, buf);
  Serial.printf("[PUB] Passenger  → %s%s\n", buf, passengerStatus().c_str());

  // 2. Temperature
  doc.clear();
  doc["bus_id"]      = BUS_ID;
  doc["temperature"] = serialized(String(temperature, 1)); // 1 decimal place
  serializeJson(doc, buf);
  mqtt.publish(TOPIC_TEMPERATURE, buf);
  Serial.printf("[PUB] Temperature → %s%s\n", buf, temperatureStatus().c_str());

  // 3. Air quality (CO2)
  doc.clear();
  doc["bus_id"]  = BUS_ID;
  doc["co2_ppm"] = co2Ppm;
  serializeJson(doc, buf);
  mqtt.publish(TOPIC_AIR, buf);
  Serial.printf("[PUB] Air Quality → %s%s\n", buf, co2Status().c_str());

  Serial.println("---");
}

// ── Setup ─────────────────────────────────────────────────────────────────────
void setup() {
  Serial.begin(115200);
  randomSeed(analogRead(0));

  setupWifi();
  mqtt.setServer(MQTT_SERVER, MQTT_PORT);
  Serial.println("[Ready] Publishing every " + String(PUBLISH_INTERVAL_MS / 1000) + "s");
  Serial.println("[Thresholds] Passenger >40 | Temp >32°C | CO2 >1000ppm");
}

// ── Loop ──────────────────────────────────────────────────────────────────────
void loop() {
  if (!mqtt.connected()) reconnectMqtt();
  mqtt.loop();

  unsigned long now = millis();
  if (now - lastPublish >= PUBLISH_INTERVAL_MS) {
    lastPublish = now;
    simulateSensors();
    publishSensors();
  }
}
