import pandas as pd
from sklearn.ensemble import RandomForestRegressor
import joblib
import os

def train_eta_model():
    print("Mulai melatih Model ETA...")
    
    # 1. Bikin data simulasi buat latihan AI
    data = {
        'hour': [8, 9, 17, 12, 19, 8, 17, 13],
        'day_of_week': [1, 1, 5, 2, 3, 2, 5, 4],
        'traffic_level': [80, 50, 90, 30, 70, 85, 95, 40],
        'distance_to_stop': [2.5, 2.5, 4.0, 1.2, 3.5, 2.0, 5.0, 1.5],
        'eta_minutes': [15, 10, 25, 5, 18, 14, 32, 7] 
    }
    df = pd.DataFrame(data)
    
    # 2. Memisahkan fitur dan target
    X = df[['hour', 'day_of_week', 'traffic_level', 'distance_to_stop']]
    y = df['eta_minutes']
    
    # 3. Train menggunakan algoritma Random Forest
    model = RandomForestRegressor(n_estimators=10, random_state=42)
    model.fit(X, y)
    
    # 4. Simpan model jadi file beneran
    os.makedirs('app/models', exist_ok=True)
    joblib.dump(model, 'app/models/eta_model.pkl')
    print("Model ETA berhasil disimpan di app/models/eta_model.pkl!")

if __name__ == "__main__":
    train_eta_model()