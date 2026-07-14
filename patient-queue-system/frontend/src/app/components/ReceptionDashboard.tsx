import { useState, useEffect, useCallback, useRef } from "react";
import { Page, SessionUser } from "../App";
import {
  UserPlus, CheckCircle, Clock,
  X, Activity, RefreshCw, AlertCircle, Search,
  Flame
} from "lucide-react";
import { toast } from "sonner";
import { api } from "../api";

interface Props { navigate: (p: Page) => void; session: SessionUser; }

interface QueueEntry {
  id: number;
  ticketCode: string;
  queuePosition: number;
  patientName: string;
  phone?: string;
  department: string;
  status: string;
  entryChannel: string;
  checkedInAt?: string;
  doctorName?: string;
}

interface PatientResult {
  patient_id: number;
  name: string;
  email: string;
  phone: string;
  ticket_code?: string;
  ticket_status?: string;
  queue_position?: number;
  department?: string;
}

export function ReceptionDashboard({ session, navigate }: Props) {
  const [queue, setQueue] = useState<QueueEntry[]>([]);
  const [showWalkin, setShowWalkin] = useState(false);
  const [showEmergency, setShowEmergency] = useState(false);
  const [departments, setDepartments] = useState<{ department_id: number; department_name: string }[]>([]);
  const [walkin, setWalkin] = useState({ name: "", phone: "", deptId: "" });
  const [emergency, setEmergency] = useState({ patientId: "", departmentId: "", acuityLevel: "3", symptom: "", checkInLoc: "" });
  const [submitting, setSubmitting] = useState(false);
  const [refreshing, setRefreshing] = useState(false);

  const [patients, setPatients] = useState<PatientResult[]>([]);
  const [patientQuery, setPatientQuery] = useState("");
  const [showPatientDropdown, setShowPatientDropdown] = useState(false);
  const [loadingPatients, setLoadingPatients] = useState(false);
  const [selectedPatientName, setSelectedPatientName] = useState("");
  const patientSearchRef = useRef<HTMLDivElement>(null);

  const loadQueue = useCallback(async (showToast = false) => {
    if (showToast) setRefreshing(true);
    try {
      const res = await api.getActiveTickets({});
      if (res.success) {
        const mapped: QueueEntry[] = res.tickets.map((t: any) => ({
          id: t.ticketId ?? t.id,
          ticketCode: t.ticketCode,
          queuePosition: t.queuePosition ?? t.queueNumber ?? t.sequence_number,
          patientName: t.patientName,
          phone: t.phone,
          department: t.department ?? t.department_name,
          status: t.status,
          entryChannel: t.entryChannel ?? t.entry_channel ?? 'online',
          checkedInAt: t.checkedInAt,
          doctorName: t.doctorName,
        }));
        setQueue(mapped);
      }
    } catch (err) {
      console.error("Queue load error:", err);
    } finally {
      if (showToast) setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    loadQueue();
    const interval = setInterval(() => loadQueue(), 15000);
    return () => clearInterval(interval);
  }, [loadQueue]);

  useEffect(() => {
    api.getDepartments().then(res => {
      if (res.success) setDepartments(res.departments || []);
    });
  }, []);

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (patientSearchRef.current && !patientSearchRef.current.contains(event.target as Node)) {
        setShowPatientDropdown(false);
      }
    };
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  useEffect(() => {
    if (patientQuery.length >= 2) {
      setLoadingPatients(true);
      api.searchPatients(patientQuery).then(res => {
        if (res.success) {
          setPatients(res.patients || []);
        }
        setLoadingPatients(false);
      }).catch(() => {
        setPatients([]);
        setLoadingPatients(false);
      });
    } else {
      setPatients([]);
      setLoadingPatients(false);
    }
  }, [patientQuery]);

  const selectPatient = (patient: PatientResult) => {
    setEmergency(w => ({ ...w, patientId: String(patient.patient_id) }));
    setSelectedPatientName(patient.name);
    setPatientQuery(patient.name);
    setShowPatientDropdown(false);
  };

  const clearPatientSelection = () => {
    setEmergency(w => ({ ...w, patientId: "" }));
    setSelectedPatientName("");
    setPatientQuery("");
    setShowPatientDropdown(false);
  };

  const addWalkin = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!walkin.name.trim() || !walkin.deptId) {
      toast.error("Please fill in all required fields.");
      return;
    }
    setSubmitting(true);
    try {
      const res = await api.bookAppointment({
        department_id: Number(walkin.deptId),
        entry_channel: "walk_in",
        patient_name: walkin.name,
        phone: walkin.phone,
      });
      if (res.success) {
        toast.success("Walk-in patient added to queue.");
        setShowWalkin(false);
        setWalkin({ name: "", phone: "", deptId: "" });
        loadQueue();
      } else {
        toast.error(res.message || "Failed to add patient.");
      }
    } catch (err) {
      toast.error("Error adding walk-in patient.");
    } finally {
      setSubmitting(false);
    }
  };

  const registerEmergency = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!emergency.patientId || !emergency.departmentId || !emergency.symptom) {
      toast.error("Please fill in all required fields.");
      return;
    }
    setSubmitting(true);
    try {
      const res = await api.registerEmergency({
        patient_id: Number(emergency.patientId),
        department_id: Number(emergency.departmentId),
        acuity_level: Number(emergency.acuityLevel),
        primary_symptom: emergency.symptom,
        check_in_location: emergency.checkInLoc || undefined,
      });
      if (res.success) {
        toast.success(res.message);
        setShowEmergency(false);
        setEmergency({ patientId: "", departmentId: "", acuityLevel: "3", symptom: "", checkInLoc: "" });
        setPatientQuery("");
        setSelectedPatientName("");
        setPatients([]);
        loadQueue();
      } else {
        toast.error(res.message || "Failed to register emergency.");
      }
    } catch (err) {
      toast.error("Error registering emergency patient.");
    } finally {
      setSubmitting(false);
    }
  };

  const waiting = queue.filter(q => q.status === "waiting").length;
  const inService = queue.filter(q => q.status === "in_service" || q.status === "called").length;
  const done = queue.filter(q => q.status === "completed").length;

  const getStatusBadge = (status: string) => {
    const normalized = status.toLowerCase();
    if (normalized === "waiting") {
      return { bg: "bg-amber-50", text: "text-amber-700", label: "Waiting" };
    }
    if (normalized === "called" || normalized === "in_service") {
      return { bg: "bg-emerald-50", text: "text-emerald-700", label: "In Service" };
    }
    if (normalized === "completed") {
      return { bg: "bg-slate-100", text: "text-slate-600", label: "Completed" };
    }
    if (normalized === "emergency") {
      return { bg: "bg-red-50", text: "text-red-700", label: "Emergency" };
    }
    return { bg: "bg-slate-100", text: "text-slate-600", label: status.replace(/_/g, " ") };
  };

  const formatTime = (dateStr?: string) => {
    if (!dateStr) return "—";
    return new Date(dateStr).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
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
        <div className="max-w-6xl mx-auto px-4 py-8">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
            <div>
              <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Reception Dashboard</h1>
              <p className="text-slate-500 text-sm mt-1">Welcome, {session.name} · {new Date().toLocaleDateString("en-US", { dateStyle: "long" })}</p>
            </div>
            <div className="flex gap-3">
              <button
                onClick={() => navigate("reception-patients")}
                className="flex items-center gap-2 border border-slate-200 text-slate-600 hover:bg-white hover:border-slate-300 px-3 py-2.5 rounded-lg text-sm transition-colors bg-white"
              >
                <Search size={16} />
                Patient Search
              </button>
              <button
                onClick={() => setShowEmergency(true)}
                className="flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white font-semibold px-4 py-2.5 rounded-lg text-sm transition-colors shadow-sm"
              >
                <AlertCircle size={16} />
                Emergency Triage
              </button>
              <button
                onClick={() => setShowWalkin(true)}
                className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2.5 rounded-lg text-sm transition-colors shadow-sm"
              >
                <UserPlus size={16} />
                Register Walk-in
              </button>
              <button
                onClick={() => loadQueue(true)}
                disabled={refreshing}
                className="flex items-center gap-2 border border-slate-200 text-slate-600 hover:bg-white hover:border-slate-300 px-3 py-2 rounded-lg text-sm transition-colors bg-white"
              >
                <RefreshCw size={14} className={refreshing ? "animate-spin" : ""} />
              </button>
            </div>
          </div>

          {/* Stats */}
          <div className="grid grid-cols-3 gap-4 mb-6">
            {[
              { label: "Waiting", value: waiting, color: "text-amber-500", icon: Clock, bg: "bg-amber-50" },
              { label: "In Service", value: inService, color: "text-emerald-500", icon: Activity, bg: "bg-emerald-50" },
              { label: "Completed", value: done, color: "text-slate-500", icon: CheckCircle, bg: "bg-slate-50" },
            ].map(({ label, value, color, icon: Icon, bg }) => (
              <div key={label} className="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider">{label}</p>
                    <p className={`text-3xl font-bold ${color} mt-1`}>{value}</p>
                  </div>
                  <div className={`w-10 h-10 rounded-lg ${bg} flex items-center justify-center`}>
                    <Icon size={20} className={color} />
                  </div>
                </div>
              </div>
            ))}
          </div>

          {/* Patient Search */}
          <div className="bg-white rounded-xl border border-slate-100 shadow-sm p-6 mb-6">
            <h2 className="font-semibold text-slate-900 mb-3 flex items-center gap-2">
              <Search size={16} className="text-blue-600" />
              Patient Search
            </h2>
            <div className="flex gap-2">
              <input
                type="text"
                placeholder="Search by name, email, or phone..."
                className="flex-1 border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
              />
              <button className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm font-medium transition-colors">
                Search
              </button>
            </div>
            <p className="text-xs text-slate-400 mt-2">Search for patients to view their active tickets and history.</p>
          </div>

          {/* Queue Board */}
          <div className="bg-white rounded-xl border border-slate-100 shadow-sm">
            <div className="flex items-center justify-between px-5 py-4 border-b border-slate-100">
              <h2 className="font-semibold text-slate-900">Live Queue Board — All Departments</h2>
              <span className="w-2 h-2 bg-emerald-500 rounded-full animate-pulse" />
            </div>
            {queue.length === 0 ? (
              <div className="p-10 text-center text-slate-400">No patients in today's queue yet.</div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="bg-slate-50 border-b border-slate-100">
                    <tr>
                      {["Ticket", "Patient", "Department", "Status", "Channel", "Checked In", "Doctor"].map(h => (
                        <th key={h} className="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">{h}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-50">
                    {queue.map(entry => {
                      const badge = getStatusBadge(entry.status);
                      return (
                        <tr key={entry.id} className="hover:bg-slate-50 transition-colors">
                          <td className="px-4 py-3">
                            <span className="font-mono text-xs font-bold text-blue-700 bg-blue-50 px-2 py-1 rounded-md">{entry.ticketCode}</span>
                          </td>
                          <td className="px-4 py-3 font-medium text-slate-900">{entry.patientName}</td>
                          <td className="px-4 py-3 text-slate-600">{entry.department}</td>
                          <td className="px-4 py-3">
                            <span className={`text-xs font-semibold px-2.5 py-1 rounded-full ${badge.bg} ${badge.text}`}>
                              {badge.label}
                            </span>
                          </td>
                          <td className="px-4 py-3 capitalize text-slate-500">{entry.entryChannel.replace(/_/g, " ")}</td>
                          <td className="px-4 py-3 text-slate-500">{formatTime(entry.checkedInAt)}</td>
                          <td className="px-4 py-3 text-slate-500">{entry.doctorName || "—"}</td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}
          </div>

          {/* Walk-in Modal */}
          {showWalkin && (
            <div className="fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-xl shadow-xl p-6 max-w-sm w-full border border-slate-100">
                <div className="flex items-center justify-between mb-5">
                  <h3 className="font-bold text-slate-900 text-lg">Register Walk-in Patient</h3>
                  <button onClick={() => setShowWalkin(false)} className="text-slate-400 hover:text-slate-600 transition-colors">
                    <X size={18} />
                  </button>
                </div>
                <form onSubmit={addWalkin}>
                  <div className="space-y-4">
                    <div>
                      <label className="block text-sm font-medium text-slate-700 mb-1.5">Full Name *</label>
                      <input
                        value={walkin.name}
                        onChange={e => setWalkin(w => ({ ...w, name: e.target.value }))}
                        placeholder="Patient full name"
                        className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                        required
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-slate-700 mb-1.5">Phone Number</label>
                      <input
                        value={walkin.phone}
                        onChange={e => setWalkin(w => ({ ...w, phone: e.target.value }))}
                        placeholder="09XXXXXXXXX"
                        className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-slate-700 mb-1.5">Department *</label>
                      <select
                        value={walkin.deptId}
                        onChange={e => setWalkin(w => ({ ...w, deptId: e.target.value }))}
                        className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                        required
                      >
                        <option value="">Select Department</option>
                        {departments.map(d => (
                          <option key={d.department_id} value={d.department_id}>{d.department_name}</option>
                        ))}
                      </select>
                    </div>
                  </div>
                  <div className="flex gap-3 mt-6">
                    <button
                      type="button"
                      onClick={() => setShowWalkin(false)}
                      className="flex-1 border border-slate-200 text-slate-700 font-semibold py-2.5 rounded-lg hover:bg-slate-50 text-sm transition-colors"
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      disabled={submitting}
                      className="flex-1 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-semibold py-2.5 rounded-lg transition-colors text-sm flex items-center justify-center gap-2"
                    >
                      {submitting ? <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" /> : null}
                      Add to Queue
                    </button>
                  </div>
                </form>
              </div>
            </div>
          )}

          {/* Emergency Triage Modal */}
          {showEmergency && (
            <div className="fixed inset-0 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-xl shadow-xl p-6 max-w-sm w-full border border-slate-100">
                <div className="flex items-center justify-between mb-5">
                  <h3 className="font-bold text-slate-900 text-lg flex items-center gap-2">
                    <Flame size={18} className="text-red-600" />
                    Emergency Triage
                  </h3>
                  <button onClick={() => setShowEmergency(false)} className="text-slate-400 hover:text-slate-600 transition-colors">
                    <X size={18} />
                  </button>
                </div>
                <form onSubmit={registerEmergency}>
                  <div className="space-y-4">
                    <div ref={patientSearchRef} className="relative">
                      <label className="block text-sm font-medium text-slate-700 mb-1.5">Patient *</label>
                      <div className="relative">
                        <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input
                          type="text"
                          value={patientQuery}
                          onChange={e => {
                            setPatientQuery(e.target.value);
                            setShowPatientDropdown(true);
                            if (emergency.patientId && !e.target.value) {
                              setEmergency(w => ({ ...w, patientId: "" }));
                              setSelectedPatientName("");
                            }
                          }}
                          onFocus={() => setShowPatientDropdown(true)}
                          placeholder="Search patient name..."
                          className="w-full border border-slate-200 rounded-lg pl-9 pr-8 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition"
                          required
                        />
                        {selectedPatientName && (
                          <button
                            type="button"
                            onClick={clearPatientSelection}
                            className="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                          >
                            <X size={14} />
                          </button>
                        )}
                      </div>
                      {showPatientDropdown && (patientQuery.length >= 2 || patients.length > 0) && (
                        <div className="absolute z-10 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                          {loadingPatients ? (
                            <div className="px-3 py-3 text-sm text-slate-500 text-center">Searching...</div>
                          ) : patients.length === 0 ? (
                            <div className="px-3 py-3 text-sm text-slate-400 text-center">No patients found</div>
                          ) : (
                            patients.map(p => (
                              <button
                                key={p.patient_id}
                                type="button"
                                onClick={() => selectPatient(p)}
                                className="w-full text-left px-3 py-2.5 hover:bg-slate-50 transition-colors border-b border-slate-50 last:border-b-0"
                              >
                                <p className="text-sm font-medium text-slate-900">{p.name}</p>
                                <p className="text-xs text-slate-500">{p.email} · {p.phone}</p>
                              </button>
                            ))
                          )}
                        </div>
                      )}
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-slate-700 mb-1.5">Department *</label>
                      <select
                        value={emergency.departmentId}
                        onChange={e => setEmergency(w => ({ ...w, departmentId: e.target.value }))}
                        className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition"
                        required
                      >
                        <option value="">Select Department</option>
                        {departments.map(d => (
                          <option key={d.department_id} value={d.department_id}>{d.department_name}</option>
                        ))}
                      </select>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-slate-700 mb-1.5">Acuity Level (1-5) *</label>
                      <select
                        value={emergency.acuityLevel}
                        onChange={e => setEmergency(w => ({ ...w, acuityLevel: e.target.value }))}
                        className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition"
                        required
                      >
                        <option value="1">1 - Critical (Immediate)</option>
                        <option value="2">2 - Emergency (Very Urgent)</option>
                        <option value="3">3 - Urgent</option>
                        <option value="4">4 - Less Urgent</option>
                        <option value="5">5 - Non-Urgent</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-slate-700 mb-1.5">Primary Symptom *</label>
                      <input
                        value={emergency.symptom}
                        onChange={e => setEmergency(w => ({ ...w, symptom: e.target.value }))}
                        placeholder="e.g., Chest pain, Difficulty breathing"
                        className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition"
                        required
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-slate-700 mb-1.5">Check-in Location</label>
                      <input
                        value={emergency.checkInLoc}
                        onChange={e => setEmergency(w => ({ ...w, checkInLoc: e.target.value }))}
                        placeholder="e.g., ER Bay 3"
                        className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition"
                      />
                    </div>
                  </div>
                  <div className="flex gap-3 mt-6">
                    <button
                      type="button"
                      onClick={() => {
                        setShowEmergency(false);
                        setPatientQuery("");
                        setSelectedPatientName("");
                        setPatients([]);
                        setEmergency(w => ({ ...w, patientId: "" }));
                      }}
                      className="flex-1 border border-slate-200 text-slate-700 font-semibold py-2.5 rounded-lg hover:bg-slate-50 text-sm transition-colors"
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      disabled={submitting}
                      className="flex-1 bg-red-600 hover:bg-red-700 disabled:bg-red-400 text-white font-semibold py-2.5 rounded-lg transition-colors text-sm flex items-center justify-center gap-2"
                    >
                      {submitting ? <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" /> : null}
                      Register Emergency
                    </button>
                  </div>
                </form>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
