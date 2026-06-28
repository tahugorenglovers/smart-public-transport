# Kubernetes Deployment — Smart Public Transport

## Prerequisites
- Kubernetes cluster running (minikube, kind, or cloud)
- `kubectl` configured and pointing at the cluster
- Docker images built (see step 1 below)

---

## Step 1 — Build and load images

If using **minikube**:
```bash
eval $(minikube docker-env)
```

Build all service images:
```bash
docker compose build
```

Tag them so Kubernetes can find them:
```bash
docker tag smart-public-transport-fixed-v3-gateway       smarttransit-gateway:latest
docker tag smart-public-transport-fixed-v3-oauth         smarttransit-oauth:latest
docker tag smart-public-transport-fixed-v3-citizen-service  smarttransit-citizen-service:latest
docker tag smart-public-transport-fixed-v3-traffic-service  smarttransit-traffic-service:latest
docker tag smart-public-transport-fixed-v3-environment-service smarttransit-environment-service:latest
docker tag smart-public-transport-fixed-v3-python-ml     smarttransit-python-ml:latest
```

---

## Step 2 — Deploy the whole platform

```bash
kubectl apply -f k8s/
```

This applies all manifests in order (00 → 11). Wait for everything to be ready:
```bash
kubectl get pods -n smarttransit -w
```

---

## Step 3 — Verify (S5 Demo Scenario)

```bash
# All pods running
kubectl get pods -n smarttransit

# All services
kubectl get svc -n smarttransit

# HPAs are active
kubectl get hpa -n smarttransit
```

---

## Step 4 — Access the platform

| Service    | NodePort URL                    |
|------------|---------------------------------|
| Gateway    | http://\<node-ip\>:30010        |
| Node-RED   | http://\<node-ip\>:31810        |
| Grafana    | http://\<node-ip\>:31311        |
| Prometheus | http://\<node-ip\>:30910        |
| RabbitMQ   | internal only (port 5672/15672) |

Get minikube IP:
```bash
minikube ip
```

---

## Step 5 — Trigger HPA scaling (S5 Demo)

```bash
# Watch HPA scale up in real time
kubectl get hpa -n smarttransit -w

# Send load to trigger scale-up
kubectl run load-test --image=busybox --restart=Never -n smarttransit -- sh -c "while true; do wget -q -O- http://gateway:3000/health; done"

# Watch pods scale up
kubectl get pods -n smarttransit -w

# Clean up load test
kubectl delete pod load-test -n smarttransit
```

---

## Step 6 — Zero-downtime rolling update (S5 Demo)

```bash
# Simulate a rolling deployment update
kubectl rollout restart deployment/gateway -n smarttransit

# Watch pods roll over with zero downtime
kubectl rollout status deployment/gateway -n smarttransit
```

---

## Teardown

```bash
kubectl delete namespace smarttransit
```
