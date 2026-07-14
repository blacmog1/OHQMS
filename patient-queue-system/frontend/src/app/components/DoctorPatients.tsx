import { useState, useEffect } from "react";
import { Page, SessionUser } from "../App";
import { Search, UserPlus, FileText, Activity } from "lucide-react";
import { toast } from "sonner";
import { api } from "../api";

interface PatientResult {
  patient_id: number;
  first_name: string;
  last_name: string;
  email: string;
  phone_number: string;
  gender?: string;
  date_of_birth?: string;
}

interface Props { navigate: (p: Page) => void; session: SessionUser; }

export function DoctorPatients({ navigate, session }: Props) {
  const [patients, setPatients] = useState<PatientResult[]>([]);
  const [query, setQuery] = useState("");
  const [loading, setLoading] = useState(false);
  const [selectedPatient, setSelectedPatient] = useState<PatientResult | null>(null);
  const [medicalHistory, setMedicalHistory] = useState<any[]>([]);
  const [loadingHistory, setLoadingHistory] = useState(false);

  const searchPatients = async (q: string) => {
    if (q.length < 2) {
      setPatients([]);
      return;
    }
    setLoading(true);
    try {
      const res = await api.searchPatients(q);
      if (res.success) {
        setPatients(res.patients || []);
      }
    } catch (err: any) {
      toast.error(err.message || "Search failed.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    const timer = setTimeout(() => searchPatients(query), 300);
    return () => clearTimeout(timer);
  }, [query]);

  const loadMedicalHistory = async (patientId: number) => {
    setLoadingHistory(true);
    try {
      const res = await api.getMedicalRecords(patientId);
      if (res.success) {
        setMedicalHistory(res.records || []);
      }
    } catch (err: any) {
      toast.error(err.message || "Failed to load medical history.");
    } finally {
      setLoadingHistory(false);
    }
  };

  const selectPatient = (patient: PatientResult) => {
    setSelectedPatient(patient);
    loadMedicalHistory(patient.patient_id);
  };

  return (
    <div className="min-h-screen bg-slate-50">
      <div className="max-w-5xl mx-auto px-4 py-8">
        <div className="mb-8">
          <h1 className="text-2xl font-bold text-slate-900">Patient Search</h1>
          <p className="text-slate-500 text-sm mt-1">Search patients and view their medical history</p>
        </div>

        <div className="bg-white rounded-xl border border-slate-100 shadow-sm mb-6">
          <div className="p-4">
            <div className="relative">
              <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
              <input
                type="text"
                value={query}
                onChange={e => setQuery(e.target.value)}
                placeholder="Search by name, email, or phone..."
                className="w-full border border-slate-200 rounded-lg pl-9 pr-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-1">
            {loading && query.length >= 2 ? (
              <div className="bg-white rounded-xl border border-slate-100 shadow-sm p-6 text-center">
                <span className="w-8 h-8 border-4 border-blue-600/30 border-t-blue-600 rounded-full animate-spin inline-block mb-2" />
                <p className="text-sm text-slate-500">Searching...</p>
              </div>
            ) : patients.length > 0 ? (
              <div className="bg-white rounded-xl border border-slate-100 shadow-sm divide-y divide-slate-100">
                {patients.map(p => (
                  <button
                    key={p.patient_id}
                    onClick={() => selectPatient(p)}
                    className={`w-full text-left p-4 hover:bg-slate-50 transition-colors ${selectedPatient?.patient_id === p.patient_id ? "bg-blue-50" : ""}`}
                  >
                    <p className="font-medium text-slate-900 text-sm">{p.first_name} {p.last_name}</p>
                    <p className="text-xs text-slate-500 mt-0.5">{p.email}</p>
                    <p className="text-xs text-slate-400 mt-0.5">{p.phone_number}</p>
                  </button>
                ))}
              </div>
            ) : query.length >= 2 ? (
              <div className="bg-white rounded-xl border border-slate-100 shadow-sm p-6 text-center">
                <p className="text-sm text-slate-400">No patients found.</p>
              </div>
            ) : (
              <div className="bg-white rounded-xl border border-slate-100 shadow-sm p-6 text-center">
                <p className="text-sm text-slate-400">Type at least 2 characters to search.</p>
              </div>
            )}
          </div>

          <div className="lg:col-span-2">
            {selectedPatient ? (
              <div className="space-y-4">
                <div className="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
                  <h3 className="font-semibold text-slate-900 mb-3">Patient Details</h3>
                  <div className="grid grid-cols-2 gap-3 text-sm">
                    <div>
                      <span className="text-slate-500">Name:</span>
                      <p className="font-medium text-slate-900">{selectedPatient.first_name} {selectedPatient.last_name}</p>
                    </div>
                    <div>
                      <span className="text-slate-500">Email:</span>
                      <p className="font-medium text-slate-900">{selectedPatient.email}</p>
                    </div>
                    <div>
                      <span className="text-slate-500">Phone:</span>
                      <p className="font-medium text-slate-900">{selectedPatient.phone_number}</p>
                    </div>
                    <div>
                      <span className="text-slate-500">Gender:</span>
                      <p className="font-medium text-slate-900 capitalize">{selectedPatient.gender || "—"}</p>
                    </div>
                    <div>
                      <span className="text-slate-500">DOB:</span>
                      <p className="font-medium text-slate-900">{selectedPatient.date_of_birth ? new Date(selectedPatient.date_of_birth).toLocaleDateString() : "—"}</p>
                    </div>
                  </div>
                </div>

                <div className="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
                  <h3 className="font-semibold text-slate-900 mb-3">Medical History</h3>
                  {loadingHistory ? (
                    <div className="text-center py-8">
                      <span className="w-8 h-8 border-4 border-blue-600/30 border-t-blue-600 rounded-full animate-spin inline-block mb-2" />
                      <p className="text-sm text-slate-500">Loading records...</p>
                    </div>
                  ) : medicalHistory.length === 0 ? (
                    <p className="text-sm text-slate-400 text-center py-6">No medical records found.</p>
                  ) : (
                    <div className="space-y-3">
                      {medicalHistory.map(record => (
                        <div key={record.record_id} className="bg-slate-50 rounded-xl p-4 border border-slate-100">
                          <div className="flex items-center justify-between mb-2">
                            <span className="text-xs font-medium text-slate-500">{new Date(record.visit_date).toLocaleString()}</span>
                            <span className="text-xs font-medium text-blue-600">{record.department_name || "General"}</span>
                          </div>
                          {record.symptoms && (
                            <div className="text-sm text-slate-700 mb-1">
                              <span className="font-medium">Symptoms:</span> {record.symptoms}
                            </div>
                          )}
                          {record.treatment_notes && (
                            <div className="text-sm text-slate-700">
                              <span className="font-medium">Treatment:</span> {record.treatment_notes}
                            </div>
                          )}
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              </div>
            ) : (
              <div className="bg-white rounded-xl border border-slate-100 shadow-sm p-12 text-center">
                <Activity size={32} className="text-slate-300 mx-auto mb-3" />
                <p className="text-slate-400">Select a patient to view details and medical history.</p>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
