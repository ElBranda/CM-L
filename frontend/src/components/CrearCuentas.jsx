import { useState } from "react";
import Header from "./Header";
import { useNavigate } from "react-router-dom";

const CrearCuentas = () => {
    const navigate = useNavigate();
    const [form, setForm] = useState({
        nombre_usuario: "",
        nombre: "",
        apellido: "",
        email: "",
        rol: "empleado",
        contraseña: "",
        confirmar_contraseña: "",
        activo: 1
    });

    const cancelButton = () => {
        navigate(-1);
    }

    const handleChange = e => {
        const { name, value } = e.target;
        setForm(prev => ({ ...prev, [name]: value }));
    }

    const handleSubmit = async e => {
        e.preventDefault();

        if (form.contraseña !== form.confirmar_contraseña) {
            alert("Las contraseñas no coinciden");
            return;
        }

        try {
            const res = await fetch("http://localhost/padel-backend/create_user.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(form)
            });
            
            const data = await res.json();

            if (data.success) {
                alert("Usuario creado con éxito");
                setForm({ nombre_usuario: "", nombre: "", apellido: "", email: "", rol: "empleado", contraseña: "", confirmar_contraseña: "", activo: 1 });
                navigate("/admin/cuentas");
            } else {
                alert(data.error || "Error al crear usuario");
            }
        } catch (err) {
            console.error(err);
            alert("Error de conexión");
        }
    };

    return (
        <form onSubmit={handleSubmit} className="crear-form">
            <div className="form-container">
                <div>
                    <label htmlFor="nombre_usuario">Nombre de usuario</label>
                    <input
                        name="nombre_usuario"
                        value={form.nombre_usuario}
                        onChange={handleChange}
                    /> 
                </div>
                <div>
                    <label htmlFor="nombre">Nombre</label>
                    <input
                        name="nombre"
                        value={form.nombre}
                        onChange={handleChange}
                    />
                </div>
                <div>
                    <label htmlFor="apellido">Apellido</label>
                    <input
                        name="apellido"
                        value={form.apellido}
                        onChange={handleChange}
                    />
                </div>
                <div>
                    <label htmlFor="email">Email</label>
                    <input
                        name="email"
                        value={form.email}
                        onChange={handleChange}
                    />
                </div>
                <div>
                    <label htmlFor="rol">Rol</label>
                    <select name="rol" id={form.rol} onChange={handleChange}>
                        <option value="empleado">Empleado</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div>
                    <label htmlFor="contraseña">Contraseña</label>
                    <input
                        name="contraseña"
                        type="password"
                        value={form.contraseña}
                        onChange={handleChange}
                    />
                </div>
                <div>
                    <label htmlFor="confirmar_contraseña">Confirmar contraseña</label>
                    <input
                        name="confirmar_contraseña"
                        type="password"
                        value={form.confirmar_contraseña}
                        onChange={handleChange}
                    />
                </div>
                <button type="button" onClick={cancelButton} className="cancelar-button">Cancelar</button>
                <button type="submit" className="crear-button">Crear usuario</button>
            </div>
        </form>
    );
}

export default CrearCuentas;