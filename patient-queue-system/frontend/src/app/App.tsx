import { useState, useEffect, Suspense, lazy } from "react";

import { Navbar } from "./components/Navbar";
import Sidepanel from "./components/Sidepanel";

const LandingPage = lazy(() => import("./components/LandingPage").then(m => ({ default: m.LandingPage })));
const LoginPage = lazy(() => import("./components/LoginPage").then(m => ({ default: m.LoginPage })));
const RegisterPage = lazy(() => import("./components/RegisterPage").then(m => ({ default: m.RegisterPage })));
const PatientDashboard = lazy(() => import("./components/PatientDashboard").then(m => ({ default: m.PatientDashboard })));
const BookAppointment = lazy(() => import("./components/BookAppointment").then(m => ({ default: m.BookAppointment })));
const MyAppointments = lazy(() => import("./components/MyAppointments").then(m => ({ default: m.MyAppointments })));
const QueueTracking = lazy(() => import("./components/QueueTracking").then(m => ({ default: m.QueueTracking })));
const PatientProfile = lazy(() => import("./components/PatientProfile").then(m => ({ default: m.PatientProfile })));
const PatientMedicalRecords = lazy(() => import("./components/PatientMedicalRecords").then(m => ({ default: m.PatientMedicalRecords })));
const ReceptionDashboard = lazy(() => import("./components/ReceptionDashboard").then(m => ({ default: m.ReceptionDashboard })));
const ReceptionPatients = lazy(() => import("./components/ReceptionPatients").then(m => ({ default: m.ReceptionPatients })));
const DoctorDashboard = lazy(() => import("./components/DoctorDashboard").then(m => ({ default: m.DoctorDashboard })));
const DoctorPatients = lazy(() => import("./components/DoctorPatients").then(m => ({ default: m.DoctorPatients })));
const AdminDashboard = lazy(() => import("./components/AdminDashboard").then(m => ({ default: m.AdminDashboard })));
const AdminStaffManagement = lazy(() => import("./components/AdminStaffManagement").then(m => ({ default: m.AdminStaffManagement })));
const AdminPatients = lazy(() => import("./components/AdminPatients").then(m => ({ default: m.AdminPatients })));
const AdminAppointments = lazy(() => import("./components/AdminAppointments").then(m => ({ default: m.AdminAppointments })));
const AdminDepartments = lazy(() => import("./components/AdminDepartments").then(m => ({ default: m.AdminDepartments })));
const AdminAuditLogs = lazy(() => import("./components/AdminAuditLogs").then(m => ({ default: m.AdminAuditLogs })));
const AdminSettings = lazy(() => import("./components/AdminSettings").then(m => ({ default: m.AdminSettings })));

import { Toaster } from "sonner";
import { api } from "./api";
import {
  LayoutDashboard, CalendarPlus, CalendarCheck, Clock, UserRound,
  ListChecks, Stethoscope, Users, Users2, FileText, Settings,
  type LucideIcon,
} from "lucide-react";

export type Page =
  | "landing"
  | "login"
  | "register"
  | "patient-dashboard"
  | "book-appointment"
  | "my-appointments"
  | "queue-tracking"
  | "patient-profile"
  | "patient-medical-records"
  | "reception-dashboard"
  | "reception-patients"
  | "doctor-dashboard"
  | "doctor-patients"
  | "admin-dashboard"
  | "admin-staff"
  | "admin-patients"
  | "admin-appointments"
  | "admin-departments"
  | "admin-audit-logs"
  | "admin-settings";

export type Role = "patient" | "receptionist" | "doctor" | "admin" | null;

export interface SessionUser {
  id?: number;
  name: string;
  email: string;
  role: Role;
  avatar?: string;
}

export interface NavItem {
  label: string;
  page: Page;
  icon: LucideIcon;
}

export function navItemsFor(role: Role): NavItem[] {
  switch (role) {
    case "patient":
      return [
        { label: "Dashboard", page: "patient-dashboard", icon: LayoutDashboard },
        { label: "Book Appointment", page: "book-appointment", icon: CalendarPlus },
        { label: "My Appointments", page: "my-appointments", icon: CalendarCheck },
        { label: "Queue Tracking", page: "queue-tracking", icon: Clock },
        { label: "Medical Records", page: "patient-medical-records", icon: FileText },
        { label: "My Profile", page: "patient-profile", icon: UserRound },
      ];
    case "receptionist":
      return [
        { label: "Dashboard", page: "reception-dashboard", icon: LayoutDashboard },
        { label: "Patient Registration", page: "reception-patients", icon: Users2 },
        { label: "Live Queue", page: "reception-dashboard", icon: ListChecks },
      ];
    case "doctor":
      return [
        { label: "Dashboard", page: "doctor-dashboard", icon: LayoutDashboard },
        { label: "My Patients", page: "doctor-patients", icon: Stethoscope },
      ];
    case "admin":
      return [
        { label: "Dashboard", page: "admin-dashboard", icon: LayoutDashboard },
        { label: "Staff Management", page: "admin-staff", icon: Users },
        { label: "Patients", page: "admin-patients", icon: Users2 },
        { label: "Appointments", page: "admin-appointments", icon: CalendarCheck },
        { label: "Departments", page: "admin-departments", icon: ListChecks },
        { label: "Audit Logs", page: "admin-audit-logs", icon: FileText },
        { label: "Settings", page: "admin-settings", icon: Settings },
      ];
    default:
      return [];
  }
}

const VALID_PAGES: Page[] = [
  "landing", "login", "register",
  "patient-dashboard", "book-appointment", "my-appointments",
  "queue-tracking", "patient-profile", "patient-medical-records",
  "reception-dashboard", "reception-patients",
  "doctor-dashboard", "doctor-patients",
  "admin-dashboard", "admin-staff", "admin-patients", "admin-appointments",
  "admin-departments", "admin-audit-logs", "admin-settings",
];

const AUTH_PAGES = new Set<Page>([
  "patient-dashboard", "book-appointment", "my-appointments",
  "queue-tracking", "patient-profile", "patient-medical-records",
  "reception-dashboard", "reception-patients",
  "doctor-dashboard", "doctor-patients",
  "admin-dashboard", "admin-staff", "admin-patients", "admin-appointments",
  "admin-departments", "admin-audit-logs", "admin-settings",
]);

const hashToPage = (hash: string): Page | null => {
  const h = hash.replace(/^#/, "");
  return (VALID_PAGES as string[]).includes(h) ? (h as Page) : null;
};

export default function App() {
  const [page, setPage] = useState<Page>(() => hashToPage(window.location.hash) ?? "landing");
  const [session, setSession] = useState<SessionUser | null>(() => {
    const saved = localStorage.getItem("ohaqrs_session");
    return saved ? JSON.parse(saved) : null;
  });
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [sessionResolved, setSessionResolved] = useState(false);

  useEffect(() => {
    const onHash = () => {
      const p = hashToPage(window.location.hash);
      if (p) setPage(p);
    };
    window.addEventListener("hashchange", onHash);
    return () => window.removeEventListener("hashchange", onHash);
  }, []);

  useEffect(() => {
    if (!window.location.hash) {
      window.location.hash = page;
    }
  }, []);

  useEffect(() => {
    const saved = localStorage.getItem("ohaqrs_session");
    if (!saved) {
      setSessionResolved(true);
      return;
    }

    api.getCurrentUser()
      .then(res => {
        if (res.success && res.user) {
          const user: SessionUser = {
            id: res.user.id,
            name: res.user.name || res.user.email,
            email: res.user.email,
            role: res.user.role,
          };
          setSession(user);

          const cur = (window.location.hash.replace(/^#/, "") || "landing") as Page;
          if (cur === "landing" || cur === "login" || cur === "register") {
            if (user.role === "patient") navigate("patient-dashboard");
            else if (user.role === "receptionist") navigate("reception-dashboard");
            else if (user.role === "doctor") navigate("doctor-dashboard");
            else if (user.role === "admin") navigate("admin-dashboard");
          }
        }
      })
      .catch(() => {
        localStorage.removeItem("ohaqrs_session");
        setSession(null);
      })
      .finally(() => setSessionResolved(true));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    if (!sessionResolved) return;
    if (!session && AUTH_PAGES.has(page) && page !== "landing") {
      navigate("landing");
    }
  }, [session, sessionResolved, page]);

  useEffect(() => {
    if (session) {
      localStorage.setItem("ohaqrs_session", JSON.stringify(session));
    } else {
      localStorage.removeItem("ohaqrs_session");
    }
  }, [session]);

  const navigate = (p: Page) => {
    if (window.location.hash.replace(/^#/, "") === p) {
      setPage(p);
    } else {
      window.location.hash = p;
    }
  };

  const login = (user: SessionUser) => {
    setSession(user);
    if (user.role === "patient") navigate("patient-dashboard");
    else if (user.role === "receptionist") navigate("reception-dashboard");
    else if (user.role === "doctor") navigate("doctor-dashboard");
    else if (user.role === "admin") navigate("admin-dashboard");
  };

  const logout = async () => {
    try {
      await api.logout();
    } catch (e) {
      console.error("Logout API error:", e);
    }
    setSession(null);
    setDrawerOpen(false);
    navigate("landing");
  };

  const showNav = session !== null;
  const items = session ? navItemsFor(session.role) : [];

  return (
    <div className="min-h-screen bg-slate-50 flex flex-col">
      <Toaster position="bottom-right" richColors />

      {showNav && (
        <Navbar
          session={session}
          items={items}
          onMenuClick={() => setDrawerOpen(true)}
          logout={logout}
        />
      )}

      {showNav && (
        <Sidepanel
          open={drawerOpen}
          onClose={() => setDrawerOpen(false)}
          items={items}
          currentPage={page}
          onNavigate={(p) => { setDrawerOpen(false); navigate(p); }}
          session={session}
        />
      )}

      <main className="flex-1">
        <Suspense fallback={
          <div className="flex items-center justify-center h-screen">
            <span className="w-10 h-10 border-4 border-blue-600/30 border-t-blue-600 rounded-full animate-spin" />
          </div>
        }>
          {page === "landing" && <LandingPage navigate={navigate} />}
          {page === "login" && <LoginPage navigate={navigate} login={login} />}
          {page === "register" && <RegisterPage navigate={navigate} login={login} />}
          {page === "patient-dashboard" && <PatientDashboard navigate={navigate} session={session!} />}
          {page === "book-appointment" && <BookAppointment navigate={navigate} session={session!} />}
          {page === "my-appointments" && <MyAppointments navigate={navigate} session={session!} />}
          {page === "queue-tracking" && <QueueTracking navigate={navigate} session={session!} />}
          {page === "patient-profile" && <PatientProfile navigate={navigate} session={session!} />}
          {page === "patient-medical-records" && <PatientMedicalRecords navigate={navigate} session={session!} />}
          {page === "reception-dashboard" && <ReceptionDashboard navigate={navigate} session={session!} />}
          {page === "reception-patients" && <ReceptionPatients navigate={navigate} session={session!} />}
          {page === "doctor-dashboard" && <DoctorDashboard navigate={navigate} session={session!} />}
          {page === "doctor-patients" && <DoctorPatients navigate={navigate} session={session!} />}
          {page === "admin-dashboard" && <AdminDashboard navigate={navigate} session={session!} />}
          {page === "admin-staff" && <AdminStaffManagement navigate={navigate} session={session!} />}
          {page === "admin-patients" && <AdminPatients navigate={navigate} session={session!} />}
          {page === "admin-appointments" && <AdminAppointments navigate={navigate} session={session!} />}
          {page === "admin-departments" && <AdminDepartments navigate={navigate} session={session!} />}
          {page === "admin-audit-logs" && <AdminAuditLogs navigate={navigate} session={session!} />}
          {page === "admin-settings" && <AdminSettings navigate={navigate} session={session!} />}
        </Suspense>
      </main>
    </div>
  );
}
