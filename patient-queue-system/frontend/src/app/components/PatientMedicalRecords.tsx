import { useState, useEffect } from "react";
import { Page, SessionUser } from "../App";
import { FileText, Activity, Calendar } from "lucide-react";
import { toast } from "sonner";
import { api } from "../api";

interface MedicalRecord {
  record_id: number;
  visit_date: string;
  symptoms?: string;
  treatment_notes?: string;
  department_name?: string;
  doctor_first_name?: string;
  doctor_last_name?: string;
}

interface Props { navigate: (p: Page) => void; session: SessionUser; }

export function PatientMedicalRecords({ session }: Props) {
  const [records, setRecords] = useState<MedicalRecord[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const loadRecords = async () => {
      try {
        const res = await api.getMedicalRecords();
        if (res.success) {
          setRecords(res.records || []);
        }
      } catch (err: any) {
        toast.error(err.message || "Failed to load medical records.");
      } finally {
        setLoading(false);
      }
    };
    loadRecords();
  }, []);

  return (
    <div className="min-h-screen bg-slate-50">
      <div className="max-w-4xl mx-auto px-4 py-8">
        <div className="mb-8">
          <h1 className="text-2xl font-bold text-slate-900">Medical Records</h1>
          <p className="text-slate-500 text-sm mt-1">Your complete health history and visit records</p>
        </div>

        {loading ? (
          <div className="text-center py-16">
            <span className="w-10 h-10 border-4 border-blue-600/30 border-t-blue-600 rounded-full animate-spin inline-block mb-3" />
            <p className="text-slate-500">Loading records...</p>
          </div>
        ) : records.length === 0 ? (
          <div className="bg-white rounded-xl border border-slate-100 shadow-sm p-12 text-center">
            <FileText size={32} className="text-slate-300 mx-auto mb-3" />
            <p className="text-slate-400">No medical records found.</p>
          </div>
        ) : (
          <div className="space-y-4">
            {records.map(record => (
              <div key={record.record_id} className="bg-white rounded-xl border border-slate-100 shadow-sm p-5 hover:shadow-md transition-shadow">
                <div className="flex items-start justify-between mb-3">
                  <div className="flex items-center gap-2">
                    <div className="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center">
                      <Activity size={16} className="text-blue-600" />
                    </div>
                    <div>
                      <p className="text-sm font-semibold text-slate-900">
                        {record.doctor_first_name} {record.doctor_last_name}
                      </p>
                      <p className="text-xs text-slate-500">{record.department_name || "General"}</p>
                    </div>
                  </div>
                  <span className="text-xs text-slate-500 flex items-center gap-1">
                    <Calendar size={12} />
                    {new Date(record.visit_date).toLocaleDateString()}
                  </span>
                </div>
                {record.symptoms && (
                  <div className="mb-2">
                    <span className="text-xs font-semibold text-slate-500 uppercase tracking-wide">Symptoms</span>
                    <p className="text-sm text-slate-700 mt-0.5 bg-slate-50 rounded-lg p-2.5">{record.symptoms}</p>
                  </div>
                )}
                {record.treatment_notes && (
                  <div>
                    <span className="text-xs font-semibold text-slate-500 uppercase tracking-wide">Treatment Notes</span>
                    <p className="text-sm text-slate-700 mt-0.5 bg-slate-50 rounded-lg p-2.5 whitespace-pre-wrap">{record.treatment_notes}</p>
                  </div>
                )}
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
