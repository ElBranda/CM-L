import { useState } from 'react'
import reactLogo from './assets/react.svg'
import viteLogo from '/vite.svg'
import './App.css'
import Turnos from './Turnos'
import { BrowserRouter, Routes, Route } from "react-router-dom";
import LoginForm from './components/LoginForm'
import AdminPanel from "./pages/AdminPanel"
import NoAutorizado from "./pages/NoAutorizado"
import EmpleadoPanel from "./pages/EmpleadoPanel";
import PrivateRoute from './components/PrivateRoute'
import Home from './Home'
import AdminLayout from './layouts/AdminLayout'
import AdminCuentas from './components/AdminCuentas'
import CrearCuentas from './components/CrearCuentas'
import EmpleadoLayout from './layouts/EmpleadoLayout'
import Canchas from './components/Canchas'
import NuevaCancha from './components/NuevaCancha'
import ReservarCancha from './components/ReservarCancha';
import ReservaExitosa from './components/ReservaExitosa';
import PublicLayout from './layouts/PublicLayout';
import PanelGeneral from './components/PanelGeneral';
import ReservasCancha from './components/ReservasCancha';


function App() {
  const [count, setCount] = useState(0)

  return (
    // <div className="bg-red-500 text-white p-4">
    //   Tailwind está funcionando ❤️
    // </div>
    <Routes>
      <Route element={<PublicLayout />}>
        <Route path="/" element={<Home />} />
        <Route path="/login" element={<LoginForm />} />
        <Route path="/reservar/:id" element={<ReservarCancha />} />
        <Route path="/reserva-exitosa" element={<ReservaExitosa />} />
      </Route>
      
      <Route element={
        <PrivateRoute allowedRoles={['admin']}>
          <AdminLayout />
        </PrivateRoute>
      }>
        <Route path="/admin" element={<AdminPanel />} />
        <Route path="/admin/canchas" element={<Canchas />} />
        <Route path="/admin/canchas/nueva" element={<NuevaCancha />} />
        <Route path="/admin/turnos" element={<Turnos />} />
        <Route path="/admin/cuentas" element={<AdminCuentas />} />
        <Route path="/admin/cuentas/crear" element={<CrearCuentas />} />
      </Route>

      <Route path="/empleado" element={
        <PrivateRoute allowedRoles={["admin", "empleado"]}>
          <EmpleadoLayout />
        </PrivateRoute>
      }>
        <Route path="/empleado" element={<PanelGeneral />} />
        <Route path="/empleado/canchas" element={<Canchas />} />
        <Route path="/empleado/canchas/nueva" element={<NuevaCancha />} />
        <Route path="/empleado/turnos" element={<Turnos />} />
        <Route path="/empleado/cuentas" element={<AdminCuentas />} />
        <Route path="/empleado/ver-horarios/:canchaId" element={<ReservasCancha />} />
      </Route>
      <Route path="/no-autorizado" element={<NoAutorizado />} />
    </Routes>
  );
}

export default App
