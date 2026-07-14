import { X, Activity, LogOut } from "lucide-react";
import { SessionUser, Page, NavItem } from "../App";

interface SidepanelProps {
  open: boolean;
  onClose: () => void;
  items: NavItem[];
  currentPage: Page;
  onNavigate: (page: Page) => void;
  session: SessionUser;
}

export default function Sidepanel({ open, onClose, items, currentPage, onNavigate, session }: SidepanelProps) {
  const initials = (session.name || session.email || "?")
    .split(" ")
    .map(n => n[0])
    .join("")
    .substring(0, 2)
    .toUpperCase();

  return (
    <>
      {/* Overlay backdrop */}
      <div
        onClick={onClose}
        className={`fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm transition-opacity duration-300 ${
          open ? "opacity-100" : "opacity-0 pointer-events-none"
        }`}
        aria-hidden="true"
      />

      {/* Drawer */}
      <aside
        className={`fixed top-0 left-0 z-50 h-full w-72 max-w-[80vw] bg-slate-900 text-slate-100 flex flex-col shadow-2xl transform transition-transform duration-300 ease-out ${
          open ? "translate-x-0" : "-translate-x-full"
        }`}
      >
        {/* Brand + close */}
        <div className="flex items-center justify-between px-5 h-16 border-b border-slate-800">
          <div className="flex items-center gap-2.5">
            <div className="bg-blue-600 text-white rounded-lg p-1.5">
              <Activity size={20} />
            </div>
            <div className="leading-tight">
              <p className="font-bold text-white text-base tracking-tight">OHAQRS</p>
              <p className="text-[10px] text-slate-400 -mt-0.5">Hospital Queue System</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg p-1.5 transition-colors"
            aria-label="Close menu"
          >
            <X size={20} />
          </button>
        </div>

        {/* Navigation */}
        <nav className="flex-1 overflow-y-auto py-4 px-3 space-y-1">
          {items.map(item => {
            const Icon = item.icon;
            const active = currentPage === item.page;
            return (
              <button
                key={item.label}
                onClick={() => onNavigate(item.page)}
                className={`w-full text-left flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors ${
                  active
                    ? "bg-blue-600 text-white shadow-sm"
                    : "text-slate-300 hover:bg-slate-800 hover:text-white"
                }`}
              >
                <Icon size={18} className={active ? "text-white" : "text-slate-400"} />
                {item.label}
              </button>
            );
          })}
        </nav>

        {/* User footer */}
        <div className="p-4 border-t border-slate-800">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-semibold flex-shrink-0">
              {initials}
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-white truncate">{session.name}</p>
              <p className="text-xs text-slate-400 capitalize truncate">{session.role}</p>
            </div>
            <button
              onClick={onClose}
              className="text-slate-400 hover:text-red-400 hover:bg-slate-800 rounded-lg p-1.5 transition-colors"
              aria-label="Close"
            >
              <LogOut size={16} />
            </button>
          </div>
        </div>
      </aside>
    </>
  );
}
