import React, { useState, useEffect } from 'react';
import { api } from '../api';
import { Page, SessionUser } from '../App';
import { UserPlus, Pencil, Trash2, ArrowLeft, ShieldCheck } from 'lucide-react';

interface StaffMember {
  id: number;
  email: string;
  role: string;
  first_name: string;
  last_name: string;
  department_id?: number;
  department_name?: string;
  room_number?: string;
  doctor_status?: string;
  status?: string;
}

interface Department {
  department_id: number;
  department_name: string;
}

interface Props {
  navigate: (p: Page) => void;
  session: SessionUser;
}

export function AdminStaffManagement({ navigate }: Props) {
  const [staff, setStaff] = useState<StaffMember[]>([]);
  const [departments, setDepartments] = useState<Department[]>([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [formData, setFormData] = useState({
    email: '',
    password: '',
    role: 'doctor',
    first_name: '',
    last_name: '',
    department_id: '',
    room_number: '',
    status: 'active',
  });

  useEffect(() => {
    loadStaff();
    loadDepartments();
  }, []);

  const loadStaff = async () => {
    try {
      const data = await api.getStaff();
      setStaff(data.staff || []);
    } catch (err) {
      console.error('Failed to load staff:', err);
    } finally {
      setLoading(false);
    }
  };

  const loadDepartments = async () => {
    try {
      const data = await api.getDepartments();
      setDepartments(data.departments || []);
    } catch (err) {
      console.error('Failed to load departments:', err);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      if (editingId) {
        await api.updateStaff({
          user_id: editingId,
          ...(formData.status && { status: formData.status }),
          ...(formData.department_id && { department_id: Number(formData.department_id) }),
          ...(formData.room_number && { room_number: formData.room_number }),
        });
      } else {
        await api.addStaff({
          email: formData.email,
          password: formData.password,
          role: formData.role as 'doctor' | 'receptionist',
          first_name: formData.first_name,
          last_name: formData.last_name,
          ...(formData.department_id && { department_id: Number(formData.department_id) }),
          ...(formData.room_number && { room_number: formData.room_number }),
        });
      }
      setShowForm(false);
      setEditingId(null);
      setFormData({ email: '', password: '', role: 'doctor', first_name: '', last_name: '', department_id: '', room_number: '', status: 'active' });
      loadStaff();
    } catch (err) {
      console.error('Failed to save staff:', err);
    }
  };

  const handleEdit = (member: StaffMember) => {
    setEditingId(member.id);
    setFormData({
      email: member.email,
      password: '',
      role: member.role === 'receptionist' ? 'receptionist' : 'doctor',
      first_name: member.first_name,
      last_name: member.last_name,
      department_id: member.department_id?.toString() || '',
      room_number: member.room_number || '',
      status: member.status || 'active',
    });
    setShowForm(true);
  };

  const handleDelete = async (userId: number) => {
    if (!confirm('Are you sure you want to remove this staff member?')) return;
    try {
      await api.deleteStaff(userId);
      loadStaff();
    } catch (err) {
      console.error('Failed to delete staff:', err);
    }
  };

  const doctors = staff.filter(s => s.role === 'doctor');
  const receptionists = staff.filter(s => s.role === 'receptionist');

  const inputCls = "w-full px-3.5 py-2.5 border border-slate-200 rounded-lg text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition";
  const labelCls = "block text-sm font-medium text-slate-700 mb-1.5";

  return (
    <div className="min-h-screen bg-slate-50">
      <div className="max-w-6xl mx-auto px-4 py-8">
        <div className="flex items-center justify-between gap-4 mb-8">
          <div>
            <button
              onClick={() => navigate('admin-dashboard')}
              className="flex items-center gap-1.5 text-sm text-slate-500 hover:text-blue-600 mb-2 transition-colors"
            >
              <ArrowLeft size={15} /> Back to Dashboard
            </button>
            <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Staff Management</h1>
            <p className="text-slate-500 text-sm mt-1">Add and manage doctors and receptionists</p>
          </div>
          <button
            onClick={() => { setShowForm(true); setEditingId(null); setFormData({ email: '', password: '', role: 'doctor', first_name: '', last_name: '', department_id: '', room_number: '', status: 'active' }); }}
            className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2.5 rounded-lg text-sm transition-colors shadow-sm"
          >
            <UserPlus size={16} /> Add Staff Member
          </button>
        </div>

        {showForm && (
          <div className="bg-white rounded-xl shadow-sm border border-slate-100 p-6 mb-6">
            <h2 className="text-lg font-semibold mb-4 text-slate-900">{editingId ? 'Edit Staff Member' : 'Add New Staff Member'}</h2>
            <form onSubmit={handleSubmit} className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className={labelCls}>Email</label>
                <input
                  type="email"
                  required
                  disabled={!!editingId}
                  value={formData.email}
                  onChange={e => setFormData({ ...formData, email: e.target.value })}
                  className={inputCls}
                />
              </div>
              {!editingId && (
                <div>
                  <label className={labelCls}>Password</label>
                  <input
                    type="password"
                    required
                    minLength={8}
                    value={formData.password}
                    onChange={e => setFormData({ ...formData, password: e.target.value })}
                    className={inputCls}
                  />
                </div>
              )}
              <div>
                <label className={labelCls}>Role</label>
                <select
                  value={formData.role}
                  onChange={e => setFormData({ ...formData, role: e.target.value })}
                  className={inputCls}
                >
                  <option value="doctor">Doctor</option>
                  <option value="receptionist">Receptionist</option>
                </select>
              </div>
              <div>
                <label className={labelCls}>First Name</label>
                <input
                  type="text"
                  required
                  value={formData.first_name}
                  onChange={e => setFormData({ ...formData, first_name: e.target.value })}
                  className={inputCls}
                />
              </div>
              <div>
                <label className={labelCls}>Last Name</label>
                <input
                  type="text"
                  required
                  value={formData.last_name}
                  onChange={e => setFormData({ ...formData, last_name: e.target.value })}
                  className={inputCls}
                />
              </div>
              {formData.role === 'doctor' && (
                <>
                  <div>
                    <label className={labelCls}>Department</label>
                    <select
                      value={formData.department_id}
                      onChange={e => setFormData({ ...formData, department_id: e.target.value })}
                      className={inputCls}
                    >
                      <option value="">Select Department</option>
                      {departments.map(dept => (
                        <option key={dept.department_id} value={dept.department_id}>{dept.department_name}</option>
                      ))}
                    </select>
                  </div>
                  <div>
                    <label className={labelCls}>Room Number</label>
                    <input
                      type="text"
                      value={formData.room_number}
                      onChange={e => setFormData({ ...formData, room_number: e.target.value })}
                      className={inputCls}
                    />
                  </div>
                </>
              )}
              <div className="md:col-span-2 flex gap-3">
                <button type="submit" className="bg-blue-600 text-white px-4 py-2.5 rounded-lg hover:bg-blue-700 transition-colors text-sm font-semibold shadow-sm">
                  {editingId ? 'Update' : 'Add'} Staff Member
                </button>
                <button type="button" onClick={() => { setShowForm(false); setEditingId(null); }} className="bg-slate-100 text-slate-700 px-4 py-2.5 rounded-lg hover:bg-slate-200 transition-colors text-sm font-semibold">
                  Cancel
                </button>
              </div>
            </form>
          </div>
        )}

        {loading ? (
          <div className="text-center py-12 text-slate-400">Loading staff...</div>
        ) : (
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div className="bg-white rounded-xl shadow-sm border border-slate-100 p-6">
              <h2 className="text-lg font-semibold mb-4 flex items-center gap-2 text-slate-900">
                <span className="w-2.5 h-2.5 bg-blue-500 rounded-full"></span>
                Doctors ({doctors.length})
              </h2>
              <div className="space-y-3">
                {doctors.map(member => (
                  <div key={member.id} className="border border-slate-100 rounded-xl p-4 hover:shadow-md hover:border-blue-200 transition-all">
                    <div className="flex justify-between items-start gap-3">
                      <div className="min-w-0">
                        <h3 className="font-semibold text-slate-900">{member.first_name} {member.last_name}</h3>
                        <p className="text-sm text-slate-500 truncate">{member.email}</p>
                        <div className="flex flex-wrap gap-2 mt-2">
                          <span className="text-xs bg-blue-50 text-blue-700 px-2 py-1 rounded-full font-medium">Doctor</span>
                          {member.department_name && <span className="text-xs bg-slate-100 text-slate-600 px-2 py-1 rounded-full">{member.department_name}</span>}
                          {member.room_number && <span className="text-xs bg-slate-100 text-slate-600 px-2 py-1 rounded-full">Room {member.room_number}</span>}
                        </div>
                      </div>
                      <div className="flex gap-1 flex-shrink-0">
                        <button onClick={() => handleEdit(member)} className="p-2 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-colors" title="Edit"><Pencil size={15} /></button>
                        <button onClick={() => handleDelete(member.id)} className="p-2 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors" title="Remove"><Trash2 size={15} /></button>
                      </div>
                    </div>
                  </div>
                ))}
                {doctors.length === 0 && <p className="text-slate-400 text-sm">No doctors added yet.</p>}
              </div>
            </div>

            <div className="bg-white rounded-xl shadow-sm border border-slate-100 p-6">
              <h2 className="text-lg font-semibold mb-4 flex items-center gap-2 text-slate-900">
                <span className="w-2.5 h-2.5 bg-emerald-500 rounded-full"></span>
                Receptionists ({receptionists.length})
              </h2>
              <div className="space-y-3">
                {receptionists.map(member => (
                  <div key={member.id} className="border border-slate-100 rounded-xl p-4 hover:shadow-md hover:border-emerald-200 transition-all">
                    <div className="flex justify-between items-start gap-3">
                      <div className="min-w-0">
                        <h3 className="font-semibold text-slate-900">{member.first_name} {member.last_name}</h3>
                        <p className="text-sm text-slate-500 truncate">{member.email}</p>
                        <span className="text-xs bg-emerald-50 text-emerald-700 px-2 py-1 rounded-full inline-block mt-2 font-medium">Receptionist</span>
                      </div>
                      <div className="flex gap-1 flex-shrink-0">
                        <button onClick={() => handleEdit(member)} className="p-2 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-colors" title="Edit"><Pencil size={15} /></button>
                        <button onClick={() => handleDelete(member.id)} className="p-2 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors" title="Remove"><Trash2 size={15} /></button>
                      </div>
                    </div>
                  </div>
                ))}
                {receptionists.length === 0 && <p className="text-slate-400 text-sm">No receptionists added yet.</p>}
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
