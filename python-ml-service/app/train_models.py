import pandas as pd
import random
from sklearn.ensemble import RandomForestRegressor, RandomForestClassifier
import joblib
import os

os.makedirs('app/models', exist_ok=True)

# 1. TRAINING MODEL 1: BUS ARRIVAL (ETA)
def train_eta_model():
    print("Menggenerate data dan melatih Model 1 (ETA)...")
    
    rows = []
    for _ in range(500):
        hour = random.randint(0, 23)
        day = random.randint(1, 7)
        traffic = random.randint(10, 100)
        distance = round(random.uniform(0.5, 15.0), 2)
        
        base_time = distance * 3
        if traffic > 60:
            base_time += (traffic / 10) * 2
        if hour in [7, 8, 9, 16, 17, 18]:
            base_time += 5
            
        rows.append([hour, day, traffic, distance, int(base_time)])
        
    df = pd.DataFrame(rows, columns=['hour', 'day_of_week', 'traffic_level', 'distance_to_stop', 'eta_minutes'])
    
    X = df[['hour', 'day_of_week', 'traffic_level', 'distance_to_stop']]
    y = df['eta_minutes']
    
    model = RandomForestRegressor(n_estimators=50, random_state=42)
    model.fit(X, y)
    
    joblib.dump(model, 'app/models/eta_model.pkl')
    print("-> Sukses: 'app/models/eta_model.pkl' berhasil diperbarui!")

# 2. TRAINING MODEL 2: PASSENGER SURGE
def train_passenger_model():
    print("Menggenerate data dan melatih Model 2 (Passenger Surge)...")
    
    rows = []
    for _ in range(500):
        stop_id = random.randint(1, 10)
        hour = random.randint(0, 23)
        day = random.randint(1, 7)
        
        if stop_id in [1, 2, 3] and (16 <= hour <= 19) and day <= 5:
            crowd = "HIGH"
        elif (7 <= hour <= 9) and day <= 5:
            crowd = "HIGH"
        else:
            crowd = "LOW"
            
        rows.append([stop_id, hour, day, crowd])
        
    df = pd.DataFrame(rows, columns=['stop_id', 'hour', 'day_of_week', 'crowd_prediction'])
    
    X = df[['stop_id', 'hour', 'day_of_week']]
    y = df['crowd_prediction']
    
    model = RandomForestClassifier(n_estimators=50, random_state=42)
    model.fit(X, y)
    
    joblib.dump(model, 'app/models/passenger_model.pkl')
    print("-> Sukses: 'app/models/passenger_model.pkl' berhasil disimpan!")

# 3. TRAINING MODEL 3: ANOMALY DETECTION
def train_anomaly_model():
    print("Menggenerate data dan melatih Model 3 (Anomaly Detection)...")
    
    rows = []
    for _ in range(500):
        bus_id = random.randint(1, 20)
        speed = random.randint(0, 60)
        stop_duration = random.randint(0, 40)
        
        if speed == 0 and stop_duration > 15:
            is_anomaly = 1 
        else:
            is_anomaly = 0 
            
        rows.append([bus_id, speed, stop_duration, is_anomaly])
        
    df = pd.DataFrame(rows, columns=['bus_id', 'speed', 'stop_duration', 'is_anomaly'])
    
    X = df[['bus_id', 'speed', 'stop_duration']]
    y = df['is_anomaly']
    
    model = RandomForestClassifier(n_estimators=50, random_state=42)
    model.fit(X, y)
    
    joblib.dump(model, 'app/models/anomaly_model.pkl')
    print("-> Sukses: 'app/models/anomaly_model.pkl' berhasil disimpan!")


if __name__ == "__main__":
    print("=== Memulai Proses Training 3 Model ML ===")
    train_eta_model()
    train_passenger_model()
    train_anomaly_model()
    print("=== Semua Model Selesai Dilatih! ===")