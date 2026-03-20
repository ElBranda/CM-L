import { Navigate } from "react-router-dom";

const PrivateRoute = ({ children, allowedRoles }) => {
    const token = localStorage.getItem("token");
    const rol = localStorage.getItem("rol");

    if (!token) return <Navigate to="/login" />;
    if (!allowedRoles.includes(rol)) return <Navigate to="/no-autorizado" />;

    return children;
};

export default PrivateRoute;