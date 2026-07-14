# OHAQRS - Online Hospital & Queue Registration System

**A comprehensive, production-ready hospital patient queue management system with real-time tracking, automated notifications, and advanced analytics.**

## 🎯 Overview

OHAQRS is a full-stack web application designed to streamline hospital operations by:
- ✅ Managing patient appointments and walk-in queues
- ✅ Tracking real-time queue status and patient positions
- ✅ Automating email/SMS notifications
- ✅ Providing doctor and admin dashboards
- ✅ Maintaining comprehensive audit logs
- ✅ Generating advanced analytics and reports
- ✅ Supporting multiple user roles with fine-grained permissions

## 📋 Features

### Patient Features
- 👤 User registration and secure login
- 📅 Book appointments with available doctors
- ⏱️ Track appointment status and queue position
- 📱 Real-time queue tracking via dashboard
- 🔔 Email notifications for appointments
- 📋 View appointment history and medical records
- ⭐ Rate doctors and provide feedback
- 📞 Reschedule or cancel appointments

### Doctor Features
- 👨‍⚕️ Manage daily schedule and availability
- 📊 View queue and patient list
- ✅ Mark patients as completed
- 🚨 Emergency triage alerts
- 📈 View performance metrics
- 🔔 Real-time notifications
- 👁️ Patient medical records access

### Reception Staff
- 📝 Check-in walk-in patients
- 🎫 Generate queue tickets
- 📞 Call next patient
- 📊 View queue status
- 👥 Manage patient information

### Admin Features
- 👨‍💼 Manage users (create, edit, deactivate)
- 🏥 Manage departments and doctors
- 📊 Advanced queue analytics
- 📈 Performance reports
- 🔐 System configuration
- 🗂️ Audit logs and compliance
- 🔐 Security settings

## 🏗️ Technology Stack

### Backend
- **Framework**: PHP 8.1+
- **Database**: PostgreSQL 12+
- **Session**: PHP Session Management
- **Authentication**: Password hashing (Argon2id/bcrypt)
- **API**: RESTful JSON API
- **Security**: CORS, CSRF protection, Rate limiting

### Frontend
- **Framework**: React 18+ with TypeScript
- **Build Tool**: Vite
- **UI Library**: shadcn/ui + Radix UI
- **Styling**: Tailwind CSS
- **HTTP Client**: Fetch API

### Infrastructure
- **Database**: Neon Cloud PostgreSQL (scalable)
- **PHP Server**: Apache/Nginx
- **Optional**: Redis for caching
- **Deployment**: Docker support

## 📦 Installation

### Quick Start (Development)

```bash
# 1. Clone repository
git clone https://github.com/yourusername/ohaqrs.git
cd ohaqrs

# 2. Copy environment configuration
cp patient-queue-system/.env.example patient-queue-system/.env

# 3. Edit .env with your database credentials
nano patient-queue-system/.env

# 4. Run setup script
bash setup.sh

# 5. Start PHP server
cd patient-queue-system
php -S 127.0.0.1:8000

# 6. In another terminal, start frontend
cd patient-queue-system/frontend
npm run dev

# 7. Access at http://localhost:5173
```

### Production Deployment

See [PRODUCTION_SETUP.md](./PRODUCTION_SETUP.md) for detailed deployment instructions.

## 🔒 Security Features

### Authentication & Authorization
- ✅ Secure session management with HttpOnly cookies
- ✅ Argon2id password hashing (GPU-resistant)
- ✅ Role-based access control (RBAC)
- ✅ Session regeneration on login (prevents fixation)
- ✅ Automatic password rehashing on upgrade

### Protection Against Attacks
- ✅ CSRF token validation
- ✅ Rate limiting (brute force protection)
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS protection (output encoding)
- ✅ CORS with configurable origins
- ✅ Security headers (X-Frame-Options, CSP, etc.)

### Audit & Compliance
- ✅ Comprehensive audit logging
- ✅ Failed login tracking
- ✅ Activity monitoring
- ✅ Data encryption support
- ✅ Soft deletes for data recovery
- ✅ IP address logging
- ✅ User-Agent tracking

### Future Security Features
- 🔄 JWT token support
- 🔐 Two-Factor Authentication (2FA)
- 🔑 API token management
- 📧 Email verification
- 🛡️ Advanced threat detection

## 📊 Database Schema

### Core Tables
- `users` - User accounts and authentication
- `patient` - Patient profile information
- `doctor` - Doctor information and assignment
- `department` - Hospital departments
- `queue_ticket` - Queue tickets and appointment tracking
- `doctor_schedule` - Doctor availability schedules
- `emergency_patient` - Emergency triage bypass

### Audit & Security
- `audit_log` - Comprehensive activity logs
- `failed_login_attempt` - Failed authentication attempts
- `api_token` - API tokens for external integrations
- `user_session` - Session management
- `two_factor_setting` - 2FA configuration
- `password_reset_token` - Password reset tokens

### Analytics
- `real_time_tracking` - Event tracking for analytics
- `queue_metrics` - Daily queue performance metrics
- `appointment_feedback` - Patient feedback and ratings

## 🚀 API Endpoints

### Authentication
- `POST /actions/login.php` - User login
- `POST /actions/register.php` - User registration
- `POST /actions/logout.php` - User logout
- `POST /actions/forgot-password.php` - Password reset request

### Appointments
- `POST /actions/book-appointment.php` - Book new appointment
- `GET /actions/get-patient-appointments.php` - Get appointments
- `POST /actions/reschedule-appointment.php` - Reschedule appointment
- `POST /actions/cancel-appointment.php` - Cancel appointment
- `POST /actions/complete-visit.php` - Mark visit complete

### Queue Management
- `GET /actions/get-active-tickets.php` - Get active queue
- `GET /actions/get-queue-status.php` - Queue statistics
- `POST /actions/serve-next-patient.php` - Serve next patient
- `GET /actions/get-department-queue.php` - Department queue

### Management
- `GET /actions/get-doctors.php` - List doctors
- `POST /actions/register_doctor.php` - Register doctor
- `POST /actions/update-doctor-status.php` - Update doctor status
- `GET/POST/PUT/DELETE /actions/manage-doctor-schedule.php` - Schedule management

### Analytics
- `GET /actions/get-dashboard-stats.php` - Dashboard statistics
- `GET /actions/get-queue-analytics.php` - Advanced analytics

See [API_REFERENCE.md](./API_REFERENCE.md) for complete documentation.

## 📈 Performance & Scalability

### Database
- Optimized indexes on frequently queried columns
- Query optimization for queue ordering
- Connection pooling ready
- Supports horizontal scaling with PostgreSQL

### Backend
- Rate limiting to prevent abuse
- Response compression ready
- Optional Redis caching
- Efficient JSON API responses

### Frontend
- Vite for fast builds
- Code splitting and lazy loading
- Tailwind CSS for minimal CSS output
- Image optimization with shadcn/ui

## 📊 Analytics Dashboard

### Admin Analytics
- Total appointments and completion rates
- Average wait times and service times
- No-show analysis
- Doctor performance metrics
- Department comparison
- Hourly distribution analysis
- Custom date range reports

### Doctor Dashboard
- Today's queue
- Patient performance metrics
- Schedule management
- Patient ratings

### Patient Dashboard
- Appointment status
- Queue position
- Historical appointments
- Feedback history

## 🛠️ Development

### Project Structure
```
ohaqrs/
├── patient-queue-system/
│   ├── actions/              # API endpoints (PHP)
│   ├── config/               # Configuration files
│   ├── includes/             # Reusable PHP includes
│   ├── frontend/             # React frontend
│   │   ├── src/
│   │   │   ├── app/          # Main app components
│   │   │   ├── components/   # UI components
│   │   │   └── styles/       # Styling
│   │   └── package.json
│   └── templates/            # Email templates
├── schema/                   # Database migrations
├── tools/                    # Utility scripts
├── PRODUCTION_SETUP.md       # Deployment guide
└── API_REFERENCE.md          # API documentation
```

### Adding New Endpoints

1. Create endpoint in `patient-queue-system/actions/endpoint-name.php`
2. Include required security files
3. Implement validation and error handling
4. Add to API frontend client (`frontend/src/app/api.ts`)
5. Create UI component if needed
6. Document in API_REFERENCE.md

### Database Migrations

1. Create SQL file in `schema/` directory with number prefix
2. Test locally: `psql hospital_queue < schema/migration-file.sql`
3. Update documentation
4. Add to deployment guide

## 📝 Environment Configuration

See `.env.example` for all available configuration options:

```env
# Database
DB_HOST=localhost
DB_PORT=5432
DB_USER=app_user
DB_PASS=secure_password
DB_NAME=hospital_queue

# Security
SESSION_SECURE=true
SESSION_SAMESITE=Strict
CSRF_TOKEN_LENGTH=32

# Rate Limiting
RATE_LIMIT_ENABLED=true
LOGIN_RATE_LIMIT_REQUESTS=5

# Email
MAIL_DRIVER=smtp
MAIL_FROM_ADDRESS=noreply@hospital.com

# Logging
LOG_LEVEL=info
LOG_PATH=/var/log/ohaqrs
```

## 🧪 Testing

### Manual Testing Checklist

**Authentication**
- [ ] Register new user
- [ ] Login with valid credentials
- [ ] Login with invalid credentials
- [ ] Password reset flow
- [ ] Logout

**Appointments**
- [ ] Book appointment as patient
- [ ] Reschedule appointment
- [ ] Cancel appointment
- [ ] View appointment history

**Queue Management**
- [ ] Check-in walk-in patient
- [ ] View queue status
- [ ] Serve next patient
- [ ] Mark patient as completed

**Admin Features**
- [ ] View analytics dashboard
- [ ] Create new doctor
- [ ] Manage departments
- [ ] View audit logs
- [ ] User management

**Security**
- [ ] Rate limiting works (try 6+ logins)
- [ ] CSRF protection active
- [ ] SQL injection prevented
- [ ] XSS protection active
- [ ] Session security maintained

## 📞 Support & Documentation

- **Documentation**: See individual markdown files
- **API Reference**: [API_REFERENCE.md](./API_REFERENCE.md)
- **Setup Guide**: [PRODUCTION_SETUP.md](./PRODUCTION_SETUP.md)
- **Issues**: GitHub Issues
- **Email**: support@ohaqrs.hospital

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open Pull Request

## 📄 License

This project is proprietary software. All rights reserved.

## ⚠️ Disclaimer

This system is designed for healthcare institutions. Ensure compliance with local healthcare regulations (HIPAA, GDPR, etc.) before deployment.

## 🙏 Acknowledgments

- Built with PHP, PostgreSQL, React, and TypeScript
- UI components from shadcn/ui and Radix UI
- Icons from Lucide React
- Styling with Tailwind CSS

---

**Last Updated**: January 2024  
**Version**: 1.0.0  
**Status**: Production Ready ✅
