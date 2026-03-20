import "./UserHeader.css";
import { useNavigate } from "react-router-dom";

const UserHeader = () => {
  const navigate = useNavigate();

  const login = () => {
    navigate("/login");
  };

  return (
    <header className="user-header">
      <nav className="user-nav">
        <button className="boton-estandar home-button" onClick={() => navigate("/")}>
          Home
        </button>
        <button className="boton-estandar login-button" onClick={login}>
          Iniciar Sesión
        </button>
      </nav>
    </header>
  );
};

export default UserHeader;
