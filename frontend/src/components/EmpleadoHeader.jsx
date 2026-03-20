// src/components/AdminHeader.jsx
import React from 'react'
import { logout } from "../utils/auth";
import { useNavigate } from "react-router-dom";
import { NavLink } from 'react-router-dom';
//import "./Header.css"

export default function EmpleadoHeader() {
    const navigate = useNavigate();
    const nombreUsuario = localStorage.getItem("nombre");

    const handleLogout = () => {
        logout();
        navigate("/login");
    };

  return (
    <header className="empleado-header">
      <div className="empleado-header-top">
        <div className="admin-usuario">
          <span>Hola, {nombreUsuario}</span>
          <button className="admin-cerrar" onClick={handleLogout}>Cerrar Sesión</button>
        </div>
      </div>
        
        <nav className="admin-nav">
          
        <ul>
          <li><NavLink to="/empleado/canchas">Canchas</NavLink></li>
          <li><NavLink to="/empleado/ventas">Ventas</NavLink></li>
          <li><NavLink to="/empleado/cierre-caja">Cierre Caja</NavLink></li>
          <li><NavLink to="/empleado/movimientos">Movimientos</NavLink></li>
        </ul>
      </nav>
    </header>
    
  )
}
