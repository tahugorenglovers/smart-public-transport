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

// ---------------
// REQUEST LOGGING
// ---------------
app.use(morgan(':timestamp :method :url :status :response-time ms'));
morgan.token('timestamp', () => new Date().toISOString());

// -------------
// RATE LIMITERS
// -------------
// Global Rate Limit (100 req/15 menit per IP)
const globalLimiter = rateLimit({
    windowMs: 15 * 60 * 1000, 
    max: 100,
    standardHeaders: true,
    legacyHeaders: false,
    message: {
        status: "error",
        code: 429,
        message: "Terlalu banyak permintaan dari alamat IP ini, silakan coba lagi nanti"
    }
});
app.use(globalLimiter);

// Token Rate Limit (500 req/1 jam untuk user login)
const authLimiter = rateLimit({
    windowMs: 60 * 60 * 1000, 
    max: 500,
    standardHeaders: true,
    legacyHeaders: false,
    keyGenerator: (req) => req.headers.authorization || req.ip,
    message: {
        status: "error",
        code: 429,
        message: "Terlalu banyak permintaan untuk token ini, silakan coba lagi nanti"
    }
});
app.use(authLimiter);

// ----------------
// JWT VERIFICATION
// ----------------
function authenticateToken(req, res, next) {
    const authHeader = req.headers['authorization'];
    const token = authHeader && authHeader.split(' ')[1];

    if (!token) {
        return res.status(401).json({
            status: "error",
            code: 401,
            message: "Akses ditolak. Membutuhkan Token JWT"
        });
    }

    jwt.verify(token, JWT_SECRET, (err, user) => {
        if (err) {
            return res.status(403).json({
                status: "error",
                code: 403,
                message: "Dilarang masuk! Token tidak valid atau telah kedaluwarsa"
            });
        }
        req.user = user;
        // Kalo token valid, masukin ke limiter khusus token
        authLimiter(req, res, next);
    });
}

// -----------------
// HEALTH AGGREGATOR 
// -----------------
app.get('/health', async (req, res) => {
    const services = [
        { name: "oauth-server", url: `${OAUTH_URL}/health` },
        { name: "citizen-service", url: `${CITIZEN_URL}/health` },
        { name: "traffic-service", url: `${TRAFFIC_URL}/health` },
        { name: "environment-service", url: `${ENVIRONMENT_URL}/health` },
        { name: "python-ml-service", url: `${ML_URL}/health` }
    ];

    const healthStatus = {
        gateway: "UP",
        upstreams: {}
    };

    for (let service of services) {
        try {
            const controller = new AbortController();
            const id = setTimeout(() => controller.abort(), 1000); // timeout 1 detik
            const response = await fetch(service.url, { signal: controller.signal });
            clearTimeout(id);
            healthStatus.upstreams[service.name] = response.ok ? "UP" : "DOWN";
        } catch (err) {
            healthStatus.upstreams[service.name] = "DOWN";
        }
    }
    res.json(healthStatus);
});

// -------------
// ROUTING PROXY
// -------------
const proxyOptions = (target) => ({
    target,
    changeOrigin: true,
    onError: (err, req, res) => {
        req.status(502).json({
            status: "error",
            code: 502,
            message: "Bad Gateway. Layanan tujuan sedang tidak aktif"
        });
    }
});

app.use('/oauth', createProxyMiddleware(proxyOptions(OAUTH_URL)));
app.use('/api/tickets', createProxyMiddleware(proxyOptions(CITIZEN_URL)));
app.use('/api/reports', createProxyMiddleware(proxyOptions(CITIZEN_URL)));
app.use('/api/notifications', createProxyMiddleware(proxyOptions(CITIZEN_URL)));
app.use('/api/traffic', authenticateToken, createProxyMiddleware(proxyOptions(TRAFFIC_URL)));
app.use('/api/environment', authenticateToken, createProxyMiddleware(proxyOptions(ENVIRONMENT_URL)));
app.use('/predict', authenticateToken, createProxyMiddleware(proxyOptions(ML_URL)));
app.use('/detect', authenticateToken, createProxyMiddleware(proxyOptions(ML_URL)));

// -----------------------
// SERVER RUNNING
// -----------------------
app.listen(PORT, () => {
    console.log(`[GATEWAY] Berjalan di port ${PORT}`);
});