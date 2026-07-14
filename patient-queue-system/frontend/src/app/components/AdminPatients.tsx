import { useState, useEffect } from "react";
import { Page, SessionUser } from "../App";
import { Search, RefreshCw, ChevronRight, Phone, Calendar } from "lucide-react";
import { toast } from "sonner";
import { api } from "../api";

interface Patient {
  patient_id: number;
  first_name: string;
  last_name: string;
  email: string;
  phone_number: string;
  date_of_birth?: string;
  gender?: string;
  address?: string;
  created_at: string;
}

interface Props { navigate: (p: Page) => void; session: SessionUser; }

export function AdminPatients({ navigate }: Props) {
  const [patients, setPatients] = useState<Patient[]>([]);
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const loadPatients = async (pageNum = 1, searchQuery = "") => {
    try {
      const res = await api.getAllPatients({ search: searchQuery || undefined, page: pageNum, limit: 20 });
      if (res.success) {
        setPatients(res.patients || []);
        setTotalPages(res.pagination?.total_pages || 1);
        setPage(res.pagination?.page || 1);
      }
    } catch (err: any) {
      toast.error(err.message || "Failed to load patients.");
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    loadPatients();
  }, []);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    loadPatients(1, search);
  };

  return (
    <div className="min-h-screen bg-slate-50">
      <div className="max-w-6xl mx-auto px-4 py-8">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
          <div>
            <h1 className="text-2xl font-bold text-slate-900">Patients</h1>
            <p className="text-slate-500 text-sm mt-1">View and manage all registered patients</p>
          </div>
          <button
            onClick={() => { setRefreshing(true); loadPatients(page, search); }}
            disabled={refreshing}
            className="flex items-center gap-2 border border-slate-200 text-slate-600 hover:bg-white px-3 py-2 rounded-lg text-sm transition-colors bg-white"
          >
            <RefreshCw size={14} className={refreshing ? "animate-spin" : ""} />
            Refresh
          </button>
        </div>

        <div className="bg-white rounded-xl border border-slate-100 shadow-sm mb-6">
          <form onSubmit={handleSearch} className="p-4 flex gap-3">
            <div className="relative flex-1">
              <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
              <input
                type="text"
                value={search}
                onChange={e => setSearch(e.target.value)}
                placeholder="Search by name, email, or phone..."
                className="w-full border border-slate-200 rounded-lg pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
            <button type="submit" className="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2 rounded-lg text-sm transition-colors">
              Search
            </button>
          </form>
        </div>

        {loading ? (
          <div className="text-center py-16">
            <span className="w-10 h-10 border-4 border-blue-600/30 border-t-blue-600 rounded-full animate-spin inline-block mb-3" />
            <p className="text-slate-500">Loading patients...</p>
          </div>
        ) : patients.length === 0 ? (
          <div className="bg-white rounded-xl border border-slate-100 shadow-sm p-12 text-center">
            <p className="text-slate-400">No patients found.</p>
          </div>
        ) : (
          <>
            <div className="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="bg-slate-50 border-b border-slate-100">
                      <th className="text-left px-5 py-3 font-semibold text-slate-600">Name</th>
                      <th className="text-left px-5 py-3 font-semibold text-slate-600">Email</th>
                      <th className="text-left px-5 py-3 font-semibold text-slate-600">Phone</th>
                      <th className="text-left px-5 py-3 font-semibold text-slate-600">DOB</th>
                      <th className="text-left px-5 py-3 font-semibold text-slate-600">Registered</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {patients.map(p => (
                      <tr key={p.patient_id} className="hover:bg-slate-50 transition-colors">
                        <td className="px-5 py-3">
                          <button
                            onClick={() => navigate("patient-profile")}
                            className="font-medium text-blue-600 hover:text-blue-700 hover:underline"
                          >
                            {p.first_name} {p.last_name}
                          </button>
                        </td>
                        <td className="px-5 py-3 text-slate-600">{p.email}</td>
                        <td className="px-5 py-3 text-slate-600">{p.phone_number}</td>
                        <td className="px-5 py-3 text-slate-600">{p.date_of_birth ? new Date(p.date_of_birth).toLocaleDateString() : "—"}</td>
                        <td className="px-5 py-3 text-slate-600">{new Date(p.created_at).toLocaleDateString()}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>

            {totalPages > 1 && (
              <div className="flex items-center justify-between mt-4">
                <button
                  onClick={() => { setPage(p => Math.max(1, p - 1)); loadPatients(Math.max(1, page - 1), search); }}
                  disabled={page <= 1}
                  className="px-4 py-2 border border-slate-200 rounded-lg text-sm disabled:opacity-50 hover:bg-white transition-colors"
                >
                  Previous
                </button>
                <span className="text-sm text-slate-500">Page {page} of {totalPages}</span>
                <button
                  onClick={() => { setPage(p => Math.min(totalPages, p + 1)); loadPatients(Math.min(totalPages, page + 1), search); }}
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
