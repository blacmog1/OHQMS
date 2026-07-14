import { useState, useEffect } from "react";
import { Page, SessionUser } from "../App";
import { Calendar, ChevronRight, ChevronLeft, CheckCircle, User, Building } from "lucide-react";
import { toast } from "sonner";
import { api } from "../api";

interface Props { navigate: (p: Page) => void; session: SessionUser; }

const SLOTS = ["08:00","08:30","09:00","09:30","10:00","10:30","11:00","11:30","13:00","13:30","14:00","14:30","15:00","15:30","16:00"];

const STEPS = ["Department", "Doctor", "Schedule", "Confirm"];

const getDeptIcon = (prefix: string) => {
  switch (prefix.toUpperCase()) {
    case 'CAR': return '❤️';
    case 'GEN': return '🩺';
    case 'ORT': return '🦴';
    case 'PED': return '👶';
    case 'NEU': return '🧠';
    case 'DER': return '✨';
    default: return '🏥';
  }
};

export function BookAppointment({ navigate }: Props) {
  const [step, setStep]   = useState(0);
  const [departments, setDepartments] = useState<any[]>([]);
  const [dept, setDept]   = useState<number | "">("");
  const [doctors, setDoctors] = useState<any[]>([]);
  const [doctor, setDoctor] = useState<any | null>(null);
  const [date, setDate]   = useState("");
  const [slot, setSlot]   = useState("");
  const [reason, setReason] = useState("");

  const [loading, setLoading] = useState(false);
  const [done, setDone]   = useState(false);
  const [ticketCode, setTicketCode] = useState("");
  const [queuePosition, setQueuePosition] = useState(1);

  const minDate = new Date().toISOString().split("T")[0];

  useEffect(() => {
    api.getDepartments()
      .then(res => {
        if (res.success) setDepartments(res.departments);
      })
      .catch(err => {
        toast.error("Failed to load departments: " + (err.message || err));
      });
  }, []);

  useEffect(() => {
    if (dept !== "") {
      setDoctor(null);
      api.getDoctors(dept)
        .then(res => {
          if (res.success) setDoctors(res.doctors);
        })
        .catch(err => {
          toast.error("Failed to load doctors: " + (err.message || err));
        });
    } else {
      setDoctors([]);
      setDoctor(null);
    }
  }, [dept]);

  const confirm = async () => {
    if (dept === "") return;
    setLoading(true);
    try {
      const slotTime = date && slot ? `${date}T${slot}:00` : null;
      const res = await api.bookAppointment({
        department_id: dept,
        entry_channel: "online",
        doctor_id: doctor ? doctor.id : null,
        scheduled_slot_at: slotTime,
      });
      if (res.success) {
        setTicketCode(res.ticket.ticket_code);
        setQueuePosition(res.ticket.queue_position);
        setDone(true);
        toast.success("Appointment booked successfully!");
      }
    } catch (err: any) {
      toast.error(err.message || "Failed to book appointment. Please try again.");
    } finally {
      setLoading(false);
    }
  };

  const selectedDeptObj = departments.find(d => d.department_id === dept);

  if (done) {
    return (
      <div className="max-w-lg mx-auto px-4 py-16 text-center">
        <div className="w-20 h-20 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-5">
          <CheckCircle size={40} className="text-emerald-500" />
        </div>
        <h2 className="text-2xl font-bold text-slate-900 mb-2">Appointment Confirmed</h2>
        <p className="text-slate-500 mb-2 font-medium text-sm">
          Your queue number is <strong className="text-blue-600">#{ticketCode}</strong> (Position #{queuePosition} in line)
        </p>

        <div className="bg-slate-50 rounded-xl p-5 text-sm text-left mb-6 border border-slate-100 shadow-sm space-y-2">
          <p><span className="font-semibold text-slate-500">Ticket Code:</span> <span className="font-bold text-slate-900">{ticketCode}</span></p>
          <p><span className="font-semibold text-slate-500">Queue Position:</span> <span className="font-bold text-slate-900">#{queuePosition}</span></p>
          <p><span className="font-semibold text-slate-500">Doctor:</span> <span className="font-bold text-slate-900">{doctor ? doctor.name : "Any Available Doctor"}</span></p>
          <p><span className="font-semibold text-slate-500">Department:</span> <span className="font-bold text-slate-900">{selectedDeptObj?.department_name}</span></p>
          {date && slot && (
            <>
              <p><span className="font-semibold text-slate-500">Date:</span> <span className="font-bold text-slate-900">{date}</span></p>
              <p><span className="font-semibold text-slate-500">Time:</span> <span className="font-bold text-slate-900">{slot}</span></p>
            </>
          )}
        </div>
        <div className="flex gap-3 justify-center">
          <button onClick={() => navigate("my-appointments")} className="bg-blue-600 text-white font-semibold px-5 py-2.5 rounded-xl hover:bg-blue-700 transition-colors shadow-sm">
            View Appointments
          </button>
          <button onClick={() => navigate("patient-dashboard")} className="border border-slate-200 text-slate-700 font-semibold px-5 py-2.5 rounded-xl hover:bg-slate-50 transition-colors">
            Dashboard
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-3xl mx-auto px-4 py-8">
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-slate-900 mb-1">Book an Appointment</h1>
        <p className="text-slate-500">Complete the steps below to schedule your visit</p>
      </div>

      {/* Stepper */}
      <div className="flex items-center mb-8">
        {STEPS.map((s, i) => (
          <div key={s} className="flex items-center flex-1">
            <div className="flex items-center gap-2">
              <div className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold transition-colors ${
                i < step ? "bg-blue-600 text-white" : i === step ? "bg-blue-600 text-white ring-4 ring-blue-100" : "bg-slate-200 text-slate-500"
              }`}>
                {i < step ? <CheckCircle size={16} /> : i + 1}
              </div>
              <span className={`text-sm font-medium hidden sm:block ${i <= step ? "text-blue-700" : "text-slate-400"}`}>{s}</span>
            </div>
            {i < STEPS.length - 1 && (
              <div className={`flex-1 h-0.5 mx-3 ${i < step ? "bg-blue-600" : "bg-slate-200"}`} />
            )}
          </div>
        ))}
      </div>

      <div className="bg-white rounded-xl border border-slate-100 shadow-sm p-6">
        {/* Step 0: Department */}
        {step === 0 && (
          <div>
            <h2 className="font-semibold text-slate-900 mb-4 flex items-center gap-2">
              <Building size={18} className="text-blue-600" />
              Select a Department
            </h2>
            <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
              {departments.map(d => (
                <button
                  key={d.department_id}
                  onClick={() => setDept(d.department_id)}
                  className={`p-4 rounded-lg border-2 text-left transition-all hover:shadow-md ${
                    dept === d.department_id ? "border-blue-500 bg-blue-50" : "border-slate-200 hover:border-blue-300"
                  }`}
                >
                  <div className="text-2xl mb-2">{getDeptIcon(d.prefix_code)}</div>
                  <div className="font-semibold text-sm text-slate-900">{d.department_name}</div>
                  <div className="text-xs text-slate-500 mt-0.5">{d.prefix_code} Department</div>
                </button>
              ))}
            </div>
          </div>
        )}

        {/* Step 1: Doctor */}
        {step === 1 && (
          <div>
            <h2 className="font-semibold text-slate-900 mb-4 flex items-center gap-2">
              <User size={18} className="text-blue-600" />
              Select a Doctor (Optional)
            </h2>
            <div className="space-y-3">
              <button
                onClick={() => setDoctor(null)}
                className={`w-full flex items-center justify-between p-4 rounded-lg border-2 text-left transition-all ${
                  doctor === null ? "border-blue-500 bg-blue-50" : "border-slate-200 hover:border-blue-300"
                }`}
              >
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center font-bold text-blue-700">
                    ?
                  </div>
                  <div>
                    <p className="font-semibold text-sm text-slate-900">Any Available Doctor</p>
                    <p className="text-xs text-slate-500">First available doctor in the department</p>
                  </div>
                </div>
              </button>

              {doctors.map(d => (
                <button
                  key={d.id}
                  onClick={() => setDoctor(d)}
                  className={`w-full flex items-center justify-between p-4 rounded-lg border-2 text-left transition-all ${
                    doctor?.id === d.id ? "border-blue-500 bg-blue-50" : "border-slate-200 hover:border-blue-300"
                  }`}
                >
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center font-bold text-blue-700">
                      {d.name.split(" ").pop()?.charAt(0)}
                    </div>
                    <div>
                      <p className="font-semibold text-sm text-slate-900">{d.name}</p>
                      <p className="text-xs text-slate-500">Room: {d.room_number || "TBD"}</p>
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    <span className={`text-xs px-2 py-0.5 rounded-full capitalize ${
                      d.status === "available" ? "bg-emerald-100 text-emerald-700" : "bg-amber-100 text-amber-700"
                    }`}>
                      {d.status}
                    </span>
                  </div>
                </button>
              ))}
            </div>
          </div>
        )}

        {/* Step 2: Schedule */}
        {step === 2 && (
          <div>
            <h2 className="font-semibold text-slate-900 mb-4 flex items-center gap-2">
              <Calendar size={18} className="text-blue-600" />
              Choose Date & Time (Optional)
            </h2>
            <div className="mb-4">
              <label className="block text-sm font-medium text-slate-700 mb-1.5">Appointment Date (Leave blank to queue today)</label>
              <input
                type="date"
                min={minDate}
                value={date}
                onChange={e => { setDate(e.target.value); setSlot(""); }}
                className="border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition w-full sm:w-64 bg-white"
              />
            </div>
            {date && (
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-2">Available Time Slots</label>
                <div className="grid grid-cols-3 sm:grid-cols-5 gap-2">
                  {SLOTS.map(s => (
                    <button
                      key={s}
                      onClick={() => setSlot(s)}
                      className={`py-2 text-sm rounded-lg border-2 font-medium transition-all ${
                        slot === s ? "bg-blue-600 text-white border-blue-600" : "border-slate-200 hover:border-blue-400 hover:bg-blue-50 text-slate-700"
                      }`}
                    >
                      {s}
                    </button>
                  ))}
                </div>
                <p className="text-xs text-slate-400 mt-2">Default clinic hours. Availability is verified in real time when booking.</p>
              </div>
            )}
            <div className="mt-4">
              <label className="block text-sm font-medium text-slate-700 mb-1.5">Reason for Visit</label>
              <textarea
                value={reason}
                onChange={e => setReason(e.target.value)}
                placeholder="Brief description of your symptoms or reason..."
                rows={3}
                className="w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition resize-none bg-white"
              />
            </div>
          </div>
        )}

        {/* Step 3: Confirm */}
        {step === 3 && (
          <div>
            <h2 className="font-semibold text-slate-900 mb-4 flex items-center gap-2">
              <CheckCircle size={18} className="text-blue-600" />
              Review and Confirm
            </h2>
            <div className="bg-slate-50 border border-slate-100 rounded-xl p-5 space-y-3 text-sm">
              {[
                ["Department", selectedDeptObj?.department_name],
                ["Doctor", doctor ? doctor.name : "Any Available Doctor"],
                ["Specialty", doctor ? doctor.specialty : "General Medicine"],
                ["Date", date || "Today (Walk-in)"],
                ["Time", slot || "Immediate Queue"],
                ["Reason", reason || "Not specified"],
              ].map(([label, val]) => (
                <div key={label} className="flex justify-between">
                  <span className="text-slate-500 font-medium">{label}</span>
                  <span className="text-slate-900 font-semibold">{val}</span>
                </div>
              ))}
            </div>
            <p className="text-xs text-slate-400 mt-3">
              By confirming, you agree that your information will be shared with the clinic for your appointment.
            </p>
          </div>
        )}

        {/* Navigation */}
        <div className="flex justify-between mt-6 pt-5 border-t border-slate-100">
          <button
            onClick={() => step === 0 ? navigate("patient-dashboard") : setStep(s => s - 1)}
            disabled={loading}
            className="flex items-center gap-2 text-slate-600 hover:text-slate-900 font-medium text-sm disabled:opacity-50 transition-colors"
          >
            <ChevronLeft size={16} />
            {step === 0 ? "Cancel" : "Back"}
          </button>
          <button
            onClick={() => step < 3 ? setStep(s => s + 1) : confirm()}
            disabled={
              loading ||
              (step === 0 && !dept) ||
              (step === 2 && date && !slot)
            }
            className="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-300 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors shadow-sm hover:shadow-md"
          >
            {loading ? (
              <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
            ) : null}
            {step === 3 ? "Confirm Booking" : "Continue"}
            <ChevronRight size={16} />
          </button>
        </div>
      </div>
    </div>
  );
}
