import { useState, useEffect } from "react";
import { Page, SessionUser } from "../App";
import { Plus, Pencil, Trash2, Users, Activity, X, Check } from "lucide-react";
import { toast } from "sonner";
import { api } from "../api";

interface Department {
  department_id: number;
  department_name: string;
  prefix_code: string;
  doctor_count?: number;
  active_tickets?: number;
}

interface Props { navigate: (p: Page) => void; session: SessionUser; }

export function AdminDepartments({ navigate }: Props) {
  const [departments, setDepartments] = useState<Department[]>([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState<Department | null>(null);
  const [form, setForm] = useState({ department_name: "", prefix_code: "" });
  const [saving, setSaving] = useState(false);

  const loadDepartments = async () => {
    try {
      const [deptRes, staffRes] = await Promise.all([
        api.getDepartments(),
        api.getStaff(),
      ]);
      if (deptRes.success) {
        const depts = deptRes.departments || [];
        const doctors = (staffRes.staff || []).filter((s: any) => s.role === "doctor");
        const enriched = depts.map((d: Department) => ({
          ...d,
          doctor_count: doctors.filter((doc: any) => doc.department_id === d.department_id).length,
        }));
        setDepartments(enriched);
      }
    } catch (err: any) {
      toast.error(err.message || "Failed to load departments.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadDepartments();
  }, []);

  const openAdd = () => {
    setEditing(null);
    setForm({ department_name: "", prefix_code: "" });
    setShowForm(true);
  };

  const openEdit = (dept: Department) => {
    setEditing(dept);
    setForm({ department_name: dept.department_name, prefix_code: dept.prefix_code });
    setShowForm(true);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      if (editing) {
        const res = await api.updateDepartment({
          department_id: editing.department_id,
          department_name: form.department_name,
          prefix_code: form.prefix_code,
        });
        if (res.success) {
          toast.success("Department updated.");
          setShowForm(false);
          loadDepartments();
        } else {
          toast.error(res.message || "Failed to update department.");
        }
      } else {
        const res = await api.addDepartment({
          department_name: form.department_name,
          prefix_code: form.prefix_code,
        });
        if (res.success) {
          toast.success("Department created.");
          setShowForm(false);
          loadDepartments();
        } else {
          toast.error(res.message || "Failed to create department.");
        }
      }
    } catch (err: any) {
      toast.error(err.message || "Operation failed.");
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (dept: Department) => {
    if (!confirm(`Delete department "${dept.department_name}"? This cannot be undone.`)) return;
    try {
      const res = await api.deleteDepartment(dept.department_id);
      if (res.success) {
        toast.success("Department deleted.");
        loadDepartments();
      } else {
        toast.error(res.message || "Failed to delete department.");
      }
    } catch (err: any) {
      toast.error(err.message || "Delete failed.");
    }
  };

  return (
    <div className="min-h-screen bg-slate-50">
      <div className="max-w-5xl mx-auto px-4 py-8">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
          <div>
            <h1 className="text-2xl font-bold text-slate-900">Departments</h1>
            <p className="text-slate-500 text-sm mt-1">Manage clinic departments</p>
          </div>
          <button
            onClick={openAdd}
            className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2.5 rounded-lg text-sm transition-colors"
          >
            <Plus size={16} />
            Add Department
          </button>
        </div>

        {loading ? (
          <div className="text-center py-16">
            <span className="w-10 h-10 border-4 border-blue-600/30 border-t-blue-600 rounded-full animate-spin inline-block mb-3" />
            <p className="text-slate-500">Loading departments...</p>
          </div>
        ) : departments.length === 0 ? (
          <div className="bg-white rounded-xl border border-slate-100 shadow-sm p-12 text-center">
            <p className="text-slate-400">No departments found.</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {departments.map(dept => (
              <div key={dept.department_id} className="bg-white rounded-xl border border-slate-100 shadow-sm p-5 hover:shadow-md transition-shadow">
                <div className="flex items-start justify-between mb-3">
                  <div>
                    <h3 className="font-semibold text-slate-900">{dept.department_name}</h3>
                    <p className="text-xs text-slate-500 font-mono mt-0.5">{dept.prefix_code}</p>
                  </div>
                  <div className="flex gap-1">
                    <button
                      onClick={() => openEdit(dept)}
                      className="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                    >
                      <Pencil size={14} />
                    </button>
                    <button
                      onClick={() => handleDelete(dept)}
                      className="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                    >
                      <Trash2 size={14} />
                    </button>
                  </div>
                </div>
                <div className="flex items-center gap-4 text-xs text-slate-500">
                  <span className="flex items-center gap-1">
                    <Users size={12} />
                    {dept.doctor_count} doctors
                  </span>
                </div>
              </div>
            ))}
          </div>
        )}

        {showForm && (
          <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-2xl shadow-xl p-6 max-w-md w-full">
              <div className="flex items-center justify-between mb-4">
                <h3 className="font-bold text-slate-900">{editing ? "Edit Department" : "Add Department"}</h3>
                <button onClick={() => setShowForm(false)} className="text-slate-400 hover:text-slate-600">
                  <X size={20} />
                </button>
              </div>
              <form onSubmit={handleSubmit} className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1.5">Department Name</label>
                  <input
                    type="text"
                    value={form.department_name}
                    onChange={e => setForm({ ...form, department_name: e.target.value })}
                    className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    required
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-slate-700 mb-1.5">Prefix Code</label>
                  <input
                    type="text"
                    value={form.prefix_code}
                    onChange={e => setForm({ ...form, prefix_code: e.target.value.toUpperCase() })}
                    className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    required
                    maxLength={10}
                  />
                </div>
                <button
                  type="submit"
                  disabled={saving}
                  className="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-semibold py-2.5 rounded-xl transition-colors flex items-center justify-center gap-2"
                >
                  {saving ? <><span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" /> Saving...</> : <><Check size={16} /> {editing ? "Update" : "Create"}</>}
                </button>
              </form>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
