from fastapi import FastAPI
from pydantic import BaseModel

app = FastAPI(title="Smart Transit - Python ML Service")

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
    # Rumus simulasi sementara sebelum ada model .pkl asli
    base_eta = data.distance_to_stop * 4
    if data.traffic_level > 70:
        base_eta += 8
    return {"eta_minutes": int(base_eta)}

# --- MODEL 2: PASSENGER SURGE PREDICTION ---
class PassengerRequest(BaseModel):
    stop_id: int
    hour: int
    day_of_week: int

@app.post("/predict/passenger")
def predict_passenger(data: PassengerRequest):
    # Simulasi jam sibuk pulang kerja (jam 16-19) dan akhir pekan
    if 16 <= data.hour <= 19 or data.day_of_week >= 5:
        prediction = "HIGH"
    else:
        prediction = "LOW"
    return {"crowd_prediction": prediction}

# --- MODEL 3: ANOMALY DETECTION ---
class AnomalyRequest(BaseModel):
    bus_id: int
    speed: float
    stop_duration: int

@app.post("/detect/anomaly")
def detect_anomaly(data: AnomalyRequest):
    # Simulasi: Jika bis berhenti (speed 0) lebih dari 15 menit
    if data.speed == 0 and data.stop_duration > 15:
        is_anomaly = True
        severity = "HIGH"
    else:
        is_anomaly = False
        severity = "NONE"
    return {"is_anomaly": is_anomaly, "severity": severity}