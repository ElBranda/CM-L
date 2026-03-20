import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import "./PanelGeneral.css";

const PanelGeneral = () => {
  const navigate = useNavigate();
  const [canchas, setCanchas] = useState([]);
  const [reservas, setReservas] = useState({});

  const formatFecha = (date) => {
    const año = date.getFullYear();
    const mes = String(date.getMonth() + 1).padStart(2, "0");
    const día = String(date.getDate()).padStart(2, "0");
    return `${año}-${mes}-${día}`;
  };

  const [fecha, setFecha] = useState(() => formatFecha(new Date()));

  useEffect(() => {
    fetch("http://localhost/padel-backend/get_canchas.php")
      .then(res => res.json())
      .then(data => setCanchas(data))
      .catch(err => console.error("Error al cargar canchas", err));
  }, []);

  useEffect(() => {
    canchas.forEach(cancha => {
      fetch(`http://localhost/padel-backend/get_reservas.php?cancha_id=${cancha.id}&fecha=${fecha}`)
        .then(res => res.json())
        .then(data => {
          setReservas(prev => ({ ...prev, [cancha.id]: data.horarios }));
        })
        .catch(err => console.error("Error al cargar reservas", err));
    });
  }, [fecha, canchas]);

  const generarHorarios = () => {
    const horarios = [];
    for (let h = 7; h <= 23; h++) {
      for (let m of [0, 30]) {
        const horaStr = h.toString().padStart(2, "0");
        const minutoStr = m.toString().padStart(2, "0");
        horarios.push(`${horaStr}:${minutoStr}`);
      }
    }
    horarios.push("00:00");
    return horarios;
  };

  const verHorariosFecha = () => {
    navigate(`ver-horarios?fecha=${fecha}`);
  };

  const verHorariosCancha = (canchaId) => {
    navigate(`ver-horarios/${canchaId}?fecha=${fecha}`);
  };

  return (
    <div className="panel-container">
      <div className="panel-header">
        <h2>Turnos Disponibles - {fecha}</h2>

        <div className="fecha-control-vertical">
          <label htmlFor="fecha">Elegir otra fecha:</label>
          <div className="fecha-input-boton">
            <input
              id="fecha"
              type="date"
              value={fecha}
              onChange={e => setFecha(e.target.value)}
            />
            <button className="boton-ver-horarios" onClick={verHorariosFecha}>
              Ver Horarios
            </button>
          </div>
        </div>
      </div>

      <div className="grid-canchas">
        {canchas.map(cancha => (
          <div key={cancha.id} className="card-cancha">
            <div className="encabezado-cancha">
              <h3>{cancha.nombre_cancha}</h3>
              <p>{cancha.deporte}</p>
            </div>

            <div className="grid-horarios">
              {generarHorarios().map(h => (
                <button
                  key={h}
                  className="boton-horario"
                  onClick={() => verHorariosCancha(cancha.id)}
                >
                  {h}
                </button>
              ))}
            </div>

            <div style={{ marginTop: "1em", textAlign: "right" }}>
              <button
                className="boton-ver-disponibilidad"
                onClick={() => verHorariosCancha(cancha.id)}
              >
                Ver grilla completa
              </button>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};

export default PanelGeneral;
