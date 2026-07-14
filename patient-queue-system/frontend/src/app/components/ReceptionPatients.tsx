import { useState } from "react";
import { Page, SessionUser } from "../App";
import { UserPlus, Search, X, Check } from "lucide-react";
import { toast } from "sonner";
import { api } from "../api";

interface Props { navigate: (p: Page) => void; session: SessionUser; }

export function ReceptionPatients({ navigate }: Props) {
  const [showForm, setShowForm] = useState(false);
  const [query, setQuery] = useState("");
  const [results, setResults] = useState<any[]>([]);
  const [searching, setSearching] = useState(false);
  const [form, setForm] = useState({
    first_name: "",
    last_name: "",
    email: "",
    phone_number: "",
    date_of_birth: "",
    gender: "",
    address: "",
  });
  const [saving, setSaving] = useState(false);

  const searchExisting = async (q: string) => {
    if (q.length < 2) {
      setResults([]);
      return;
    }
    setSearching(true);
    try {
      const res = await api.searchPatients(q);
      if (res.success) {
        setResults(res.patients || []);
      }
    } catch {
      setResults([]);
    } finally {
      setSearching(false);
    }
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    searchExisting(query);
  };

  const handleRegister = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      const res = await api.registerPatient({
        first_name: form.first_name,
        last_name: form.last_name,
        email: form.email,
        phone_number: form.phone_number,
        date_of_birth: form.date_of_birth || undefined,
        gender: form.gender || undefined,
        address: form.address || undefined,
      });
      if (res.success) {
        toast.success(`Patient registered. Temporary password: ${res.temporary_password}`);
        setShowForm(false);
        setForm({ first_name: "", last_name: "", email: "", phone_number: "", date_of_birth: "", gender: "", address: "" });
      } else {
        toast.error(res.message || "Registration failed.");
      }
    } catch (err: any) {
      toast.error(err.message || "Registration failed.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="min-h-screen bg-slate-50">
      <div className="max-w-4xl mx-auto px-4 py-8">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
          <div>
            <h1 className="text-2xl font-bold text-slate-900">Patient Registration</h1>
            <p className="text-slate-500 text-sm mt-1">Search existing patients or register new ones</p>
          </div>
          <button
            onClick={() => setShowForm(true)}
            className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2.5 rounded-lg text-sm transition-colors"
          >
            <UserPlus size={16} />
            Register New Patient
          </button>
        </div>

        <div className="bg-white rounded-xl border border-slate-100 shadow-sm mb-6">
          <form onSubmit={handleSearch} className="p-4 flex gap-3">
            <div className="relative flex-1">
              <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
              <input
                type="text"
                value={query}
                onChange={e => setQuery(e.target.value)}
                placeholder="Search by name, email, or phone..."
                className="w-full border border-slate-200 rounded-lg pl-9 pr-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
            <button type="submit" className="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2 rounded-lg text-sm transition-colors">
              Search
            </button>
          </form>
        </div>

        {searching ? (
          <div className="text-center py-8">
            <span className="w-8 h-8 border-4 border-blue-600/30 border-t-blue-600 rounded-full animate-spin inline-block mb-2" />
            <p className="text-sm text-slate-500">Searching...</p>
          </div>
        ) : results.length > 0 ? (
          <div className="bg-white rounded-xl border border-slate-100 shadow-sm divide-y divide-slate-100">
            {results.map(p => (
              <div key={p.patient_id} className="p-4 flex items-center justify-between">
                <div>
                  <p className="font-medium text-slate-900 text-sm">{p.name}</p>
                  <p className="text-xs text-slate-500">{p.email} · {p.phone}</p>
                  {p.department && <p className="text-xs text-slate-400 mt-0.5">Current queue: {p.department}</p>}
                </div>
              </div>
            ))}
          </div>
        ) : query.length >= 2 ? (
          <div className="bg-white rounded-xl border border-slate-100 shadow-sm p-8 text-center">
            <p className="text-slate-400">No patients found matching "{query}"</p>
          </div>
        ) : (
          <div className="bg-white rounded-xl border border-slate-100 shadow-sm p-8 text-center">
            <p className="text-slate-400">Type at least 2 characters to search for existing patients.</p>
          </div>
        )}

        {showForm && (
          <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl p-6 max-w-lg w-full max-h-[90vh] overflow-y-auto">
              <div className="flex items-center justify-between mb-4">
                <h3 className="font-bold text-slate-900">Register New Patient</h3>
                <button onClick={() => setShowForm(false)} className="text-slate-400 hover:text-slate-600">
                  <X size={20} />
                </button>
              </div>
              <form onSubmit={handleRegister} className="space-y-4">
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">First Name *</label>
                    <input type="text" value={form.first_name} onChange={e => setForm({ ...form, first_name: e.target.value })} className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">Last Name *</label>
                    <input type="text" value={form.last_name} onChange={e => setForm({ ...form, last_name: e.target.value })} className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required />
                  </div>
                </div>
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1.5">Email *</label>
                  <input type="email" value={form.email} onChange={e => setForm({ ...form, email: e.target.value })} className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required />
                </div>
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1.5">Phone Number *</label>
                  <input type="tel" value={form.phone_number} onChange={e => setForm({ ...form, phone_number: e.target.value })} className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required />
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">Date of Birth</label>
                    <input type="date" value={form.date_of_birth} onChange={e => setForm({ ...form, date_of_birth: e.target.value })} className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1.5">Gender</label>
                    <select value={form.gender} onChange={e => setForm({ ...form, gender: e.target.value })} className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                      <option value="">Select</option>
                      <option value="male">Male</option>
                      <option value="female">Female</option>
                      <option value="other">Other</option>
                    </select>
                  </div>
                </div>
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1.5">Address</label>
                  <textarea value={form.address} onChange={e => setForm({ ...form, address: e.target.value })} rows={2} className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                </div>
                <button
                  type="submit"
                  disabled={saving}
                  className="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-semibold py-2.5 rounded-xl transition-colors flex items-center justify-center gap-2"
                >
                  {saving ? <><span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" /> Registering...</> : <><Check size={16} /> Register Patient</>}
                </button>
              </form>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
