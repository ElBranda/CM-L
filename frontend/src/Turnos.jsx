import { useDeferredValue, useEffect, useState } from "react";
function Turnos() {
    const [turnos, setTurnos] = useState([]);

    useEffect(() => {
        fetch("http://localhost/padel-backend/obtenerTurnos.php")
            .then(res => res.json())
            .then(data => setTurnos(data));
    }, []);

    return (
        <div>
            <h2>Turnos disponibles</h2>
            <ul>
                {turnos.map(t => (
                    <li key={t.id}>
                        {t.fecha} - {t.hora} - {t.cancha} - ({t.estado})
                    </li>
                ))}
            </ul>
        </div>
    );
}

export default Turnos;