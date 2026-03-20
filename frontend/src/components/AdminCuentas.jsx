import React, {useState, useEffect} from "react";
import { useNavigate } from "react-router-dom";

const AdminCuentas = () => {
    const navigate = useNavigate();
    const [cuentas, setCuentas] = useState([]);

    const crearCuenta = () => {
        navigate("/admin/cuentas/crear");
    }

    const handleDelete = async (id) => {
        const confirmar = window.confirm("¿Estás seguro de que querés borrar este usuario?");
        if (!confirmar) return;

        try {
            const res = await fetch(`http://localhost/padel-backend/delete_user.php?id=${id}`, {
            method: "DELETE"
            });

            const data = await res.json();

            if (data.success) {
                alert("Usuario borrado");
                setCuentas(prev => prev.filter(c => c.id !== id));
            } else {
                alert("Error al borrar usuario");
            }
        } catch (err) {
            console.error(err);
            alert("Error de conexión");
        }
    };


    useEffect(() => {
        

        fetch("http://localhost/padel-backend/get_cuentas.php")
            .then(res => res.json())
            .then(data => setCuentas(data))
    }, [])

    return (
        <div className="admin-cuenta">
            <div className="admin-cuenta-nav">
                <h2>Administrar Cuentas</h2>
                <button className="admin-cuenta-crear" onClick={crearCuenta}>Crear usuario</button>
            </div>
            <table className="admin-cuenta-tabla">
                <thead>
                    <tr>
                        <th className="text-left">Usuario</th>
                        <th className="text-left">Nombre</th>
                        <th className="text-left">Email</th>
                        <th className="text-left">Rol</th>
                        <th className="text-left">Activo</th>
                        <th className="text-left">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    {cuentas.map(cuenta => (
                        <tr key={cuenta.id}>
                            <td className="text-left">{cuenta.nombre_usuario}</td>
                        <td className="text-left">{cuenta.nombre + " " + cuenta.apellido}</td>
                        <td className="text-left">{cuenta.email}</td>
                        <td className="text-left"><span className={cuenta.rol === "admin" ? "bg-cyan-500 rounded" : "bg-yellow-500 rounded"} style={{ padding: '.25em' }}>{cuenta.rol}</span></td>
                        <td className="text-left"><span className={cuenta.activo == 1 ? "bg-green-700 rounded text-white" : "bg-red-500 rounded"} style={{ padding: '.25em' }}>{cuenta.activo == 1 ? "Sí" : "No"}</span></td>
                        <td className="text-left"><button style={{ backgroundColor:"blue", color:"white", borderRadius:".25em", padding:".25em", cursor:"pointer", marginRight:".5em" }}>Editar</button>
                            {localStorage.getItem("rol") === "admin" && cuenta.id != 1 && (
                                <button
                                style={{
                                    backgroundColor: "red",
                                    color: "white",
                                    borderRadius: ".25em",
                                    padding: ".25em",
                                    cursor: "pointer"
                                }}
                                onClick={() => handleDelete(cuenta.id)}
                                >
                                Borrar
                                </button>
                            )}</td>

                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default AdminCuentas;