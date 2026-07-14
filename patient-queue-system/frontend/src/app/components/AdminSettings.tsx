import { useState, useEffect } from "react";
import { Page, SessionUser } from "../App";
import { Save, X } from "lucide-react";
import { toast } from "sonner";

interface Props { navigate: (p: Page) => void; session: SessionUser; }

export function AdminSettings({ navigate }: Props) {
  const [settings, setSettings] = useState({
    clinic_name: "OHAQRS Clinic",
    contact_email: "admin@ohaqrs.local",
    contact_phone: "",
    address: "",
    session_timeout: "3600",
    maintenance_mode: "false",
  });
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    const saved = localStorage.getItem("ohaqrs_settings");
    if (saved) {
      try {
        setSettings(JSON.parse(saved));
      } catch {}
    }
  }, []);

  const handleSave = () => {
    setSaving(true);
    localStorage.setItem("ohaqrs_settings", JSON.stringify(settings));
    setTimeout(() => {
      setSaving(false);
      toast.success("Settings saved.");
    }, 500);
  };

  return (
    <div className="min-h-screen bg-slate-50">
      <div className="max-w-2xl mx-auto px-4 py-8">
        <div className="mb-8">
          <h1 className="text-2xl font-bold text-slate-900">Settings</h1>
          <p className="text-slate-500 text-sm mt-1">Configure system preferences</p>
        </div>

        <div className="bg-white rounded-xl border border-slate-100 shadow-sm p-6 space-y-5">
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">Clinic Name</label>
            <input
              type="text"
              value={settings.clinic_name}
              onChange={e => setSettings({ ...settings, clinic_name: e.target.value })}
              className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">Contact Email</label>
            <input
              type="email"
              value={settings.contact_email}
              onChange={e => setSettings({ ...settings, contact_email: e.target.value })}
              className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">Contact Phone</label>
            <input
              type="tel"
              value={settings.contact_phone}
              onChange={e => setSettings({ ...settings, contact_phone: e.target.value })}
              className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">Clinic Address</label>
            <textarea
              value={settings.address}
              onChange={e => setSettings({ ...settings, address: e.target.value })}
              rows={3}
              className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">Session Timeout (seconds)</label>
            <input
              type="number"
              value={settings.session_timeout}
              onChange={e => setSettings({ ...settings, session_timeout: e.target.value })}
              className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div className="flex items-center gap-3">
            <input
              type="checkbox"
              id="maintenance"
              checked={settings.maintenance_mode === "true"}
              onChange={e => setSettings({ ...settings, maintenance_mode: e.target.checked ? "true" : "false" })}
              className="w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500"
            />
            <label htmlFor="maintenance" className="text-sm font-medium text-slate-700">Maintenance Mode</label>
          </div>
          <div className="flex gap-3 pt-2">
            <button
              onClick={handleSave}
              disabled={saving}
              className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors"
            >
              <Save size={16} />
              Save Settings
            </button>
            <button
              onClick={() => navigate("admin-dashboard")}
              className="flex items-center gap-2 border border-slate-200 text-slate-600 hover:bg-slate-50 px-5 py-2.5 rounded-xl transition-colors"
            >
              <X size={16} />
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
