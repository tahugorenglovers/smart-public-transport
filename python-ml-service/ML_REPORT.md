# ML Report — Smart Public Transport Platform

**Python ML Service | FastAPI + scikit-learn**
**Mata Kuliah: Pembangunan Perangkat Lunak Orientasi Service — Semester VI**

---

## 1. Pendahuluan

Laporan ini mendokumentasikan seluruh proses pengembangan machine learning untuk platform SmartTransit, mencakup pembuatan dataset, eksplorasi data (EDA), preprocessing, pelatihan model, dan evaluasi performa. Platform menggunakan **4 model ML** yang diintegrasikan melalui Python FastAPI dan RabbitMQ.

### 1.1 Daftar Model

| # | Model | Tipe | Tujuan |
|---|-------|------|--------|
| 1 | ETA Prediction | Regresi | Memprediksi waktu kedatangan bus (menit) |
| 2 | Passenger Surge | Klasifikasi Biner | Memprediksi kepadatan penumpang (HIGH/LOW) |
| 3 | Anomaly Detection | Klasifikasi Biner | Mendeteksi bus yang berhenti abnormal |
| 4 | Driver Behavior | Klasifikasi Multi-kelas | Mengklasifikasi perilaku pengemudi (4 kelas) |

### 1.2 Teknologi

- **Language:** Python 3.11
- **Framework:** FastAPI (serving), scikit-learn (training)
- **Model Format:** joblib `.pkl`
- **Dataset:** Synthetic data yang di-generate dari domain knowledge transportasi publik

---

## 2. Dataset

Karena data historis armada bus nyata tidak tersedia, seluruh dataset di-generate secara sintetis menggunakan aturan domain yang realistis. Pendekatan ini umum digunakan dalam riset transportasi cerdas ketika sistem baru belum beroperasi cukup lama untuk mengumpulkan data historis.

### 2.1 Model 1 — ETA Dataset

**Ukuran:** 500 baris  
**Fitur:**

| Fitur | Tipe | Range | Keterangan |
|-------|------|-------|-----------|
| `hour` | Integer | 0–23 | Jam dalam sehari |
| `day_of_week` | Integer | 1–7 | Hari (1=Senin, 7=Minggu) |
| `traffic_level` | Integer | 10–100 | Kepadatan lalu lintas (%) |
| `distance_to_stop` | Float | 0.5–15.0 km | Jarak ke halte tujuan |

**Target:** `eta_minutes` (Integer) — waktu tempuh dalam menit

**Aturan generasi:**
- `eta_minutes = distance × 3` (baseline)
- +20% jika `traffic_level > 60`
- +5 menit jika jam sibuk (07–09, 16–18)

**Statistik Target:**

| Statistik | Nilai |
|-----------|-------|
| Min | 1 menit |
| Max | 67 menit |
| Mean | 32.51 menit |

---

### 2.2 Model 2 — Passenger Surge Dataset

**Ukuran:** 500 baris  
**Fitur:**

| Fitur | Tipe | Range | Keterangan |
|-------|------|-------|-----------|
| `stop_id` | Integer | 1–10 | ID halte bus |
| `hour` | Integer | 0–23 | Jam dalam sehari |
| `day_of_week` | Integer | 1–7 | Hari |

**Target:** `crowd_prediction` (Kategorik: HIGH / LOW)

**Distribusi kelas:**

| Kelas | Jumlah | Persentase |
|-------|--------|-----------|
| LOW | 446 | 89.2% |
| HIGH | 54 | 10.8% |

**Aturan generasi:**
- HIGH jika: `stop_id ∈ {1,2,3}` AND `jam 16–19` AND `hari kerja`
- HIGH jika: `jam 07–09` AND `hari kerja`
- LOW: semua kondisi lainnya

> ⚠️ **Catatan EDA:** Dataset imbalanced (89:11). Ini mencerminkan realita transportasi di mana kondisi sangat padat hanya terjadi pada jam-jam tertentu.

---

### 2.3 Model 3 — Anomaly Detection Dataset

**Ukuran:** 500 baris  
**Fitur:**

| Fitur | Tipe | Range | Keterangan |
|-------|------|-------|-----------|
| `bus_id` | Integer | 1–20 | ID bus |
| `speed` | Integer | 0–60 km/h | Kecepatan bus |
| `stop_duration` | Integer | 0–40 detik | Durasi berhenti |

**Target:** `is_anomaly` (Binary: 0 / 1)

**Distribusi kelas:**

| Kelas | Jumlah | Persentase |
|-------|--------|-----------|
| Normal (0) | 498 | 99.6% |
| Anomali (1) | 2 | 0.4% |

**Definisi anomali:** Bus dengan `speed = 0` AND `stop_duration > 15 detik`

> ⚠️ **Catatan EDA:** Dataset sangat imbalanced (498:2). Ini adalah keterbatasan desain dataset yang perlu diperbaiki pada iterasi berikutnya. Lihat bagian Limitasi.

---

### 2.4 Model 4 — Driver Behavior Dataset

**Ukuran:** 1000 baris  
**Fitur:**

| Fitur | Tipe | Range | Keterangan |
|-------|------|-------|-----------|
| `speed` | Integer | 10–110 km/h | Kecepatan kendaraan |
| `acceleration` | Float | 0.1–8.0 m/s² | Percepatan |
| `brake_force` | Float | 0.1–10.0 | Gaya pengereman |
| `turn_rate` | Integer | 1–45 °/s | Laju tikungan |
| `vibration` | Float | 0.0–2.5 | Getaran kendaraan |

**Target:** `label` (Kategorik: safe / normal / aggressive / dangerous)

**Distribusi kelas:**

| Kelas | Jumlah | Persentase |
|-------|--------|-----------|
| dangerous | 730 | 73.0% |
| aggressive | 218 | 21.8% |
| normal | 50 | 5.0% |
| safe | 2 | 0.2% |

**Aturan labeling (threshold-based):**

| Label | Kondisi |
|-------|---------|
| dangerous | speed > 80 OR acceleration > 5.5 OR brake_force > 7.5 OR turn_rate > 35 |
| aggressive | speed > 65 OR acceleration > 4.0 OR brake_force > 5.5 OR turn_rate > 25 OR vibration > 1.5 |
| normal | speed > 40 OR acceleration > 2.0 OR brake_force > 3.0 OR turn_rate > 12 OR vibration > 0.6 |
| safe | semua nilai di bawah threshold normal |

> ⚠️ **Catatan EDA:** Distribusi kelas sangat condong ke `dangerous` (73%) karena threshold yang tumpang tindih. Kelas `safe` hanya memiliki 2 sampel, menyebabkan model tidak bisa memprediksi kelas ini dengan baik.

---

## 3. Eksplorasi Data (EDA)

### 3.1 Korelasi Fitur — Model ETA

Feature importance dari Random Forest menunjukkan:

| Fitur | Importance |
|-------|-----------|
| `distance_to_stop` | **71.89%** ← dominan |
| `traffic_level` | 27.18% |
| `hour` | 0.65% |
| `day_of_week` | 0.27% |

**Insight:** Jarak ke halte adalah prediktor utama ETA. Variasi harian dan jam hampir tidak berkontribusi setelah jarak diperhitungkan.

### 3.2 Korelasi Fitur — Model Passenger Surge

| Fitur | Importance |
|-------|-----------|
| `hour` | **60.74%** ← dominan |
| `day_of_week` | 19.68% |
| `stop_id` | 19.58% |

**Insight:** Jam dalam sehari adalah faktor terpenting untuk memprediksi kepadatan, sesuai dengan pola jam sibuk di kota.

### 3.3 Korelasi Fitur — Model Driver Behavior

| Fitur | Importance |
|-------|-----------|
| `acceleration` | **24.73%** ← terpenting |
| `turn_rate` | 23.38% |
| `speed` | 23.56% |
| `brake_force` | 23.15% |
| `vibration` | 5.18% |

**Insight:** Empat sensor utama (kecepatan, akselerasi, rem, tikungan) berkontribusi hampir merata (~23% masing-masing). Vibration kurang informatif dalam dataset ini.

---

## 4. Preprocessing

Semua preprocessing dilakukan langsung saat pembuatan dataset sintetis:

| Langkah | Model | Keterangan |
|---------|-------|-----------|
| Tidak ada missing values | Semua | Data di-generate secara programatik |
| Tidak ada encoding | Semua | Fitur numerik semua, tidak ada fitur kategorik pada input |
| Label encoding otomatis | Model 2, 3, 4 | scikit-learn menangani string labels secara internal |
| Train-test split | Semua | 80% train, 20% test, `random_state=42` |
| Tidak dilakukan normalisasi | Semua | Random Forest tidak sensitif terhadap skala fitur |

---

## 5. Model & Training

Seluruh model menggunakan **Random Forest** sesuai rekomendasi spesifikasi tugas:

| | ETA | Passenger | Anomaly | Driver |
|---|---|---|---|---|
| Algoritma | RandomForestRegressor | RandomForestClassifier | RandomForestClassifier | RandomForestClassifier |
| n_estimators | 50 | 50 | 50 | **100** |
| random_state | 42 | 42 | 42 | 42 |
| max_depth | default | default | default | default |

Model 4 (Driver Behavior) menggunakan 100 trees karena klasifikasi multi-kelas membutuhkan lebih banyak tree untuk stabilitas.

---

## 6. Evaluasi Model

### 6.1 Model 1 — ETA Prediction (Regresi)

**Metrik:**

| Metrik | Nilai | Keterangan |
|--------|-------|-----------|
| **MAE** | **1.85 menit** | Rata-rata error prediksi |
| **R² Score** | **0.9759** | 97.6% variance dijelaskan model |

**Interpretasi:** Model sangat baik. Error rata-rata kurang dari 2 menit untuk prediksi ETA dengan range 1–67 menit. R² = 0.976 menunjukkan model mampu menjelaskan 97.6% variasi data.

---

### 6.2 Model 2 — Passenger Surge (Klasifikasi Biner)

**Confusion Matrix** (baris = aktual, kolom = prediksi):

```
              Prediksi HIGH   Prediksi LOW
Aktual HIGH        13               0
Aktual LOW          0              87
```

**Metrik:**

| Metrik | Nilai |
|--------|-------|
| **Accuracy** | **100%** |
| **Precision** | **100%** |
| **Recall** | **100%** |

**Interpretasi:** Model mencapai performa sempurna pada dataset ini karena aturan klasifikasi sangat deterministik (jam + stop_id + hari kerja). Pada data dunia nyata, performa akan lebih rendah karena ada variasi yang tidak terprediksi.

---

### 6.3 Model 3 — Anomaly Detection (Klasifikasi Biner)

**Confusion Matrix:**

```
              Prediksi 0    Prediksi 1
Aktual 0         98              0
Aktual 1          2              0
```

**Metrik:**

| Metrik | Nilai | Keterangan |
|--------|-------|-----------|
| **Accuracy** | **98%** | Menyesatkan karena imbalance |
| **Precision** | **0%** | Model tidak berhasil mendeteksi anomali |
| **Recall** | **0%** | Semua anomali salah diklasifikasi |

**Interpretasi & Limitasi:** Accuracy 98% terlihat tinggi tapi menyesatkan — model sebenarnya hanya memprediksi "tidak anomali" untuk semua kasus (majority class bias). Ini disebabkan dataset yang sangat imbalanced (498 normal : 2 anomali).

**Perbaikan yang direkomendasikan untuk iterasi berikutnya:**
- Gunakan teknik oversampling (SMOTE) untuk kelas minoritas
- Seimbangkan dataset menjadi minimal 80:20
- Gunakan metrik F1-score atau AUC-ROC daripada accuracy untuk evaluasi

> Meskipun model memiliki keterbatasan pada precision/recall, endpoint `/detect/anomaly` tetap berfungsi dengan fallback logic berbasis threshold jika model tidak tersedia.

---

### 6.4 Model 4 — Driver Behavior (Klasifikasi Multi-kelas)

**Confusion Matrix** (baris = aktual, kolom = prediksi):

```
             safe   normal   aggressive   dangerous
safe            0        0            0           0
normal          0        7            1           0
aggressive      0        0           43           0
dangerous       0        0            0         149
```

**Metrik per kelas:**

| Kelas | Precision | Recall | F1-Score | Support |
|-------|-----------|--------|----------|---------|
| safe | — | — | — | 0 (tidak ada di test set) |
| normal | 1.0000 | 0.8750 | 0.9333 | 8 |
| aggressive | 0.9773 | 1.0000 | 0.9885 | 43 |
| dangerous | 1.0000 | 1.0000 | 1.0000 | 149 |

**Metrik keseluruhan:**

| Metrik | Nilai |
|--------|-------|
| **Accuracy** | **99.5%** |
| **Precision (weighted)** | **99.51%** |
| **Recall (weighted)** | **99.50%** |

**Interpretasi:** Model sangat baik untuk kelas `dangerous` dan `aggressive` — justru yang paling penting untuk keselamatan. Kelas `safe` tidak dapat diprediksi karena hanya ada 2 sampel dalam seluruh dataset (0.2%), yang menyebabkan tidak ada sampel kelas ini masuk ke test set.

**Feature importance:**
- Keempat sensor utama berkontribusi hampir merata (~23%), artinya tidak ada sensor yang bisa diabaikan
- Vibration (5.18%) paling kurang informatif — sensor ini mungkin perlu dikalibrasi lebih baik pada data nyata

---

## 7. Ringkasan Evaluasi

| Model | Algoritma | Dataset | Metrik Utama | Nilai | Status |
|-------|-----------|---------|--------------|-------|--------|
| ETA Prediction | RF Regressor | 500 baris | R² / MAE | 0.976 / 1.85 menit | ✅ Baik |
| Passenger Surge | RF Classifier | 500 baris | Accuracy | 100% | ✅ Baik* |
| Anomaly Detection | RF Classifier | 500 baris | F1-Score | 0% | ⚠️ Perlu perbaikan |
| Driver Behavior | RF Classifier | 1000 baris | Accuracy | 99.5% | ✅ Sangat Baik |

*Performa sempurna pada dataset sintetis; perlu validasi pada data nyata.

---

## 8. Integrasi dengan Platform

### Alur Prediksi Real-time (S1 & S6)

```
Wokwi Simulator
    │ MQTT publish driver telemetry
    ▼
Node-RED → POST /internal/traffic/telemetry
    │
Traffic Service → RabbitMQ: driver.telemetry.updated
    │
Python ML Consumer (thread terpisah dari FastAPI)
    │ load driver_behavior_model.pkl
    │ predict(speed, acceleration, brake_force, turn_rate, vibration)
    │
    ├── hasil = 'safe' / 'normal' → tidak ada aksi
    └── hasil = 'aggressive' / 'dangerous'
            │ publish ke RabbitMQ: driver.behavior.detected
            ▼
        Citizen Consumer
            │ simpan ke citizen_driver_alerts
            └── broadcast notifikasi ke semua warga
```

### Endpoint REST ML Service

| Endpoint | Model | Input | Output |
|----------|-------|-------|--------|
| `POST /predict/traffic` | ETA Model | route_id, hour, day_of_week, current_speed, vehicle_count | congestion_level, estimated_delay |
| `POST /predict/eta` | ETA Model | hour, day_of_week, traffic_level, distance_to_stop | eta_minutes |
| `POST /predict/passenger` | Passenger Model | stop_id, hour, day_of_week | crowd_prediction |
| `POST /detect/anomaly` | Anomaly Model | bus_id, speed, stop_duration | is_anomaly, severity |
| `POST /predict/driver-behavior` | Driver Model | speed, acceleration, brake_force, turn_rate, vibration | behavior, confidence |

---

## 9. Limitasi & Rekomendasi

### Limitasi Saat Ini

1. **Dataset sintetis** — Semua data di-generate, belum divalidasi dengan data armada bus nyata
2. **Anomaly Detection imbalanced** — Hanya 2 sampel anomali dari 500 data, model tidak belajar dengan efektif
3. **Driver Behavior imbalanced** — Kelas `safe` hampir tidak ada (0.2%), model tidak dapat mengenalinya
4. **Tidak ada cross-validation** — Evaluasi hanya menggunakan satu split train/test

### Rekomendasi Pengembangan Selanjutnya

1. **Kumpulkan data nyata** dari GPS bus dan sensor pengemudi setelah sistem berjalan minimal 3 bulan
2. **Terapkan SMOTE** (Synthetic Minority Oversampling Technique) untuk menyeimbangkan dataset anomali dan driver behavior
3. **Gunakan k-Fold Cross Validation** (k=5 atau 10) untuk evaluasi yang lebih robust
4. **Pertimbangkan model alternatif** untuk anomaly detection: Isolation Forest atau LSTM untuk data time-series
5. **Feature engineering** tambahan: tambahkan fitur waktu (menit dalam jam), cuaca, hari libur nasional
6. **Model retraining pipeline** — Implementasikan retraining otomatis setiap minggu saat data nyata mulai terkumpul
