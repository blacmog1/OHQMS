# OHAQRS API Reference

## Base URL
- **Development**: `http://127.0.0.1:8000`
- **Production**: `https://your-backend-api.com`

## Authentication

Most endpoints require a valid session. Users must login first to get a session cookie.

### Headers
```
Content-Type: application/json
Authorization: Bearer {token} (optional, for future JWT support)
X-CSRF-Token: {csrf_token} (for state-changing operations)
```

---

## Authentication Endpoints

### POST /actions/login.php
Authenticate user and create session.

**Request**
```json
{
  "email": "user@hospital.com",
  "password": "securepassword"
}
```

**Response (200 OK)**
```json
{
  "success": true,
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "user@hospital.com",
    "role": "patient"
  }
}
```

**Error Responses**
- `400 Bad Request`: Missing or invalid input
- `401 Unauthorized`: Invalid email or password
- `429 Too Many Requests`: Rate limited

---

### POST /actions/register.php
Register a new user.

**Request**
```json
{
  "firstName": "John",
  "lastName": "Doe",
  "email": "john@hospital.com",
  "phone": "+1234567890",
  "password": "securepassword",
  "confirmPassword": "securepassword",
  "role": "patient",
  "dateOfBirth": "1990-01-15"
}
```

**Response (200 OK)**
```json
{
  "success": true,
  "message": "Registration successful. Please login.",
  "user_id": 1
}
```

**Error Responses**
- `400 Bad Request`: Validation failed
- `409 Conflict`: Email already exists
- `422 Unprocessable Entity`: Invalid data

---

### POST /actions/logout.php
Destroy user session.

**Response (200 OK)**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

---

### POST /actions/forgot-password.php
Request password reset.

**Request**
```json
{
  "email": "user@hospital.com"
}
```

**Response (200 OK)**
```json
{
  "success": true,
  "message": "Password reset link sent to your email"
}
```

---

## Appointment Management

### POST /actions/book-appointment.php
Book a new appointment.

**Request**
```json
{
  "department_id": 1,
  "entry_channel": "online",
  "doctor_id": 5,
  "scheduled_slot_at": "2024-01-20T14:30:00+00:00"
}
```

**Response (200 OK)**
```json
{
  "success": true,
  "ticket": {
    "ticket_id": 123,
    "ticket_code": "GEN-014",
    "status": "waiting",
    "sequence_number": 14,
    "booked_at": "2024-01-20T10:00:00Z"
  }
}
```

---

### GET /actions/get-patient-appointments.php
Get patient's appointments.

**Query Parameters**
- `status` (optional): Filter by status (waiting, completed, cancelled, etc.)

**Response (200 OK)**
```json
{
  "success": true,
  "appointments": [
    {
      "ticket_id": 123,
      "ticket_code": "GEN-014",
      "status": "waiting",
      "doctor_name": "Dr. Ana Cruz",
      "department": "General Medicine",
      "scheduled_slot_at": "2024-01-20T14:30:00Z"
    }
  ]
}
```

---

### POST /actions/reschedule-appointment.php
Reschedule an existing appointment.

**Request**
```json
{
  "ticket_id": 123,
  "new_scheduled_slot": "2024-01-22T15:00:00+00:00",
  "reason": "Conflict with work schedule"
}
```

**Response (200 OK)**
```json
{
  "success": true,
  "message": "Appointment rescheduled successfully",
  "new_slot": "2024-01-22T15:00:00Z",
  "ticket_code": "GEN-014"
}
```

---

### POST /actions/cancel-appointment.php
Cancel an appointment.

**Request**
```json
{
  "ticket_id": 123,
  "reason": "Cannot attend appointment"
}
```

**Response (200 OK)**
```json
{
  "success": true,
  "message": "Appointment cancelled successfully"
}
```

---

## Queue Management

### GET /actions/get-active-tickets.php
Get active queue tickets.

**Query Parameters**
- `department_id` (optional): Filter by department
- `doctor_id` (optional): Filter by doctor
- `status` (optional): Comma-separated statuses

**Response (200 OK)**
```json
{
  "success": true,
  "tickets": [
    {
      "ticket_id": 123,
      "ticket_code": "GEN-014",
      "status": "called",
      "patient_name": "John Doe",
      "doctor_name": "Dr. Ana Cruz",
      "sequence_number": 14
    }
  ]
}
```

---

### GET /actions/get-queue-status.php
Get current queue status for a department.

**Query Parameters**
- `department_id` (required): Department ID

**Response (200 OK)**
```json
{
  "success": true,
  "department": "General Medicine",
  "queue_status": {
    "waiting": 12,
    "in_service": 3,
    "completed_today": 45,
    "average_wait_time": 25
  }
}
```

---

### POST /actions/serve-next-patient.php
Serve next patient (doctor/receptionist only).

**Request**
```json
{
  "department_id": 1,
  "doctor_id": 5
}
```

**Response (200 OK)**
```json
{
  "success": true,
  "ticket": {
    "ticket_id": 123,
    "ticket_code": "GEN-014",
    "patient_name": "John Doe",
    "room_number": "G-101"
  }
}
```

---

### POST /actions/complete-visit.php
Mark patient visit as completed (doctor only).

**Request**
```json
{
  "ticket_id": 123,
  "treatment_notes": "Patient treated for flu symptoms..."
}
```

**Response (200 OK)**
```json
{
  "success": true,
  "message": "Visit completed successfully"
}
```

---

## Doctor Management

### GET /actions/get-doctors.php
Get list of doctors.

**Query Parameters**
- `department_id` (optional): Filter by department

**Response (200 OK)**
```json
{
  "success": true,
  "doctors": [
    {
      "doctor_id": 1,
      "name": "Dr. Ana Cruz",
      "department": "Cardiology",
      "status": "available",
      "room_number": "C-201"
    }
  ]
}
```

---

### POST /actions/register_doctor.php
Register a new doctor (admin only).

**Request**
```json
{
  "firstName": "Maria",
  "lastName": "Gonzales",
  "email": "maria@hospital.com",
  "departmentId": 2,
  "roomNumber": "G-101"
}
```

**Response (200 OK)**
```json
{
  "success": true,
  "doctor_id": 15,
  "temporary_password": "TempPass123!"
}
```

---

### POST /actions/update-doctor-status.php
Update doctor availability status.

**Request**
```json
{
  "status": "available"
}
```

**Status Values**: `available`, `busy`, `on_break`

**Response (200 OK)**
```json
{
  "success": true,
  "message": "Doctor status updated"
}
```

---

## Schedule Management

### GET /actions/manage-doctor-schedule.php
Get doctor's schedule.

**Query Parameters**
- `doctor_id` (required for admin): Doctor ID
- `date` (optional): Specific date (YYYY-MM-DD)

**Response (200 OK)**
```json
{
  "success": true,
  "schedules": [
    {
      "schedule_id": 1,
      "doctor_id": 5,
      "schedule_date": "2024-01-20",
      "start_time": "09:00",
      "end_time": "17:00",
      "max_patients": 15,
      "is_available": true
    }
  ]
}
```

---

### POST /actions/manage-doctor-schedule.php
Create a new schedule.

**Request**
```json
{
  "doctor_id": 5,
  "schedule_date": "2024-01-20",
  "start_time": "09:00",
  "end_time": "17:00",
  "max_patients": 15,
  "is_available": true
}
```

**Response (200 OK)**
```json
{
  "success": true,
  "message": "Schedule created successfully",
  "schedule_id": 1
}
```

---

### PUT /actions/manage-doctor-schedule.php
Update a schedule.

**Request**
```json
{
  "schedule_id": 1,
  "is_available": false,
  "max_patients": 12
}
```

---

### DELETE /actions/manage-doctor-schedule.php
Delete a schedule.

**Query Parameters**
- `schedule_id` (required): Schedule ID

---

## Department Management

### GET /actions/get-departments.php
Get list of all departments.

**Response (200 OK)**
```json
{
  "success": true,
  "departments": [
    {
      "department_id": 1,
      "department_name": "General Medicine",
      "prefix_code": "GEN"
    },
    {
      "department_id": 2,
      "department_name": "Cardiology",
      "prefix_code": "CARD"
    }
  ]
}
```

---

## Dashboard & Analytics

### GET /actions/get-dashboard-stats.php
Get dashboard statistics.

**Response (200 OK)**
```json
{
  "success": true,
  "stats": {
    "total_appointments_today": 45,
    "completed_today": 38,
    "pending": 7,
    "average_wait_time": 25,
    "no_show_rate": 3.5
  }
}
```

---

### GET /actions/get-queue-analytics.php
Get advanced queue analytics (admin only).

**Query Parameters**
- `period`: `day`, `week`, or `month`
- `start_date` (optional): YYYY-MM-DD
- `end_date` (optional): YYYY-MM-DD
- `department_id` (optional): Filter by department

**Response (200 OK)**
```json
{
  "success": true,
  "period": "day",
  "overall_metrics": {
    "total_tickets": 45,
    "completed": 40,
    "no_show": 2,
    "avg_wait_time_minutes": 22.5,
    "avg_service_time_minutes": 15.3
  },
  "department_metrics": [
    {
      "department_name": "General Medicine",
      "total_tickets": 30,
      "completed": 28,
      "avg_wait_time": 20
    }
  ],
  "doctor_metrics": [
    {
      "name": "Dr. Ana Cruz",
      "total_patients": 12,
      "avg_service_time": 14
    }
  ]
}
```

---

## User Profile

### GET /actions/get-current-user.php
Get current logged-in user information.

**Response (200 OK)**
```json
{
  "success": true,
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@hospital.com",
    "role": "patient",
    "avatar": "https://..."
  }
}
```

---

## Error Handling

All endpoints follow standard HTTP status codes:

- `200 OK`: Request successful
- `400 Bad Request`: Validation failed
- `401 Unauthorized`: Authentication required
- `403 Forbidden`: Insufficient permissions
- `404 Not Found`: Resource not found
- `409 Conflict`: Resource conflict (duplicate email, etc.)
- `422 Unprocessable Entity`: Validation error with details
- `429 Too Many Requests`: Rate limit exceeded
- `500 Internal Server Error`: Server error

### Error Response Format
```json
{
  "success": false,
  "message": "Descriptive error message",
  "errors": {
    "field_name": "Field-specific error message"
  }
}
```

---

## Rate Limiting

- **General endpoints**: 100 requests per minute
- **Login**: 5 requests per 5 minutes (per IP)
- **Registration**: 3 requests per hour (per IP)

Rate limit info is included in response headers:
- `X-RateLimit-Remaining`: Remaining requests
- `X-RateLimit-Reset`: Unix timestamp when limit resets

---

## Data Types

### Role
- `patient` - Patient user
- `doctor` - Medical doctor
- `receptionist` - Reception staff
- `admin` - System administrator

### Queue Status
- `waiting` - Waiting in queue
- `checked_in` - Checked in at reception
- `called` - Called by doctor
- `in_service` - Being served by doctor
- `completed` - Visit completed
- `cancelled` - Appointment cancelled
- `no_show` - Patient did not show up

### Doctor Status
- `available` - Currently available
- `busy` - Currently with patient
- `on_break` - On break

---

## Pagination

Endpoints that return lists support pagination via query parameters:
- `page`: Page number (default: 1)
- `per_page`: Items per page (default: 20, max: 100)

Example: `GET /actions/get-active-tickets.php?page=2&per_page=50`

---

## Webhooks (Future)

Webhooks will be available for:
- Appointment created/updated/cancelled
- Queue position changed
- Doctor status changed
- Emergency triage alert

Subscribe at: POST `/webhooks/subscribe`

---

## Support

For API issues and questions:
- Email: api-support@ohaqrs.hospital
- Documentation: https://docs.ohaqrs.hospital
- Status Page: https://status.ohaqrs.hospital
