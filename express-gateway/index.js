import dotenv from 'dotenv';
dotenv.config();
import express from 'express';
import { createProxyMiddleware } from 'http-proxy-middleware';
import rateLimit from 'express-rate-limit';
import jwt from 'jsonwebtoken';
import morgan from 'morgan';
import mysql from 'mysql2/promise';
import axios from 'axios';

const app = express();
const PORT = process.env.PORT || 3000;
const JWT_SECRET = process.env.JWT_SECRET;

// ----------------
// KONEKSI DATABASE
// ----------------
const db = mysql.createPool({
    host: process.env.DB_HOST,
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_NAME,
});

// ----------- 
// URL SERVICE (portnya blum fix)
// -----------
const OAUTH_SERVICE_URL = process.env.OAUTH_SERVICE_URL || 'http://localhost:3002';
const CITIZEN_SERVICE_URL = process.env.CITIZEN_SERVICE_URL || 'http://localhost:8000';
const TRAFFIC_SERVICE_URL = process.env.TRAFFIC_SERVICE_URL || 'http://localhost:8001';
const ENV_SERVICE_URL = process.env.ENV_SERVICE_URL || 'http://localhost:8002';
const PYTHON_ML_URL = process.env.PYTHON_ML_URL || 'http://localhost:5000';

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
    validate: { keyGenerator: false },
    handler: (req, res) => sendStandardError(res, 429, "Terlalu banyak permintaan dari IP ini, coba lagi nanti")
});
app.use(globalLimiter);

// Token Rate Limit (500 req/1 jam untuk user login)
const authLimiter = rateLimit({
    windowMs: 60 * 60 * 1000, 
    max: 500,
    standardHeaders: true,
    legacyHeaders: false,
    validate: { keyGenerator: false },
    keyGenerator: (req) => req.headers.authorization || req.ip,
    handler: (req, res) => sendStandardError(res, 429, "Terlalu banyak permintaan untuk token ini, coba lagi nanti")
});

// IoT Rate Limit (60 req/1 menit)
const telemetryLimiter = rateLimit({
    windowMs: 60 * 1000, 
    max: 60,
    standardHeaders: true,
    legacyHeaders: false,
    validate: { keyGenerator: false },
    keyGenerator: (req) => req.headers.authorization || req.ip,
    handler: (req, res) => sendStandardError(res, 429, "Spam Terdeteksi! Sensor mengirim data terlalu cepat", "traffic-service")
});

// -------------------------
// INTERNAL IoT ROUTE
// Node-RED runs inside the Docker network and calls this directly.
// Traffic data from sensors is not user-authenticated — it comes from
// trusted internal services, so JWT is not required here.
// Rate-limited to 120 req/min (one bus every 3-5 seconds * many buses).
// -------------------------
const iotLocationLimiter = rateLimit({
    windowMs: 60 * 1000,
    max: 120,
    validate: { keyGenerator: false },
    keyGenerator: (req) => req.ip,
    handler: (req, res) => sendStandardError(res, 429, "IoT rate limit exceeded", "gateway")
});

app.post('/internal/traffic/location', iotLocationLimiter, createProxyMiddleware({
    target: TRAFFIC_SERVICE_URL,
    changeOrigin: true,
    pathRewrite: { '^/internal/traffic/location': '/api/traffic/location' },
    on: {
        error: (err, req, res) => sendStandardError(res, 502, "Traffic service unreachable", "gateway")
    }
}));

app.post('/internal/traffic/telemetry', iotLocationLimiter, createProxyMiddleware({
    target: TRAFFIC_SERVICE_URL,
    changeOrigin: true,
    pathRewrite: { '^/internal/traffic/telemetry': '/api/traffic/telemetry' },
    on: {
        error: (err, req, res) => sendStandardError(res, 502, "Traffic service unreachable", "gateway")
    }
}));

// Internal IoT routes for environment sensors (called by Node-RED, no JWT)
app.post('/internal/environment/passenger', iotLocationLimiter, createProxyMiddleware({
    target: ENV_SERVICE_URL,
    changeOrigin: true,
    pathRewrite: { '^/internal/environment/passenger': '/api/environment/passenger' },
    on: { error: (err, req, res) => sendStandardError(res, 502, "Environment service unreachable", "gateway") }
}));

app.post('/internal/environment/temperature', iotLocationLimiter, createProxyMiddleware({
    target: ENV_SERVICE_URL,
    changeOrigin: true,
    pathRewrite: { '^/internal/environment/temperature': '/api/environment/temperature' },
    on: { error: (err, req, res) => sendStandardError(res, 502, "Environment service unreachable", "gateway") }
}));

app.post('/internal/environment/air', iotLocationLimiter, createProxyMiddleware({
    target: ENV_SERVICE_URL,
    changeOrigin: true,
    pathRewrite: { '^/internal/environment/air': '/api/environment/air' },
    on: { error: (err, req, res) => sendStandardError(res, 502, "Environment service unreachable", "gateway") }
}));

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
            return sendStandardError(res, 401, "Unauthorized. Token sudah di revoke");
        }

        jwt.verify(token, JWT_SECRET, (err, user) => {
            if (err) return sendStandardError(res, 403, "Forbidden. Token yang dimasukkan tidak valid");
            req.user = user;
            authLimiter(req, res, next);
        });
    } catch (err) {
        jwt.verify(token, JWT_SECRET, (err, user) => {
            if (err) return sendStandardError(res, 403, "Forbidden.");
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

// -------------------------------------------------------------------
// RATE LIMIT — ML predictions (10 req/menit per token untuk demo S3)
// -------------------------------------------------------------------
const mlLimiter = rateLimit({
    windowMs: 60 * 1000,
    max: 10,
    standardHeaders: true,
    legacyHeaders: false,
    validate: { keyGenerator: false },
    keyGenerator: (req) => req.headers.authorization || req.ip,
    handler: (req, res) => sendStandardError(res, 429, "Rate limit ML terlampaui. Maksimal 10 prediksi per menit.", "python-ml-service")
});

// -----------------------------------------------------------------------
// ROUTING PROXY
//
// PENTING — HPM v3 path stripping:
// Jika pakai app.use('/prefix', hpm(...)), Express STRIP prefix dari req.url
// sebelum HPM menerima request → upstream menerima path salah → 404/405.
//
// Fix: pakai `pathFilter` di dalam config HPM dan mount di app.use() tanpa path.
// Express tidak strip apapun, HPM menerima full URL, upstream dapat path yang benar.
// -----------------------------------------------------------------------

const makeProxy = (pathFilter, target, serviceName, ...extraMiddleware) =>
    app.use(
        authenticateToken,
        ...extraMiddleware,
        createProxyMiddleware({
            pathFilter,
            target,
            changeOrigin: true,
            on: {
                error: (err, req, res) =>
                    sendStandardError(res, 502, `Bad Gateway. Layanan [${serviceName}] sedang tidak aktif`, serviceName)
            }
        })
    );

// ── Public: OAuth
// oauth-server daftarkan route sebagai /token /introspect /revoke (tanpa prefix /oauth).
// app.use('/oauth', ...) di sini justru BENAR karena kita INGIN Express strip '/oauth'
// supaya upstream menerima '/token', '/introspect', '/revoke'.
app.use('/oauth', createProxyMiddleware({
    target: OAUTH_SERVICE_URL,
    changeOrigin: true,
    on: { error: (err, req, res) => sendStandardError(res, 502, "OAuth server tidak aktif", "oauth-server") }
}));

// ── Protected: semua pakai pathFilter (Express tidak strip path) ──

// Citizen service — tickets, reports, notifications, citizens
makeProxy(
    ['/api/citizens', '/api/reports', '/api/notifications', '/api/tickets'],
    CITIZEN_SERVICE_URL, "citizen-service"
);

// Traffic service — telemetry punya rate limit sendiri, sisanya normal
makeProxy('/api/traffic/telemetry', TRAFFIC_SERVICE_URL, "traffic-service", telemetryLimiter);
makeProxy('/api/traffic',           TRAFFIC_SERVICE_URL, "traffic-service");

// Environment service
makeProxy('/api/environment', ENV_SERVICE_URL, "environment-service");

// Python ML service — rate limited 10/menit untuk demo S3
makeProxy(['/predict', '/detect'], PYTHON_ML_URL, "python-ml-service", mlLimiter);

// callback oauth google
app.get('/api/oauth/callback', async (req, res) => {
    const { code } = req.query;

    if (!code) {
        return res.status(400).json({ 
            status: "error", 
            message: "Code dari Google tidak ditemukan" 
        });
    }

    try {
        const tokenResponse = await axios.post('https://oauth2.googleapis.com/token', {
            code,
            client_id: process.env.GOOGLE_CLIENT_ID,
            client_secret: process.env.GOOGLE_CLIENT_SECRET,
            redirect_uri: 'http://localhost:3000/api/oauth/callback',
            grant_type: 'authorization_code'
        });

        const { id_token } = tokenResponse.data;

        const oauthServerResponse = await axios.post('http://localhost:3002/token', {
            grant_type: 'google',
            id_token: id_token
        });

        return res.json({
            status: "success",
            message: "Login Google Berhasil lewat API Gateway (Stateless Backend)",
            data: oauthServerResponse.data
        });

    } catch (error) {
        console.error(error.response?.data || error.message);
        return res.status(500).json({
            status: "error",
            message: "Gagal memproses login Google di tingkat Gateway"
        });
    }
});

// -----------------------
// SERVER RUNNING
// -----------------------
app.listen(PORT, () => {
    console.log(`[API-GATEWAY] Berjalan di port ${PORT}`);
});