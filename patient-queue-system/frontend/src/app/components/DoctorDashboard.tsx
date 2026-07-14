import { useState, useEffect, useCallback } from "react";
import { Page, SessionUser } from "../App";
import {
  User, ClipboardList, CheckCircle, ChevronDown,
  ChevronUp, FileText, RefreshCw, AlertCircle
} from "lucide-react";
import { toast } from "sonner";
import { api } from "../api";

interface Props { navigate: (p: Page) => void; session: SessionUser; }

interface Patient {
  id: number;
  ticketId: number;
  ticketCode: string;
  queuePosition: number;
  name: string;
  phone?: string;
  reason?: string;
  entryChannel: string;
  status: "waiting" | "called" | "in_service" | "completed" | "no_show" | "cancelled";
  scheduledAt?: string;
}

export function DoctorDashboard({ session }: Props) {
  const [patients, setPatients]   = useState<Patient[]>([]);
  const [selected, setSelected]   = useState<Patient | null>(null);
  const [showHistory, setShowHistory] = useState(false);
  const [medicalHistory, setMedicalHistory] = useState<any[]>([]);
  const [loadingHistory, setLoadingHistory] = useState(false);
  const [consult, setConsult]     = useState({ diagnosis: "", prescription: "", notes: "", followUp: "" });
  const [saving, setSaving]       = useState(false);
  const [loading, setLoading]     = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError]         = useState("");

  const fetchQueue = useCallback(async (showRefresh = false) => {
    if (showRefresh) setRefreshing(true);
    try {
      const res = await api.getActiveTickets({
        doctor_id: session.id ? undefined : undefined,
      });
      if (res.success) {
        const mapped: Patient[] = res.tickets.map((t: any) => ({
          id: t.patientId,
          ticketId: t.ticketId ?? t.id,
          ticketCode: t.ticketCode,
          queuePosition: t.queuePosition ?? t.queueNumber ?? t.sequence_number,
          name: t.patientName,
          phone: t.phone,
          reason: t.reason,
          entryChannel: t.entryChannel ?? t.entry_channel ?? 'online',
          status: t.status,
          scheduledAt: t.scheduledAt,
        }));
        setPatients(mapped);

        const active = mapped.find(p => p.status === "called" || p.status === "in_service");
        const first  = mapped.find(p => p.status === "waiting");
        if (!selected || !mapped.find(p => p.ticketId === selected.ticketId)) {
          setSelected(active ?? first ?? mapped[0] ?? null);
        }
        setError("");
      }
    } catch (err: any) {
      setError(err.message || "Failed to load patient queue.");
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [selected]);

  useEffect(() => {
    fetchQueue();
    const interval = setInterval(() => fetchQueue(false), 15000);
    return () => clearInterval(interval);
  }, []);

  useEffect(() => {
    if (!selected) {
      setMedicalHistory([]);
      return;
    }
    let cancelled = false;
    setLoadingHistory(true);
    api.getMedicalRecords(selected.id).then(res => {
      if (!cancelled && res.success) {
        setMedicalHistory(res.records || []);
      }
    }).catch(() => setMedicalHistory([])).finally(() => { if (!cancelled) setLoadingHistory(false); });
    return () => { cancelled = true; };
  }, [selected]);

  const complete = async () => {
    if (!selected) return;
    if (!consult.diagnosis.trim()) { toast.error("Please enter a diagnosis."); return; }
    setSaving(true);
    try {
      const treatmentNote = [
        consult.diagnosis && `Diagnosis: ${consult.diagnosis}`,
        consult.prescription && `Rx: ${consult.prescription}`,
        consult.notes && `Notes: ${consult.notes}`,
        consult.followUp && `Follow-up: ${consult.followUp}`,
      ].filter(Boolean).join("\n");

      const res = await api.completeVisit(selected.ticketId, treatmentNote, selected.reason || '');
      if (res.success) {
        toast.success("Consultation saved. Patient marked as completed.");
        setConsult({ diagnosis: "", prescription: "", notes: "", followUp: "" });
        setShowHistory(false);
        await fetchQueue(false);
      }
    } catch (err: any) {
      toast.error(err.message || "Failed to complete visit.");
    } finally {
      setSaving(false);
    }
  };

  const serveNext = async () => {
    try {
      const res = await api.serveNextPatient();
      if (res.success) {
        toast.success(`Now serving: ${res.ticket?.patient_name ?? "next patient"}`);
        await fetchQueue(false);
      }
    } catch (err: any) {
      toast.error(err.message || "Failed to call next patient.");
    }
  };

  const waiting   = patients.filter(p => p.status === "waiting").length;
  const done      = patients.filter(p => p.status === "completed").length;
  const inService = patients.filter(p => p.status === "called" || p.status === "in_service").length;

  const statusConfig: Record<string, { label: string; className: string }> = {
    waiting:    { label: "Waiting",    className: "bg-amber-50 text-amber-700 border-amber-200" },
    called:     { label: "Called",     className: "bg-emerald-50 text-emerald-700 border-emerald-200" },
    in_service: { label: "In Service", className: "bg-emerald-50 text-emerald-700 border-emerald-200" },
    completed:  { label: "Completed",  className: "bg-slate-100 text-slate-600 border-slate-200" },
    no_show:    { label: "No Show",    className: "bg-red-50 text-red-700 border-red-200" },
    cancelled:  { label: "Cancelled",  className: "bg-red-50 text-red-700 border-red-200" },
  };

  const channelLabel = (ch: string) => ch.replace(/_/g, " ").replace(/\b\w/g, c => c.toUpperCase());

  if (loading || !session) {
    return (
      <div className="max-w-6xl mx-auto px-4 py-16 text-center">
        <span className="w-10 h-10 border-4 border-blue-600/30 border-t-blue-600 rounded-full animate-spin inline-block mb-3" />
        <p className="text-slate-500">Loading your patient queue...</p>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-slate-50">
      <div className="flex-1 overflow-auto">
        <div className="max-w-6xl mx-auto px-4 py-8">
          <div className="flex items-center justify-between mb-6">
            <div>
              <h1 className="text-2xl font-bold text-slate-900">Doctor Dashboard</h1>
              <p className="text-slate-500 text-sm">{session.name}</p>
            </div>
            <div className="flex gap-3">
              <button
                onClick={serveNext}
                className="flex items-center gap-2 bg-emerald-500 hover:bg-emerald-600 text-white font-semibold px-4 py-2.5 rounded-lg text-sm transition-colors"
              >
                Call Next Patient
              </button>
              <button
                onClick={() => fetchQueue(true)}
                disabled={refreshing}
                className="flex items-center gap-2 border border-slate-200 text-slate-600 hover:bg-slate-100 px-3 py-2 rounded-lg text-sm transition-colors"
              >
                <RefreshCw size={14} className={refreshing ? "animate-spin" : ""} />
              </button>
            </div>
          </div>

          <div className="grid grid-cols-3 gap-4 mb-6">
            {[
              { label: "Waiting",     value: waiting,   color: "text-amber-600", bg: "bg-amber-50" },
              { label: "In Service",  value: inService, color: "text-emerald-600", bg: "bg-emerald-50" },
              { label: "Completed",   value: done,       color: "text-slate-600", bg: "bg-slate-100" },
            ].map(({ label, value, color, bg }) => (
              <div key={label} className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 text-center">
                <div className={`text-3xl font-bold ${color}`}>{value}</div>
                <div className="text-xs text-slate-500 mt-1 font-medium">{label}</div>
              </div>
            ))}
          </div>

          {error && (
            <div className="bg-red-50 border border-red-200 rounded-xl p-4 mb-6 flex items-center gap-2 text-red-700 text-sm">
              <AlertCircle size={16} />
              {error}
            </div>
          )}

          <div className="grid lg:grid-cols-3 gap-6">
            <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
              <div className="px-6 py-4 border-b border-slate-100">
                <h2 className="font-semibold text-slate-900 text-sm">Today's Patients ({patients.length})</h2>
              </div>
              <div className="divide-y divide-slate-100 max-h-[520px] overflow-y-auto">
                {patients.length === 0 ? (
                  <div className="p-8 text-center text-slate-400 text-sm">No patients in your queue today.</div>
                ) : (
                  patients.map(p => {
                    const status = statusConfig[p.status] || statusConfig.waiting;
                    return (
                      <button
                        key={p.ticketId}
                        onClick={() => { setSelected(p); setShowHistory(false); }}
                        className={`w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-slate-50 transition-colors ${
                          selected?.ticketId === p.ticketId ? "bg-blue-50 border-r-2 border-blue-600" : ""
                        }`}
                      >
                        <div className="w-9 h-9 rounded-lg flex items-center justify-center text-xs font-bold flex-shrink-0 bg-slate-100 text-slate-700">
                          #{p.ticketCode}
                        </div>
                        <div className="flex-1 min-w-0">
                          <p className="text-sm font-semibold text-slate-900 truncate">{p.name}</p>
                          <p className="text-xs text-slate-500 truncate">{channelLabel(p.entryChannel)} · Position #{p.queuePosition}</p>
                        </div>
                        <span className={`text-xs px-2 py-0.5 rounded-full font-medium border flex-shrink-0 ${status.className}`}>
                          {status.label}
                        </span>
                      </button>
                    );
                  })
                )}
              </div>
            </div>

            <div className="lg:col-span-2 space-y-4">
              {!selected ? (
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-12 text-center text-slate-400">
                  <User size={40} className="mx-auto mb-3 text-slate-300" />
                  <p>Select a patient to view details</p>
                </div>
              ) : (
                <>
                  <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <div className="flex items-start justify-between flex-wrap gap-3">
                      <div className="flex items-start gap-4">
                        <div className="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center text-white font-bold text-lg flex-shrink-0">
                          {selected.name.charAt(0)}
                        </div>
                        <div>
                          <h3 className="font-bold text-slate-900">{selected.name}</h3>
                          {selected.phone && <p className="text-sm text-slate-500">{selected.phone}</p>}
                          {selected.reason && <p className="text-sm text-slate-600 mt-1 italic">"{selected.reason}"</p>}
                          <p className="text-xs text-slate-400 mt-1">
                            Ticket #{selected.ticketCode} · {channelLabel(selected.entryChannel)} · Position #{selected.queuePosition}
                          </p>
                        </div>
                      </div>
                      <button
                        onClick={() => setShowHistory(!showHistory)}
                        className="flex items-center gap-1.5 text-sm text-blue-600 hover:bg-blue-50 border border-blue-200 px-3 py-1.5 rounded-lg transition-colors"
                      >
                        <ClipboardList size={14} />
                        History
                        {showHistory ? <ChevronUp size={14} /> : <ChevronDown size={14} />}
                      </button>
                    </div>

                    {showHistory && (
                      <div className="mt-5 pt-5 border-t border-slate-100">
                        <h4 className="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Medical History</h4>
                        {loadingHistory ? (
                          <p className="text-sm text-slate-400">Loading history...</p>
                        ) : medicalHistory.length === 0 ? (
                          <p className="text-sm text-slate-400">No previous visits recorded.</p>
                        ) : (
                          <div className="space-y-3">
                            {medicalHistory.slice(0, 5).map(record => (
                              <div key={record.record_id} className="bg-slate-50 rounded-lg p-4">
                                <div className="flex items-center justify-between mb-1">
                                  <span className="text-xs font-semibold text-slate-700">
                                    {new Date(record.visit_date).toLocaleDateString()}
                                  </span>
                                  <span className="text-xs text-slate-500">{record.department}</span>
                                </div>
                                <p className="text-xs text-slate-600 mb-1"><span className="font-semibold">Symptoms:</span> {record.symptoms}</p>
                                {record.treatment_notes && (
                                  <p className="text-xs text-slate-600"><span className="font-semibold">Treatment:</span> {record.treatment_notes}</p>
                                )}
                                <p className="text-xs text-slate-400 mt-1">Dr. {record.doctor_name}</p>
                              </div>
                            ))}
                          </div>
                        )}
                      </div>
                    )}
                  </div>

                  {selected.status !== "completed" && selected.status !== "no_show" && selected.status !== "cancelled" ? (
                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                      <h3 className="font-semibold text-slate-900 mb-4 flex items-center gap-2">
                        <FileText size={16} className="text-blue-600" />
                        Consultation Notes
                      </h3>
                      <div className="space-y-4">
                        <div>
                          <label className="block text-sm font-medium text-slate-700 mb-1.5">Symptoms / Chief Complaint</label>
                          <textarea
                            value={selected.reason || consult.diagnosis}
                            onChange={e => setConsult(c => ({...c, diagnosis: e.target.value}))}
                            placeholder="Patient's presenting symptoms or reason for visit..."
                            rows={2}
                            className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition resize-none"
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-slate-700 mb-1.5">Diagnosis</label>
                          <input
                            value={consult.diagnosis}
                            onChange={e => setConsult(c => ({...c, diagnosis: e.target.value}))}
                            placeholder="Primary diagnosis..."
                            className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-slate-700 mb-1.5">Prescription</label>
                          <textarea
                            value={consult.prescription}
                            onChange={e => setConsult(c => ({...c, prescription: e.target.value}))}
                            placeholder="Medications and dosage..."
                            rows={2}
                            className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition resize-none"
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-slate-700 mb-1.5">Clinical Notes</label>
                          <textarea
                            value={consult.notes}
                            onChange={e => setConsult(c => ({...c, notes: e.target.value}))}
                            placeholder="Observations, findings, instructions..."
                            rows={3}
                            className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition resize-none"
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-slate-700 mb-1.5">Follow-up Date</label>
                          <input
                            type="date"
                            value={consult.followUp}
                            onChange={e => setConsult(c => ({...c, followUp: e.target.value}))}
                            className="border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                          />
                        </div>
                      </div>
                      <button
                        onClick={complete}
                        disabled={saving}
                        className="mt-5 flex items-center gap-2 bg-emerald-500 hover:bg-emerald-600 disabled:bg-emerald-300 text-white font-semibold px-5 py-2.5 rounded-lg transition-colors"
                      >
                        {saving
                          ? <><span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />Saving...</>
                          : <><CheckCircle size={16} />Complete & Save</>}
                      </button>
                    </div>
                  ) : (
                    <div className="bg-slate-100 border border-slate-200 rounded-xl p-6 text-center">
                      <CheckCircle size={32} className="text-slate-400 mx-auto mb-2" />
                      <p className="font-semibold text-slate-700">Consultation Completed</p>
                      <p className="text-sm text-slate-500 mt-1">This patient has been attended to.</p>
                    </div>
                  )}
                </>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
