from fastapi import FastAPI
from pydantic import BaseModel
import joblib
import os

app = FastAPI(title="Smart Transit - Python ML Service")

ETA_MODEL_PATH = "app/models/eta_model.pkl"
PASSENGER_MODEL_PATH = "app/models/passenger_model.pkl"
ANOMALY_MODEL_PATH = "app/models/anomaly_model.pkl"

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