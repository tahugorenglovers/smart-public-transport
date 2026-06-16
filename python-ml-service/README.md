# Python ML Service (FastAPI)

Servis ini bertanggung jawab untuk menangani seluruh logika Machine Learning pada sistem Smart Public Transport, termasuk prediksi kedatangan bus, prediksi lonjakan penumpang, dan deteksi anomali armada.

## Fitur & Endpoint API

### 1. Bus Arrival Prediction (ETA)
* **Endpoint:** POST /predict/eta
* **Fungsi:** Memprediksi sisa waktu kedatangan bus berdasarkan tingkat kemacetan dan jarak.
* **Request:**
```json
{
    "hour": 8,
    "day_of_week": 1,
    "traffic_level": 80,
    "distance_to_stop": 2.5
}
```
* **Response:**
```json
{
    "eta_minutes": 7
}
```

### 2. Passenger Surge Prediction
* **Endpoint:** POST /predict/passenger
* **Fungsi:** Memprediksi tingkat kepadatan penumpang di halte pada jam tertentu.
* **Request:** 
```json
{
    "stop_id": 3,
    "hour": 17,
    "day_of_week": 5
}
```
* **Response:**
```json
{
    "crowd_prediction": "HIGH"
}
```

### 3. Bus Anomaly Detection
* **Endpoint:** POST /detect/anomaly
* **Fungsi:** Mendeteksi jika ada bus yang berhenti terlalu lama di luar halte.
* **Request:** 
```json
{
    "bus_id": 1,
    "speed": 0,
    "stop_duration": 20
}
```
* **Response:**
```json
{
    "is_anomaly": true,
    "severity": "HIGH"
}
```
## 🛠️ Cara Menjalankan Secara Lokal

1. Masuk ke folder servis:
```bash
cd python-ml-service
```
2. Install semua library:
```bash
pip install -r requirements.txt
```
3. Jalankan server FastAPI:
```bash
python -m uvicorn app.main:app --reload
```
4. Buka Dokumentasi API (Swagger UI):
Akses URL berikut di browser untuk mencoba API: http://127.0.0.1:8000/docs