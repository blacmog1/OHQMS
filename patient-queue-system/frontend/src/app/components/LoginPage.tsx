import { useState } from "react";
import { Page, SessionUser } from "../App";
import { Activity, Eye, EyeOff, AlertCircle, KeyRound, X } from "lucide-react";
import { toast } from "sonner";
import { api } from "../api";

interface Props {
  navigate: (p: Page) => void;
  login: (user: SessionUser) => void;
}

export function LoginPage({ navigate, login }: Props) {
  const [email, setEmail]       = useState("");
  const [password, setPassword] = useState("");
  const [showPw, setShowPw]     = useState(false);
  const [error, setError]       = useState("");
  const [loading, setLoading]   = useState(false);

  const [showForgot, setShowForgot]         = useState(false);
  const [forgotEmail, setForgotEmail]       = useState("");
  const [forgotLoading, setForgotLoading]   = useState(false);
  const [forgotResult, setForgotResult]     = useState<{ tempPassword?: string; message: string } | null>(null);

  const validate = () => {
    if (!email.trim()) return "Email is required.";
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return "Enter a valid email.";
    if (!password) return "Password is required.";
    if (password.length < 6) return "Password must be at least 6 characters.";
    return "";
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const err = validate();
    if (err) { setError(err); return; }
    setError("");
    setLoading(true);

    try {
      const res = await api.login(email, password);
      toast.success(`Welcome back, ${res.user.name || res.user.email}!`);
      login({
        id: res.user.id,
        name: res.user.name || "System User",
        email: res.user.email,
        role: res.user.role,
      });
    } catch (err: any) {
      setError(err?.message || "Invalid email or password.");
      setLoading(false);
    }
  };

  const handleForgotPassword = async () => {
    if (!forgotEmail.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(forgotEmail)) {
      toast.error("Enter a valid email address.");
      return;
    }
    setForgotLoading(true);
    setForgotResult(null);
    try {
      const res = await api.forgotPassword(forgotEmail);
      setForgotResult({
        tempPassword: res.temp_password,
        message: res.message,
      });
    } catch (err: any) {
      toast.error(err?.message || "Failed to reset password.");
    } finally {
      setForgotLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 flex items-center justify-center p-4">
      <div className="w-full max-w-md">
        {/* Logo */}
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-14 h-14 bg-blue-600 rounded-2xl shadow-lg mb-4">
            <Activity size={28} className="text-white" />
          </div>
          <h1 className="text-2xl font-bold text-slate-900">Welcome back</h1>
          <p className="text-slate-500 mt-1">Sign in to your OHAQRS account</p>
        </div>

        <div className="bg-white rounded-2xl shadow-sm border border-slate-100 p-8">
          {error && (
            <div className="flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg p-3 mb-5">
              <AlertCircle size={15} className="flex-shrink-0" />
              {error}
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-5" noValidate>
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1.5">Email Address</label>
              <input
                type="email"
                value={email}
                onChange={e => { setEmail(e.target.value); setError(""); }}
                placeholder="you@hospital.com"
                className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1.5">Password</label>
              <div className="relative">
                <input
                  type={showPw ? "text" : "password"}
                  value={password}
                  onChange={e => { setPassword(e.target.value); setError(""); }}
                  placeholder="••••••••"
                  className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                />
                <button
                  type="button"
                  onClick={() => setShowPw(!showPw)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                >
                  {showPw ? <EyeOff size={16} /> : <Eye size={16} />}
                </button>
              </div>
              <div className="text-right mt-1">
                <button
                  type="button"
                  onClick={() => { setShowForgot(true); setForgotEmail(email); setForgotResult(null); }}
                  className="text-xs text-blue-600 hover:text-blue-700"
                >
                  Forgot password?
                </button>
              </div>
            </div>
            <button
              type="submit"
              disabled={loading}
              className="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-semibold py-2.5 rounded-lg transition-colors flex items-center justify-center gap-2"
            >
              {loading ? (
                <><span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" /> Signing in...</>
              ) : "Sign In"}
            </button>
          </form>

          <p className="text-center text-sm text-slate-500 mt-5">
            Don't have an account?{" "}
            <button onClick={() => navigate("register")} className="text-blue-600 font-semibold hover:text-blue-700">
              Create account
            </button>
          </p>
        </div>

        <p className="text-center text-xs text-slate-400 mt-4">
          <button onClick={() => navigate("landing")} className="hover:text-slate-600">← Back to home</button>
        </p>
      </div>

      {/* Forgot Password Modal */}
      {showForgot && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-2xl shadow-xl p-6 max-w-sm w-full">
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-2">
                <KeyRound size={18} className="text-blue-600" />
                <h3 className="font-bold text-slate-900">Reset Password</h3>
              </div>
              <button
                onClick={() => { setShowForgot(false); setForgotResult(null); }}
                className="text-slate-400 hover:text-slate-600"
              >
                <X size={18} />
              </button>
            </div>

            {!forgotResult ? (
              <>
                <p className="text-sm text-slate-500 mb-4">
                  Enter your email address and we will generate a temporary password for you.
                </p>
                <input
                  type="email"
                  value={forgotEmail}
                  onChange={e => setForgotEmail(e.target.value)}
                  placeholder="your@email.com"
                  className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 mb-4"
                />
                <button
                  onClick={handleForgotPassword}
                  disabled={forgotLoading}
                  className="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-semibold py-2.5 rounded-xl transition-colors flex items-center justify-center gap-2 text-sm"
                >
                  {forgotLoading
                    ? <><span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" /> Sending...</>
                    : "Generate Temporary Password"
                  }
                </button>
              </>
            ) : (
              <div className="text-center">
                <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-4 mb-4">
                  <p className="text-sm text-emerald-800 font-medium mb-2">Password Reset Successful</p>
                  <p className="text-xs text-emerald-700 mb-3">{forgotResult.message}</p>
                  {forgotResult.tempPassword && (
                    <div className="bg-white border border-emerald-300 rounded-lg p-3">
                      <p className="text-xs text-slate-500 mb-1">Your temporary password:</p>
                      <p className="font-mono font-bold text-lg text-slate-900 tracking-wider">{forgotResult.tempPassword}</p>
                      <p className="text-xs text-red-500 mt-1">Change this after logging in!</p>
                    </div>
                  )}
                </div>
                <button
                  onClick={() => {
                    setShowForgot(false);
                    setForgotResult(null);
                    setEmail(forgotEmail);
                    setPassword(forgotResult.tempPassword ?? "");
                  }}
                  className="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors"
                >
                  Log In With Temporary Password
                </button>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
