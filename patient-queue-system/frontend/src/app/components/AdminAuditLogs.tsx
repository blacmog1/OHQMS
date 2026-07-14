import { useState, useEffect } from "react";
import { Page, SessionUser } from "../App";
import { RefreshCw, Filter, Search } from "lucide-react";
import { toast } from "sonner";
import { api } from "../api";

interface LogEntry {
  log_id: number;
  action: string;
  entity_type: string;
  entity_id?: number;
  details?: string;
  ip_address?: string;
  created_at: string;
  user_email?: string;
  user_role?: string;
}

interface Props { navigate: (p: Page) => void; session: SessionUser; }

export function AdminAuditLogs({ navigate }: Props) {
  const [logs, setLogs] = useState<LogEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [actionFilter, setActionFilter] = useState("");
  const [entityFilter, setEntityFilter] = useState("");
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);

  const loadLogs = async (pageNum = 1) => {
    try {
      const res = await api.getAuditLogs({
        action: actionFilter || undefined,
        entity_type: entityFilter || undefined,
        page: pageNum,
        limit: 20,
      });
      if (res.success) {
        setLogs(res.logs || []);
        setTotalPages(res.pagination?.total_pages || 1);
        setPage(res.pagination?.page || 1);
      }
    } catch (err: any) {
      toast.error(err.message || "Failed to load audit logs.");
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    loadLogs();
  }, []);

  const formatDetails = (details?: string) => {
    if (!details) return "—";
    try {
      const parsed = JSON.parse(details);
      return JSON.stringify(parsed, null, 2);
    } catch {
      return details;
    }
  };

  return (
    <div className="min-h-screen bg-slate-50">
      <div className="max-w-6xl mx-auto px-4 py-8">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
          <div>
            <h1 className="text-2xl font-bold text-slate-900">Audit Logs</h1>
            <p className="text-slate-500 text-sm mt-1">System activity and security logs</p>
          </div>
          <button
            onClick={() => { setRefreshing(true); loadLogs(page); }}
            disabled={refreshing}
            className="flex items-center gap-2 border border-slate-200 text-slate-600 hover:bg-white px-3 py-2 rounded-lg text-sm transition-colors bg-white"
          >
            <RefreshCw size={14} className={refreshing ? "animate-spin" : ""} />
            Refresh
          </button>
        </div>

        <div className="bg-white rounded-xl border border-slate-100 shadow-sm mb-6">
          <form onSubmit={e => { e.preventDefault(); setLoading(true); loadLogs(1); }} className="p-4 flex flex-col sm:flex-row gap-3">
            <input
              type="text"
              value={actionFilter}
              onChange={e => setActionFilter(e.target.value)}
              placeholder="Filter by action (e.g., login, appointment_created)"
              className="flex-1 border border-slate-200 rounded-lg px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
            <input
              type="text"
              value={entityFilter}
              onChange={e => setEntityFilter(e.target.value)}
              placeholder="Filter by entity type (e.g., user, queue_ticket)"
              className="flex-1 border border-slate-200 rounded-lg px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
            <button type="submit" className="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2 rounded-lg text-sm transition-colors">
              Filter
            </button>
          </form>
        </div>

        {loading ? (
          <div className="text-center py-16">
            <span className="w-10 h-10 border-4 border-blue-600/30 border-t-blue-600 rounded-full animate-spin inline-block mb-3" />
            <p className="text-slate-500">Loading logs...</p>
          </div>
        ) : logs.length === 0 ? (
          <div className="bg-white rounded-xl border border-slate-100 shadow-sm p-12 text-center">
            <p className="text-slate-400">No audit logs found.</p>
          </div>
        ) : (
          <>
            <div className="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="bg-slate-50 border-b border-slate-100">
                      <th className="text-left px-5 py-3 font-semibold text-slate-600">ID</th>
                      <th className="text-left px-5 py-3 font-semibold text-slate-600">User</th>
                      <th className="text-left px-5 py-3 font-semibold text-slate-600">Action</th>
                      <th className="text-left px-5 py-3 font-semibold text-slate-600">Entity</th>
                      <th className="text-left px-5 py-3 font-semibold text-slate-600">IP Address</th>
                      <th className="text-left px-5 py-3 font-semibold text-slate-600">Timestamp</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {logs.map(log => (
                      <tr key={log.log_id} className="hover:bg-slate-50 transition-colors">
                        <td className="px-5 py-3 font-mono text-slate-500">#{log.log_id}</td>
                        <td className="px-5 py-3 text-slate-700">{log.user_email || "System"}</td>
                        <td className="px-5 py-3">
                          <span className="font-medium text-slate-900">{log.action.replace(/_/g, " ")}</span>
                        </td>
                        <td className="px-5 py-3 text-slate-600 capitalize">{log.entity_type}{log.entity_id ? ` #${log.entity_id}` : ""}</td>
                        <td className="px-5 py-3 text-slate-500 font-mono text-xs">{log.ip_address || "—"}</td>
                        <td className="px-5 py-3 text-slate-600">{new Date(log.created_at).toLocaleString()}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>

            {totalPages > 1 && (
              <div className="flex items-center justify-between mt-4">
                <button
                  onClick={() => loadLogs(Math.max(1, page - 1))}
                  disabled={page <= 1}
                  className="px-4 py-2 border border-slate-200 rounded-lg text-sm disabled:opacity-50 hover:bg-white transition-colors"
                >
                  Previous
                </button>
                <span className="text-sm text-slate-500">Page {page} of {totalPages}</span>
                <button
                  onClick={() => loadLogs(Math.min(totalPages, page + 1))}
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
