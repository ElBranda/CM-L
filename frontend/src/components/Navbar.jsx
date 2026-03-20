import { Link, useNavigate } from "react-router-dom";
import { getRol, logout } from "../utils/auth";

const Navbar = () => {
  const navigate = useNavigate();
  const rol = getRol();

  const handleLogout = () => {
    logout();
    navigate("/login");
  };

  return (
    <nav>
      <Link to="/">Inicio</Link>
      {rol === "admin" && <Link to="/admin">Admin</Link>}
      {rol === "empleado" && <Link to="/empleado">Empleado</Link>}
      {rol && <button onClick={handleLogout}>Cerrar sesión</button>}
    </nav>
  );
};

export default Navbar;
