from fastapi import FastAPI
from pydantic import BaseModel
import joblib
import os
import json
import asyncio
import pika
import time

app = FastAPI(title="Smart Transit - Python ML Service")

ETA_MODEL_PATH = "app/models/eta_model.pkl"
PASSENGER_MODEL_PATH = "app/models/passenger_model.pkl"
ANOMALY_MODEL_PATH = "app/models/anomaly_model.pkl"
DRIVER_MODEL_PATH = "app/models/driver_behavior_model.pkl"

@app.get("/health")
def health_check():
    return {"status": "healthy"}

# --- MODEL 1: BUS ARRIVAL PREDICTION (ETA) ---
class ETARequest(BaseModel):
    hour: int
    day_of_week: int
    traffic_level: int
    distance_to_stop: float

@app.post("/predict/eta")
def predict_eta(data: ETARequest):
    if os.path.exists(ETA_MODEL_PATH):
        model = joblib.load(ETA_MODEL_PATH)
        input_data = [[data.hour, data.day_of_week, data.traffic_level, data.distance_to_stop]]
        prediction = model.predict(input_data)
        return {"eta_minutes": int(prediction[0])}
    return {"eta_minutes": 10}

# --- MODEL 2: PASSENGER SURGE PREDICTION ---
class PassengerRequest(BaseModel):
    stop_id: int
    hour: int
    day_of_week: int

@app.post("/predict/passenger")
def predict_passenger(data: PassengerRequest):
    if os.path.exists(PASSENGER_MODEL_PATH):
        model = joblib.load(PASSENGER_MODEL_PATH)
        input_data = [[data.stop_id, data.hour, data.day_of_week]]
        prediction = model.predict(input_data)
        return {"crowd_prediction": str(prediction[0])}
    return {"crowd_prediction": "LOW"}

# --- /predict/traffic (S3 Demo Scenario) ---
# The spec requires POST /predict/traffic for the S3 demo.
# This endpoint takes current traffic conditions and predicts
# the congestion level (LOW / MEDIUM / HIGH / CRITICAL).
# Uses the same features as the ETA model which was trained on
# traffic_level, hour, and distance data.
class TrafficPredictionRequest(BaseModel):
    route_id: int
    hour: int
    day_of_week: int
    current_speed: float          # km/h — lower = more congested
    vehicle_count: int            # number of vehicles on route

@app.post("/predict/traffic")
def predict_traffic(data: TrafficPredictionRequest):
    # Derive a traffic_level score (0-100) from current conditions
    # so we can reuse the trained ETA model's traffic_level feature
    speed_factor   = max(0, min(100, int((1 - data.current_speed / 80) * 100)))
    vehicle_factor = min(100, data.vehicle_count * 2)
    traffic_level  = int((speed_factor + vehicle_factor) / 2)

    # Predict ETA using existing model (traffic_level drives the output)
    eta_minutes = 10  # default if model not trained yet
    if os.path.exists(ETA_MODEL_PATH):
        model      = joblib.load(ETA_MODEL_PATH)
        prediction = model.predict([[data.hour, data.day_of_week, traffic_level, 5.0]])
        eta_minutes = int(prediction[0])

    # Map traffic_level to a human-readable congestion label
    if traffic_level >= 75:
        congestion = "CRITICAL"
    elif traffic_level >= 50:
        congestion = "HIGH"
    elif traffic_level >= 25:
        congestion = "MEDIUM"
    else:
        congestion = "LOW"

    return {
        "route_id":        data.route_id,
        "congestion_level": congestion,
        "traffic_score":   traffic_level,
        "estimated_delay": eta_minutes,
        "recommendation":  "Gunakan rute alternatif" if traffic_level >= 50 else "Rute aman dilalui"
    }

# --- MODEL 3: ANOMALY DETECTION ---
class AnomalyRequest(BaseModel):
    bus_id: int
    speed: float
    stop_duration: int

@app.post("/detect/anomaly")
def detect_anomaly(data: AnomalyRequest):
    if os.path.exists(ANOMALY_MODEL_PATH):
        model = joblib.load(ANOMALY_MODEL_PATH)
        input_data = [[data.bus_id, data.speed, data.stop_duration]]
        prediction = model.predict(input_data)
        
        is_anomaly = True if prediction[0] == 1 else False
        severity = "HIGH" if is_anomaly else "NONE"
        
        return {"is_anomaly": is_anomaly, "severity": severity}
    return {"is_anomaly": False, "severity": "NONE"}

# --- MODEL 4: DRIVER BEHAVIOR DETECTION  ---
class DriverBehaviorRequest(BaseModel):
    speed: float
    acceleration: float
    brake_force: float
    turn_rate: float
    vibration: float

@app.post("/predict/driver-behavior")
def predict_driver_behavior(data: DriverBehaviorRequest):
    if os.path.exists(DRIVER_MODEL_PATH):
        model = joblib.load(DRIVER_MODEL_PATH)
        input_data = [[data.speed, data.acceleration, data.brake_force, data.turn_rate, data.vibration]]
        
        prediction = model.predict(input_data)[0]
        
        confidence = 0.95 
        
        return {"behavior": str(prediction), "confidence": confidence}
    
    return {"behavior": "normal", "confidence": 1.0}

@app.on_event("startup")
async def startup_event():
    loop = asyncio.get_event_loop()
    loop.run_in_executor(None, start_rabbitmq_consumer)

def start_rabbitmq_consumer():
    max_retries = 10
    for attempt in range(1, max_retries + 1):
        try:
            connection = pika.BlockingConnection(pika.ConnectionParameters(host=os.getenv('RABBITMQ_HOST', 'localhost')))
            channel = connection.channel()

            # NOTE: traffic-service publishes 'driver.telemetry.updated' to the
            # 'smarttransit' topic exchange (see php-traffic/app/Config/RabbitMQ.php),
            # not to the default exchange. A queue only receives messages from a
            # topic exchange if it's explicitly bound to it - just declaring a
            # queue with a matching name does nothing on its own.
            channel.exchange_declare(exchange='smarttransit', exchange_type='topic', durable=True)
            channel.queue_declare(queue='driver.telemetry.updated', durable=True)
            channel.queue_bind(exchange='smarttransit', queue='driver.telemetry.updated', routing_key='driver.telemetry.updated')

            def callback(ch, method, properties, body):
                data = json.loads(body)

                speed = data.get("speed", 0)
                acceleration = data.get("acceleration", 0)
                brake_force = data.get("brake_force", 0)
                turn_rate = data.get("turn_rate", 0)
                vibration = data.get("vibration", 0)
                bus_id = data.get("bus_id", 1)

                if os.path.exists(DRIVER_MODEL_PATH):
                    model = joblib.load(DRIVER_MODEL_PATH)
                    prediction = model.predict([[speed, acceleration, brake_force, turn_rate, vibration]])[0]

                    # citizen-service's consumer treats both 'dangerous' and
                    # 'aggressive' as alert-worthy, so publish for both.
                    if prediction in ("dangerous", "aggressive"):
                        payload = {
                            "bus_id": bus_id,
                            "behavior": str(prediction),
                            "severity": "high" if prediction == "dangerous" else "medium"
                        }
                        # Publish through the same topic exchange the rest of the
                        # platform uses, rather than the default exchange (which
                        # only worked by coincidence if a queue happened to
                        # already exist with this exact name).
                        ch.basic_publish(
                            exchange='smarttransit',
                            routing_key='driver.behavior.detected',
                            body=json.dumps(payload)
                        )
                ch.basic_ack(delivery_tag=method.delivery_tag)

            channel.basic_consume(queue='driver.telemetry.updated', on_message_callback=callback)
            print("[*] RabbitMQ Consumer started listening to driver.telemetry.updated...")
            channel.start_consuming()
            break
        except Exception as e:
            print(f"[!] Gagal menyambungkan ke RabbitMQ (attempt {attempt}/{max_retries}): {e}")
            time.sleep(5)
