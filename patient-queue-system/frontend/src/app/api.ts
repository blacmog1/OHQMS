/**
 * Centralized API helper functions to communicate with the PHP backend actions.
 *
 * - On localhost (dev): uses Vite proxy → relative paths like /actions/login.php
 * - On Vercel (production): uses VITE_API_BASE_URL env var pointing to Render backend
 */

// Base URL: use Vite proxy in dev (empty = relative path), direct URL in production
const BASE_URL = import.meta.env.VITE_API_BASE_URL ?? '';

// Helper to make fetch requests and check JSON response
async function request(path: string, options: RequestInit = {}) {
  const url = `${BASE_URL}${path}`;

  // 'include' is needed for cross-origin requests (e.g., Render backend)
  // For same-origin (Vite proxy or same host), 'same-origin' is sufficient
  options.credentials = BASE_URL ? 'include' : 'same-origin';

  const response = await fetch(url, options);
  const data = await response.json().catch(() => null);

  if (!response.ok) {
    const errorMsg = data?.message || `HTTP error! Status: ${response.status}`;
    const errors = data?.errors || null;
    throw { message: errorMsg, errors, status: response.status };
  }

  return data;
}

export const api = {
  // 1. Auth APIs
  async login(email: string, password: string) {
    return request('/actions/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password }),
    });
  },

  async logout() {
    return request('/actions/logout.php', {
      method: 'POST',
    });
  },

  async register(payload: any) {
    return request('/actions/register.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
  },

  async registerDoctor(payload: any) {
    return request('/actions/register_doctor.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
  },

  async forgotPassword(email: string) {
    return request('/actions/forgot-password.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email }),
    });
  },

  // 2. Department & Doctor lookup APIs
  async getDepartments() {
    return request('/actions/get-departments.php');
  },

  async getDoctors(departmentId?: number) {
    const url = departmentId !== undefined 
      ? `/actions/get-doctors.php?department_id=${departmentId}`
      : '/actions/get-doctors.php';
    return request(url);
  },

  // 3. Queue & Booking APIs
  async bookAppointment(payload: {
    department_id: number;
    entry_channel: 'online' | 'walk_in';
    doctor_id?: number | null;
    scheduled_slot_at?: string | null;
    patient_name?: string; // used for reception walk-ins
    phone?: string;        // used for reception walk-ins
  }) {
    return request('/actions/book-appointment.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
  },

  async cancelAppointment(ticketId: number, markAs?: 'cancelled' | 'no_show') {
    return request('/actions/cancel-appointment.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ticket_id: ticketId, mark_as: markAs ?? 'cancelled' }),
    });
  },

  async getQueueStatus(ticketId?: number) {
    const url = ticketId !== undefined
      ? `/actions/get-queue-status.php?ticket_id=${ticketId}`
      : '/actions/get-queue-status.php';
    try {
      return await request(url);
    } catch (err: any) {
      if (err.status === 404) {
        return { success: false, message: err.message, ticket: null };
      }
      throw err;
    }
  },

  async getActiveTickets(filters: {
    department_id?: number | null;
    doctor_id?: number | null;
    status?: string | null;
  } = {}) {
    const params = new URLSearchParams();
    if (filters.department_id) params.append('department_id', String(filters.department_id));
    if (filters.doctor_id) params.append('doctor_id', String(filters.doctor_id));
    if (filters.status) params.append('status', filters.status);
    
    const url = `/actions/get-active-tickets.php?${params.toString()}`;
    return request(url);
  },

  // 4. Staff Flow APIs
  async serveNextPatient(departmentId?: number) {
    return request('/actions/serve-next-patient.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ department_id: departmentId }),
    });
  },

  async completeVisit(ticketId: number, treatmentNotes?: string, symptoms?: string) {
    return request('/actions/complete-visit.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ticket_id: ticketId, treatment_notes: treatmentNotes, symptoms: symptoms }),
    });
  },

  async updateDoctorStatus(status: 'available' | 'busy' | 'on_break', doctorId?: number) {
    return request('/actions/update-doctor-status.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ status, doctor_id: doctorId }),
    });
  },

  // 5. Admin KPIs API
  async getDashboardStats() {
    return request('/actions/get-dashboard-stats.php');
  },

  async getCurrentUser() {
    return request('/actions/get-current-user.php');
  },

  async getPatientAppointments() {
    return request('/actions/get-patient-appointments.php');
  },

  async changePassword(currentPassword: string, newPassword: string) {
    return request('/actions/change-password.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ current_password: currentPassword, new_password: newPassword, confirm_password: newPassword }),
    });
  },

  async getMedicalRecords(patientId?: number) {
    const url = patientId ? `/actions/get-medical-records.php?patient_id=${patientId}` : '/actions/get-medical-records.php';
    return request(url);
  },

  async getProfile() {
    return request('/actions/get-profile.php');
  },

  async updateProfile(payload: {
    first_name: string;
    last_name: string;
    phone?: string;
    dob?: string;
    gender?: string;
    address?: string;
  }) {
    return request('/actions/update-profile.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
  },

  async addMedicalRecord(payload: {
    patient_id: number;
    queue_ticket_id?: number;
    symptoms: string;
    treatment_notes: string;
  }) {
    return request('/actions/add-medical-record.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
  },

  async patientCheckIn(ticketCode: string) {
    return request('/actions/patient-check-in.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ticket_code: ticketCode }),
    });
  },

  async searchPatients(query: string) {
    return request(`/actions/search-patients.php?q=${encodeURIComponent(query)}`);
  },

  async registerEmergency(payload: {
    patient_id: number;
    department_id: number;
    acuity_level: number;
    primary_symptom: string;
    check_in_location?: string;
    last_vitals?: any;
  }) {
    return request('/actions/register-emergency.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
  },

  // 6. Staff Management APIs
  async getStaff() {
    return request('/actions/get-staff.php');
  },

  async addStaff(payload: {
    email: string;
    password: string;
    role: 'doctor' | 'receptionist';
    first_name: string;
    last_name: string;
    department_id?: number;
    room_number?: string;
  }) {
    return request('/actions/add-staff.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
  },

  async updateStaff(payload: {
    user_id: number;
    status?: string;
    department_id?: number;
    room_number?: string;
  }) {
    return request('/actions/update-staff.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
  },

  async deleteStaff(userId: number) {
    return request('/actions/delete-staff.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ user_id: userId }),
    });
  },

  async getDoctorPerformance() {
    return request('/actions/get-doctor-performance.php');
  },

  async getAllPatients(filters?: { search?: string; page?: number; limit?: number }) {
    const params = new URLSearchParams();
    if (filters?.search) params.append('search', filters.search);
    if (filters?.page) params.append('page', String(filters.page));
    if (filters?.limit) params.append('limit', String(filters.limit));
    const qs = params.toString();
    return request(`/actions/get-all-patients.php${qs ? '?' + qs : ''}`);
  },

  async getAllAppointments(filters?: {
    date_from?: string;
    date_to?: string;
    department_id?: number;
    doctor_id?: number;
    status?: string;
    page?: number;
    limit?: number;
  }) {
    const params = new URLSearchParams();
    if (filters?.date_from) params.append('date_from', filters.date_from);
    if (filters?.date_to) params.append('date_to', filters.date_to);
    if (filters?.department_id) params.append('department_id', String(filters.department_id));
    if (filters?.doctor_id) params.append('doctor_id', String(filters.doctor_id));
    if (filters?.status) params.append('status', filters.status);
    if (filters?.page) params.append('page', String(filters.page));
    if (filters?.limit) params.append('limit', String(filters.limit));
    const qs = params.toString();
    return request(`/actions/get-all-appointments.php${qs ? '?' + qs : ''}`);
  },

  async addDepartment(payload: { department_name: string; prefix_code: string }) {
    return request('/actions/add-department.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
  },

  async updateDepartment(payload: { department_id: number; department_name?: string; prefix_code?: string }) {
    return request('/actions/update-department.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
  },

  async deleteDepartment(departmentId: number) {
    return request('/actions/delete-department.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ department_id: departmentId }),
    });
  },

  async getAuditLogs(filters?: {
    user_id?: number;
    action?: string;
    entity_type?: string;
    date_from?: string;
    date_to?: string;
    page?: number;
    limit?: number;
  }) {
    const params = new URLSearchParams();
    if (filters?.user_id) params.append('user_id', String(filters.user_id));
    if (filters?.action) params.append('action', filters.action);
    if (filters?.entity_type) params.append('entity_type', filters.entity_type);
    if (filters?.date_from) params.append('date_from', filters.date_from);
    if (filters?.date_to) params.append('date_to', filters.date_to);
    if (filters?.page) params.append('page', String(filters.page));
    if (filters?.limit) params.append('limit', String(filters.limit));
    const qs = params.toString();
    return request(`/actions/get-audit-logs.php${qs ? '?' + qs : ''}`);
  },

  async registerPatient(payload: {
    first_name: string;
    last_name: string;
    email: string;
    phone_number: string;
    date_of_birth?: string;
    gender?: string;
    address?: string;
    password?: string;
  }) {
    return request('/actions/register-patient.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
  },

  async updateAppointmentStatus(payload: { ticket_id: number; status: string }) {
    return request('/actions/update-appointment-status.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
  },

  exportQueueReport() {
    const token = document.cookie.split('; ').find(row => row.startsWith('session='))?.split('=')[1];
    window.open('/actions/export-queue-report.php', '_blank');
  },
};
