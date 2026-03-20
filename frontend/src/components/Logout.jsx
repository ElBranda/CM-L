import { logout } from "../utils/auth";
import { useNavigate } from "react-router-dom";

const Logout = () => {
    const navigate = useNavigate();

    const handleLogout = () => {
        logout();
        navigate("/login");
    };

    return <button onClick={handleLogout}>Cerrar sessión</button>;
}

export default Logout;