# OHAQRS - Testing & Quality Assurance Guide

## 🧪 Testing Strategy

This guide covers manual and automated testing for the OHAQRS system.

## ✅ Pre-Deployment Testing Checklist

### 1. Database & Backend

- [ ] Database connection working
- [ ] All migrations applied successfully
- [ ] Demo data seeded correctly
- [ ] Prepared statements preventing SQL injection
- [ ] Error handling working properly
- [ ] Logging system active

**Test Commands:**
```bash
# Test database connection
curl http://127.0.0.1:8000/actions/test_connection.php

# Check logs
tail -f /var/log/ohaqrs/ohaqrs_security.log
```

### 2. Authentication & Security

#### Registration
- [ ] Valid registration accepted
- [ ] Invalid email rejected
- [ ] Weak password rejected
- [ ] Duplicate email rejected
- [ ] Password confirmation validation
- [ ] Role selection working

**Test Case 1 - Valid Registration:**
```bash
curl -X POST http://127.0.0.1:8000/actions/register.php \
  -H "Content-Type: application/json" \
  -d '{
    "firstName": "Test",
    "lastName": "User",
    "email": "testuser@hospital.com",
    "phone": "+1234567890",
    "password": "SecurePass123",
    "confirmPassword": "SecurePass123",
    "role": "patient"
  }'
```

**Test Case 2 - Weak Password:**
```bash
curl -X POST http://127.0.0.1:8000/actions/register.php \
  -H "Content-Type: application/json" \
  -d '{
    "firstName": "Test",
    "lastName": "User",
    "email": "test2@hospital.com",
    "phone": "+1234567890",
    "password": "weak",
    "confirmPassword": "weak",
    "role": "patient"
  }'
# Should return 422 Unprocessable Entity
```

#### Login & Session
- [ ] Valid login creates session
- [ ] Invalid password rejected
- [ ] Non-existent email doesn't leak info
- [ ] Rate limiting after 5 attempts
- [ ] Session regeneration on login
- [ ] Logout destroys session
- [ ] Session timeout working

**Test Case 3 - Rate Limiting:**
```bash
# Run this 6 times in quick succession
for i in {1..6}; do
  curl -X POST http://127.0.0.1:8000/actions/login.php \
    -H "Content-Type: application/json" \
    -d '{
      "email": "patient@demo.com",
      "password": "wrongpassword"
    }'
done
# 6th attempt should return 429 Too Many Requests
```

### 3. Appointment Management

#### Book Appointment
- [ ] Patient can book appointment
- [ ] Valid department required
- [ ] Valid doctor required (if specified)
- [ ] Future date/time required
- [ ] Ticket code generated correctly
- [ ] Email notification sent

**Test Case 4 - Book Appointment:**
```bash
curl -X POST http://127.0.0.1:8000/actions/book-appointment.php \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your_session_id" \
  -d '{
    "department_id": 1,
    "entry_channel": "online",
    "doctor_id": 1,
    "scheduled_slot_at": "2024-01-25T14:30:00Z"
  }'
```

#### Reschedule Appointment
- [ ] Patient can reschedule own appointment
- [ ] Invalid ticket rejected
- [ ] Future date required
- [ ] Only waiting status allowed
- [ ] Notification sent

**Test Case 5 - Reschedule:**
```bash
curl -X POST http://127.0.0.1:8000/actions/reschedule-appointment.php \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your_session_id" \
  -d '{
    "ticket_id": 1,
    "new_scheduled_slot": "2024-01-27T15:00:00Z",
    "reason": "Conflict with work"
  }'
```

#### Cancel Appointment
- [ ] Patient can cancel own appointment
- [ ] Receptionist can cancel any
- [ ] Notification sent
- [ ] Status updated to cancelled

### 4. Queue Management

#### Get Active Tickets
- [ ] Patient sees own tickets
- [ ] Doctor sees department tickets
- [ ] Admin sees all tickets
- [ ] Filtering by status works
- [ ] Pagination works

**Test Case 6 - Get Tickets:**
```bash
curl http://127.0.0.1:8000/actions/get-active-tickets.php \
  -H "Cookie: PHPSESSID=your_session_id"
```

#### Serve Next Patient
- [ ] Next patient in queue selected
- [ ] Atomic operation (no double-serving)
- [ ] Status updated to "called"
- [ ] Doctor assigned correctly
- [ ] Patient notification sent

**Test Case 7 - Serve Next:**
```bash
curl -X POST http://127.0.0.1:8000/actions/serve-next-patient.php \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your_session_id" \
  -d '{"department_id": 1}'
```

### 5. Doctor Management

- [ ] Doctor registration working
- [ ] Doctor can update status
- [ ] Schedule management functional
- [ ] Status updates reflected in queue
- [ ] Doctor sees correct statistics

**Test Case 8 - Update Doctor Status:**
```bash
curl -X POST http://127.0.0.1:8000/actions/update-doctor-status.php \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your_session_id" \
  -d '{"status": "busy"}'
```

### 6. Admin Functions

- [ ] View all users
- [ ] View analytics
- [ ] Create/edit departments
- [ ] Create/edit doctors
- [ ] View audit logs
- [ ] System configuration accessible

**Test Case 9 - Get Analytics:**
```bash
curl "http://127.0.0.1:8000/actions/get-queue-analytics.php?period=day" \
  -H "Cookie: PHPSESSID=your_session_id"
```

### 7. Data Validation

- [ ] Email format validation
- [ ] Phone number validation
- [ ] Date/time format validation
- [ ] Integer ranges validated
- [ ] Required fields enforced
- [ ] Error messages descriptive

### 8. Error Handling

- [ ] 400 Bad Request for invalid input
- [ ] 401 Unauthorized for unauthenticated
- [ ] 403 Forbidden for unauthorized
- [ ] 404 Not Found for missing resources
- [ ] 422 Unprocessable Entity for validation
- [ ] 429 Too Many Requests for rate limit
- [ ] 500 Internal Server Error handled gracefully

### 9. CORS & Security

- [ ] CORS headers correct
- [ ] OPTIONS preflight working
- [ ] Origin validation strict
- [ ] Credentials included properly
- [ ] Security headers present
  - [ ] X-Frame-Options
  - [ ] X-Content-Type-Options
  - [ ] X-XSS-Protection

**Test CORS:**
```bash
# Check headers
curl -i http://127.0.0.1:8000/actions/login.php

# Check security headers
curl -i http://127.0.0.1:8000/actions/get-current-user.php
```

### 10. Performance Testing

- [ ] Page load time < 3 seconds
- [ ] API response time < 500ms
- [ ] Database queries optimized
- [ ] No N+1 query problems
- [ ] Memory usage acceptable
- [ ] Concurrent users supported (load test)

**Simple Load Test:**
```bash
# Using Apache Bench
ab -n 1000 -c 10 http://127.0.0.1:8000/actions/get-departments.php

# Using wrk (if available)
wrk -t4 -c100 -d30s http://127.0.0.1:8000/actions/get-departments.php
```

## 🧩 Integration Testing

### User Flow Testing

#### Complete Patient Journey
1. Register as patient
2. Browse departments
3. Select doctor
4. Book appointment
5. Receive confirmation email
6. Check queue status
7. Receive check-in notification
8. Complete visit feedback
9. View appointment history

#### Complete Doctor Journey
1. Login as doctor
2. View today's schedule
3. Update availability status
4. View queue
5. Serve next patient
6. Complete patient visit
7. Review performance metrics

#### Complete Admin Journey
1. Login as admin
2. Create new department
3. Register new doctor
4. View system analytics
5. Check audit logs
6. Configure system settings
7. Manage users

## 📊 API Testing

### Using Postman/Insomnia

Import collection for all endpoints:
1. Create new environment with variables:
   - `base_url`: http://127.0.0.1:8000
   - `session_id`: obtained from login

2. Test each endpoint:
   - Check status code
   - Validate response structure
   - Verify data types
   - Test error cases

## 🔍 Code Quality

### PHP Code Quality
```bash
# Using PHPStan
phpstan analyse patient-queue-system/actions

# Using PHP Mess Detector
phpmd patient-queue-system/actions text cleancode,codesize

# Using PHP CodeSniffer
phpcs patient-queue-system/actions
```

### Frontend Code Quality
```bash
cd patient-queue-system/frontend

# TypeScript check
npx tsc --noEmit

# ESLint
npm run lint

# Prettier formatting
npm run format
```

## 📋 Browser Compatibility

Test on:
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Mobile Chrome
- [ ] Mobile Safari

## ♿ Accessibility Testing

- [ ] Keyboard navigation works
- [ ] Screen reader compatible
- [ ] Color contrast sufficient (WCAG AA)
- [ ] Form labels present
- [ ] ARIA labels where needed
- [ ] Focus indicators visible

## 📱 Mobile Testing

- [ ] Responsive layout
- [ ] Touch-friendly buttons
- [ ] Mobile performance good
- [ ] Scrolling smooth
- [ ] Modals work properly
- [ ] Camera access (if needed)

## 🔐 Security Testing

### Manual Security Tests
- [ ] SQL injection attempts blocked
- [ ] XSS payloads escaped
- [ ] CSRF tokens validated
- [ ] Authentication required
- [ ] Authorization enforced
- [ ] Sensitive data not exposed
- [ ] Rate limiting effective

**SQL Injection Test:**
```bash
curl -X POST http://127.0.0.1:8000/actions/login.php \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@hospital.com\" OR \"1\"=\"1",
    "password": "test"
  }'
# Should be safely escaped, not execute injection
```

**XSS Test:**
```bash
# In registration form
"firstName": "<script>alert(\"XSS\")</script>"
# Should be escaped in output
```

## 📊 Test Results Template

```
Test Date: 2024-01-20
Tester: [Name]
Environment: Development/Staging/Production

PASSED TESTS:
- [x] Authentication flow
- [x] Appointment booking
- [x] Queue management

FAILED TESTS:
- [ ] None

ISSUES FOUND:
- [List any issues]

BLOCKERS:
- [List any blockers]

RECOMMENDATIONS:
- [List recommendations]

Sign-off: _________________ Date: __________
```

## 🚀 Deployment Checklist

Before deploying to production:

- [ ] All tests passed
- [ ] Code reviewed
- [ ] Database backed up
- [ ] Security settings configured
- [ ] SSL certificate installed
- [ ] Email configured
- [ ] Logging configured
- [ ] Rate limiting active
- [ ] CORS configured for production
- [ ] Environment variables set
- [ ] Admin account created
- [ ] Documentation updated

## 📞 Support

For testing issues or questions:
- Contact: testing@ohaqrs.hospital
- Reference: This document location
