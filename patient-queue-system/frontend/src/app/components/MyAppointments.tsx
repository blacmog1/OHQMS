import { useState, useEffect } from "react";
import { Page, SessionUser } from "../App";
import { Calendar, Clock, Search, AlertTriangle, RefreshCw, AlertCircle, Inbox } from "lucide-react";
import { toast } from "sonner";
import { api } from "../api";

interface Props { navigate: (p: Page) => void; session: SessionUser; }

interface Appointment {
  id: number;
  ticketCode: string;
  doctor?: string;
  dept: string;
  scheduledAt?: string;
  status: string;
  entryChannel: string;
  queuePosition?: number;
  createdAt: string;
}

const STATUS_STYLE: Record<string, string> = {
  waiting:     "bg-amber-50 text-amber-700 border-amber-200",
  called:      "bg-blue-50 text-blue-700 border-blue-200",
  in_service:  "bg-emerald-50 text-emerald-700 border-emerald-200",
  completed:   "bg-slate-100 text-slate-600 border-slate-200",
  cancelled:   "bg-red-50 text-red-600 border-red-200",
  no_show:     "bg-red-50 text-red-600 border-red-200",
};

const STATUS_LABEL: Record<string, string> = {
  waiting:    "Waiting",
  called:     "Called",
  in_service: "In Service",
  completed:  "Completed",
  cancelled:  "Cancelled",
  no_show:    "No Show",
};

export function MyAppointments({ navigate }: Props) {
  const [appts, setAppts]       = useState<Appointment[]>([]);
  const [search, setSearch]     = useState("");
  const [status, setStatus]     = useState("");
  const [loading, setLoading]   = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError]       = useState("");
  const [confirming, setConfirming] = useState<number | null>(null);

  const fetchAppts = async (showRefresh = false) => {
    if (showRefresh) setRefreshing(true);
    try {
      const res = await api.getPatientAppointments();
      if (res.success) {
        const mapped: Appointment[] = (res.appointments ?? []).map((t: any) => ({
          id: t.ticket_id,
          ticketCode: t.ticket_code,
          doctor: t.doctor_first_name
            ? `Dr. ${t.doctor_first_name} ${t.doctor_last_name}`
            : undefined,
          dept: t.department_name,
          scheduledAt: t.scheduled_slot_at,
          status: t.status,
          entryChannel: t.entry_channel,
          queuePosition: t.sequence_number,
          createdAt: t.booked_at ?? t.scheduled_slot_at,
        }));
        setAppts(mapped);
        setError("");
      }
    } catch (err: any) {
      setError(err.message || "Failed to load appointments.");
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchAppts();
  }, []);

  const filtered = appts.filter(a => {
    const q = search.toLowerCase();
    const matchQ = !q || (a.doctor ?? "").toLowerCase().includes(q) || a.dept.toLowerCase().includes(q);
    const matchS = !status || a.status === status;
    return matchQ && matchS;
  });

  const cancelAppt = async (id: number) => {
    try {
      const res = await api.cancelAppointment(id);
      if (res.success) {
        toast.success("Appointment cancelled.");
        setConfirming(null);
        setAppts(prev => prev.map(a => a.id === id ? { ...a, status: "cancelled" } : a));
      }
    } catch (err: any) {
      toast.error(err.message || "Failed to cancel appointment.");
    }
  };

  const formatDate = (iso?: string) => {
    if (!iso) return "Today";
    return new Date(iso).toLocaleDateString("en-US", { dateStyle: "medium" });
  };

  const formatTime = (iso?: string) => {
    if (!iso) return "";
    return new Date(iso).toLocaleTimeString("en-US", { hour: "2-digit", minute: "2-digit" });
  };

  const isInactive = (s: string) => s === "cancelled" || s === "no_show" || s === "completed";

  return (
    <div className="min-h-screen bg-slate-50">
      <div className="max-w-4xl mx-auto px-4 py-10">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
          <div>
            <h1 className="text-2xl font-bold text-slate-900">My Appointments</h1>
            <p className="text-slate-500 text-sm mt-1">
              {appts.length} appointment{appts.length !== 1 ? "s" : ""} total
            </p>
          </div>
          <div className="flex gap-3">
            <button
              onClick={() => fetchAppts(true)}
              disabled={refreshing}
              className="border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 px-4 py-2.5 rounded-lg text-sm font-medium flex items-center gap-2 transition-colors disabled:opacity-60"
            >
              <RefreshCw size={14} className={refreshing ? "animate-spin" : ""} />
              Refresh
            </button>
            <button
              onClick={() => navigate("book-appointment")}
              className="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2.5 rounded-lg text-sm flex items-center gap-2 transition-colors"
            >
              <Calendar size={15} />
              Book New
            </button>
          </div>
        </div>

        <div className="bg-white border border-slate-100 shadow-sm rounded-xl p-6 mb-6">
          <div className="flex flex-col sm:flex-row gap-4">
            <div className="relative flex-1">
              <Search size={16} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
              <input
                value={search}
                onChange={e => setSearch(e.target.value)}
                placeholder="Search by doctor or department"
                className="w-full pl-10 pr-4 py-3 border border-slate-200 rounded-lg text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
              />
            </div>
            <select
              value={status}
              onChange={e => setStatus(e.target.value)}
              className="border border-slate-200 rounded-lg px-3.5 py-3 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
            >
              <option value="">All Status</option>
              <option value="waiting">Waiting</option>
              <option value="called">Called</option>
              <option value="in_service">In Service</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
        </div>

        {error && (
          <div className="bg-red-50 border border-red-200 rounded-xl p-4 mb-6 flex items-center gap-3 text-red-600 text-sm">
            <AlertCircle size={18} className="flex-shrink-0" />
            {error}
          </div>
        )}

        {loading ? (
          <div className="bg-white border border-slate-100 shadow-sm rounded-xl py-20">
            <span className="w-10 h-10 border-4 border-blue-600/20 border-t-blue-600 rounded-full animate-spin inline-block mb-4" />
            <p className="text-slate-500">Loading your appointments</p>
          </div>
        ) : appts.length === 0 ? (
          <div className="bg-white border border-slate-100 shadow-sm rounded-xl p-6 py-20 text-center">
            <div className="w-14 h-14 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-4">
              <Inbox size={26} className="text-slate-400" />
            </div>
            <h3 className="font-semibold text-slate-900 mb-1">No appointments yet</h3>
            <p className="text-slate-500 text-sm mb-6">Book your first appointment to get started.</p>
            <button
              onClick={() => navigate("book-appointment")}
              className="bg-blue-600 text-white font-semibold px-5 py-2.5 rounded-lg hover:bg-blue-700 transition-colors text-sm"
            >
              Book Appointment
            </button>
          </div>
        ) : filtered.length === 0 ? (
          <div className="bg-white border border-slate-100 shadow-sm rounded-xl p-6 py-20 text-center">
            <div className="w-14 h-14 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-4">
              <Search size={26} className="text-slate-400" />
            </div>
            <h3 className="font-semibold text-slate-900 mb-1">No matching appointments</h3>
            <p className="text-slate-500 text-sm mb-6">Try adjusting your search or filters.</p>
            <button
              onClick={() => { setSearch(""); setStatus(""); }}
              className="border border-slate-200 text-slate-600 font-medium px-5 py-2.5 rounded-lg hover:bg-slate-50 transition-colors text-sm"
            >
              Clear Filters
            </button>
          </div>
        ) : (
          <div className="grid gap-4">
            {filtered.map(a => (
              <div
                key={a.id}
                className={`bg-white rounded-xl border border-slate-100 shadow-sm p-6 transition-all ${
                  isInactive(a.status) ? "opacity-70" : "hover:shadow-md"
                }`}
              >
                <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                  <div className="flex-1">
                    <div className="flex items-center flex-wrap gap-2 mb-3">
                      <span className="font-bold text-blue-600 text-lg">#{a.ticketCode}</span>
                      <span className={`text-xs font-semibold px-2.5 py-1 rounded-full border ${STATUS_STYLE[a.status] ?? "bg-slate-100 text-slate-600 border-slate-200"}`}>
                        {STATUS_LABEL[a.status] ?? a.status}
                      </span>
                      <span className={`text-xs px-2.5 py-1 rounded-full font-medium ${
                        a.entryChannel === "walk_in" ? "bg-amber-50 text-amber-700 border border-amber-200" : "bg-blue-50 text-blue-700 border border-blue-200"
                      }`}>
                        {a.entryChannel === "walk_in" ? "Walk In" : "Online"}
                      </span>
                    </div>
                    <h3 className="text-lg font-semibold text-slate-900">{a.doctor ?? "Assigned Doctor"}</h3>
                    <p className="text-sm text-slate-500 mt-0.5">{a.dept}</p>
                    <div className="flex flex-wrap gap-x-6 gap-y-2 mt-4 text-sm text-slate-500">
                      <span className="flex items-center gap-1.5">
                        <Calendar size={15} className="text-slate-400" />
                        {formatDate(a.scheduledAt ?? a.createdAt)}
                      </span>
                      {a.scheduledAt && (
                        <span className="flex items-center gap-1.5">
                          <Clock size={15} className="text-slate-400" />
                          {formatTime(a.scheduledAt)}
                        </span>
                      )}
                      {a.queuePosition && a.status === "waiting" && (
                        <span className="font-semibold text-blue-600">
                          Queue Position #{a.queuePosition}
                        </span>
                      )}
                    </div>
                  </div>
                  {(a.status === "waiting" || a.status === "called") && (
                    <div className="flex gap-3 flex-shrink-0">
                      <button
                        onClick={() => navigate("queue-tracking")}
                        className="text-sm font-semibold text-blue-600 hover:bg-blue-50 border border-blue-200 px-3.5 py-2 rounded-lg transition-colors"
                      >
                        Track Queue
                      </button>
                      {confirming === a.id ? (
                        <div className="flex gap-2 items-center bg-red-50 border border-red-200 rounded-lg px-3.5 py-2">
                          <AlertTriangle size={15} className="text-red-500" />
                          <span className="text-xs text-red-600 font-medium">Cancel?</span>
                          <button onClick={() => cancelAppt(a.id)} className="text-xs text-red-500 font-bold hover:underline">Yes</button>
                          <button onClick={() => setConfirming(null)} className="text-xs text-slate-500 hover:underline">No</button>
                        </div>
                      ) : (
                        <button
                          onClick={() => setConfirming(a.id)}
                          className="text-sm font-semibold text-red-500 hover:bg-red-50 border border-red-200 px-3.5 py-2 rounded-lg transition-colors"
                        >
                          Cancel
                        </button>
                      )}
                    </div>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
