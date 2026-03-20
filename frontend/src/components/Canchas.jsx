import { useNavigate } from "react-router-dom";
import "./Canchas.css";
import { useState } from "react";
import { useEffect } from "react";

const Canchas = () => {
    const navigate = useNavigate();
    const [canchas, setCanchas] = useState([]);

    const CanchaNueva = () => {
        navigate("nueva");
    }

    useEffect(() => {
        fetch("http://localhost/padel-backend/get_canchas.php")
            .then(res => res.json())
            .then(data => setCanchas(data))
    }, [])

    return (
        <>
            <div className="canchas-container">
                <div className="canchas-header">
                    <h2>Listado de Canchas</h2>
                    <button className="canchas-crear-button" onClick={CanchaNueva}>Nueva Cancha</button>
                </div>

                <table className="canchas-table">
                    <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Deporte</th>
                        <th>Ubicación</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    {canchas.map(cancha => (
                        <tr key={cancha.id}>
                        <td>{cancha.nombre_cancha}</td>
                        <td>{cancha.deporte}</td>
                        <td>{cancha.ubicacion || "-"}</td>
                        <td>
                            <span className={cancha.activa == 1 ? "estado-activa" : "estado-inactiva"}>
                            {cancha.activa == 1 ? "Activa" : "No activa"}
                            </span>
                        </td>
                        <td>
                            <button className="boton-horario">Ver/Horarios</button>
                            <button className="boton-editar">Editar</button>
                            <button className="boton-eliminar">Eliminar</button>
                        </td>
                        </tr>
                    ))}
                    </tbody>
                </table>
            </div>
        </>
    );
}

export default Canchas;