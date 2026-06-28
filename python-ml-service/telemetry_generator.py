import requests
import time
import random
import json

API_URL = "http://localhost:8000/predict/driver-behavior"

def generate_telemetry(bus_id=1):
    is_aggressive = random.random() < 0.2

    if is_aggressive:
        speed = round(random.uniform(70.0, 105.0), 1)
        acceleration = round(random.uniform(3.5, 6.0), 1)
        brake_force = round(random.uniform(7.0, 10.0), 1)
        turn_rate = round(random.uniform(25.0, 45.0), 1)
        vibration = round(random.uniform(1.2, 2.5), 2)
    else:
        speed = round(random.uniform(30.0, 60.0), 1)
        acceleration = round(random.uniform(0.5, 2.5), 1)
        brake_force = round(random.uniform(0.0, 3.5), 1)
        turn_rate = round(random.uniform(0.0, 15.0), 1)
        vibration = round(random.uniform(0.1, 0.6), 2)

    return {
        "bus_id": bus_id,
        "speed": speed,
        "acceleration": acceleration,
        "brake_force": brake_force,
        "turn_rate": turn_rate,
        "vibration": vibration
    }

def run_generator():
    print(f"[*] Menjalankan Synthetic Telemetry Generator...")
    print(f"[*] Target Endpoint: {API_URL}")
    print("[*] Tekan CTRL+C untuk berhenti.\n")
    
    while True:
        payload = generate_telemetry(bus_id=1)
        
        try:
            response = requests.post(API_URL, json=payload)
            
            print(f"[+] Payload: {json.dumps(payload)}")
            print(f"    Status: {response.status_code} | Respon: {response.text}")
            
        except requests.exceptions.RequestException as e:
            print(f"[!] Gagal mengirim data. Pastikan API sudah nyala. Error: {e}")
            
        time.sleep(2)

if __name__ == "__main__":
    run_generator()