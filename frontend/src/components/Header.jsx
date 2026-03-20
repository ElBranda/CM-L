import { logout } from "../utils/auth";
import { useNavigate } from "react-router-dom";
import "./Header.css"

const Header = () => {
    const navigate = useNavigate();

    const handleLogout = () => {
        logout();
        navigate("/login");
    };

    return (
        <header className="admin-header">
            <h3>Panel de Administración</h3>
            <button onClick={handleLogout}>Cerrar Sesión</button>
        </header>
    );
};

export default Header;