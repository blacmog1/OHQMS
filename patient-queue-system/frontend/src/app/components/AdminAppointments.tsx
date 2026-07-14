import { useState, useEffect } from "react";
import { Page, SessionUser } from "../App";
import { Search, RefreshCw, Filter, X } from "lucide-react";
import { toast } from "sonner";
import { api } from "../api";

interface Appointment {
  ticket_id: number;
  ticket_code: string;
  status: string;
  entry_channel: string;
  booked_at: string;
  scheduled_slot_at?: string;
  patient_first: string;
  patient_last: string;
  patient_email: string;
  patient_phone: string;
  doctor_first?: string;
  doctor_last?: string;
  department_name: string;
}

interface Props { navigate: (p: Page) => void; session: SessionUser; }

export function AdminAppointments({ navigate }: Props) {
  const [appointments, setAppointments] = useState<Appointment[]>([]);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const loadAppointments = async (pageNum = 1) => {
    try {
      const res = await api.getAllAppointments({
        status: statusFilter || undefined,
        page: pageNum,
        limit: 20,
      });
      if (res.success) {
        setAppointments(res.appointments || []);
        setTotalPages(res.pagination?.total_pages || 1);
        setPage(res.pagination?.page || 1);
      }
    } catch (err: any) {
      toast.error(err.message || "Failed to load appointments.");
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    loadAppointments();
  }, [statusFilter]);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    loadAppointments(1);
  };

  const getStatusBadge = (status: string) => {
    const s = status.toLowerCase();
    if (s === "waiting" || s === "checked_in") return { bg: "bg-amber-50", text: "text-amber-700", label: s.replace("_", " ") };
    if (s === "called" || s === "in_service") return { bg: "bg-emerald-50", text: "text-emerald-700", label: s.replace("_", " ") };
    if (s === "completed") return { bg: "bg-slate-100", text: "text-slate-600", label: "Completed" };
    if (s === "cancelled" || s === "no_show") return { bg: "bg-red-50", text: "text-red-700", label: s.replace("_", " ") };
    return { bg: "bg-slate-100", text: "text-slate-600", label: status.replace(/_/g, " ") };
  };

  return (
    <div className="min-h-screen bg-slate-50">
      <div className="max-w-6xl mx-auto px-4 py-8">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
          <div>
            <h1 className="text-2xl font-bold text-slate-900">Appointments</h1>
            <p className="text-slate-500 text-sm mt-1">View and manage all appointments across the system</p>
          </div>
          <button
            onClick={() => { setRefreshing(true); loadAppointments(page); }}
            disabled={refreshing}
            className="flex items-center gap-2 border border-slate-200 text-slate-600 hover:bg-white px-3 py-2 rounded-lg text-sm transition-colors bg-white"
          >
            <RefreshCw size={14} className={refreshing ? "animate-spin" : ""} />
            Refresh
          </button>
        </div>

        <div className="bg-white rounded-xl border border-slate-100 shadow-sm mb-6">
          <form onSubmit={handleSearch} className="p-4 flex flex-col sm:flex-row gap-3">
            <div className="relative flex-1">
              <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
              <input
                type="text"
                value={search}
                onChange={e => setSearch(e.target.value)}
                placeholder="Search by ticket code, patient, or doctor..."
                className="w-full border border-slate-200 rounded-lg pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
            <select
              value={statusFilter}
              onChange={e => setStatusFilter(e.target.value)}
              className="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">All Statuses</option>
              <option value="waiting">Waiting</option>
              <option value="checked_in">Checked In</option>
              <option value="called">Called</option>
              <option value="in_service">In Service</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
              <option value="no_show">No Show</option>
            </select>
            <button type="submit" className="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2 rounded-lg text-sm transition-colors">
              Filter
            </button>
          </form>
        </div>

        {loading ? (
          <div className="text-center py-16">
            <span className="w-10 h-10 border-4 border-blue-600/30 border-t-blue-600 rounded-full animate-spin inline-block mb-3" />
            <p className="text-slate-500">Loading appointments...</p>
          </div>
        ) : appointments.length === 0 ? (
          <div className="bg-white rounded-xl border border-slate-100 shadow-sm p-12 text-center">
            <p className="text-slate-400">No appointments found.</p>
          </div>
        ) : (
          <>
            <div className="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="bg-slate-50 border-b border-slate-100">
                      <th className="text-left px-5 py-3 font-semibold text-slate-600">Ticket</th>
                      <th className="text-left px-5 py-3 font-semibold text-slate-600">Patient</th>
                      <th className="text-left px-5 py-3 font-semibold text-slate-600">Doctor</th>
                      <th className="text-left px-5 py-3 font-semibold text-slate-600">Department</th>
                      <th className="text-left px-5 py-3 font-semibold text-slate-600">Status</th>
                      <th className="text-left px-5 py-3 font-semibold text-slate-600">Channel</th>
                      <th className="text-left px-5 py-3 font-semibold text-slate-600">Booked At</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {appointments.map(a => {
                      const badge = getStatusBadge(a.status);
                      return (
                        <tr key={a.ticket_id} className="hover:bg-slate-50 transition-colors">
                          <td className="px-5 py-3 font-mono font-semibold text-slate-900">#{a.ticket_code}</td>
                          <td className="px-5 py-3 text-slate-700">{a.patient_first} {a.patient_last}</td>
                          <td className="px-5 py-3 text-slate-600">{a.doctor_first ? `${a.doctor_first} ${a.doctor_last}` : "—"}</td>
                          <td className="px-5 py-3 text-slate-600">{a.department_name}</td>
                          <td className="px-5 py-3">
                            <span className={`text-xs font-semibold px-2.5 py-1 rounded-full ${badge.bg} ${badge.text}`}>
                              {badge.label}
                            </span>
                          </td>
                          <td className="px-5 py-3 text-slate-600 capitalize">{a.entry_channel.replace("_", " ")}</td>
                          <td className="px-5 py-3 text-slate-600">{new Date(a.booked_at).toLocaleString()}</td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </div>

            {totalPages > 1 && (
              <div className="flex items-center justify-between mt-4">
                <button
                  onClick={() => loadAppointments(Math.max(1, page - 1))}
                  disabled={page <= 1}
                  className="px-4 py-2 border border-slate-200 rounded-lg text-sm disabled:opacity-50 hover:bg-white transition-colors"
                >
                  Previous
                </button>
                <span className="text-sm text-slate-500">Page {page} of {totalPages}</span>
                <button
                  onClick={() => loadAppointments(Math.min(totalPages, page + 1))}
                  disabled={page >= totalPages}
                  className="px-4 py-2 border border-slate-200 rounded-lg text-sm disabled:opacity-50 hover:bg-white transition-colors"
                >
                  Next
                </button>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
}
