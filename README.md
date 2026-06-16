# Smart Public Transport Platform

## Kelompok 1

### Mata Kuliah

Pembangunan Perangkat Lunak Orientasi Service (Semester VI)

---

## Anggota Tim

| Nama   | Tugas                         |
| ------ | ----------------------------- |
| Aldin  | System Integration & DevOps   |
| Najwa  | API Gateway & OAuth Server    |
| Taraka | Citizen Service (PHP MVC)     |
| Albi   | Traffic Service (PHP MVC)     |
| Melva  | Environment Service (PHP MVC) |
| Paruk  | Python ML Service (FastAPI)   |

---

# Project Overview

Smart Public Transport Platform adalah sistem transportasi publik berbasis microservice yang mengintegrasikan:

* IoT Sensor Simulator
* MQTT Broker
* Node-RED
* REST API
* RabbitMQ
* Machine Learning
* Docker
* Kubernetes

untuk melakukan monitoring armada bus kota secara real-time.

---

# Architecture

Bus Simulator

↓

MQTT Broker

↓

Node-RED

↓

API Gateway

↓

Traffic Service

↓

Environment Service

↓

RabbitMQ

↓

Python ML Service

↓

Citizen Notification

---

# Services

## API Gateway

* Routing
* JWT Verification
* Rate Limiting
* Request Logging

Port:

3000

---

## OAuth Server

* Access Token
* Refresh Token
* Token Validation

Port:

3002

---

## Citizen Service

Features:

* Ticket Booking
* Ticket History
* Citizen Reports
* Notifications

Port:

8000

---

## Traffic Service

Features:

* Bus Tracking
* GPS Location Monitoring
* ETA Monitoring

Port:

8001

---

## Environment Service

Features:

* Passenger Monitoring
* Temperature Monitoring
* Alert Management

Port:

8002

---

## Python ML Service

Features:

* ETA Prediction
* Passenger Surge Prediction
* Bus Anomaly Detection

Port:

5000

---

# Tech Stack

Backend:

* Express.js
* PHP 8.2
* FastAPI

Database:

* MySQL 8

Message Broker:

* RabbitMQ

IoT:

* Mosquitto MQTT
* Node-RED

Monitoring:

* Prometheus
* Grafana

Containerization:

* Docker
* Docker Compose

Orchestration:

* Kubernetes

---

# Database

Database Name:

smarttransit

Schema tersedia pada:

database/schema.sql

Seed tersedia pada:

database/seed.sql

---

# Environment Variables

Copy file:

.env.example

menjadi:

.env

Kemudian sesuaikan konfigurasi database dan service.

---

# Running with Docker

Build seluruh service:

```bash
docker compose up --build
```

Menjalankan di background:

```bash
docker compose up -d
```

Menghentikan seluruh service:

```bash
docker compose down
```

---

# API Documentation

Lihat:

docs/api-contract.md

---

# RabbitMQ Events

Lihat:

docs/rabbitmq-contract.md

---

# Kubernetes Deployment

Manifest tersedia pada:

k8s/

Deployment:

```bash
kubectl apply -f k8s/
```

---

# License

Academic Project - Universitas
