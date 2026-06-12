import dotenv from 'dotenv';
dotenv.config();
import express from 'express';
import mysql from 'mysql2/promise';
import bcrypt from 'bcrypt';
import jwt from 'jsonwebtoken';
import morgan from 'morgan';

const app = express();
app.use(express.json());
app.use(morgan('dev'));

const PORT = process.env.PORT || 4000;
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

// ------------------------------
// Fungsi Buat Hasilin Token JWT
// ------------------------------
function generateTokens(user) {
    const accessToken = jwt.sign({ 
        id: user.id, 
        name: user.name, 
        email: user.email 
    }, JWT_SECRET, { 
        expiresIn: '1h' 
    });
    const refreshToken = jwt.sign({ 
        id: user.id 
    }, JWT_REFRESH_SECRET, { 
        expiresIn: '7d' 
    });
    return { 
        accessToken, 
        refreshToken 
    };
}

// -----------------
// POST /oauth/token 
// -----------------
app.post('/oauth/token', async (req, res) => {
    const { email, password, google_token, refresh_token, grant_type } = req.body;

    try {
        let user = null;
        if (grant_type === 'refresh_token' || refresh_token) {
            const tokenToVerify = refresh_token;
            if (!tokenToVerify) {
                return res.status(400).json({
                  error: "Refresh token diperlukan"  
                });
            }

            try {
                const decode = jwt.verify(tokenToVerify, JWT_REFRESH_SECRET);
                
                const [rows] = await db.query('SELECT * FROM citizen_users WHERE id = ?', [decode.id]);
                user = rows[0];
                if (!user) {
                    return res.status(401).json({
                        error: "User tidak ditemukan"
                    });
                }
            } catch (err) {
                return res.status(401).json({ 
                    error: "Refresh token tidak valid atau expired" 
                });
            }
        }

        // Login Google OAuth
        else if (google_token) {
            // Verifikasi token google ke API Google
            const googleRes = await fetch(`https://oauth2.googleapis.com/tokeninfo?id_token=${google_token}`);
            if (!googleRes.ok) {
                return res.status(400).json({ 
                    error: "Google token tidak valid" 
                }
            )};
            
            const googleUser = await googleRes.json();
            
            // Cek apakah email sudah terdaftar di MySQL
            const [rows] = await db.query('SELECT * FROM citizen_users WHERE email = ?', [googleUser.email]);
            user = rows[0];

            // Jika belum, buat akun baru
            if (!user) {
                const [result] = await db.query(
                    'INSERT INTO citizen_users (name, email, nik) VALUES (?, ?, ?)',
                    [googleUser.name, googleUser.email, `GGL-${Date.now()}`] // NIK dummy buat user Google
                );
                const [newUser] = await db.query('SELECT * FROM citizen_users WHERE id = ?', [result.insertId]);
                user = newUser[0];
            }
        } 

        // Login biasa (Email & Password)
        else if (email && password) {
            const [rows] = await db.query('SELECT * FROM citizen_users WHERE email = ?', [email]);
            user = rows[0];

            if (!user || !user.password_hash) {
                return res.status(401).json({ 
                    error: "Email atau password salah" 
                });
            }

            // Cek password 
            const isMatch = await bcrypt.compare(password, user.password_hash);
            if (!isMatch) return res.status(401).json({ error: "Email atau password salah" });
        } else {
            return res.status(400).json({ 
                error: "Method login tidak dikenali" 
            });
        }

        const tokens = generateTokens(user);
        return res.json({
            access_token: tokens.accessToken,
            refresh_token: tokens.refreshToken,
            expires_in: 3600
        });

    } catch (error) {
        console.error(error);
        return res.status(500).json({ 
            error: "Internal Server Error" 
        });
    }
});

// ----------------------
// POST /oauth/introspect
// ----------------------
app.post('/oauth/introspect', (req, res) => {
    const { token } = req.body;
    if (!token) return res.status(400).json({ 
        active: false, 
        error: "Token diperlukan" 
    });

    try {
        const decoded = jwt.verify(token, JWT_SECRET);
        return res.json({
            active: true,
            client_id: "smarttransit_warga",
            exp: decoded.exp,
            user: { 
                id: decoded.id, 
                name: decoded.name, 
                email: decoded.email 
            }
        });
    } catch (err) {
        return res.json({ 
            active: false, 
            message: "Token expired atau tidak valid" 
        });
    }
});

// ------------------
// POST /oauth/revoke
// ------------------
app.post('/oauth/revoke', (req, res) => {
    const { token } = req.body;
    // Pada sistem stateless tanpa redis/DB token blacklist, cukup me-return success
    return res.json({ 
        status: "success", 
        message: "Token berhasil dicabut" 
    });
});

app.listen(PORT, () => {
    console.log(`[OAuth-Server] Berjalan di port ${PORT}`);
});