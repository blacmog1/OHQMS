# OHAQRS – Online Hospital & Queue Registration System

A full-stack hospital queue management system with a React/Vite frontend and PHP backend connected to a Neon Cloud PostgreSQL database.

---

## 📁 Project Structure

```
patient-queue-system/
├── actions/          ← PHP backend API endpoints
│   ├── login.php
│   ├── register.php
│   ├── forgot-password.php
│   ├── book-appointment.php
│   ├── get-active-tickets.php
│   ├── get-dashboard-stats.php
│   ├── seed.php          ← Run once to create demo accounts
│   └── ...more endpoints
├── config/
│   ├── db.php            ← Neon PostgreSQL connection (pre-configured)
│   └── session.php
├── src/              ← React frontend (TypeScript)
│   └── app/
│       ├── App.tsx       ← Main app + routing
│       ├── api.ts        ← API client (calls PHP backend)
│       └── components/   ← All dashboards and pages
├── database.sql      ← Database schema (run once in Neon dashboard)
├── package.json
├── vite.config.ts
└── index.html
```

---

## ⚙️ Requirements

| Tool | Minimum Version |
|------|-----------------|
| Node.js | v18 or higher |
| PHP | v8.1 or higher (with `pdo_pgsql` and `pgsql` extensions enabled) |
| XAMPP | v8.2 recommended |

---

## 🚀 Setup Instructions

### Step 1 – Install dependencies

```bash
npm install
```

### Step 2 – Configure PHP

In your XAMPP `php.ini`, make sure these lines are **uncommented** (no semicolon at start):

```ini
extension=pdo_pgsql
extension=pgsql
```

> Usually found around line 947–949 of `php.ini`.
> Tip: Search for `;extension=pdo_pgsql` and remove the semicolon.

### Step 3 – Start the PHP backend server

```bash
# Run from inside the project folder
php -c "C:\path\to\xampp\php\php.ini" -S 127.0.0.1:8000 -t .
```

Replace `C:\path\to\xampp` with your actual XAMPP installation path.

### Step 4 – Seed the database (first time only)

Open your browser and navigate to:

```
http://127.0.0.1:8000/actions/seed.php
```

You should see a JSON success response confirming the 4 demo accounts were created.

### Step 5 – Start the React frontend

```bash
npm run dev
```

Open your browser to: **http://localhost:5173/**

---

## 🔑 Admin Login Account

| Role | Email | Password |
|------|-------|----------|
| 🔧 Admin | kevin@gmail.com | Kevin@1234 |

> New users must register via the application. Staff accounts are created by the admin.

---

## 🛠️ Features & Dashboards

- **Patient Dashboard**: Book appointments, track queue position, view status
- **Reception Dashboard**: Register walk-in patients, manage queue
- **Doctor Dashboard**: View assigned patients, mark completed, update availability
- **Admin Dashboard**: System-wide statistics, department management

---

## 🗄️ Database

The backend connects to a **Neon Cloud PostgreSQL** database (already configured in `config/db.php`).

If you need to reset the database schema, run the contents of `database.sql` in your Neon dashboard SQL editor.

---

## 🔧 Vite Proxy (Frontend → Backend)

The `vite.config.ts` file proxies all `/actions/*` requests from the React frontend to the PHP backend at `http://127.0.0.1:8000`. This allows the frontend at port 5173 to call PHP APIs without CORS errors.

---

## 📝 Bug Fixes Applied

| Bug | Fix |
|-----|-----|
| Login failing ("invalid email/password") | Added `seed.php` to insert demo accounts into Neon DB |
| Forgot password not working | Created `actions/forgot-password.php` endpoint + modal UI in login page |
| Appointments not showing across dashboards | Fixed `get-active-tickets.php` with role-aware filtering (doctor/patient/admin/reception) |
| PHP extension not loading | Fixed `php.ini` include_path syntax + enabled `pdo_pgsql` and `pgsql` |

---

## 📧 Contact

Built as part of the OHAQRS academic project.