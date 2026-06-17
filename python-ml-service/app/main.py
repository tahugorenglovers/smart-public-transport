from fastapi import FastAPI
from pydantic import BaseModel
import joblib
import os
import json
import asyncio
import pika

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
    try:
        connection = pika.BlockingConnection(pika.ConnectionParameters(host=os.getenv('RABBITMQ_HOST', 'localhost')))
        channel = connection.channel()

        channel.queue_declare(queue='driver.telemetry.updated', durable=True)

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
                
                if prediction == "dangerous":
                    payload = {
                        "bus_id": bus_id,
                        "behavior": "dangerous",
                        "severity": "high"
                    }
                    ch.basic_publish(
                        exchange='',
                        routing_key='driver.behavior.detected',
                        body=json.dumps(payload)
                    )
            ch.basic_ack(delivery_tag=method.delivery_tag)

        channel.basic_consume(queue='driver.telemetry.updated', on_message_callback=callback)
        print("[*] RabbitMQ Consumer started listening to driver.telemetry.updated...")
        channel.start_consuming()
    except Exception as e:
        print(f"[!] Gagal menyambungkan ke RabbitMQ: {e}")