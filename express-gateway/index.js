import dotenv from 'dotenv';
dotenv.config();
import express from 'express';
import { createProxyMiddleware } from 'http-proxy-middleware';
import rateLimit from 'express-rate-limit';
import jwt from 'jsonwebtoken';
import morgan from 'morgan';
import mysql from 'mysql2';

const app = express();
const PORT = process.env.PORT;
const JWT_SECRET = process.env.JWT_SECRET;

// ----------------
// KONEKSI DATABASE
// ----------------
const db = await mysql.createPool({
    host: process.env.DB_HOST,
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_NAME,
});

// ----------- 
// URL SERVICE (portnya blum fix)
// -----------
const OAUTH_SERVICE_URL = process.env.OAUTH_SERVICE_URL;
const CITIZEN_SERVICE_URL = process.env.CITIZEN_SERVICE_URL;
const TRAFFIC_SERVICE_URL = process.env.TRAFFIC_SERVICE_URL;
const ENV_SERVICE_URL = process.env.ENV_SERVICE_URL;
const PYTHON_ML_URL = process.env.PYTHON_ML_URL;

// ---------------
// REQUEST LOGGING
// ---------------
app.use(morgan((tokens, req, res) => {
    return JSON.stringify({
        timestamp: new Date().toISOString(),
        method: tokens.method(req, res),
        path: tokens.url(req, res),
        status: parseInt(tokens.status(req, res)),
        responseTime: `${tokens['response-time'](req, res)} ms`
    });
}));

// --------------
// STANDARD ERROR
// --------------
const sendStandardError = (res, statusCode, message, serviceName = "api-gateway") => {
    return res.status(statusCode).json({
        status: "error",
        code: statusCode,
        data: null,
        message: message,
        timestamp: new Date().toISOString(),
        service: serviceName
    });
};

// -------------
// RATE LIMITING
// -------------
// Global Rate Limit (100 req/15 menit per IP)
const globalLimiter = rateLimit({
    windowMs: 15 * 60 * 1000, 
    max: 100,
    standardHeaders: true,
    legacyHeaders: false,
    handler: (req, res) => sendStandardError(req, 429, "Terlalu banyak permintaan dari IP ini, coba lagi nanti")
});
app.use(globalLimiter);

// Token Rate Limit (500 req/1 jam untuk user login)
const authLimiter = rateLimit({
    windowMs: 60 * 60 * 1000, 
    max: 500,
    standardHeaders: true,
    legacyHeaders: false,
    keyGenerator: (req) => req.headers.authorization || req.ip,
    handler: (req, res) => sendStandardError(req, 429, "Terlalu banyak permintaan untuk token ini, coba lagi nanti")
});

// ----------------
// JWT VERIFICATION
// ----------------
async function authenticateToken(req, res, next) {
    const authHeader = req.headers['authorization'];
    const token = authHeader && authHeader.split(' ')[1];

    if (!token) {
        return sendStandardError(res, 401, "Akses ditolak. Membutuhkan Token JWT");
    }

    try {
        const [rows] = await db.query('SELECT id FROM oauth_token_blacklist WHERE token = ?', [token]);
        if (rows.length > 0) {
            return sendStandardError(res, 401, "Token yang dimsukkan sudah di-blacklist");
        }
        
        jwt.verify(token, JWT_SECRET, (err, user) => {
            if (err) {
                return sendStandardError(res, 403, "Token tidak valid atau telah kedaluwarsa");
            }
            req.user = user;
            // Kalo token valid, masukin ke limiter khusus token
            authLimiter(req, res, next);
        });
    } catch (err) {
        jwt.verify(token, JWT_SECRET, (err, user) => {
            if (err) return sendStandardError(res, 403, "Anda tidak dapat mengakses resource ini");
            req.user = user;
            next();
        });
    }
}

// -----------------
// HEALTH AGGREGATOR 
// -----------------
app.get('/health', async (req, res) => {
    const upstreams = [
        { name: "oauth-server", url: `${OAUTH_SERVICE_URL}/health` },
        { name: "citizen-service", url: `${CITIZEN_SERVICE_URL}/health` },
        { name: "traffic-service", url: `${TRAFFIC_SERVICE_URL}/health` },
        { name: "environment-service", url: `${ENV_SERVICE_URL}/health` },
        { name: "python-ml-service", url: `${PYTHON_ML_URL}/health` }
    ];

    const upstreamStatus = {};

    for (let service of upstreams) {
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 1500); // timeout 1,5 detik
            
            const response = await fetch(service.url, { signal: controller.signal });
            clearTimeout(timeoutId);
            upstreamStatus[service.name] = response.ok ? "UP" : "DOWN";
        } catch (err) {
            upstreamStatus[service.name] = "DOWN";
        }
    }
    return res.status(200).json({
        status: "success",
        code: 200,
        data: {
            gateway: "UP",
            upstreams: upstreamStatus
        },
        message: "Status Health Check berhasil dirangkum",
        timestamp: new Date().toISOString(),
        service: "api-gateway"
    });
});

// -------------
// ROUTING PROXY
// -------------
const configureProxy = (targetUrl, serviceName) => ({
    target: targetUrl,
    changeOrigin: true,
    onError: (err, req, res) => {
        sendStandardError(res, 502, `Bad Gateway. Layanan [${serviceName}] sedang tidak aktif`, serviceName);
    }
});

// Publik
app.use('/oauth', createProxyMiddleware(configureProxy(OAUTH_SERVICE_URL, "oauth-server")));

// Protected
app.use('/api/citizens', authenticateToken, createProxyMiddleware(configureProxy(CITIZEN_SERVICE_URL, "citizen-service")));
app.use('/api/reports', authenticateToken, createProxyMiddleware(configureProxy(CITIZEN_SERVICE_URL, "citizen-service")));
app.use('/api/notifications', authenticateToken, createProxyMiddleware(configureProxy(CITIZEN_SERVICE_URL, "citizen-service")));

app.use('/api/traffic', authenticateToken, createProxyMiddleware(configureProxy(TRAFFIC_SERVICE_URL, "traffic-service")));
app.use('/api/environment', authenticateToken, createProxyMiddleware(configureProxy(ENV_SERVICE_URL, "environment-service")));

app.use('/predict', authenticateToken, createProxyMiddleware(configureProxy(PYTHON_ML_URL, "python-ml-service")));
app.use('/detect', authenticateToken, createProxyMiddleware(configureProxy(PYTHON_ML_URL, "python-ml-service")));

// -----------------------
// SERVER RUNNING
// -----------------------
app.listen(PORT, () => {
    console.log(`[API-GATEWAY] Berjalan di port ${PORT}`);
});