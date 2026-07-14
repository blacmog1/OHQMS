import { Page } from "../App";
import {
  Activity, Calendar, Clock, Shield, Users,
  ChevronRight
} from "lucide-react";

interface Props { navigate: (p: Page) => void; }

const features = [
  { icon: Calendar, title: "Easy Scheduling", desc: "Book appointments online in seconds, 24/7 from any device." },
  { icon: Clock, title: "Real-Time Queue", desc: "Track your live queue position and estimated wait time." },
  { icon: Shield, title: "Secure & Private", desc: "Your health data is encrypted and protected at every step." },
  { icon: Users, title: "Multi-Role Access", desc: "Patients, doctors, receptionists & admins each get a tailored portal." },
];

export function LandingPage({ navigate }: Props) {
  return (
    <div className="flex flex-col min-h-screen">
      {/* Header */}
      <header className="bg-white border-b border-slate-100 sticky top-0 z-40">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="bg-blue-600 text-white rounded-lg p-1.5">
              <Activity size={20} />
            </div>
            <span className="font-bold text-slate-900 text-lg">OHAQRS</span>
          </div>
          <div className="flex items-center gap-3">
            <button
              onClick={() => navigate("login")}
              className="text-sm font-medium text-slate-600 hover:text-blue-600 px-4 py-2 rounded-lg hover:bg-blue-50 transition-colors"
            >
              Sign In
            </button>
            <button
              onClick={() => navigate("register")}
              className="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-5 py-2 rounded-lg transition-colors shadow-sm"
            >
              Get Started
            </button>
          </div>
        </div>
      </header>

      {/* Hero */}
      <section className="relative bg-gradient-to-br from-blue-600 to-indigo-700 text-white py-24 px-4 overflow-hidden">
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.18),transparent_60%)]" />
        <div className="absolute -bottom-24 -left-24 w-96 h-96 rounded-full bg-white/10 blur-3xl" />
        <div className="max-w-4xl mx-auto text-center">
          <h1 className="text-4xl sm:text-5xl font-extrabold leading-tight mb-5">
            Online Hospital Appointment<br />& Queue Reservation System
          </h1>
          <p className="text-blue-100 text-lg mb-8 max-w-2xl mx-auto">
            Skip the waiting room chaos. Book appointments, track your queue in real time,
            and manage your health records — all in one place.
          </p>
          <div className="flex flex-col sm:flex-row gap-3 justify-center">
            <button
              onClick={() => navigate("register")}
              className="bg-white text-blue-700 hover:bg-blue-50 font-bold px-7 py-3 rounded-xl transition-colors shadow-lg flex items-center justify-center gap-2"
            >
              Book an Appointment <ChevronRight size={18} />
            </button>
            <button
              onClick={() => navigate("login")}
              className="border border-white/30 hover:bg-white/10 font-semibold px-7 py-3 rounded-xl transition-colors flex items-center justify-center gap-2"
            >
              Sign In
            </button>
          </div>
        </div>
      </section>

      {/* Features */}
      <section className="py-16 px-4 bg-slate-50">
        <div className="max-w-5xl mx-auto">
          <h2 className="text-2xl font-bold text-center text-slate-900 mb-2">Everything you need</h2>
          <p className="text-slate-500 text-center mb-10">One platform for patients, staff, and administrators.</p>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            {features.map(({ icon: Icon, title, desc }) => (
              <div key={title} className="bg-white rounded-xl p-5 shadow-sm border border-slate-100 hover:shadow-md transition-shadow">
                <div className="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center mb-3">
                  <Icon size={20} className="text-blue-600" />
                </div>
                <h3 className="font-semibold text-slate-900 mb-1">{title}</h3>
                <p className="text-sm text-slate-500">{desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* CTA */}
      <section className="bg-blue-600 py-14 px-4 text-center text-white">
        <h2 className="text-2xl font-bold mb-3">Ready to modernize your clinic experience?</h2>
        <p className="text-blue-100 mb-6">Join thousands of patients managing their health smarter.</p>
        <button
          onClick={() => navigate("register")}
          className="bg-white text-blue-700 font-bold px-8 py-3 rounded-xl hover:bg-blue-50 transition-colors shadow-lg inline-flex items-center gap-2"
        >
          Create Free Account <ChevronRight size={18} />
        </button>
      </section>

      {/* Footer */}
      <footer className="bg-slate-900 text-slate-400 py-8 px-4 text-center text-sm">
        <div className="flex items-center justify-center gap-2 mb-2">
          <div className="bg-blue-600 text-white rounded p-1">
            <Activity size={14} />
          </div>
          <span className="text-white font-semibold">OHAQRS</span>
        </div>
        <p>© 2026 Online Hospital Appointment & Queue Reservation System. All rights reserved.</p>
      </footer>
    </div>
  );
}
