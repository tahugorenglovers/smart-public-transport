import pandas as pd
import random
from sklearn.ensemble import RandomForestRegressor, RandomForestClassifier
from sklearn.model_selection import train_test_split
from sklearn.metrics import accuracy_score, precision_score, recall_score, confusion_matrix
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

# 4. TRAINING MODEL 4: DRIVER BEHAVIOR DETECTION 
def train_driver_behavior_model():
    print("\nMenggenerate data (~1000 row) dan melatih Model 4 (Driver Behavior)...")
    
    rows = []
    for _ in range(1000):
        speed = random.randint(10, 110)         
        acceleration = round(random.uniform(0.1, 8.0), 2)  
        brake_force = round(random.uniform(0.1, 10.0), 2) 
        turn_rate = random.randint(1, 45)       
        vibration = round(random.uniform(0.0, 2.5), 2)    
        
        if speed > 80 or acceleration > 5.5 or brake_force > 7.5 or turn_rate > 35:
            label = "dangerous"
        elif speed > 65 or acceleration > 4.0 or brake_force > 5.5 or turn_rate > 25 or vibration > 1.5:
            label = "aggressive"
        elif speed > 40 or acceleration > 2.0 or brake_force > 3.0 or turn_rate > 12 or vibration > 0.6:
            label = "normal"
        else:
            label = "safe"
            
        rows.append([speed, acceleration, brake_force, turn_rate, vibration, label])
        
    df = pd.DataFrame(rows, columns=['speed', 'acceleration', 'brake_force', 'turn_rate', 'vibration', 'label'])
    
    X = df[['speed', 'acceleration', 'brake_force', 'turn_rate', 'vibration']]
    y = df['label']
    
    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)
    
    model = RandomForestClassifier(n_estimators=100, random_state=42)
    model.fit(X_train, y_train)
    
    y_pred = model.predict(X_test)
    
    acc = accuracy_score(y_test, y_pred)
    prec = precision_score(y_test, y_pred, average='weighted')
    rec = recall_score(y_test, y_pred, average='weighted')
    cm = confusion_matrix(y_test, y_pred)
    
    print("\n=== HASIL EVALUASI MODEL DRIVER BEHAVIOR ===")
    print(f"Accuracy  : {acc:.4f}")
    print(f"Precision : {prec:.4f}")
    print(f"Recall    : {rec:.4f}")
    print("Confusion Matrix:")
    print(cm)
    print("============================================\n")
    
    joblib.dump(model, 'app/models/driver_behavior_model.pkl')
    print("-> Sukses: 'app/models/driver_behavior_model.pkl' berhasil disimpan!")

if __name__ == "__main__":
    print("=== Memulai Proses Training 3 Model ML ===")
    train_eta_model()
    train_passenger_model()
    train_anomaly_model()
    train_driver_behavior_model()
    print("=== Semua Model Selesai Dilatih! ===")