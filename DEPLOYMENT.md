# OHAQMS Deployment Guide

## Architecture

- **Frontend**: React/TypeScript/Vite → Deploy to **Vercel**
- **Backend**: PHP 8.2 → Deploy to **Render** (or Railway, Fly.io)
- **Database**: PostgreSQL → **Neon Tech** (serverless PostgreSQL)

---

## 1. GitHub Repository

**Remote URL**: https://github.com/blacmog1/OHQMS.git

**Push command** (run in `C:\Users\pc\sql`):
```powershell
git push origin main
```

If prompted for credentials:
- **Username**: `blacmog1`
- **Password**: Use a GitHub Personal Access Token (PAT) with `repo` scope
- Generate PAT at: https://github.com/settings/tokens

---

## 2. Neon Database Setup

1. Go to https://neon.tech and create a new project
2. Create a database named `ohqms` (or use default `neondb`)
3. Copy the connection string (e.g., `postgresql://neondb_owner:password@ep-xxx.region.aws.neon.tech/neondb?sslmode=require`)
4. Run the schema migrations in order:
   ```powershell
   psql -U neondb_owner -d neondb -h ep-xxx.region.aws.neon.tech -f schema/01_create_tables.sql
   psql -U neondb_owner -d neondb -h ep-xxx.region.aws.neon.tech -f schema/02_queue_system.sql
   psql -U neondb_owner -d neondb -h ep-xxx.region.aws.neon.tech -f schema/03_patient_medical_records.sql
   # ... run all schema files in order
   ```
5. Create the admin account:
   ```powershell
   psql -U neondb_owner -d neondb -h ep-xxx.region.aws.neon.tech -f schema/09_create_admin_account.sql
   ```

---

## 3. Backend Deployment (Render)

1. Go to https://render.com and create a new **Web Service**
2. Connect your GitHub repo `blacmog1/OHQMS`
3. Configure:
   - **Root Directory**: `patient-queue-system`
   - **Runtime**: PHP
   - **Build Command**: `composer install --no-dev` (if using composer) or leave empty
   - **Start Command**: `php -S 0.0.0.0:$PORT -t .`
   - **Plan**: Free tier or higher

4. Add Environment Variables in Render dashboard:
   ```
   DB_PROVIDER=neon
   PGHOST=your-neon-host.region.aws.neon.tech
   PGPORT=5432
   PGUSER=neondb_owner
   PGPASSWORD=your-neon-password
   PGDATABASE=neondb
   PGSSLMODE=require
   DATABASE_URL=postgresql://neondb_owner:password@host/neondb?sslmode=require
   SESSION_SAMESITE=None
   SESSION_SECURE=true
   SESSION_HTTPONLY=true
   CORS_ALLOWED_ORIGINS=https://your-vercel-app.vercel.app
   ```

5. Deploy and note the backend URL (e.g., `https://ohqms-backend.onrender.com`)

---

## 4. Frontend Deployment (Vercel)

1. Go to https://vercel.com and import your GitHub repo `blacmog1/OHQMS`
2. Configure:
   - **Root Directory**: `patient-queue-system/frontend`
   - **Framework Preset**: Vite
   - **Build Command**: `npm run build`
   - **Output Directory**: `dist`

3. Add Environment Variable in Vercel dashboard:
   ```
   VITE_API_BASE_URL=https://your-render-backend.onrender.com
   ```

4. Deploy and note the frontend URL (e.g., `https://ohqms.vercel.app`)

---

## 5. Post-Deployment

1. Update CORS in backend `.env`:
   ```
   CORS_ALLOWED_ORIGINS=https://your-vercel-app.vercel.app
   ```

2. Login with admin credentials:
   - Email: `kevin@gmail.com`
   - Password: `Kevin@1234`

3. Test all features:
   - Admin: Dashboard, Staff Management, Patients, Appointments, Departments, Audit Logs, Settings
   - Doctor: Dashboard, Patient Search, Medical History
   - Receptionist: Dashboard, Patient Registration, Queue Management
   - Patient: Dashboard, Book Appointment, My Appointments, Queue Tracking, Medical Records, Profile

---

## 6. Local Development

```powershell
# Terminal 1 - Backend
C:\Users\pc\sql\tools\php\php.exe -S localhost:8000 -t C:\Users\pc\sql\patient-queue-system

# Terminal 2 - Frontend
cd C:\Users\pc\sql\patient-queue-system\frontend
npm run dev
```

Open: http://localhost:5173
