import { useState, useEffect } from "react";
import { Page, SessionUser } from "../App";
import {
  Calendar, Clock, CheckCircle, Activity,
  ChevronRight, AlertCircle, Users, TrendingUp, FileText
} from "lucide-react";
import { toast } from "sonner";
import { api } from "../api";

interface Props { navigate: (p: Page) => void; session: SessionUser; }

interface Appointment {
  id: string;
  doctor: string;
  dept: string;
  date: string;
  time: string;
  status: string;
}

interface ActivityItem {
  text: string;
  time: string;
  icon: any;
  color: string;
}

const STATUS = {
  confirmed: "bg-emerald-100 text-emerald-700",
  pending: "bg-amber-100 text-amber-700",
  cancelled: "bg-red-100 text-red-700",
  completed: "bg-slate-100 text-slate-600",
};

export function PatientDashboard({ session, navigate }: Props) {
  const [activeTab, setActiveTab] = useState<"upcoming" | "activity">("upcoming");
  const [appointments, setAppointments] = useState<Appointment[]>([]);
  const [activity, setActivity] = useState<ActivityItem[]>([]);
  const [stats, setStats] = useState({ upcoming: 0, completed: 0, queue: 0, records: 0 });
  const [queueInfo, setQueueInfo] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    setLoading(true);
    try {
      const [apptRes, queueRes, recordsRes] = await Promise.all([
        api.getPatientAppointments(),
        api.getQueueStatus(),
        api.getMedicalRecords(),
      ]);

      if (apptRes.success) {
        const appts = (apptRes.appointments || []).map((a: any) => ({
          id: a.id || a.ticket_id,
          doctor: a.doctor_name || 'Unknown Doctor',
          dept: a.department_name || 'General',
          date: a.appointment_date || a.date,
          time: a.appointment_time || a.time,
          status: a.status,
        }));
        setAppointments(appts);
        
        const upcoming = appts.filter((a: Appointment) => a.status === 'confirmed' || a.status === 'pending').length;
        const completed = appts.filter((a: Appointment) => a.status === 'completed').length;
        setStats(prev => ({ ...prev, upcoming, completed }));
      }

      if (queueRes.success && queueRes.ticket) {
        setQueueInfo(queueRes.ticket);
        setStats(prev => ({ ...prev, queue: 1 }));
      }

      if (recordsRes.success) {
        setStats(prev => ({ ...prev, records: recordsRes.count || 0 }));
      }

      // Build activity feed from real data
      const activityItems: ActivityItem[] = [];
      if (apptRes.success && apptRes.appointments) {
        apptRes.appointments.slice(0, 3).forEach((a: any) => {
          if (a.status === 'confirmed') {
            activityItems.push({
              text: `Appointment confirmed — ${a.doctor_name || 'Doctor'}`,
              time: new Date(a.created_at || a.appointment_date).toLocaleDateString(),
              icon: CheckCircle,
              color: "text-emerald-500",
            });
          }
        });
      }
      if (queueRes.success && queueRes.ticket) {
        activityItems.push({
          text: `Queue entry assigned — #${queueRes.ticket.ticket_code}`,
          time: "Today",
          icon: Users,
          color: "text-blue-500",
        });
      }
      setActivity(activityItems);
    } catch (err) {
      console.error("Load dashboard data error:", err);
    } finally {
      setLoading(false);
    }
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
      <div className="max-w-5xl mx-auto px-4 py-8">
          {/* Header */}
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
            <div>
              <h1 className="text-2xl font-bold text-slate-900">Patient Dashboard</h1>
              <p className="text-slate-500 mt-0.5">Welcome back, {session.name}</p>
            </div>
            <button
              onClick={() => navigate("book-appointment")}
              className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2.5 rounded-xl text-sm transition-colors"
            >
              <Calendar size={16} />
              Book Appointment
            </button>
          </div>

          {/* Stats */}
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            {[
              { label: "Upcoming", value: stats.upcoming, color: "text-blue-600 bg-blue-50" },
              { label: "Completed", value: stats.completed, color: "text-emerald-600 bg-emerald-50" },
              { label: "Queue Tickets", value: stats.queue, color: "text-amber-600 bg-amber-50" },
              { label: "Records", value: stats.records, color: "text-purple-600 bg-purple-50" },
            ].map(({ label, value, color }) => (
              <div key={label} className="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
                <div className={`w-10 h-10 rounded-lg flex items-center justify-center mb-3 ${color}`}>
                  <Activity size={18} />
                </div>
                <div className="text-2xl font-bold text-slate-900">{value}</div>
                <div className="text-sm text-slate-500 mt-0.5">{label}</div>
              </div>
            ))}
          </div>

          {/* Upcoming Appointments & Activity */}
          <div className="grid lg:grid-cols-3 gap-6">
            <div className="lg:col-span-2 bg-white rounded-xl border border-slate-100 shadow-sm">
              <div className="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                <h3 className="font-semibold text-slate-900">Appointments</h3>
                <div className="flex gap-1 bg-slate-100 rounded-lg p-0.5">
                  <button
                    onClick={() => setActiveTab("upcoming")}
                    className={`px-3 py-1.5 rounded-md text-xs font-medium transition-all ${
                      activeTab === "upcoming" ? "bg-white text-slate-900 shadow-sm" : "text-slate-500"
                    }`}
                  >
                    Upcoming
                  </button>
                  <button
                    onClick={() => setActiveTab("activity")}
                    className={`px-3 py-1.5 rounded-md text-xs font-medium transition-all ${
                      activeTab === "activity" ? "bg-white text-slate-900 shadow-sm" : "text-slate-500"
                    }`}
                  >
                    Activity
                  </button>
                </div>
              </div>
              <div className="p-5 space-y-3">
                {activeTab === "upcoming" && appointments.length === 0 ? (
                  <p className="text-sm text-slate-400 text-center py-8">No upcoming appointments.</p>
                ) : activeTab === "upcoming" ? (
                  appointments.map(appt => (
                    <div key={appt.id} className="flex items-start justify-between p-4 bg-slate-50 rounded-xl hover:bg-blue-50 transition-colors">
                      <div className="flex items-start gap-3">
                        <div className="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                          <Calendar size={18} className="text-blue-600" />
                        </div>
                        <div>
                          <p className="text-sm font-semibold text-slate-900">{appt.doctor}</p>
                          <p className="text-xs text-slate-500">{appt.dept} · {appt.date} at {appt.time}</p>
                        </div>
                      </div>
                      <span className={`text-xs font-semibold px-2.5 py-1 rounded-full ${STATUS[appt.status as keyof typeof STATUS] || 'bg-slate-100 text-slate-600'}`}>
                        {appt.status}
                      </span>
                    </div>
                  ))
                ) : activity.length === 0 ? (
                  <p className="text-sm text-slate-400 text-center py-8">No recent activity.</p>
                ) : (
                  activity.map((item, idx) => (
                    <div key={idx} className="flex items-start gap-3">
                      <div className={`mt-0.5 ${item.color}`}>
                        <item.icon size={16} />
                      </div>
                      <div className="flex-1">
                        <p className="text-sm text-slate-700">{item.text}</p>
                        <p className="text-xs text-slate-400 mt-0.5">{item.time}</p>
                      </div>
                    </div>
                  ))
                )}
              </div>
            </div>

            {/* Quick Actions */}
            <div className="space-y-5">
              <div className="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
                <h3 className="font-semibold text-slate-900 mb-4">Quick Actions</h3>
                <div className="space-y-2">
                  {[
                    { label: "Book Appointment", icon: Calendar, action: () => navigate("book-appointment") },
                    { label: "My Appointments", icon: Clock, action: () => navigate("my-appointments") },
                    { label: "Track Queue", icon: Users, action: () => navigate("queue-tracking") },
                    { label: "Medical Records", icon: FileText, action: () => navigate("patient-medical-records") },
                    { label: "My Profile", icon: Activity, action: () => navigate("patient-profile") },
                  ].map(({ label, icon: Icon, action }) => (
                    <button
                      key={label}
                      onClick={action}
                      className="w-full flex items-center gap-3 px-4 py-3 rounded-lg border border-slate-200 hover:border-blue-300 hover:bg-blue-50 transition-colors text-sm text-slate-700"
                    >
                      <Icon size={16} className="text-slate-400" />
                      {label}
                      <ChevronRight size={14} className="ml-auto text-slate-400" />
                    </button>
                  ))}
                </div>
              </div>

              {queueInfo && (
                <div className="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-xl p-4 text-white text-center">
                  <h4 className="font-semibold text-sm mb-1">Queue Status</h4>
                  <p className="text-2xl font-bold">#{queueInfo.ticket_code}</p>
                  <p className="text-xs text-white/80 mt-1">Position #{queueInfo.queue_position} · {queueInfo.status.replace('_', ' ')}</p>
                </div>
              )}
            </div>
          </div>
        </div>
    </div>
  );
}
