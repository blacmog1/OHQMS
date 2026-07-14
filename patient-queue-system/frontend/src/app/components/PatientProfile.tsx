import { useState, useEffect } from "react";
import { Page, SessionUser } from "../App";
import { User, Lock, FileText, Save, Eye, EyeOff, Camera } from "lucide-react";
import { toast } from "sonner";
import { api } from "../api";

interface Props { navigate: (p: Page) => void; session: SessionUser; }

interface MedicalRecord {
  record_id: number;
  visit_date: string;
  symptoms: string;
  treatment_notes: string;
  doctor_name: string;
  department: string;
  ticket_code?: string;
  ticket_status?: string;
}

const TABS = [
  { id: "personal", label: "Personal Info", icon: User },
  { id: "security", label: "Security", icon: Lock },
  { id: "medical", label: "Medical Summary", icon: FileText },
];

const MONTHS = [
  { value: "01", label: "Jan" }, { value: "02", label: "Feb" }, { value: "03", label: "Mar" },
  { value: "04", label: "Apr" }, { value: "05", label: "May" }, { value: "06", label: "Jun" },
  { value: "07", label: "Jul" }, { value: "08", label: "Aug" }, { value: "09", label: "Sep" },
  { value: "10", label: "Oct" }, { value: "11", label: "Nov" }, { value: "12", label: "Dec" },
];

const DAYS = Array.from({ length: 31 }, (_, i) => {
  const d = String(i + 1).padStart(2, "0");
  return { value: d, label: String(i + 1) };
});

const currentYear = new Date().getFullYear();
const YEARS = Array.from({ length: 120 }, (_, i) => {
  const y = String(currentYear - i);
  return { value: y, label: y };
});

export function PatientProfile({ session }: Props) {
  const [tab, setTab] = useState("personal");
  const [saving, setSaving] = useState(false);
  const [showPw, setShowPw] = useState(false);
  const [changingPw, setChangingPw] = useState(false);
  const [pwForm, setPwForm] = useState({ current: '', new: '', confirm: '' });
  const [medicalRecords, setMedicalRecords] = useState<MedicalRecord[]>([]);
  const [loadingRecords, setLoadingRecords] = useState(false);
  const [loadingProfile, setLoadingProfile] = useState(true);

  if (!session) {
    return (
      <div className="min-h-screen bg-slate-50 flex items-center justify-center">
        <span className="w-10 h-10 border-4 border-blue-600/30 border-t-blue-600 rounded-full animate-spin" />
      </div>
    );
  }

  const [form, setForm] = useState({
    firstName: session.name.split(" ")[0],
    lastName:  session.name.split(" ").slice(1).join(" ") || "",
    email:     session.email,
    phone:     "",
    dob:       "",
    gender:    "",
    address:   "",
  });

  useEffect(() => {
    loadProfile();
  }, []);

  useEffect(() => {
    if (tab === "medical") {
      loadMedicalRecords();
    }
  }, [tab]);

  const loadProfile = async () => {
    try {
      const res = await api.getProfile();
      if (res.success && res.user) {
        setForm({
          firstName: res.user.first_name || session.name.split(" ")[0],
          lastName:  res.user.last_name || session.name.split(" ").slice(1).join(" ") || "",
          email:     res.user.email || session.email,
          phone:     res.user.phone || "",
          dob:       res.user.dob || "",
          gender:    res.user.gender || "",
          address:   res.user.address || "",
        });
      }
    } catch (err) {
      console.error("Failed to load profile:", err);
    } finally {
      setLoadingProfile(false);
    }
  };

  const loadMedicalRecords = async () => {
    setLoadingRecords(true);
    try {
      const res = await api.getMedicalRecords();
      if (res.success) {
        setMedicalRecords(res.records || []);
      }
    } catch (err) {
      console.error("Failed to load medical records:", err);
    } finally {
      setLoadingRecords(false);
    }
  };

  const dobParts = form.dob ? form.dob.split("-") : ["", "", ""];
  const dobYear  = dobParts[0] || "";
  const dobMonth = dobParts[1] || "";
  const dobDay   = dobParts[2] || "";

  const handleDobChange = (type: "year" | "month" | "day", val: string) => {
    const parts = form.dob ? form.dob.split("-") : ["", "", ""];
    let y = parts[0] || "";
    let m = parts[1] || "";
    let d = parts[2] || "";

    if (type === "year") y = val;
    if (type === "month") m = val;
    if (type === "day") d = val;

    setForm(f => ({ ...f, dob: `${y}-${m}-${d}` }));
  };

  const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) =>
    setForm(f => ({ ...f, [k]: e.target.value }));

  const save = async () => {
    setSaving(true);
    try {
      await api.updateProfile({
        first_name: form.firstName,
        last_name: form.lastName,
        phone: form.phone,
        dob: form.dob,
        gender: form.gender,
        address: form.address,
      });
      toast.success("Profile updated successfully!");
    } catch (err: any) {
      toast.error(err.message || "Failed to update profile.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="max-w-3xl mx-auto px-4 py-8">
      <h1 className="text-2xl font-bold text-slate-900 mb-6">My Profile</h1>

      <div className="flex flex-col md:flex-row gap-6">
        {/* Sidebar */}
        <div className="md:w-56 flex-shrink-0">
          <div className="bg-white rounded-xl border border-slate-100 shadow-sm p-5 text-center mb-4">
            <div className="relative inline-block mb-3">
              <div className="w-20 h-20 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-3xl font-bold mx-auto">
                {session.name.charAt(0)}
              </div>
              <button className="absolute bottom-0 right-0 w-7 h-7 bg-blue-600 text-white rounded-full flex items-center justify-center hover:bg-blue-700 transition-colors">
                <Camera size={13} />
              </button>
            </div>
            <p className="font-semibold text-slate-900 text-sm">{session.name}</p>
            <p className="text-xs text-slate-500 mt-0.5">{session.email}</p>
            <span className="mt-2 inline-block bg-blue-100 text-blue-700 text-xs font-semibold px-2.5 py-0.5 rounded-full capitalize">
              {session.role}
            </span>
          </div>

          {/* Tabs */}
          <div className="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
            {TABS.map(({ id, label, icon: Icon }) => (
              <button
                key={id}
                onClick={() => setTab(id)}
                className={`w-full flex items-center gap-2.5 px-4 py-3 text-sm font-medium transition-colors text-left ${
                  tab === id ? "bg-blue-50 text-blue-700 border-r-2 border-blue-600" : "text-slate-600 hover:bg-slate-50"
                }`}
              >
                <Icon size={15} />
                {label}
              </button>
            ))}
          </div>
        </div>

        {/* Content */}
        <div className="flex-1 bg-white rounded-xl border border-slate-100 shadow-sm p-6">
          {loadingProfile ? (
            <div className="text-center py-8 text-slate-400">Loading profile...</div>
          ) : (
            <>
              {tab === "personal" && (
                <div>
                  <h2 className="font-semibold text-slate-900 mb-5">Personal Information</h2>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {[
                      { id: "firstName", label: "First Name", placeholder: "Maria" },
                      { id: "lastName",  label: "Last Name",  placeholder: "Last name" },
                    ].map(({ id, label, placeholder }) => (
                      <div key={id}>
                        <label className="block text-sm font-medium text-slate-700 mb-1.5">{label}</label>
                        <input
                          value={(form as any)[id]}
                          onChange={set(id)}
                          placeholder={placeholder}
                          className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                        />
                      </div>
                    ))}
                    <div>
                      <label className="block text-sm font-medium text-slate-700 mb-1.5">Email (read-only)</label>
                      <input
                        value={form.email}
                        readOnly
                        className="w-full border border-slate-200 bg-slate-50 rounded-lg px-3.5 py-2.5 text-sm text-slate-500 cursor-not-allowed"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-slate-700 mb-1.5">Phone Number</label>
                      <input
                        value={form.phone}
                        onChange={set("phone")}
                        placeholder="+254 700 000 000"
                        className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-slate-700 mb-1.5">Date of Birth</label>
                      <div className="flex gap-1.5">
                        <select
                          value={dobMonth}
                          onChange={e => handleDobChange("month", e.target.value)}
                          className="w-[35%] border border-slate-200 rounded-lg px-1.5 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                        >
                          <option value="">Month</option>
                          {MONTHS.map(m => (
                            <option key={m.value} value={m.value}>{m.label}</option>
                          ))}
                        </select>
                        <select
                          value={dobDay}
                          onChange={e => handleDobChange("day", e.target.value)}
                          className="w-[28%] border border-slate-200 rounded-lg px-1.5 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                        >
                          <option value="">Day</option>
                          {DAYS.map(d => (
                            <option key={d.value} value={d.value}>{d.label}</option>
                          ))}
                        </select>
                        <select
                          value={dobYear}
                          onChange={e => handleDobChange("year", e.target.value)}
                          className="w-[37%] border border-slate-200 rounded-lg px-1.5 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                        >
                          <option value="">Year</option>
                          {YEARS.map(y => (
                            <option key={y.value} value={y.value}>{y.label}</option>
                          ))}
                        </select>
                      </div>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-slate-700 mb-1.5">Gender</label>
                      <select
                        value={form.gender}
                        onChange={set("gender")}
                        className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                      >
                        <option value="">Select Gender</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                        <option value="prefer-not">Prefer not to say</option>
                      </select>
                    </div>
                    <div className="sm:col-span-2">
                      <label className="block text-sm font-medium text-slate-700 mb-1.5">Address</label>
                      <input
                        value={form.address}
                        onChange={set("address")}
                        placeholder="123 Main Street, City"
                        className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                      />
                    </div>
                  </div>
                  <button
                    onClick={save}
                    disabled={saving}
                    className="mt-6 flex items-center gap-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors"
                  >
                    {saving ? <><span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />Saving...</> : <><Save size={15} />Save Changes</>}
                  </button>
                </div>
              )}

              {tab === "security" && (
                <div>
                  <h2 className="font-semibold text-slate-900 mb-5">Change Password</h2>
                  <div className="space-y-4 max-w-sm">
                    {[
                      { label: "Current Password",  id: "currentPw", value: pwForm.current, key: "current" },
                      { label: "New Password",      id: "newPw", value: pwForm.new, key: "new" },
                      { label: "Confirm Password",  id: "confirmPw", value: pwForm.confirm, key: "confirm" },
                    ].map(({ label, id, value, key }) => (
                      <div key={id}>
                        <label className="block text-sm font-medium text-slate-700 mb-1.5">{label}</label>
                        <div className="relative">
                          <input
                            type={showPw ? "text" : "password"}
                            placeholder="••••••••"
                            value={value}
                            onChange={e => setPwForm(f => ({ ...f, [key]: e.target.value }))}
                            className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                          />
                          <button
                            type="button"
                            onClick={() => setShowPw(!showPw)}
                            className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"
                          >
                            {showPw ? <EyeOff size={14} /> : <Eye size={14} />}
                          </button>
                        </div>
                      </div>
                    ))}
                    <button
                      onClick={async () => {
                        if (!pwForm.current || !pwForm.new || !pwForm.confirm) {
                          toast.error("Please fill in all password fields.");
                          return;
                        }
                        if (pwForm.new !== pwForm.confirm) {
                          toast.error("New passwords do not match.");
                          return;
                        }
                        setChangingPw(true);
                        try {
                          await api.changePassword(pwForm.current, pwForm.new);
                          toast.success("Password changed successfully!");
                          setPwForm({ current: '', new: '', confirm: '' });
                        } catch (err: any) {
                          toast.error(err.message || "Failed to change password.");
                        } finally {
                          setChangingPw(false);
                        }
                      }}
                      disabled={changingPw}
                      className="bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm"
                    >
                      {changingPw ? "Updating..." : "Update Password"}
                    </button>
                  </div>
                </div>
              )}

              {tab === "medical" && (
                <div>
                  <h2 className="font-semibold text-slate-900 mb-5">Medical Records</h2>
                  {loadingRecords ? (
                    <div className="text-center py-8 text-slate-400">Loading medical records...</div>
                  ) : medicalRecords.length === 0 ? (
                    <div className="text-center py-8 text-slate-400">No medical records found. Visit history will appear here after consultations.</div>
                  ) : (
                    <div className="space-y-4">
                      {medicalRecords.map(record => (
                        <div key={record.record_id} className="border border-slate-200 rounded-xl p-5 hover:shadow-sm transition">
                          <div className="flex items-start justify-between mb-3">
                            <div>
                              <h3 className="font-semibold text-slate-900">Visit on {new Date(record.visit_date).toLocaleDateString()}</h3>
                              <p className="text-sm text-slate-500">{record.doctor_name} · {record.department}</p>
                              {record.ticket_code && (
                                <span className="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded mt-1 inline-block">Ticket #{record.ticket_code}</span>
                              )}
                            </div>
                            <span className={`text-xs font-semibold px-2 py-0.5 rounded-full ${
                              record.ticket_status === 'completed' ? 'bg-emerald-100 text-emerald-700' :
                              record.ticket_status === 'in_service' || record.ticket_status === 'called' ? 'bg-amber-100 text-amber-700' :
                              'bg-slate-100 text-slate-600'
                            }`}>
                              {record.ticket_status ? record.ticket_status.replace('_', ' ') : 'Visit'}
                            </span>
                          </div>
                          <div className="space-y-2">
                            <div>
                              <p className="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Symptoms</p>
                              <p className="text-sm text-slate-700 bg-slate-50 rounded-lg p-3">{record.symptoms}</p>
                            </div>
                            {record.treatment_notes && (
                              <div>
                                <p className="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Treatment Notes</p>
                                <p className="text-sm text-slate-700 bg-slate-50 rounded-lg p-3">{record.treatment_notes}</p>
                              </div>
                            )}
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  );
}
