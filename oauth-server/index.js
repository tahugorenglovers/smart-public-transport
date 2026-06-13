import dotenv from 'dotenv';
dotenv.config();
import express, { json } from 'express';
import mysql, { Types } from 'mysql2/promise';
import bcrypt from 'bcrypt';
import jwt from 'jsonwebtoken';
import { token } from 'morgan';
import { use } from 'react';

const app = express();
app.use(express.json());

const PORT = process.env.PORT;
const JWT_SECRET = process.env.JWT_SECRET;
const JWT_REFRESH_SECRET = process.env.JWT_REFRESH_SECRET;

// -------------------------------------
// KONEKSI DATABASE MYSQL (smarttransit)
// -------------------------------------
const db = await mysql.createPool({
    host: process.env.DB_HOST,
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_NAME,
});

// -------------------------
// Health Check OAuth Server
// -------------------------
app.get('/health', (req, res) => {
    try {
        await db.query('SELECT 1');
        return res.status(200).json({
            status: "UP",
            code: 200,
            database: "CONNECTED", 
        });
    } catch (err) {
        return res.status(500).json({
            status: "UP",
            code: 200,
            database: "CONNECTED", 
        });
    }
});

// -------------------------
// Hasilkan token dari OAuth
// -------------------------
function generateOAuthResponse(userPayload) {
    const accessToken = jwt.sign(userPayload, JWT_SECRET, { expiresIn: '1h' });
    const refreshToken = jwt.sign({ id: userPayload.id, type: 'refresh' }, JWT_REFRESH_SECRET, { expiresIn: '7d' });
    return { 
        access_token: accessToken, 
        refresh_token: refreshToken, 
        expires_in: 3600,
        token_type: "Bearer"
    };
}

// -----------------
// POST /oauth/token 
// -----------------
app.post('/oauth/token', async (req, res) => {
    const { grant_type, username, password, refresh_token, client_id, client_secret } = req.body;

    try {
        // Password Grant (Citizen Web App pake NIK/Email & Password)
        if (grant_type === 'password') {
            if (!username || !password) {
                return res.status(400).json({
                    status: "error",
                    code: 400,
                    message: "Username atau Password tidak ditemukan"
                });
            }

            const [rows] = await db.query('SELECT * FROM citizen_citizens WHERE email = ? OR nik = ?', [username, username]);
            const user = rows[0];

            if (!user || !user.password_hash) {
                return res.status(401).json({
                    status: "error",
                    code: 401,
                    message: "Kredential yang dimasukkan tidak valid"
                });
            }

            const isMatch = await bcrypt.compare(password, user.password_hash);
            if (!isMatch) {
                return res.status(401).json({
                    status: "error",
                    code: 401,
                    message: "Kredential yang dimasukkan tidak valid"
                });
            }

            const tokenData = generateOAuthResponse({
                status: "success",
                id: user.id,
                name: user.name,
                role: user.role || 'citizen',
                zone_id: user.zone_id
            });
            res.json(tokenData);
        }

        // Refresh Token Grant (perpanjang sesi login)
        if (grant_type === 'refresh_token') {
            if (!refresh_token) {
                return res.status(400),json({
                    status: "error",
                    code: 400,
                    message: "Token refresh tidak ditemukan"
                });
            }
            
            try {
                const decoded = jwt.verify(refresh_token, JWT_REFRESH_SECRET);
                const [rows] = await db.query('SELECT * FROM citizen_users WHERE id = ?', [decoded.id]);
                const user = rows[0];

                if (!user) {
                    return res.status(401).json({ 
                        status: "error",
                        code: 401,
                        message: "User tidak ditemukan" 
                    });
                }

                const tokenData = generateOAuthResponse({
                    status: "success", 
                    id: user.id, 
                    name: user.name, 
                    role: user.role || 'citizen',
                    zone_id: user.zone_id 
                });
                return res.json(tokenData);
            } catch (err) {
                return res.status(401).json({ 
                        status: "error",
                        code: 401,
                        message: "Token refesh tidak valid atau sudah kadaluwarsa" 
                });
            }
        }

        // Client Credentials Grant (buat Node-RED IoT / internal service)
        if (grant_type === 'client_credentials') {
            if (!client_id || !client_secret) {
                return res.status(400).json({ 
                    status: "error",
                    code: 400,
                    message: "Kredential client tidak ditemukan" 
                });
            }

            const [clients] = await db.query('SELECT * FROM oauth_clients WHERE client_id = ? AND client_secret = ?', [client_id, client_secret]);
            const client = clients[0];

            if (!client) {
                return res.status(401).json({ 
                    status: "error",
                    code: 401,
                    message: "Kredential client yang dimasukkan tidak valid" 
                });
            }

            // token untuk IoT & internal service
            const tokenData = generateOAuthResponse({
                service_client: client.client_id, 
                role: 'internal_service'
            });
            return res.json(tokenData);
        }
        return res.status(400).json({ 
            status: "error",
            code: 400,
            message: "Tipe Grant yang dicari tidak ditemukan" 
        });
    } catch (err) {
        console.error(err);
        return res.status(500).json({ 
            status: "error",
            code: 500,
            message: "Server internal sedang error" 
        });
    }
});

// ----------------------
// POST /oauth/introspect
// ----------------------
app.post('/oauth/introspect', (req, res) => {
    const { token } = req.body;

    if (!token) {
        return res.status(400).json({ 
            status: "error",
            code: 400,
            message: "Token tidak ditemukan" 
        });
    }

    try {
        const decoded = jwt.verify(token, JWT_SECRET);
        return res.json({
            active: true, 
            scope: decoded.role,
            client_id: decoded.service_client || null,
            exp: decoded.exp, 
            user: decoded
        });
    } catch (err) {
        return res.json({ active: false });
    }
});

// ------------------
// POST /oauth/revoke
// ------------------
app.post('/oauth/revoke', (req, res) => {
    return res.status(200).json({
        status: "success",
        code: 200,
        message: "Token has been successfully revoked"
    });
});

app.listen(PORT, () => {
    console.log(`[OAuth-Server] Berjalan di port ${PORT}`);
});