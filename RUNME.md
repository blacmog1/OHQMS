# 🚀 OHAQRS - HOW TO RUN THE SYSTEM

## ✅ Everything is Ready! 

Your system is configured and all files are in place. Follow these simple steps to run it.

---

## 📊 Quick Overview

Your OHAQRS Hospital Queue Management System consists of:

```
┌─────────────────┐         ┌──────────────────┐
│   Frontend      │         │   Backend        │
│  (React/Vite)   │────────│  (PHP 8.2)       │
│  :5173          │  JSON   │  :8000           │
└─────────────────┘         └──────────────────┘
         │                          │
         └──────────────────────────┘
              PostgreSQL (Neon Cloud)
```

---

## 🛠️ Setup Prerequisites

### Verify You Have These Installed:

**1. Check PHP** (Comes with the project)
```powershell
& "C:\Users\pc\sql\tools\php\php.exe" -v
```
✅ Should show: **PHP 8.2.28**

**2. Check Node.js** (You may need to install)
```powershell
node -v
npm -v
```
If command not found, [download Node.js](https://nodejs.org)

---

## 🎯 THREE WAYS TO RUN THE SYSTEM

### **Option 1: EASIEST - Use Startup Scripts (Recommended) ⭐**

#### Step 1: Open PowerShell as Administrator
- Press `Win + R`
- Type: `powershell`
- Click "Run as Administrator"

#### Step 2: Start Backend
```powershell
C:\Users\pc\sql\start-backend.ps1
```

You should see:
```
✅ PHP found: C:\Users\pc\sql\tools\php\php.exe
🚀 Starting PHP development server...
   Server: http://localhost:8000
```

**Leave this window open!**

#### Step 3: Open ANOTHER PowerShell Window
- Press `Win + R` again
- Type: `powershell`

#### Step 4: Start Frontend
```powershell
C:\Users\pc\sql\start-frontend.ps1
```

You should see:
```
✅ Using npm
📦 Installing dependencies...
🚀 Starting Vite development server...
   Server: http://localhost:5173
```

#### Step 5: Access the System
- Open browser → **http://localhost:5173**

✅ **Done! System is running!**

---

### **Option 2: MANUAL - Run Commands Directly**

#### Step 1: Start Backend Server

Open PowerShell and run:
```powershell
cd C:\Users\pc\sql\patient-queue-system

& "C:\Users\pc\sql\tools\php\php.exe" -S localhost:8000
```

Expected output:
```
Development Server started at http://localhost:8000
Press Ctrl-C to quit.
```

**Keep this window open!**

#### Step 2: Start Frontend Server

Open ANOTHER PowerShell window and run:
```powershell
cd C:\Users\pc\sql\patient-queue-system\frontend

npm install

npm run dev
```

Expected output:
```
  VITE v5.x.x  ready in xxx ms

  ➜  Local:   http://localhost:5173/
  ➜  press h to show help
```

#### Step 3: Open Browser
Visit: **http://localhost:5173**

---

### **Option 3: DOCKER - Use Containers**

If you have Docker installed:

```powershell
cd C:\Users\pc\sql

docker-compose up
```

Access at: **http://localhost:80**

> Note: Requires Docker Desktop. [Download here](https://www.docker.com/products/docker-desktop)

---

## 🔑 Login Credentials (After Database Setup)

Once database is initialized, use these test accounts:

### Admin Account
- **Email**: admin@hospital.local
- **Password**: Admin@123456

### Doctor Account
- **Email**: doctor1@hospital.local
- **Password**: Doctor@123456

### Patient Account
- **Email**: patient1@hospital.local
- **Password**: Patient@123456

> ⚠️ **Important**: Change these passwords in production!

---

## 📱 Access Points

Once running, you can access:

| Service | URL | Purpose |
|---------|-----|---------|
| **Frontend** | http://localhost:5173 | Web application (main entry) |
| **Backend API** | http://localhost:8000 | REST API endpoints |
| **Test Connection** | http://localhost:8000/actions/test_connection.php | Verify backend works |
| **Login API** | http://localhost:8000/actions/login.php | Authentication endpoint |

---

## 🔍 Testing Backend Connection

Open PowerShell and run:

```powershell
$response = Invoke-WebRequest -Uri "http://localhost:8000/actions/test_connection.php" -Method Get
$response.Content | ConvertFrom-Json | ConvertTo-Json
```

Should show:
```json
{
  "status": "success",
  "message": "Database connection successful"
}
```

---

## ❌ Troubleshooting

### Problem: "PHP command not found"
**Solution:**
```powershell
& "C:\Users\pc\sql\tools\php\php.exe" -S localhost:8000
```
Always use the full path to PHP!

### Problem: "Port 8000 already in use"
**Solution:** Use different port:
```powershell
& "C:\Users\pc\sql\tools\php\php.exe" -S localhost:8001
```

### Problem: "npm: command not found"
**Solution:**
1. [Download Node.js](https://nodejs.org)
2. Install it
3. Restart PowerShell
4. Verify: `node -v`

### Problem: "Database connection failed"
**Solution:** Check `.env` file:
```powershell
cat C:\Users\pc\sql\.env
```
Verify these exist:
- `PGHOST` = your Neon database host
- `PGUSER` = your Neon username
- `PGPASSWORD` = your Neon password
- `PGDATABASE` = your database name

### Problem: "Cannot find module" (frontend)
**Solution:** Reinstall dependencies:
```powershell
cd C:\Users\pc\sql\patient-queue-system\frontend
rm -r node_modules
npm install
npm run dev
```

### Problem: "Port 5173 already in use"
**Solution:**
```powershell
npm run dev -- --port 5174
```

---

## 📊 System Status Check

To verify everything is set up correctly:

```powershell
# Check PHP
& "C:\Users\pc\sql\tools\php\php.exe" -v

# Check Node.js
node -v

# Check .env exists
Test-Path "C:\Users\pc\sql\.env"

# Check project files
Get-ChildItem "C:\Users\pc\sql\patient-queue-system\actions" -Filter "*.php" | Measure-Object | Select-Object Count
```

---

## 🔧 Need to Stop the Servers?

### Stop Backend
In the backend PowerShell window:
```
Press Ctrl + C
```

### Stop Frontend
In the frontend PowerShell window:
```
Press Ctrl + C
```

---

## 📚 Next Steps

After system is running:

1. **Explore the UI**: Test all features
2. **Review API Docs**: See [API_REFERENCE.md](API_REFERENCE.md)
3. **Run Tests**: Follow [TESTING_GUIDE.md](TESTING_GUIDE.md)
4. **Setup Database**: Run SQL schema files (see [PRODUCTION_SETUP.md](PRODUCTION_SETUP.md))
5. **Deploy**: Use [PRODUCTION_SETUP.md](PRODUCTION_SETUP.md) for production

---

## 🎓 Common Tasks

### Start Both Servers at Once
```powershell
C:\Users\pc\sql\start-all.ps1
```

### View Real-Time Logs
Both servers output logs to PowerShell. Keep windows visible to see:
- API requests
- Database queries
- Errors and warnings

### Change Backend Port
```powershell
& "C:\Users\pc\sql\tools\php\php.exe" -S localhost:3000
```

### Build Frontend for Production
```powershell
cd C:\Users\pc\sql\patient-queue-system\frontend
npm run build
```

---

## ✅ Verification Checklist

- [ ] PHP is running on port 8000
- [ ] Frontend is running on port 5173
- [ ] Browser shows login page at http://localhost:5173
- [ ] Can see API response from test endpoint
- [ ] Database connection shows "successful"
- [ ] Can login with test credentials

---

## 🎉 You're All Set!

Your system is ready. Start with Option 1 (startup scripts) for the easiest experience.

**Questions?** Check the documentation:
- [README.md](README.md) - Project overview
- [API_REFERENCE.md](API_REFERENCE.md) - API documentation
- [TESTING_GUIDE.md](TESTING_GUIDE.md) - Testing procedures
- [PRODUCTION_SETUP.md](PRODUCTION_SETUP.md) - Deployment guide

---

## 📞 Quick Reference

| Need | Command |
|------|---------|
| Start Backend | `C:\Users\pc\sql\start-backend.ps1` |
| Start Frontend | `C:\Users\pc\sql\start-frontend.ps1` |
| Start Both | `C:\Users\pc\sql\start-all.ps1` |
| Stop Server | `Ctrl + C` in PowerShell |
| Check PHP | `& "C:\Users\pc\sql\tools\php\php.exe" -v` |
| Check Node | `node -v` |

---

**Ready to go! Happy hospital queue management! 🏥✨**
