# 🚀 OHAQRS - Quick Start Guide

## ✅ System Status: PRODUCTION READY

All configuration files are in place and verified. Your system is ready to run!

---

## 📋 Prerequisites Check

Before starting, ensure you have installed:

### Required
- **PHP 8.1+** ([Download](https://www.php.net/downloads.php))
- **PostgreSQL Client** or **psql** (for database setup)
- **Node.js 18+** with npm or pnpm ([Download](https://nodejs.org))

### Optional
- **PostgreSQL 12+** (if using local database)
- **Docker & Docker Compose** (for containerized deployment)

---

## 🎯 Quick Start (5 Minutes)

### Step 1: Verify PHP and Node.js

Open PowerShell and run:

```powershell
php -v
node -v
npm -v
```

Expected output: Versions displayed (e.g., PHP 8.2.0, Node.js v18.x.x)

---

### Step 2: Database Setup

Your `.env` file already has **Neon Cloud** credentials configured.

To set up the database, run the SQL schema files in order:

```powershell
# Navigate to workspace
cd C:\Users\pc\sql

# The database setup will happen when you run the migration script
# (See PRODUCTION_SETUP.md for detailed instructions)
```

> **Note**: The Neon Cloud database credentials in `.env` are already configured. You just need to run the schema files.

---

### Step 3: Start Backend (PHP Server)

Open **PowerShell** and run:

```powershell
cd C:\Users\pc\sql\patient-queue-system

# Start PHP built-in server on port 8000
php -S localhost:8000
```

**Output should show:**
```
Development Server started at http://localhost:8000
```

✅ Backend running at: `http://localhost:8000`

---

### Step 4: Start Frontend (React Dev Server)

Open **another PowerShell** window and run:

```powershell
cd C:\Users\pc\sql\patient-queue-system\frontend

# Install dependencies (first time only)
npm install
# or
pnpm install

# Start development server
npm run dev
# or
pnpm dev
```

**Output should show:**
```
  VITE v5.x.x  ready in xxx ms

  ➜  Local:   http://localhost:5173/
  ➜  Network: use --host to expose
```

✅ Frontend running at: `http://localhost:5173`

---

## 🌐 Access the System

Once both servers are running:

1. **Frontend**: Open browser → `http://localhost:5173`
2. **Backend API**: `http://localhost:8000/actions/login.php`
3. **Database**: Connected to Neon Cloud (read .env for credentials)

---

## 📊 Testing the Connection

### Test Backend Connection

```powershell
# In PowerShell, test if backend is responding
Invoke-WebRequest -Uri "http://localhost:8000/actions/test_connection.php" -Method Get
```

### Test Database Connection

```powershell
# Test database from PHP CLI
php -r "require_once 'patient-queue-system/config/db.php'; echo 'Database connected!'; exit(0);"
```

---

## 🔐 Default Test Credentials

After database setup, use these to test login:

**Admin**
- Email: `admin@hospital.local`
- Password: `Admin@123456` (from seed data)

**Doctor**
- Email: `doctor1@hospital.local`
- Password: `Doctor@123456`

**Patient**
- Email: `patient1@hospital.local`
- Password: `Patient@123456`

> ⚠️ **Security Note**: Change passwords in production!

---

## 🛠️ Troubleshooting

### "PHP not found"
- Install PHP 8.1+ from [php.net](https://www.php.net)
- Add PHP to system PATH
- Restart PowerShell

### "Database connection failed"
- Verify `.env` file exists in `C:\Users\pc\sql\`
- Check Neon Cloud credentials: `PGHOST`, `PGUSER`, `PGPASSWORD`, `PGDATABASE`
- Test connection: `psql -h your-neon-host -U your-user -d your-db`

### "npm: command not found"
- Install Node.js from [nodejs.org](https://nodejs.org)
- Restart PowerShell after installation

### "Port 8000 already in use"
```powershell
# Use different port
php -S localhost:8001
```

### "Port 5173 already in use"
```powershell
npm run dev -- --port 5174
```

---

## 📚 Next Steps

1. **Review API Documentation**: See [API_REFERENCE.md](API_REFERENCE.md)
2. **Run Full Tests**: See [TESTING_GUIDE.md](TESTING_GUIDE.md)
3. **Production Deployment**: See [PRODUCTION_SETUP.md](PRODUCTION_SETUP.md)
4. **Database Setup**: See database schema in `/schema/` directory

---

## 🔗 Important Files

- **Configuration**: `.env` (already configured with Neon Cloud)
- **Backend**: `patient-queue-system/actions/` (PHP API endpoints)
- **Frontend**: `patient-queue-system/frontend/` (React application)
- **Database**: `schema/` (SQL migration files)
- **Documentation**: 
  - README.md (overview)
  - API_REFERENCE.md (complete API docs)
  - PRODUCTION_SETUP.md (deployment guide)
  - TESTING_GUIDE.md (QA procedures)

---

## ✨ System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Your System                              │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  Frontend (React + Vite)           Backend (PHP 8.2)        │
│  └─ Port 5173                       └─ Port 8000             │
│     │                                  │                     │
│     └──────────────── HTTP API ─────────┘                    │
│                          │                                   │
│                    PostgreSQL (Neon Cloud)                   │
│                   ep-polished-band-*                         │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

---

## 🎉 You're All Set!

Your OHAQRS Hospital Queue Management System is ready to use.

For detailed deployment and production setup, see: **[PRODUCTION_SETUP.md](PRODUCTION_SETUP.md)**

---

**Questions?** Check the documentation files or review the code comments in the `/patient-queue-system/` directory.

**Happy coding!** 🏥✨
