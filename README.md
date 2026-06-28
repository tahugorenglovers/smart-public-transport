# Smart Public Transport

**Pembangunan Perangkat Lunak Orientasi Service — Semester 4, S1 Informatika**

---

## Tim Pengembang

| Nama   | Tugas                                          |
|--------|------------------------------------------------|
| Aldin  | System Integration & DevOps                    |
| Najwa  | API Gateway & OAuth Server                     |
| Taraka | Citizen Service (PHP MVC)                      |
| Albi   | Traffic Service (PHP MVC) + IoT Simulator      |
| Melva  | Environment Service (PHP MVC) + IoT Simulator  |
| Paruk  | Python ML Service (FastAPI) + IoT Simulator    |

---

## Deskripsi Proyek

Kelompok Kita Membuat Smart City Smart Public Transport yang mengintegrasikan sensor iot dan machine learning microservice untuk memberikan informasi kepada operator, pengemudi, dan penumpang

---

## Arsitektur Sistem

```
Wokwi Simulators (ESP32)
   ├── Traffic Simulator      → smarttransit/traffic/gps + speed
   ├── Environment Simulator  → smarttransit/environment/passenger + temperature + air
   └── Driver Simulator       → smarttransit/driver/telemetry
          │
          ▼ MQTT (:1813)
       Mosquitto Broker
          │
          ▼ MQTT subscribe
       Node-RED (:1810)
          │
          ▼ HTTP POST (no JWT)
       API Gateway (:3010) — JWT verify, rate limiting, routing
          │
          ├──▶ OAuth Server     (:3012) — issue & validate JWT
          ├──▶ Citizen Service  (:8010) ──▶ MySQL ──▶ RabbitMQ
          ├──▶ Traffic Service  (:8011) ──▶ MySQL ──▶ RabbitMQ
          ├──▶ Environment Svc  (:8012) ──▶ MySQL ──▶ RabbitMQ
          └──▶ Python ML        (:5010)
                    │
                    ├── consumer: driver.telemetry.updated → classify behavior
                    └── publisher: driver.behavior.detected + bus.anomaly.detected
                                        │
                                        ▼ RabbitMQ consumers
                                  Citizen + Environment service
                                  simpan alert, notifikasi warga
```

---

## Teknologi

| Layer           | Teknologi                                                      |
|-----------------|----------------------------------------------------------------|
| API Gateway     | Express.js, http-proxy-middleware v3                           |
| Auth            | JWT (HS256), OAuth 2.0 (password, refresh, client_credentials) |
| PHP Services    | PHP 8.2, Slim 4, PDO, php-amqplib, firebase/php-jwt           |
| ML Service      | Python 3.11, FastAPI, scikit-learn, joblib, pika               |
| Database        | MySQL 8.0                                                      |
| Message Broker  | RabbitMQ (topic exchange: `smarttransit`)                      |
| IoT             | Mosquitto MQTT, Node-RED, Wokwi ESP32 simulators               |
| Containerisasi  | Docker, Docker Compose (14 container)                          |
| Orkestrasi      | Kubernetes (manifests di `k8s/`)                               |
| Monitoring      | Prometheus + Grafana                                           |

---

## Prerequisites

- Docker Desktop (Windows/Mac) atau Docker Engine + Docker Compose plugin (Linux)
- Git
- kubectl + minikube (untuk Kubernetes)

---

## Setup — Menjalankan Lokal

### 1. Clone repo

```bash
git clone <repo-url>
cd smart-public-transport
```

### 2. Buat file `.env`

```bash
cp .env.example .env
```

Nilai default di `.env.example` sudah bisa langsung dipakai untuk lokal. Tidak perlu mengubah apapun.

### 3. Jalankan seluruh sistem

```bash
docker compose up --build
```

Perintah ini akan build semua service, pull base image, dan menjalankan 14 container sekaligus. Pertama kali akan memakan waktu beberapa menit.

### 4. Tunggu hingga semua container healthy

```bash
docker compose ps
```

Tunggu sampai `mysql` dan `rabbitmq` statusnya `healthy`. Service lain akan otomatis start setelahnya.

### 5. Smoke test

```bash
curl http://localhost:3010/health   # status semua upstream
curl http://localhost:8010/health   # citizen
curl http://localhost:8011/health   # traffic
curl http://localhost:8012/health   # environment
curl http://localhost:5010/health   # python ML
```

---

## Setup IoT (Wokwi + Node-RED)

### Node-RED

1. Buka `http://localhost:1810`
2. Menu (☰) → **Import** → pilih `iot/nodered-flow.json`
3. Klik **Deploy**

### Wokwi Simulators

Buat tiga project ESP32 baru di [wokwi.com](https://wokwi.com), tambahkan library **PubSubClient** dan **ArduinoJson**, lalu paste masing-masing file `.ino`:

| Simulator | File | Topik MQTT |
|-----------|------|------------|
| Traffic | `iot/wokwi-traffic-simulator.ino` | `smarttransit/traffic/gps`, `smarttransit/traffic/speed` |
| Environment | `iot/wokwi-environment-simulator.ino` | `smarttransit/environment/passenger`, `temperature`, `air` |
| Driver Behavior | `iot/wokwi-driver-simulator.ino` | `smarttransit/driver/telemetry` |

Semua simulator sudah dikonfigurasi untuk publish ke `103.147.92.135:1813` (server dosen). Untuk lokal, ganti `MQTT_SERVER` ke `localhost` dan `MQTT_PORT` ke `1883`.

---

## Endpoint

Import `postman_collection.json` ke Postman untuk koleksi lengkap dengan env variable.

Base URL: `{{baseUrl}}` = `http://localhost:3010` (lokal) atau `http://103.147.92.135:3010` (server)
OAuth URL: `{{oauthUrl}}` = `http://localhost:3012` (lokal) atau `http://103.147.92.135:3012` (server)

### OAuth & Auth (langsung ke OAuth server, tanpa gateway)

| Method | Path | Keterangan |
|--------|------|------------|
| POST | `{{oauthUrl}}/token` | Login, dapatkan access + refresh token |
| POST | `{{oauthUrl}}/introspect` | Validasi token |
| POST | `{{oauthUrl}}/revoke` | Revoke token |
| GET | `{{oauthUrl}}/health` | Health check OAuth server |

### Gateway (semua butuh `Authorization: Bearer <token>` kecuali yang ditandai)

**Citizen Service**

| Method | Path | Keterangan |
|--------|------|------------|
| GET | `/api/reports` | Semua laporan |
| POST | `/api/reports` | Submit laporan baru |
| GET | `/api/reports/1` | Detail laporan |
| GET | `/api/notifications` | Notifikasi warga |
| POST | `/api/notifications/1/read` | Tandai notifikasi dibaca |
| GET | `/api/tickets` | Daftar tiket user |
| POST | `/api/tickets` | Beli tiket baru |

**Traffic Service**

| Method | Path | Keterangan |
|--------|------|------------|
| GET | `/api/traffic/current` | Posisi bus real-time |
| GET | `/api/traffic/routes` | Daftar rute |
| GET | `/api/traffic/eta/1` | ETA bus untuk rute 1 |
| POST | `/api/traffic/telemetry` | Post telemetri pengemudi |
| POST | `/internal/traffic/location` | IoT GPS update (no auth) |

**Environment Service**

| Method | Path | Keterangan |
|--------|------|------------|
| POST | `/api/environment/passenger` | Post jumlah penumpang |
| POST | `/api/environment/temperature` | Post suhu kabin |
| POST | `/api/environment/air` | Post kualitas udara (CO2) |
| GET | `/api/environment/alerts` | Daftar alert lingkungan |

**Python ML Service**

| Method | Path | Keterangan |
|--------|------|------------|
| POST | `/predict/traffic` | Prediksi kemacetan |
| POST | `/predict/eta` | Prediksi ETA |
| POST | `/predict/passenger` | Prediksi kepadatan penumpang |
| POST | `/predict/driver-behavior` | Klasifikasi perilaku pengemudi |
| POST | `/detect/anomaly` | Deteksi anomali bus → publish RabbitMQ |

**System**

| Method | Path | Keterangan |
|--------|------|------------|
| GET | `/health` | Status semua upstream (no auth) |

---

## RabbitMQ Events

Exchange: `smarttransit` (type: `topic`, durable: `true`)
Dashboard: `http://localhost:15612` — login `guest` / `guest`

| Routing Key | Publisher | Consumer | Payload |
|-------------|-----------|----------|---------|
| `driver.telemetry.updated` | Traffic Service | Python ML | `{bus_id, speed, acceleration, brake_force, turn_rate, vibration}` |
| `driver.behavior.detected` | Python ML | Citizen consumer | `{bus_id, behavior, severity}` |
| `bus.anomaly.detected` | Python ML | Environment consumer | `{bus_id, severity, message}` |
| `bus.overcrowded` | Environment Service | — | `{bus_id, passenger_count, status}` |
| `bus.temperature.alert` | Environment Service | — | `{bus_id, temperature, status}` |
| `environment.updated` | Environment Service | — | `{bus_id, passenger_count, temperature, combined_severity}` |

---

## Demo Skenario

### S1 — IoT Data Ingestion

```bash
# Jalankan Wokwi Traffic Simulator → lihat Node-RED debug panel
# Cek data masuk ke DB:
docker exec smarttransit-mysql mysql -uroot -proot smarttransit \
  -e "SELECT * FROM traffic_bus_locations ORDER BY recorded_at DESC LIMIT 5;"
```

### S2 — Citizen Login & Report

```bash
# Step 1: Login
curl -X POST http://localhost:3012/token \
  -H "Content-Type: application/json" \
  -d '{"grant_type":"password","username":"test@test.com","password":"password123"}'

# Step 2: Submit laporan (gunakan token dari step 1)
curl -X POST http://localhost:3010/api/reports \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"category":"lainnya","description":"Bus sangat terlambat","stop_id":1}'
```

### S3 — ML Real-time Prediction + Rate Limit

```bash
# Kirim prediksi (ulangi 11x untuk trigger rate limit 429)
curl -X POST http://localhost:3010/predict/traffic \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"route_id":1,"hour":8,"day_of_week":1,"current_speed":10,"vehicle_count":50}'
```

### S4 — Docker Full Stack

```bash
docker compose up --build
docker compose ps          # semua healthy
curl http://localhost:3010/health
```

### S5 — Kubernetes

Lihat `k8s/README.md` untuk panduan deploy dan demo HPA scaling.

### S6 — Anomaly Alert Flow

```bash
# Kirim telemetri ekstrem → trigger ML anomaly → RabbitMQ → alert tersimpan
curl -X POST http://localhost:3010/api/traffic/telemetry \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"bus_id":1,"speed":110,"acceleration":7.5,"brake_force":9.8,"turn_rate":45,"vibration":3.2}'

# Lihat log consumer
docker compose logs citizen-driver-alert-consumer --tail=10
docker compose logs environment-consumer --tail=10
```

---

## Kubernetes Deployment

```bash
# Build images
docker compose build

# (minikube) Load ke cluster
eval $(minikube docker-env)
docker compose build   # rebuild dalam context minikube

# Deploy semua manifest
kubectl apply -f k8s/

# Monitor
kubectl get pods -n smarttransit -w
kubectl get hpa -n smarttransit
```

Lihat `k8s/README.md` untuk instruksi lengkap.

---

## Struktur Folder

```
.
├── express-gateway/         # API Gateway (Express.js)
├── oauth-server/            # OAuth 2.0 Server (Express.js)
├── php-citizen/             # Citizen Service (PHP 8.2 + Slim 4)
├── php-traffic/             # Traffic Service (PHP 8.2 + Slim 4)
├── php-environment/         # Environment Service (PHP 8.2 + Slim 4)
├── python-ml-service/       # ML Service (FastAPI + scikit-learn)
├── database/
│   ├── schema.sql           # DDL lengkap (18 tabel)
│   └── seed.sql             # Data dummy (355+ rows)
├── iot/
│   ├── nodered-flow.json                # Import ke Node-RED
│   ├── wokwi-traffic-simulator.ino      # Simulator GPS + Speed (Albi)
│   ├── wokwi-environment-simulator.ino  # Simulator Cabin Sensors (Melva)
│   ├── wokwi-driver-simulator.ino       # Simulator Driver Behavior (Paruk)
│   └── mosquitto/config/mosquitto.conf
├── monitoring/
│   ├── prometheus.yml                   # Scrape config
│   └── grafana/provisioning/            # Auto-load datasource + dashboard
├── k8s/                     # Kubernetes manifests (12 file)
├── docs/                    # Diagram arsitektur
├── postman_collection.json  # 38 endpoint, siap import
├── docker-compose.yml       # Satu perintah = 14 container running
└── .env.example             # Template environment variables
```

