import { useState } from "react";
import { useNavigate } from "react-router-dom";
import "./LoginForm.css";

const LoginForm = () => {
  const [user, setUser] = useState("");
  const [password, setPassword] = useState("");
  const navigate = useNavigate();

  const handleLogin = async (e) => {
    e.preventDefault(); // evita el reload del form

    const res = await fetch("http://localhost/padel-backend/login.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ user, password })
    });

    const data = await res.json();

    if (data.success) {
      localStorage.setItem("token", data.token);
      localStorage.setItem("rol", data.rol);
      localStorage.setItem("nombre", data.nombre_usuario);
      if (data.rol === "admin") navigate("/admin");
      else if (data.rol === "empleado") navigate("/empleado");
    } else {
      alert(data.error);
    }
  };

  return (
    <>
        <form className="login-container" onSubmit={handleLogin}>
        <h2>Iniciar sesión</h2>
        <input
            type="text"
            placeholder="Usuario"
            value={user}
            onChange={e => setUser(e.target.value)}
            required
            />
        <input
            type="password"
            placeholder="Contraseña"
            value={password}
            onChange={e => setPassword(e.target.value)}
            required
            />
        <button type="submit" className="iniciar-button">
            Ingresar
        </button>
        </form>
    </>
  );
};

export default LoginForm;
