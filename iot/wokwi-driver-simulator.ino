/*
 * SmartTransit — Driver Behavior Simulator (Paruk)
 *
 * Publishes sensor telemetry to: smarttransit/driver/telemetry
 * Broker: broker.hivemq.com (same as other Wokwi simulators)
 *
 * Libraries needed: PubSubClient, ArduinoJson
 */

#include <WiFi.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>

const char* WIFI_SSID     = "Wokwi-GUEST";
const char* WIFI_PASSWORD = "";
const char* MQTT_SERVER   = "103.147.92.135";
const int   MQTT_PORT     = 1813;
const char* TOPIC         = "smarttransit/driver/telemetry";
const int   BUS_ID        = 1;

const long PUBLISH_INTERVAL_MS = 3000;
unsigned long lastPublish = 0;

WiFiClient   espClient;
PubSubClient mqtt(espClient);

void setupWifi() {
    Serial.print("[WiFi] Connecting");
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    while (WiFi.status() != WL_CONNECTED) {
        delay(500);
        Serial.print(".");
    }
    Serial.println("\n[WiFi] Connected!");
}

void reconnectMqtt() {
    while (!mqtt.connected()) {
        Serial.print("[MQTT] Connecting...");
        String clientId = "ESP32Driver-" + String(random(9999));
        if (mqtt.connect(clientId.c_str())) {
            Serial.println(" connected!");
        } else {
            Serial.println(" failed, retry in 3s");
            delay(3000);
        }
    }
}

void publishTelemetry() {
    float speed, acceleration, brake_force, turn_rate, vibration;
    String label;

    // Paruk's original simulation logic
    if (random(0, 100) < 20) {
        // Aggressive / dangerous (~20% of readings)
        speed        = random(800, 1200) / 10.0;
        acceleration = random(40, 70)    / 10.0;
        brake_force  = random(60, 100)   / 10.0;
        turn_rate    = random(300, 500)  / 10.0;
        vibration    = random(20, 40)    / 10.0;
        label        = "AGGRESSIVE/DANGEROUS";
    } else {
        // Safe / normal (~80% of readings)
        speed        = random(300, 600) / 10.0;
        acceleration = random(5, 25)    / 10.0;
        brake_force  = random(0, 35)    / 10.0;
        turn_rate    = random(0, 150)   / 10.0;
        vibration    = random(1, 6)     / 10.0;
        label        = "safe/normal";
    }

    StaticJsonDocument<256> doc;
    doc["bus_id"]       = BUS_ID;
    doc["speed"]        = speed;
    doc["acceleration"] = acceleration;
    doc["brake_force"]  = brake_force;
    doc["turn_rate"]    = turn_rate;
    doc["vibration"]    = vibration;

    char buf[256];
    serializeJson(doc, buf);

    mqtt.publish(TOPIC, buf);
    Serial.println("[PUB] " + String(buf) + "  [" + label + "]");
}

void setup() {
    Serial.begin(115200);
    randomSeed(analogRead(0));
    setupWifi();
    mqtt.setServer(MQTT_SERVER, MQTT_PORT);
    Serial.println("[Ready] Publishing every 3s to " + String(TOPIC));
}

void loop() {
    if (!mqtt.connected()) reconnectMqtt();
    mqtt.loop();

    unsigned long now = millis();
    if (now - lastPublish >= PUBLISH_INTERVAL_MS) {
        lastPublish = now;
        publishTelemetry();
    }
}
