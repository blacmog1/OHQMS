import { useState, useEffect, useCallback, Suspense } from "react";
import { Page, SessionUser } from "../App";
import {
  Users, Users2, Calendar, TrendingUp, Building,
  Settings, BarChart2, ShieldCheck, Search,
  UserPlus, Edit2, Ban, X, FileText, Loader2
} from "lucide-react";
import { toast } from "sonner";
import { api } from "../api";

const AdminCharts = ({ weeklyData, statusData }: { weeklyData: any[]; statusData: any[] }) => {
  const [ChartLib, setChartLib] = useState<{ BarChart: any; Bar: any; XAxis: any; YAxis: any; CartesianGrid: any; Tooltip: any; ResponsiveContainer: any; PieChart: any; Pie: any; Cell: any; Legend: any; } | null>(null);

  useEffect(() => {
    let cancelled = false;
    import("recharts").then(mod => {
      if (!cancelled) {
        setChartLib({
          BarChart: mod.BarChart,
          Bar: mod.Bar,
          XAxis: mod.XAxis,
          YAxis: mod.YAxis,
          CartesianGrid: mod.CartesianGrid,
          Tooltip: mod.Tooltip,
          ResponsiveContainer: mod.ResponsiveContainer,
          PieChart: mod.PieChart,
          Pie: mod.Pie,
          Cell: mod.Cell,
          Legend: mod.Legend,
        });
      }
    });
    return () => { cancelled = true; };
  }, []);

  if (!ChartLib) {
    return (
      <div className="grid lg:grid-cols-3 gap-5">
        <div className="lg:col-span-2 bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <div className="h-48 flex items-center justify-center text-slate-400 text-sm">Loading charts...</div>
        </div>
        <div className="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <div className="h-48 flex items-center justify-center text-slate-400 text-sm">Loading charts...</div>
        </div>
      </div>
    );
  }

  const { ResponsiveContainer, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, PieChart, Pie, Cell, Legend } = ChartLib;

  return (
    <div className="grid lg:grid-cols-3 gap-5">
      <div className="lg:col-span-2 bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <h3 className="font-semibold text-slate-900 mb-4">Weekly Appointments</h3>
        {weeklyData.length > 0 ? (
          <ResponsiveContainer width="100%" height={220}>
            <BarChart data={weeklyData}>
              <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" />
              <XAxis dataKey="day" tick={{ fontSize: 12 }} />
              <YAxis tick={{ fontSize: 12 }} />
              <Tooltip />
              <Bar dataKey="appointments" name="Booked"    fill="#bfdbfe" radius={[4,4,0,0]} />
              <Bar dataKey="completed"    name="Completed" fill="#3b82f6" radius={[4,4,0,0]} />
            </BarChart>
          </ResponsiveContainer>
        ) : (
          <div className="h-48 flex items-center justify-center text-slate-400 text-sm">No data available yet</div>
        )}
      </div>

      <div className="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <h3 className="font-semibold text-slate-900 mb-4">Status Distribution</h3>
        {statusData.length > 0 ? (
          <ResponsiveContainer width="100%" height={220}>
            <PieChart>
              <Pie data={statusData} cx="50%" cy="50%" innerRadius={55} outerRadius={80} dataKey="value" paddingAngle={3}>
                {statusData.map(({ color }, i) => <Cell key={i} fill={color} />)}
              </Pie>
              <Legend iconType="circle" iconSize={10} />
              <Tooltip />
            </PieChart>
          </ResponsiveContainer>
        ) : (
          <div className="h-48 flex items-center justify-center text-slate-400 text-sm">No data available yet</div>
        )}
      </div>
    </div>
  );
};

export default AdminCharts;
