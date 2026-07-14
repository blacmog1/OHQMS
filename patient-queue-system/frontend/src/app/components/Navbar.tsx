import { useState } from "react";
import { SessionUser, NavItem } from "../App";
import { Activity, Menu, LogOut, ChevronDown, UserRound } from "lucide-react";

interface Props {
  session: SessionUser;
  items: NavItem[];
  onMenuClick: () => void;
  logout: () => void;
}

export function Navbar({ session, onMenuClick, logout }: Props) {
  const [menuOpen, setMenuOpen] = useState(false);

  const roleBadge: Record<string, string> = {
    patient: "bg-blue-100 text-blue-700",
    receptionist: "bg-purple-100 text-purple-700",
    doctor: "bg-emerald-100 text-emerald-700",
    admin: "bg-rose-100 text-rose-700",
  };

  const initials = (session.name || session.email)
    .split(" ")
    .map(n => n[0])
    .join("")
    .substring(0, 2)
    .toUpperCase();

  return (
    <header className="bg-white/90 backdrop-blur border-b border-slate-200 sticky top-0 z-30">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-3">
        {/* Left: hamburger + brand */}
        <div className="flex items-center gap-2.5 min-w-0">
          <button
            onClick={onMenuClick}
            className="p-2 -ml-1 rounded-lg text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors"
            aria-label="Open menu"
          >
            <Menu size={22} />
          </button>
          <div className="flex items-center gap-2.5 min-w-0">
            <div className="bg-gradient-to-br from-blue-600 to-indigo-700 text-white rounded-lg p-1.5 flex-shrink-0">
              <Activity size={20} />
            </div>
            <span className="font-bold text-slate-900 text-lg tracking-tight">OHAQRS</span>
          </div>
        </div>

        {/* Right: user menu */}
        <div className="relative flex items-center gap-2">
          <button
            onClick={() => setMenuOpen(o => !o)}
            onBlur={() => setTimeout(() => setMenuOpen(false), 150)}
            className="flex items-center gap-2.5 pl-1 pr-2 py-1.5 rounded-xl hover:bg-slate-100 transition-colors"
          >
            <div className="w-9 h-9 rounded-full bg-gradient-to-br from-blue-600 to-indigo-700 flex items-center justify-center text-white text-sm font-bold flex-shrink-0">
              {initials}
            </div>
            <div className="hidden sm:block text-left leading-tight">
              <p className="text-sm font-semibold text-slate-800 truncate max-w-[10rem]">
                {session.name.split(" ")[0]}
              </p>
              <span className={`text-[10px] font-semibold px-1.5 py-0.5 rounded-full capitalize ${roleBadge[session.role!]}`}>
                {session.role}
              </span>
            </div>
            <ChevronDown size={15} className="text-slate-400 hidden sm:block" />
          </button>

          {/* Dropdown */}
          {menuOpen && (
            <div
              className="absolute right-0 top-14 w-56 bg-white rounded-xl shadow-xl border border-slate-200 py-1.5 z-40 animate-in fade-in"
              onMouseDown={e => e.preventDefault()}
            >
              <div className="px-4 py-2.5 border-b border-slate-100">
                <p className="text-sm font-semibold text-slate-800 truncate">{session.name}</p>
                <p className="text-xs text-slate-500 truncate">{session.email}</p>
              </div>
              {session.role === "patient" && (
                <button
                  onClick={() => { setMenuOpen(false); window.location.hash = "patient-profile"; }}
                  className="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition-colors"
                >
                  <UserRound size={16} className="text-slate-400" />
                  My Profile
                </button>
              )}
              <button
                onClick={logout}
                className="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors"
              >
                <LogOut size={16} />
                Sign Out
              </button>
            </div>
          )}
        </div>
      </div>
    </header>
  );
}
