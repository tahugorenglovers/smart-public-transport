import dotenv from 'dotenv';
dotenv.config();
import express from 'express';
import mysql from 'mysql2/promise';
import bcrypt from 'bcrypt';
import jwt from 'jsonwebtoken';
import morgan from 'morgan';
import { OAuth2Client } from 'google-auth-library';
const googleClient = new OAuth2Client(process.env.GOOGLE_CLIENT_ID);

const app = express();
app.use(express.json());
app.use(morgan('dev'));

const PORT = process.env.PORT || 3002;
const JWT_SECRET = process.env.JWT_SECRET;
const JWT_REFRESH_SECRET = process.env.JWT_REFRESH_SECRET;

// -------------------------------------
// KONEKSI DATABASE MYSQL (smarttransit)
// -------------------------------------
const db = mysql.createPool({
    host: process.env.DB_HOST,
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_NAME,
});

// -------------------------
// Health Check OAuth Server
// -------------------------
app.get('/health', async (req, res) => {
    try {
        await db.query('SELECT 1');
        return res.status(200).json({
            status: "UP",
            code: 200,
            database: "CONNECTED", 
        });
    } catch (err) {
        return res.status(500).json({
            status: "DOWN",
            code: 500,
            database: "DISCONNECTED", 
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
app.post('/token', async (req, res) => {
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

            const [rows] = await db.query('SELECT * FROM citizen_users WHERE email = ? OR nik = ?', [username, username]);
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
                id: user.id,
                name: user.name,
                role: 'citizen'
            });

            const expiresAt = new Date();
            expiresAt.setDate(expiresAt.getDate() + 7); // Set basi 7 hari ke depan 
            const expiresAtFormatted = expiresAt.toISOString().slice(0, 19).replace('T', ' ');

            await db.query(
                'INSERT INTO oauth_refresh_tokens (user_id, refresh_token, expires_at) VALUES (?, ?, ?)',
                [user.id, tokenData.refresh_token, expiresAtFormatted]
            );

            return res.json(tokenData);
        }

        // Refresh Token Grant (perpanjang sesi login)
        if (grant_type === 'refresh_token') {
            if (!refresh_token) {
                return res.status(400).json({
                    status: "error",
                    code: 400,
                    message: "Token refresh tidak ditemukan"
                });
            }
            
            try {
                const [rows] = await db.query('SELECT * FROM oauth_refresh_tokens WHERE refresh_token = ? AND revoked = FALSE AND expires_at > NOW()', [refresh_token]);
                const storedToken = rows[0];

                if (!storedToken) {
                    return res.status(401).json({ 
                        status: "error",
                        code: 401,
                        message: "Refresh token tidak valid, sudah kadaluwarsa, atau sudah direvoke" 
                    });
                }

                const [users] = await db.query('SELECT * FROM citizen_users WHERE id = ?', [storedToken.user_id]);
                const user = users[0];

                const tokenData = generateOAuthResponse({
                    status: "success", 
                    id: user.id, 
                    name: user.name, 
                    role: user.role || 'citizen'
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

        // Google OAuth Grant (nuker Google id_token dengan JWT)
        if (grant_type === 'google') {
            const { id_token } = req.body;

            if (!id_token) {
                return res.status(400).json({ 
                    status: "error",
                    code: 400,
                    message: "Google ID Token tidak ditemukan" 
                });
            }

            try {
                const ticket = await googleClient.verifyIdToken({
                    idToken: id_token,
                    audience: process.env.GOOGLE_CLIENT_ID, 
                });
                const payload = ticket.getPayload();
                
                const googleEmail = payload.email;
                const googleName = payload.name;

                let [rows] = await db.query('SELECT * FROM citizen_users WHERE email = ?', [googleEmail]);
                let user = rows[0];

                // klo belum ada, buat akun baru (Auto-Register)
                if (!user) {
                    const [insertResult] = await db.query(
                        'INSERT INTO citizen_users (name, email, password_hash) VALUES (?, ?, ?)',
                        [googleName, googleEmail, null] // password_hash null karena login pke Google
                    );
                    
                    const [newUserRows] = await db.query('SELECT * FROM citizen_users WHERE id = ?', [insertResult.insertId]);
                    user = newUserRows[0];
                }

                const tokenData = generateOAuthResponse({
                    id: user.id,
                    name: user.name,
                    email: user.email,
                    role: 'citizen'
                });

                // save refresh token ke database biar bisa diperpanjang
                const expiresAt = new Date();
                expiresAt.setDate(expiresAt.getDate() + 7);
                const expiresAtFormatted = expiresAt.toISOString().slice(0, 19).replace('T', ' ');

                await db.query(
                    'INSERT INTO oauth_refresh_tokens (user_id, refresh_token, expires_at) VALUES (?, ?, ?)',
                    [user.id, tokenData.refresh_token, expiresAtFormatted]
                );

                return res.json(tokenData);

            } catch (googleError) {
                return res.status(401).json({
                    status: "error",
                    code: 401,
                    message: "Google ID Token tidak valid atau sudah kadaluwarsa"
                });
            }
        }

        return res.status(400).json({ 
            status: "error",
            code: 400,
            message: "Tipe Grant yang dicari tidak ditemukan" 
        });
    } catch (err) {
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
app.post('/introspect', (req, res) => {
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
app.post('/revoke', async (req, res) => {
    const { token, refresh_token } = req.body;

    try {
        // klo dikirim access token, masukkan ke tabel blacklist
        if (token) {
            const decoded = jwt.decode(token);
            const expiryDate = decoded && decoded.exp 
                ? new Date(decoded.exp * 1000).toISOString().slice(0, 19).replace('T', ' ')
                : new Date(Date.now() + 3600000).toISOString().slice(0, 19).replace('T', ' ');

            await db.query(
                'INSERT INTO oauth_token_blacklist (token, expired_at) VALUES (?, ?)',
                [token, expiryDate]
            );
        }

        // klo refresh token, ubah status 'revoked' jadi TRUE di tabel
        if (refresh_token) {
            await db.query(
                'UPDATE oauth_refresh_tokens SET revoked = TRUE WHERE refresh_token = ?',
                [refresh_token]
            );
        }

        return res.status(200).json({
            status: "success",
            message: "Token sudah berhasil di-revoke"
        });
    } catch (error) {
        return res.status(500).json({ 
            status: "error",
            code: 500,
            message: "Proses revoke token gagal" 
        });
    }
});

app.listen(PORT, () => {
    console.log(`[OAuth-Server] Berjalan di port ${PORT}`);
});