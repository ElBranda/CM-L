// src/components/AdminHeader.jsx
import React from 'react'
import { logout } from "../utils/auth";
import { useNavigate } from "react-router-dom";
import { NavLink } from 'react-router-dom';
//import "./Header.css"

export default function AdminHeader() {
    const navigate = useNavigate();
    const nombreUsuario = localStorage.getItem("nombre");

    const handleLogout = () => {
        logout();
        navigate("/login");
    };

  return (
    <header className="admin-header">
      <div className="admin-header-top">
        <div className="admin-usuario">
          <span>Hola, {nombreUsuario}</span>
          <button className="admin-cerrar" onClick={handleLogout}>Cerrar Sesión</button>
        </div>
      </div>
        
      <nav className="admin-nav">
        <ul>
          <li><NavLink to="/admin/canchas">Canchas</NavLink></li>
          <li><NavLink to="/admin/ventas">Ventas</NavLink></li>
          <li><NavLink to="/admin/cierre-caja">Cierre Caja</NavLink></li>
          <li><NavLink to="/admin/movimientos">Movimientos</NavLink></li>
          <li><NavLink to="/admin/resumenes">Resumenes</NavLink></li>
          <li><NavLink to="/admin/precios">Gestionar Precios</NavLink></li>
          <li><NavLink to="/admin/stock">Stock</NavLink></li>
          <li><NavLink to="/admin/cuentas">Cuentas</NavLink></li>
          <li><NavLink to="/admin/picaditos">Picaditos</NavLink></li>
        </ul>
      </nav>
    </header>
    
  )
}
