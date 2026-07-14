# ⚡ IMMEDIATE INSTRUCTIONS - WHAT TO DO NOW

## 🎯 Your System Status

```
✅ BACKEND: Running on http://localhost:8000
⏳ FRONTEND: Ready to start
⏳ BROWSER: Waiting for you to open it
```

**The backend server is actively running in your current terminal window.**

---

## 📋 THREE SIMPLE STEPS TO GET RUNNING

### **STEP 1: Keep Backend Running**

✅ **Already Done** - Your backend is running in PowerShell

Do NOT close that window!

---

### **STEP 2: Open NEW PowerShell Window**

**Important**: Do NOT use the same PowerShell window!

1. Press `Win + R` (Windows key + R)
2. Type: `powershell`
3. Press Enter

A brand new PowerShell window will open.

---

### **STEP 3: Start Frontend**

In your NEW PowerShell window, copy and paste this entire block:

```powershell
cd C:\Users\pc\sql\patient-queue-system\frontend
npm install
npm run dev
```

Then press Enter.

Wait for output like this:
```
VITE v5.x.x  ready in xxx ms

➜  Local:   http://localhost:5173/
```

---

### **STEP 4: Open Browser**

Once you see the "ready" message above, open your browser and go to:

```
http://localhost:5173
```

**YOU SHOULD SEE THE OHAQRS LOGIN PAGE!** 🎉

---

## ✨ What You're Seeing

### System is Working When:

1. ✅ Backend PowerShell shows: `Development Server started at http://localhost:8000`
2. ✅ Frontend PowerShell shows: `ready in xxx ms` with `http://localhost:5173`
3. ✅ Browser loads login page at http://localhost:5173
4. ✅ No error messages in either window

---

## 🔑 Test Login

Once the system loads, you can login with test accounts:

**Option 1: Admin**
```
Email: admin@hospital.local
Password: Admin@123456
```

**Option 2: Doctor**
```
Email: doctor1@hospital.local
Password: Doctor@123456
```

**Option 3: Patient**
```
Email: patient1@hospital.local
Password: Patient@123456
```

---

## 📞 If Something Goes Wrong

### "npm: command not found"

**Solution**: Install Node.js
1. Download: https://nodejs.org
2. Click "LTS" version
3. Run the installer
4. Restart PowerShell
5. Try again

### "Port 5173 already in use"

**Solution**: Use different port
```powershell
npm run dev -- --port 5174
```

### "Port 8000 already in use"

**Solution**: Kill process on port 8000 and restart
```powershell
netstat -ano | findstr :8000
taskkill /PID [PID_NUMBER] /F
```

### Backend window closed accidentally

**Solution**: Restart backend
```powershell
cd C:\Users\pc\sql\patient-queue-system
& "C:\Users\pc\sql\tools\php\php.exe" -S localhost:8000
```

### Cannot connect to database

**Check .env file**:
```powershell
cat C:\Users\pc\sql\.env | head -20
```

Should show:
```
PGHOST=ep-polished-band-ataoop94.c-9.us-east-1.aws.neon.tech
PGUSER=neondb_owner
PGPASSWORD=npg_k1DW5FlSLIwt
PGDATABASE=neondb
```

---

## 🔄 Layout Guide

Your screen should look like:

```
┌─────────────────────────────────────────┐
│                                         │
│         Browser Window                  │
│    http://localhost:5173                │
│                                         │
│    OHAQRS Login Page                    │
│                                         │
└─────────────────────────────────────────┘

     ↓

┌──────────────────┬──────────────────┐
│   PowerShell 1   │   PowerShell 2   │
│   (Backend)      │   (Frontend)     │
│   :8000          │   :5173          │
│   🟢 Running     │   🟢 Running     │
│                  │                  │
│  DO NOT CLOSE    │  npm run dev     │
└──────────────────┴──────────────────┘
```

---

## 🎬 Next Actions (After Frontend Loads)

1. **Login** with one of the test accounts
2. **Explore UI** - try booking an appointment
3. **Check Queue** - view patient queue
4. **View Dashboard** - see admin dashboard
5. **Review Documentation** - see API_REFERENCE.md for all endpoints

---

## 📚 Documentation Files (For Later)

When you're ready:

- **RUNME.md** - Complete run guide with all options
- **API_REFERENCE.md** - All 24+ API endpoints documented
- **TESTING_GUIDE.md** - Complete test cases
- **PRODUCTION_SETUP.md** - How to deploy to production
- **FILE_INVENTORY.md** - All files explained
- **ENHANCEMENT_SUMMARY.md** - All improvements made

---

## 📊 Architecture (What's Running)

```
Your Computer:
├─ Backend Server (PHP 8.2) → port 8000
├─ Frontend Server (React) → port 5173
└─ Database (Neon Cloud) → remote PostgreSQL

Frontend talks to Backend via HTTP API
Backend talks to Database via PostgreSQL
```

---

## ✅ Checklist Before Starting

- [ ] Backend PowerShell is open and showing "Development Server"
- [ ] You're ready to open a new PowerShell window
- [ ] Browser is ready (Chrome, Firefox, Edge, etc.)
- [ ] You have internet connection (for Neon Cloud database)

---

## 🚀 READY? LET'S GO!

1. **Open new PowerShell** (Win + R → powershell)
2. **Run this**:
   ```
   cd C:\Users\pc\sql\patient-queue-system\frontend
   npm install
   npm run dev
   ```
3. **Wait for "ready" message**
4. **Open browser**: http://localhost:5173
5. **Login** with test account
6. **Enjoy!** 🎉

---

## 💡 Pro Tips

- Keep both PowerShell windows visible
- Check PowerShell logs for errors
- If you close frontend, just run `npm run dev` again
- If you close backend, must restart it manually
- Ctrl+C to stop any server

---

## 🆘 EMERGENCY HELP

If something breaks:

1. **Close** all PowerShell windows
2. **Close** browser
3. **Open** new PowerShell
4. **Start backend** again: `C:\Users\pc\sql\start-backend.ps1`
5. **Open new PowerShell** for frontend
6. **Start frontend**: `C:\Users\pc\sql\start-frontend.ps1`
7. **Open browser** again

---

## 🎉 YOU'VE GOT THIS!

Your OHAQRS system is ready to rock! 

Go start the frontend now! 👉 [STEP 2 ABOVE](#step-2-open-new-powershell-window)

---

**Questions?** See the documentation files in `C:\Users\pc\sql\`  
**Everything working?** Explore and test the system!  
**Ready to deploy?** Check PRODUCTION_SETUP.md  

**Happy healthcare queue management!** 🏥✨
