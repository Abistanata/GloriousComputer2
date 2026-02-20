# Panduan Deploy Laravel ke Vercel (Production)

Dokumen ini berisi langkah demi langkah untuk mengatasi tiga masalah production: **Google OAuth**, **images `/img/`**, dan **login user (seeder)**.

---

## 1. Google Login OAuth (Error 400: redirect_uri_mismatch)

### Yang sudah diubah di codebase
- **`config/services.php`**: Redirect URI Google sekarang punya fallback dari `APP_URL`. Jika `GOOGLE_REDIRECT_URI` tidak di-set, akan dipakai `APP_URL + '/auth/google/callback'` (tanpa trailing slash).

### Yang harus Anda lakukan

1. **Pastikan URL production satu sumber kebenaran**
   - Jika domain Vercel Anda **`gloriouscomputer.vercel.app`** (tanpa strip), pakai itu di mana-mana.
   - Jika **`glorious-computer.vercel.app`** (dengan strip), sesuaikan semua referensi.

2. **Set Environment Variables di Vercel Dashboard**
   - Buka project → **Settings** → **Environment Variables**.
   - Tambahkan (untuk Production):
     - `APP_URL` = `https://gloriouscomputer.vercel.app` (tanpa slash di akhir; sesuaikan dengan domain Anda).
     - `GOOGLE_CLIENT_ID` = (dari Google Cloud Console).
     - `GOOGLE_CLIENT_SECRET` = (dari Google Cloud Console).
     - `GOOGLE_REDIRECT_URI` = `https://gloriouscomputer.vercel.app/auth/google/callback` (tanpa slash di akhir).

3. **Google Cloud Console**
   - **APIs & Services** → **Credentials** → OAuth 2.0 Client ID.
   - **Authorized JavaScript origins**:  
     `https://gloriouscomputer.vercel.app`
   - **Authorized redirect URIs**:  
     `https://gloriouscomputer.vercel.app/auth/google/callback`
   - Jangan pakai trailing slash (`/`) di akhir.

4. **Redeploy**
   - Setelah mengubah env, lakukan **Redeploy** dari Vercel Dashboard (Deployments → ⋮ → Redeploy).

---

## 2. Images `/img/...` (404)

### Yang sudah diubah di codebase
- **`vercel.json`**: Route rewrite diperbaiki:
  - **Sebelum**: `"dest": "/public/images"` (salah; path ini tidak ada di output Vercel).
  - **Sesudah**: `"dest": "/images/$1"` sehingga request ke `/img/logotab.png` di-rewrite ke `/images/logotab.png`.

Dengan **outputDirectory: "public"**, isi folder `public/` diletakkan di root deployment. Jadi:
- `public/images/logotab.png` → URL: `https://domain-Anda/images/logotab.png`.
- Request ke `https://domain-Anda/img/logotab.png` akan dilayani sebagai `/images/logotab.png`.

### Yang harus Anda lakukan
- Pastikan file ada di **`public/images/`** (mis. `logotab.png`, `Logo2.png`, dll.).
- Setelah deploy, akses: `https://domain-Anda/img/logotab.png` (bukan `/images/...` jika Anda ingin konsisten pakai path `/img/`).

---

## 3. Login User Admin (Seeder) – "Error Sistem: Terjadi kesalahan saat menghubungi server"

Pesan itu bisa muncul karena: (1) request gagal (network/CORS), (2) server mengembalikan 500/HTML sehingga frontend tidak bisa parse JSON, atau (3) koneksi database gagal.

### Yang sudah diubah di codebase
- **TrustProxies**: `$proxies = '*'` agar URL dan scheme (HTTPS) dari Vercel proxy benar.
- **`vercel.json`**: `SESSION_DRIVER=cookie`, `SESSION_SECURE_COOKIE=true` (wajib untuk HTTPS).
- **Frontend (theme.blade.php)**:
  - Login: response body dibaca dulu sebagai `text`, lalu di-parse JSON; jika server mengembalikan 500/HTML, pesan error server (atau HTTP status) tetap bisa ditampilkan.
  - Forgot password: URL diubah ke `url("/forgot-password")` dan `credentials: 'same-origin'` ditambahkan.

### Yang harus Anda lakukan

1. **Environment Variables di Vercel (wajib untuk login + DB)**
   Di **Settings** → **Environment Variables**, set untuk **Production** (dan Preview jika perlu):
   - `DB_CONNECTION` = `mysql`
   - `DB_HOST` = `metro.proxy.rlwy.net` (atau host Railway Anda).
   - `DB_PORT` = `10612`
   - `DB_DATABASE` = `railway`
   - `DB_USERNAME` = `root`
   - `DB_PASSWORD` = (password Railway)
   - `APP_KEY` = (base64 key Laravel; jalankan `php artisan key:generate --show` lalu paste).
   - `SESSION_DRIVER` = `cookie`
   - `SESSION_SECURE_COOKIE` = `true`
   - (Opsional) `APP_DEBUG` = `false` di production.

   **Penting**: Jangan simpan `DB_PASSWORD` atau `APP_KEY` di `vercel.json`; hanya di Vercel Dashboard.

2. **Railway: akses dari luar**
   - Pastikan database MySQL Railway **Publicly accessible** (biasanya sudah untuk plan gratis).
   - Di Railway dashboard, cek **Connect** / **Variables** untuk memastikan host/port/user/password benar.

3. **Session**
   - Di Vercel, **SESSION_DRIVER** harus **cookie** (bukan `file`), karena serverless tidak punya filesystem persisten antarinvocation. Ini sudah di-set di `vercel.json`; pastikan tidak di-override oleh env lain (mis. dari `.env.production` yang tidak dipakai Vercel).

4. **Redeploy**
   - Setelah menambah/mengubah env, wajib **Redeploy** agar env baru terbaca.

5. **Jika masih error**
   - Set sementara `APP_DEBUG=true` di Vercel, coba login lagi, lalu cek **Logs** di Vercel (Functions) untuk stack trace/error (mis. DB connection refused, timeout).
   - Setelah selesai debug, set kembali `APP_DEBUG=false`.

---

## Ringkasan Environment Variables (Vercel Dashboard)

| Variable                 | Contoh / Catatan |
|-------------------------|------------------|
| `APP_URL`               | `https://gloriouscomputer.vercel.app` (no trailing slash) |
| `APP_KEY`               | Dari `php artisan key:generate --show` |
| `APP_DEBUG`             | `false` (production) |
| `DB_CONNECTION`         | `mysql` |
| `DB_HOST`               | Host Railway |
| `DB_PORT`               | Port Railway |
| `DB_DATABASE`           | Nama database |
| `DB_USERNAME`           | User database |
| `DB_PASSWORD`           | Password database |
| `SESSION_DRIVER`        | `cookie` |
| `SESSION_SECURE_COOKIE` | `true` |
| `GOOGLE_CLIENT_ID`      | Dari Google Cloud Console |
| `GOOGLE_CLIENT_SECRET`  | Dari Google Cloud Console |
| `GOOGLE_REDIRECT_URI`   | `https://gloriouscomputer.vercel.app/auth/google/callback` |

---

## File yang Diubah

- `config/services.php` – fallback redirect Google dari `APP_URL`
- `app/Http/Middleware/TrustProxies.php` – trust proxy `*`
- `vercel.json` – rewrite `/img/(.*)` → `/images/$1`, env session & APP_URL
- `resources/views/layouts/theme.blade.php` – penanganan response login (text + JSON), URL forgot-password, credentials

Setelah mengikuti langkah di atas, Google login, akses `/img/...`, dan login user admin seeder seharusnya berfungsi di production Vercel.
