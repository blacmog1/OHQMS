import { useState, useEffect } from "react";
import { Page, SessionUser } from "../App";
import {
  Users, Users2, Calendar, TrendingUp, Building,
  Settings, BarChart2, ShieldCheck, Search,
  UserPlus, Edit2, Ban, X, FileText
} from "lucide-react";
import { toast } from "sonner";
import { api } from "../api";
import AdminCharts from "./AdminCharts";

interface Props { navigate: (p: Page) => void; session: SessionUser; }

const TABS = [
  { id: "dashboard", label: "Dashboard", icon: BarChart2, page: "admin-dashboard" as Page },
  { id: "staff",     label: "Staff Management", icon: Users, page: "admin-staff" as Page },
  { id: "patients",  label: "Patients", icon: Users2, page: "admin-patients" as Page },
  { id: "appointments", label: "Appointments", icon: Calendar, page: "admin-appointments" as Page },
  { id: "depts",     label: "Departments", icon: Building, page: "admin-departments" as Page },
  { id: "audit",     label: "Audit Logs", icon: FileText, page: "admin-audit-logs" as Page },
  { id: "settings",  label: "Settings", icon: Settings, page: "admin-settings" as Page },
];

interface UserRow {
  id: number; name: string; email: string; role: string; dept: string; status: "active" | "suspended"; joined: string;
}

export function AdminDashboard({ session, navigate }: Props) {
  const [tab, setTab]     = useState("dashboard");
  const [search, setSearch] = useState("");
  const [users, setUsers] = useState<UserRow[]>([]);
  const [liveStats, setLiveStats] = useState<{
    totalPatients: number;
    activeDoctors: number;
    todayAppointments: number;
    completionRate: string;
  } | null>(null);
  const [showPerformance, setShowPerformance] = useState(false);
  const [performanceData, setPerformanceData] = useState<any[]>([]);
  const [loadingPerformance, setLoadingPerformance] = useState(false);
  const [weeklyData, setWeeklyData] = useState<any[]>([]);
  const [statusData, setStatusData] = useState<any[]>([]);
  const [departments, setDepartments] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadStats();
    loadUsers();
    loadWeeklyData();
    loadStatusData();
    loadDepartments();
  }, []);

  const loadStats = async () => {
    try {
      const res = await api.getDashboardStats();
      if (res.success) {
        setLiveStats({
          totalPatients: res.stats.total_patients ?? 0,
          activeDoctors: res.stats.active_doctors ?? 0,
          todayAppointments: res.stats.today_tickets ?? 0,
          completionRate: res.stats.completion_rate ? `${res.stats.completion_rate}%` : "N/A",
        });
      }
    } catch (err) {
      console.error("Admin stats error:", err);
    } finally {
      setLoading(false);
    }
  };

  const loadUsers = async () => {
    try {
      const res = await api.getStaff();
      if (res.success) {
        const mapped: UserRow[] = res.staff.map((s: any) => ({
          id: s.id,
          name: `${s.first_name} ${s.last_name}`,
          email: s.email,
          role: s.role,
          dept: s.department_name || "—",
          status: "active",
          joined: new Date(s.created_at).toLocaleDateString(),
        }));
        setUsers(mapped);
      }
    } catch (err) {
      console.error("Load users error:", err);
    }
  };

  const loadWeeklyData = async () => {
    try {
      const res = await api.getQueueAnalytics();
      if (res.success && res.analytics) {
        const mapped = res.analytics.map((a: any) => ({
          day: new Date(a.date).toLocaleDateString('en-US', { weekday: 'short' }),
          appointments: a.total_tickets ?? 0,
          completed: a.completed_tickets ?? 0,
        }));
        setWeeklyData(mapped);
      } else {
        setWeeklyData([]);
      }
    } catch (err) {
      setWeeklyData([]);
    }
  };

  const loadStatusData = async () => {
    try {
      const res = await api.getQueueAnalytics();
      if (res.success && res.analytics) {
        const totals = res.analytics.reduce((acc: any, a: any) => {
          acc.completed += parseInt(a.completed_tickets || 0);
          acc.waiting += parseInt(a.waiting_tickets || 0);
          acc.cancelled += parseInt(a.cancelled_tickets || 0);
          return acc;
        }, { completed: 0, waiting: 0, cancelled: 0 });
        
        setStatusData([
          { name: "Completed", value: totals.completed, color: "#10b981" },
          { name: "Waiting", value: totals.waiting, color: "#f59e0b" },
          { name: "Cancelled", value: totals.cancelled, color: "#ef4444" },
        ]);
      } else {
        setStatusData([]);
      }
    } catch (err) {
      setStatusData([]);
    }
  };

  const loadDepartments = async () => {
    try {
      const res = await api.getDepartments();
      if (res.success) {
        setDepartments(res.departments || []);
      }
    } catch (err) {
      console.error("Load departments error:", err);
    }
  };

  const loadPerformance = async () => {
    setLoadingPerformance(true);
    try {
      const res = await api.getDoctorPerformance();
      if (res.success) {
        setPerformanceData(res.doctors || []);
      }
    } catch (err) {
      console.error("Load performance error:", err);
    } finally {
      setLoadingPerformance(false);
    }
  };

  const openPerformance = () => {
    setShowPerformance(true);
    loadPerformance();
  };

  const filtered = users.filter(u => {
    const q = search.toLowerCase();
    return !q || u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q) || u.role.includes(q);
  });

  const toggleStatus = (id: number) => {
    setUsers(prev => prev.map(u =>
      u.id === id ? { ...u, status: u.status === "active" ? "suspended" : "active" } : u
    ));
    const u = users.find(x => x.id === id);
    toast.success(`${u?.name} ${u?.status === "active" ? "suspended" : "reactivated"}.`);
  };

  if (!session) {
    return (
      <div className="min-h-screen bg-slate-50 flex items-center justify-center">
        <span className="w-10 h-10 border-4 border-blue-600/30 border-t-blue-600 rounded-full animate-spin" />
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-slate-50">
      <div className="flex-1 overflow-auto">
        <div className="max-w-7xl mx-auto p-8">
          <div className="flex items-center justify-between mb-8">
            <div>
              <h1 className="text-3xl font-bold text-slate-900">Admin Dashboard</h1>
              <p className="text-slate-500 mt-1">{session.name} · System Administrator</p>
            </div>
            <span className="flex items-center gap-2 bg-emerald-50 text-emerald-700 text-xs font-semibold px-3 py-1.5 rounded-full border border-emerald-200">
              <span className="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse" />
              System Operational
            </span>
          </div>

          {/* Tabs */}
          <div className="flex gap-1 bg-slate-100 rounded-xl p-1 mb-8 w-fit overflow-x-auto">
            {TABS.map(({ id, label, icon: Icon, page }) => (
              <button
                key={id}
                onClick={() => {
                  if (page && page !== "admin-dashboard") {
                    navigate(page);
                  } else {
                    setTab(id);
                  }
                }}
                className={`flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all whitespace-nowrap ${
                  tab === id ? "bg-white text-slate-900 shadow-sm" : "text-slate-500 hover:text-slate-700"
                }`}
              >
                <Icon size={14} />
                {label}
              </button>
            ))}
          </div>

          {/* Dashboard Tab */}
          {tab === "dashboard" && (
            <div className="space-y-6">
              <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                {[
                  { label: "Total Patients",  value: liveStats?.totalPatients.toLocaleString() ?? "…", change: "All registered patients", icon: Users,       color: "text-blue-600 bg-blue-50" },
                  { label: "Active Doctors",  value: liveStats?.activeDoctors ?? "…",                   change: "All departments",       icon: ShieldCheck, color: "text-emerald-600 bg-emerald-50" },
                  { label: "Today's Tickets", value: liveStats?.todayAppointments ?? "…",                change: "Queue tickets today",  icon: Calendar,    color: "text-amber-600 bg-amber-50" },
                  { label: "Completion Rate", value: liveStats?.completionRate ?? "…",                   change: "Today's rate",         icon: TrendingUp,  color: "text-purple-600 bg-purple-50" },
                ].map(({ label, value, change, icon: Icon, color }) => (
                  <div key={label} className="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
                    <div className={`w-10 h-10 rounded-lg flex items-center justify-center mb-3 ${color}`}>
                      <Icon size={18} />
                    </div>
                    <div className="text-2xl font-bold text-slate-900">{value}</div>
                    <div className="text-sm text-slate-500 mt-0.5">{label}</div>
                    <div className="text-xs text-slate-400 mt-1">{change}</div>
                  </div>
                ))}
              </div>

              <AdminCharts weeklyData={weeklyData} statusData={statusData} />
            </div>
          )}

          {/* Staff Tab */}
          {tab === "staff" && (
            <div>
              <div className="flex flex-col sm:flex-row justify-between gap-3 mb-4">
                <div className="relative flex-1 max-w-sm">
                  <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                  <input
                    value={search}
                    onChange={e => setSearch(e.target.value)}
                    placeholder="Search staff..."
                    className="w-full pl-9 pr-4 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                  />
                </div>
                <button
                  onClick={() => navigate("admin-staff")}
                  className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2.5 rounded-xl text-sm transition-colors"
                >
                  <UserPlus size={15} /> Add Staff Member
                </button>
              </div>

              <div className="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead className="bg-slate-50 border-b border-slate-100">
                      <tr>
                        {["Name","Email","Role","Department","Status","Joined","Actions"].map(h => (
                          <th key={h} className="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">{h}</th>
                        ))}
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-50">
                      {filtered.map(u => (
                        <tr key={u.id} className="hover:bg-slate-50 transition-colors">
                          <td className="px-4 py-3">
                            <div className="flex items-center gap-2.5">
                              <div className="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                                {u.name.charAt(0)}
                              </div>
                              <span className="font-medium text-slate-900">{u.name}</span>
                            </div>
                          </td>
                          <td className="px-4 py-3 text-slate-500">{u.email}</td>
                          <td className="px-4 py-3">
                            <span className={`text-xs font-semibold px-2.5 py-1 rounded-full capitalize`}>
                              {u.role}
                            </span>
                          </td>
                          <td className="px-4 py-3 text-slate-500">{u.dept}</td>
                          <td className="px-4 py-3">
                            <span className={`text-xs font-semibold px-2.5 py-1 rounded-full ${
                              u.status === "active" ? "bg-emerald-100 text-emerald-700" : "bg-red-100 text-red-600"
                            }`}>
                              {u.status}
                            </span>
                          </td>
                          <td className="px-4 py-3 text-slate-500">{u.joined}</td>
                          <td className="px-4 py-3">
                            <div className="flex items-center gap-1">
                              <button
                                onClick={() => toast.info(`Edit ${u.name}`)}
                                className="p-1.5 hover:bg-slate-100 rounded text-slate-500 hover:text-blue-600 transition-colors"
                              >
                                <Edit2 size={13} />
                              </button>
                              <button
                                onClick={() => toggleStatus(u.id)}
                                className={`p-1.5 hover:bg-slate-100 rounded transition-colors ${
                                  u.status === "active" ? "text-slate-500 hover:text-red-600" : "text-slate-500 hover:text-emerald-600"
                                }`}
                                title={u.status === "active" ? "Suspend" : "Reactivate"}
                              >
                                <Ban size={13} />
                              </button>
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
              <p className="text-xs text-slate-400 mt-2">{filtered.length} of {users.length} users shown</p>
            </div>
          )}

          {/* Departments Tab */}
          {tab === "depts" && (
            <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
              {departments.map(d => (
                <div key={d.department_id} className="bg-white rounded-xl border border-slate-100 shadow-sm p-5 hover:shadow-md transition-shadow">
                  <div className="flex items-start justify-between mb-3">
                    <h3 className="font-semibold text-slate-900">{d.department_name}</h3>
                    <span className={`text-xs font-semibold px-2 py-0.5 rounded-full ${d.active !== false ? "bg-emerald-100 text-emerald-700" : "bg-slate-100 text-slate-500"}`}>
                      {d.active !== false ? "Active" : "Inactive"}
                    </span>
                  </div>
                  <p className="text-sm text-slate-500 mb-1">Code: {d.prefix_code}</p>
                  <div className="flex gap-4 text-xs text-slate-500 mt-3 pt-3 border-t border-slate-100">
                    <span><strong className="text-slate-900">{d.doctor_count || 0}</strong> Doctors</span>
                  </div>
                </div>
              ))}
              {departments.length === 0 && (
                <div className="col-span-full text-center py-8 text-slate-400">No departments found.</div>
              )}
            </div>
          )}

          {/* Settings Tab */}
          {tab === "settings" && (
            <div className="max-w-xl space-y-5">
              {[
                { label: "Clinic Open Time",      value: "08:00",  type: "time" },
                { label: "Clinic Close Time",     value: "17:00",  type: "time" },
                { label: "Slot Duration (min)",   value: "15",     type: "number" },
                { label: "Max Queue Per Dept",    value: "50",     type: "number" },
              ].map(({ label, value, type }) => (
                <div key={label} className="bg-white rounded-xl border border-slate-100 shadow-sm p-5 flex items-center justify-between gap-4">
                  <label className="text-sm font-medium text-slate-700">{label}</label>
                  <input
                    type={type}
                    defaultValue={value}
                    className="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition w-36"
                  />
                </div>
              ))}
              <div className="bg-white rounded-xl border border-slate-100 shadow-sm p-5 flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-slate-700">Email Notifications</p>
                  <p className="text-xs text-slate-400 mt-0.5">Send booking confirmations & reminders</p>
                </div>
                <div className="w-11 h-6 bg-blue-600 rounded-full relative cursor-pointer">
                  <div className="w-5 h-5 bg-white rounded-full absolute right-0.5 top-0.5 shadow" />
                </div>
              </div>
              <div className="bg-white rounded-xl border border-slate-100 shadow-sm p-5 flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium text-slate-700">SMS Notifications</p>
                  <p className="text-xs text-slate-400 mt-0.5">Send queue updates via SMS</p>
                </div>
                <div className="w-11 h-6 bg-slate-200 rounded-full relative cursor-pointer">
                  <div className="w-5 h-5 bg-white rounded-full absolute left-0.5 top-0.5 shadow" />
                </div>
              </div>
              <button
                onClick={() => toast.success("System settings saved.")}
                className="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm"
              >
                Save Settings
              </button>
            </div>
          )}
        </div>
      </div>

      {/* Doctor Performance Modal */}
      {showPerformance && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-2xl shadow-xl p-6 max-w-4xl w-full max-h-[80vh] overflow-y-auto">
            <div className="flex items-center justify-between mb-4">
              <h3 className="font-bold text-slate-900">Doctor Performance — Last 30 Days</h3>
              <button onClick={() => setShowPerformance(false)} className="text-slate-400 hover:text-slate-600">
                <X size={18} />
              </button>
            </div>
            {loadingPerformance ? (
              <div className="text-center py-8 text-slate-400">Loading performance data...</div>
            ) : performanceData.length === 0 ? (
              <div className="text-center py-8 text-slate-400">No performance data available.</div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="bg-slate-50 border-b border-slate-100">
                    <tr>
                      {["Doctor", "Department", "Status", "Patients Seen", "Queue Served", "Completed", "No-Shows", "Cancellations", "Avg Consult", "Completion Rate"].map(h => (
                        <th key={h} className="text-left px-3 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wide">{h}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-50">
                    {performanceData.map(d => (
                      <tr key={d.doctor_id} className="hover:bg-slate-50">
                        <td className="px-3 py-2 font-medium text-slate-900">{d.name}</td>
                        <td className="px-3 py-2 text-slate-500">{d.department}</td>
                        <td className="px-3 py-2">
                          <span className={`text-xs font-semibold px-2 py-0.5 rounded-full ${
                            d.status === 'available' ? 'bg-emerald-100 text-emerald-700' :
                            d.status === 'busy' ? 'bg-amber-100 text-amber-700' :
                            'bg-slate-100 text-slate-600'
                          }`}>{d.status}</span>
                        </td>
                        <td className="px-3 py-2 text-slate-900">{d.total_patients_seen}</td>
                        <td className="px-3 py-2 text-slate-900">{d.total_queue_served}</td>
                        <td className="px-3 py-2 text-emerald-600">{d.completed_visits}</td>
                        <td className="px-3 py-2 text-red-600">{d.no_shows}</td>
                        <td className="px-3 py-2 text-slate-500">{d.cancellations}</td>
                        <td className="px-3 py-2 text-slate-900">{d.avg_consult_minutes ? `${d.avg_consult_minutes} min` : '—'}</td>
                        <td className="px-3 py-2">
                          <span className={`text-xs font-semibold ${
                            d.completion_rate >= 80 ? 'text-emerald-600' :
                            d.completion_rate >= 50 ? 'text-amber-600' :
                            'text-red-600'
                          }`}>{d.completion_rate}%</span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
