import AdminHeader from "../components/AdminHeader";
import Header from "../components/Header";
import { Outlet } from "react-router-dom";

const AdminPanel = () => {
  return (
    <>
      
      <div style={{ padding: "20px" }}>
        <h2>Panel de Administración</h2>
        <p>Acá podés gestionar canchas, empleados, stock y horarios.</p>
        {/* Agregá botones, formularios o tablas según lo que quieras controlar */}
        <Outlet />
      </div>
    </>
  );
};

export default AdminPanel;
