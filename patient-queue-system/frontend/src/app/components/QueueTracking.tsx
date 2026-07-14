import { useState, useEffect } from "react";
import { Page, SessionUser } from "../App";
import { Clock, AlertCircle, RefreshCw, Activity, Ticket, X } from "lucide-react";
import { toast } from "sonner";
import { api } from "../api";

interface Props { navigate: (p: Page) => void; session: SessionUser; }

const STATUS_INFO: Record<string, { label: string; badge: string; dot: string }> = {
  waiting:      { label: "Waiting",      badge: "bg-amber-50 text-amber-600 border border-amber-200",     dot: "bg-amber-500" },
  "in-progress":{ label: "In Progress",  badge: "bg-emerald-50 text-emerald-600 border border-emerald-200", dot: "bg-emerald-500" },
  completed:    { label: "Completed",    badge: "bg-slate-100 text-slate-600 border border-slate-200",     dot: "bg-slate-400" },
  "no-show":    { label: "No Show",      badge: "bg-red-50 text-red-600 border border-red-200",            dot: "bg-red-500" }
};

export function QueueTracking({ navigate, session }: Props) {
  const [myTicket, setMyTicket] = useState<any | null>(null);
  const [queue, setQueue] = useState<any[]>([]);
  const [lastUpdate, setLastUpdate] = useState(new Date());
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [showCheckIn, setShowCheckIn] = useState(false);
  const [checkInCode, setCheckInCode] = useState("");
  const [checkingIn, setCheckingIn] = useState(false);

  const fetchData = async (showToast = false) => {
    try {
      if (showToast) setRefreshing(true);

      const ticketRes = await api.getQueueStatus();
      if (ticketRes.success && ticketRes.ticket) {
        setMyTicket(ticketRes.ticket);
        setError("");

        const ticketsRes = await api.getActiveTickets({});
        if (ticketsRes.success) {
          const deptTickets = ticketsRes.tickets.filter(
            (t: any) => t.department === ticketRes.ticket.department
          );
          setQueue(deptTickets);
        }
      } else {
        setError("No active ticket found for today.");
      }
      setLastUpdate(new Date());
    } catch (err: any) {
      setError(err.message || "Failed to fetch queue data.");
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchData();

    const interval = setInterval(() => {
      fetchData(false);
    }, 15000);

    return () => clearInterval(interval);
  }, []);

  const handleCancel = async () => {
    if (!myTicket) return;
    if (!window.confirm("Are you sure you want to cancel this appointment?")) return;

    try {
      const res = await api.cancelAppointment(myTicket.ticket_id);
      if (res.success) {
        toast.success("Appointment cancelled successfully.");
        fetchData();
      }
    } catch (err: any) {
      toast.error(err.message || "Failed to cancel appointment.");
    }
  };

  const handleCheckIn = async () => {
    if (!checkInCode.trim()) {
      toast.error("Please enter your ticket code.");
      return;
    }
    setCheckingIn(true);
    try {
      const res = await api.patientCheckIn(checkInCode.trim());
      if (res.success) {
        toast.success(res.message);
        setShowCheckIn(false);
        setCheckInCode("");
        fetchData();
      } else {
        toast.error(res.message);
      }
    } catch (err: any) {
      toast.error(err.message || "Check-in failed.");
    } finally {
      setCheckingIn(false);
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-slate-50">
        <div className="flex-1 flex items-center justify-center">
          <div className="text-center">
            <span className="w-10 h-10 border-4 border-blue-600/30 border-t-blue-600 rounded-full animate-spin inline-block mb-3" />
            <p className="text-slate-500">Loading queue status...</p>
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-slate-50">
        <div className="flex-1 overflow-auto">
          <div className="max-w-md mx-auto px-4 py-16 text-center">
            <div className="bg-white rounded-xl border border-slate-100 shadow-sm p-8">
              <AlertCircle size={40} className="text-red-500 mx-auto mb-4" />
              <h2 className="text-lg font-bold text-slate-900 mb-2">No Active Ticket</h2>
              <p className="text-slate-500 mb-6">{error}</p>

              {!showCheckIn ? (
                <button
                  onClick={() => setShowCheckIn(true)}
                  className="bg-blue-600 text-white font-semibold px-5 py-2.5 rounded-lg hover:bg-blue-700 transition-colors shadow-sm flex items-center gap-2 mx-auto"
                >
                  <Ticket size={16} />
                  Check In with Ticket Code
                </button>
              ) : (
                <div className="text-left">
                  <h3 className="font-semibold text-slate-900 mb-1">Self Check-In</h3>
                  <p className="text-sm text-slate-500 mb-4">
                    Enter your ticket code (e.g. GEN042) to check in.
                  </p>
                  <input
                    type="text"
                    value={checkInCode}
                    onChange={(e) => setCheckInCode(e.target.value.toUpperCase())}
                    placeholder="GEN042"
                    className="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 mb-4"
                  />
                  <div className="flex gap-3">
                    <button
                      onClick={handleCheckIn}
                      disabled={checkingIn}
                      className="flex-1 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-semibold py-2.5 rounded-lg text-sm transition-colors"
                    >
                      {checkingIn ? "Checking in..." : "Check In"}
                    </button>
                    <button
                      onClick={() => { setShowCheckIn(false); setCheckInCode(""); }}
                      className="flex-1 border border-slate-300 text-slate-700 font-semibold py-2.5 rounded-lg hover:bg-slate-50 text-sm transition-colors"
                    >
                      Cancel
                    </button>
                  </div>
                </div>
              )}

              <button
                onClick={() => navigate("book-appointment")}
                className="mt-6 bg-blue-600 text-white font-semibold px-5 py-2.5 rounded-lg hover:bg-blue-700 transition-colors shadow-sm"
              >
                Book an Appointment
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  const myPos = myTicket?.queue_position;
  const ahead = myPos !== null ? Math.max(0, myPos - 1) : 0;
  const estWait = myTicket?.estimated_wait_minutes ?? 0;
  const waitingTickets = queue.filter((q: any) => q.status === "waiting");

  let progress = 10;
  if (myTicket?.status === "completed") progress = 100;
  else if (myTicket?.status === "called" || myTicket?.status === "in_service") progress = 90;
  else if (waitingTickets.length > 0) {
    progress = Math.max(10, 100 - (ahead / waitingTickets.length) * 90);
  }

  const isActive = myTicket?.status === "called" || myTicket?.status === "in_service";
  const statusKey = isActive ? "in-progress" : (myTicket?.status || "waiting");
  const status = STATUS_INFO[statusKey] ?? STATUS_INFO.waiting;

  return (
    <div className="min-h-screen bg-slate-50">

      <div className="flex-1 overflow-auto">
        <div className="max-w-3xl mx-auto px-4 py-8">
          <div className="flex items-center justify-between mb-6">
            <div>
              <h1 className="text-2xl font-bold text-slate-900">Queue Tracker</h1>
              <p className="text-slate-500 text-sm mt-0.5">
                Real-time position updates
              </p>
            </div>
            <button
              onClick={() => fetchData(true)}
              disabled={refreshing}
              className="flex items-center gap-2 text-sm border border-slate-200 text-slate-600 hover:bg-white px-3 py-2 rounded-lg transition-colors shadow-sm disabled:opacity-60"
            >
              <RefreshCw size={14} className={refreshing ? "animate-spin" : ""} />
              Refresh
            </button>
          </div>

          {/* Main ticket card */}
          <div className="bg-gradient-to-br from-blue-600 to-indigo-700 text-white rounded-xl p-6 mb-6 shadow-sm">
            <div className="flex items-start justify-between mb-4">
              <div>
                <p className="text-white/80 text-sm font-medium mb-1">Your Queue Number</p>
                <div className="text-6xl font-black tracking-tight">
                  #{myTicket?.ticket_code}
                </div>
              </div>
              <span className="px-3 py-1.5 rounded-full text-xs font-semibold bg-white/20 border border-white/30 flex items-center gap-1.5 capitalize">
                {isActive && (
                  <span className="w-2 h-2 bg-emerald-300 rounded-full animate-pulse" />
                )}
                {status.label}
              </span>
            </div>

            {myTicket?.status === "waiting" && (
              <div className="grid grid-cols-3 gap-4 mb-4">
                {[
                  { label: "Queue Position", value: `#${myPos}` },
                  { label: "Ahead of You", value: ahead },
                  { label: "Est. Wait", value: `~${estWait} min` },
                ].map(({ label, value }) => (
                  <div
                    key={label}
                    className="bg-white/10 rounded-lg p-3 text-center border border-white/20"
                  >
                    <div className="font-bold text-lg">{value}</div>
                    <div className="text-white/70 text-xs mt-0.5">{label}</div>
                  </div>
                ))}
              </div>
            )}

            {isActive && (
              <div className="bg-white/20 border border-white/30 rounded-lg p-4 text-center mb-4 space-y-2">
                <div className="flex items-center justify-center gap-2">
                  <Activity size={20} className="animate-pulse text-emerald-300" />
                  <span className="font-semibold text-lg">It's Your Turn</span>
                </div>
                <p className="text-sm text-blue-100">
                  Please proceed to <strong className="text-white underline">{myTicket?.doctor?.room_number || "Room TBD"}</strong> to meet <strong className="text-white">{myTicket?.doctor?.name}</strong>.
                </p>
              </div>
            )}

            <div>
              <div className="flex justify-between text-xs text-white/70 mb-1.5">
                <span>Progress</span>
                <span>{Math.round(progress)}%</span>
              </div>
              <div className="h-2 bg-white/20 rounded-full overflow-hidden">
                <div
                  className="h-full bg-white rounded-full transition-all duration-700"
                  style={{ width: `${progress}%` }}
                />
              </div>
            </div>
          </div>

          {/* Actions */}
          <div className="flex gap-4 mb-6">
            <button
              onClick={() => navigate("book-appointment")}
              className="flex-1 bg-blue-600 text-white font-semibold py-2.5 rounded-lg hover:bg-blue-700 transition-colors shadow-sm flex items-center justify-center gap-2"
            >
              <Ticket size={16} />
              Book Appointment
            </button>
            <button
              onClick={handleCancel}
              className="flex-1 border border-red-200 text-red-500 font-semibold py-2.5 rounded-lg hover:bg-red-50 transition-colors flex items-center justify-center gap-2"
            >
              <X size={16} />
              Cancel Ticket
            </button>
          </div>

          {/* Queue list */}
          <div className="bg-white rounded-xl border border-slate-100 shadow-sm p-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-lg font-bold text-slate-900">Department Queue</h2>
              <span className="flex items-center gap-1.5 text-xs text-slate-500">
                <Clock size={13} />
                Updated {lastUpdate.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })}
              </span>
            </div>

            {queue.length === 0 ? (
              <p className="text-slate-500 text-sm py-6 text-center">
                No active tickets in your department right now.
              </p>
            ) : (
              <ul className="flex flex-col gap-2">
                {queue.map((t: any, i: number) => {
                  const info = STATUS_INFO[t.status] ?? STATUS_INFO.waiting;
                  const isMine = t.ticket_id === myTicket?.ticket_id;
                  return (
                    <li
                      key={t.ticket_id}
                      className={
                        "flex items-center justify-between px-4 py-3 rounded-lg border transition-colors " +
                        (isMine
                          ? "bg-blue-50 border-blue-200"
                          : "bg-white border-slate-100 hover:bg-slate-50")
                      }
                    >
                      <div className="flex items-center gap-3">
                        <span
                          className={
                            "w-8 h-8 rounded-lg flex items-center justify-center text-sm font-bold " +
                            (isMine
                              ? "bg-blue-600 text-white"
                              : "bg-slate-100 text-slate-600")
                          }
                        >
                          {i + 1}
                        </span>
                        <div>
                          <div className="font-semibold text-slate-900 text-sm">
                            #{t.ticket_code}
                            {isMine && (
                              <span className="ml-2 text-xs font-medium text-blue-600">
                                You
                              </span>
                            )}
                          </div>
                          <div className="text-xs text-slate-500">
                            {t.department}
                          </div>
                        </div>
                      </div>
                      <span
                        className={
                          "px-2.5 py-1 rounded-full text-xs font-semibold flex items-center gap-1.5 " +
                          info.badge
                        }
                      >
                        <span className={"w-1.5 h-1.5 rounded-full " + info.dot} />
                        {info.label}
                      </span>
                    </li>
                  );
                })}
              </ul>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
