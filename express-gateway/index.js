import dotenv from 'dotenv';
dotenv.config();
import express from 'express';
import { createProxyMiddleware } from 'http-proxy-middleware';
import rateLimit from 'express-rate-limit';
import jwt from 'jsonwebtoken';
import morgan from 'morgan';

const app = express();
const PORT = process.env.PORT;
const JWT_SECRET = process.env.JWT_SECRET;

// ----------- 
// URL SERVICE (portnya blum fix)
// -----------
const OAUTH_URL = process.env.OAUTH_SERVICE_URL;
const CITIZEN_URL = process.env.CITIZEN_SERVICE_URL;
const TRAFFIC_URL = process.env.TRAFFIC_SERVICE_URL;
const ENVIRONMENT_URL = process.env.ENVIRONMENT_SERVICE_URL;
const ML_URL = process.env.PYTHON_ML_URL;

// -----------------------
// MIDDLEWARE RATE LIMITER
// -----------------------
const limiter = rateLimit({
    windowMs: 1 * 60 * 1000, // 1 menit
    max: 60,
    standardHeaders: true,
    legacyHeaders: false,
    message: {
        status: 429,
        message: "Terlalu banyak request ke Gateway, silakan coba lagi nanti"
    }
});

app.use(limiter);

// -----------------------
// MIDDLEWARE LOGGING
// -----------------------
app.use(morgan('dev'));

// ---------------------------
// MIDDLEWARE JWT VERIFICATION
// ---------------------------
function authenticateToken(req, res, next) {
    const authHeader = req.headers['authorization'];
    const token = authHeader && authHeader.split(' ')[1];

    if (!token) {
        return res.status(401).json({
            status: "error",
            message: "Akses ditolak. Membutuhkan Token JWT!"
        });
    }

    jwt.verify(token, JWT_SECRET, (err, user) => {
        if (err) {
            return res.status(403).json({
                status: "error",
                message: "Token JWT yang diberikan tidak valid atau sudah kadaluwarsa"
            });
        }
        req.user = user;
        next();
    });
}

// -----------------------
// HEALTH CHECK ENDPOINT
// -----------------------
app.get('/health', (req, res) => {
    res.json({
        status: "UP",
        service: "API Gateway"
    });
});

// -----------------------------------
// ROUTING PROXY
// -----------------------------------
// Proxy ke OAuth Server (Port 3002) 
app.use('/oauth', createProxyMiddleware({
    target: OAUTH_URL,
    changeOrigin: true
}));

// Citizen Service (PHP MVC - Anggota 3) -> /api/tickets, /api/reports, /api/notifications
app.use('/api/tickets', authenticateToken, createProxyMiddleware({
    target: CITIZEN_URL,
    changeOrigin: true
}));

app.use('/api/reports', authenticateToken, createProxyMiddleware({
    target: CITIZEN_URL,
    changeOrigin: true
}));

app.use('/api/notifications', authenticateToken, createProxyMiddleware({
    target: CITIZEN_URL,
    changeOrigin: true
}));

// Traffic Service (PHP MVC - Anggota 4) -> /api/traffic/location, /api/traffic/current, /api/traffic/eta
app.use('/api/traffic', authenticateToken, createProxyMiddleware({
    target: TRAFFIC_URL,
    changeOrigin: true
}));

// Environment Service (PHP MVC - Anggota 5) -> /api/environment/passenger, /api/environment/temperature, /api/environment/alerts
app.use('/api/environment', authenticateToken, createProxyMiddleware({
    target: ENVIRONMENT_URL,
    changeOrigin: true
}));

// Python ML Service (FastAPI - Anggota 6) -> /predict/eta, /predict/passenger, /detect/anomaly
app.use('/predict', authenticateToken, createProxyMiddleware({
    target: ML_URL,
    changeOrigin: true
}));

app.use('/detect', authenticateToken, createProxyMiddleware({
    target: ML_URL,
    changeOrigin: true
}));

// -----------------------
// SERVER RUNNING
// -----------------------
app.listen(PORT, () => {
    console.log(`[API-GATEWAY] Berjalan di port ${PORT}`);
});