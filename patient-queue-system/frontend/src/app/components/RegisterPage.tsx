import { useState } from "react";
import { Page, SessionUser } from "../App";
import { Activity, Eye, EyeOff } from "lucide-react";
import { toast } from "sonner";
import { api } from "../api";

interface Props {
  navigate: (p: Page) => void;
  login: (user: SessionUser) => void;
}

// ─── Field must live OUTSIDE the parent component ────────────────────────────
// Defining it inside causes React to treat it as a new component type on every
// render, which unmounts + remounts the <input> and resets cursor/selection.
// This was the root cause of the one-character-at-a-time typing bug.
interface FieldProps {
  id?: string;
  label: string;
  type?: string;
  placeholder?: string;
  value: string;
  onChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
  error?: string;
}

function Field({ label, type = "text", placeholder, value, onChange, error }: FieldProps) {
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1.5">{label}</label>
      <input
        type={type}
        value={value}
        onChange={onChange}
        placeholder={placeholder}
        className={`w-full border rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition ${
          error ? "border-red-400 bg-red-50" : "border-gray-300"
        }`}
      />
      {error && <p className="text-xs text-red-600 mt-1">{error}</p>}
    </div>
  );
}
// ─────────────────────────────────────────────────────────────────────────────

const MONTHS = [
  { value: "01", label: "Jan" },
  { value: "02", label: "Feb" },
  { value: "03", label: "Mar" },
  { value: "04", label: "Apr" },
  { value: "05", label: "May" },
  { value: "06", label: "Jun" },
  { value: "07", label: "Jul" },
  { value: "08", label: "Aug" },
  { value: "09", label: "Sep" },
  { value: "10", label: "Oct" },
  { value: "11", label: "Nov" },
  { value: "12", label: "Dec" },
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

export function RegisterPage({ navigate, login }: Props) {
  const [form, setForm] = useState({
    firstName: "", lastName: "", email: "", phone: "",
    dob: "", gender: "", password: "", confirmPassword: "",
  });
  const [showPw, setShowPw]   = useState(false);
  const [errors, setErrors]   = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(false);

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
    setErrors(err => ({ ...err, dob: "" }));
  };

  const set = (k: keyof typeof form) =>
    (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
      setForm(f => ({ ...f, [k]: e.target.value }));
      setErrors(err => ({ ...err, [k]: "" }));
    };

  const validate = () => {
    const errs: Record<string, string> = {};
    if (!form.firstName.trim()) errs.firstName = "First name is required.";
    if (!form.lastName.trim())  errs.lastName  = "Last name is required.";
    if (!form.email.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email))
      errs.email = "Enter a valid email.";
    if (!form.phone.trim() || !/^\+?[0-9\s\-]{7,20}$/.test(form.phone.replace(/\s/g, "")))
      errs.phone = "Enter a valid phone number.";
    
    const dobVals = form.dob ? form.dob.split("-") : [];
    if (!form.dob || dobVals.length < 3 || dobVals.some(p => !p)) {
      errs.dob = "Date of birth is required.";
    }
    
    if (!form.gender) errs.gender = "Please select a gender.";
    if (form.password.length < 8)
      errs.password = "Password must be at least 8 characters.";
    if (form.password !== form.confirmPassword)
      errs.confirmPassword = "Passwords do not match.";
    return errs;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const errs = validate();
    if (Object.keys(errs).length) { setErrors(errs); return; }
    setLoading(true);

    try {
      const res = await api.register({
        firstName:       form.firstName,
        lastName:        form.lastName,
        email:           form.email,
        phone:           form.phone,
        password:        form.password,
        confirmPassword: form.confirmPassword,
        dateOfBirth:     form.dob,
      });
      toast.success("Account created! Welcome to OHAQRS");
      
      // Use user data from registration response for auto-login
      if (res.user) {
        login({
          id:    res.user.id,
          name:  res.user.name || `${form.firstName} ${form.lastName}`,
          email: res.user.email,
          role:  res.user.role,
        });
      }
    } catch (err: any) {
      if (err.errors) {
        setErrors(err.errors);
      } else {
        toast.error(err.message || "Registration failed. Please try again.");
      }
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-50 flex items-center justify-center p-4">
      <div className="w-full max-w-lg">
        {/* Header */}
        <div className="text-center mb-6">
          <div className="inline-flex items-center justify-center w-12 h-12 bg-blue-600 rounded-xl shadow-lg mb-3">
            <Activity size={24} className="text-white" />
          </div>
          <h1 className="text-2xl font-bold text-gray-900">Create your account</h1>
          <p className="text-gray-500 text-sm mt-1">Join OHAQRS — it's free for patients</p>
        </div>

        <div className="bg-white rounded-2xl shadow-lg border border-gray-100 p-8">
          <form onSubmit={handleSubmit} className="space-y-4" noValidate>

            {/* Name row */}
            <div className="grid grid-cols-2 gap-4">
              <Field label="First Name" placeholder="First name"
                value={form.firstName} onChange={set("firstName")} error={errors.firstName} />
              <Field label="Last Name"  placeholder="Last name"
                value={form.lastName}  onChange={set("lastName")}  error={errors.lastName} />
            </div>

            <Field label="Email Address" type="email" placeholder="you@example.com"
              value={form.email} onChange={set("email")} error={errors.email} />

            <Field label="Phone Number" type="tel" placeholder="09XXXXXXXXX"
              value={form.phone} onChange={set("phone")} error={errors.phone} />

            {/* DOB + Gender row */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1.5">Date of Birth</label>
                <div className="flex gap-1.5">
                  <select
                    value={dobMonth}
                    onChange={e => handleDobChange("month", e.target.value)}
                    className={`w-[35%] border rounded-lg px-1.5 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 transition ${
                      errors.dob ? "border-red-400 bg-red-50" : "border-gray-300"
                    }`}
                  >
                    <option value="">Month</option>
                    {MONTHS.map(m => (
                      <option key={m.value} value={m.value}>{m.label}</option>
                    ))}
                  </select>

                  <select
                    value={dobDay}
                    onChange={e => handleDobChange("day", e.target.value)}
                    className={`w-[28%] border rounded-lg px-1.5 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 transition ${
                      errors.dob ? "border-red-400 bg-red-50" : "border-gray-300"
                    }`}
                  >
                    <option value="">Day</option>
                    {DAYS.map(d => (
                      <option key={d.value} value={d.value}>{d.label}</option>
                    ))}
                  </select>

                  <select
                    value={dobYear}
                    onChange={e => handleDobChange("year", e.target.value)}
                    className={`w-[37%] border rounded-lg px-1.5 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 transition ${
                      errors.dob ? "border-red-400 bg-red-50" : "border-gray-300"
                    }`}
                  >
                    <option value="">Year</option>
                    {YEARS.map(y => (
                      <option key={y.value} value={y.value}>{y.label}</option>
                    ))}
                  </select>
                </div>
                {errors.dob && <p className="text-xs text-red-600 mt-1">{errors.dob}</p>}
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1.5">Gender</label>
                <select
                  value={form.gender}
                  onChange={set("gender")}
                  className={`w-full border rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition ${
                    errors.gender ? "border-red-400" : "border-gray-300"
                  }`}
                >
                  <option value="">Select...</option>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                  <option value="other">Other</option>
                  <option value="prefer-not">Prefer not to say</option>
                </select>
                {errors.gender && <p className="text-xs text-red-600 mt-1">{errors.gender}</p>}
              </div>
            </div>

            {/* Password */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
              <div className="relative">
                <input
                  type={showPw ? "text" : "password"}
                  value={form.password}
                  onChange={set("password")}
                  placeholder="Min. 8 characters"
                  className={`w-full border rounded-lg px-3.5 py-2.5 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition ${
                    errors.password ? "border-red-400" : "border-gray-300"
                  }`}
                />
                <button
                  type="button"
                  onClick={() => setShowPw(!showPw)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400"
                >
                  {showPw ? <EyeOff size={15} /> : <Eye size={15} />}
                </button>
              </div>
              {errors.password && <p className="text-xs text-red-600 mt-1">{errors.password}</p>}
            </div>

            {/* Confirm Password */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1.5">Confirm Password</label>
              <input
                type="password"
                value={form.confirmPassword}
                onChange={set("confirmPassword")}
                placeholder="Re-enter password"
                className={`w-full border rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition ${
                  errors.confirmPassword ? "border-red-400" : "border-gray-300"
                }`}
              />
              {errors.confirmPassword && (
                <p className="text-xs text-red-600 mt-1">{errors.confirmPassword}</p>
              )}
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-semibold py-2.5 rounded-lg transition-colors flex items-center justify-center gap-2 mt-2"
            >
              {loading ? (
                <><span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" /> Creating account...</>
              ) : "Create Account"}
            </button>
          </form>

          <p className="text-center text-sm text-gray-500 mt-5">
            Already have an account?{" "}
            <button onClick={() => navigate("login")} className="text-blue-600 font-semibold hover:underline">
              Sign in
            </button>
          </p>
        </div>

        <p className="text-center text-xs text-gray-400 mt-4">
          <button onClick={() => navigate("landing")} className="hover:text-gray-600">← Back to home</button>
        </p>
      </div>
    </div>
  );
}
