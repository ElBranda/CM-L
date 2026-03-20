import { useParams, useLocation, useNavigate } from "react-router-dom";
import { useState, useEffect } from "react";
import "./ReservarCancha.css";

const ReservarCancha = () => {
  const navigate = useNavigate();
  const { id } = useParams();
  const { state } = useLocation();
  const nombreCancha = state?.nombreCancha || "Cancha desconocida";

  const [nombre, setNombre] = useState("");
  const [horariosOcupados, setHorariosOcupados] = useState(new Set()); // Cambio a Set
  const [horariosSeleccionados, setHorariosSeleccionados] = useState([]);

  const formatFecha = (date) => {
    const año = date.getFullYear();
    const mes = String(date.getMonth() + 1).padStart(2, "0");
    const día = String(date.getDate()).padStart(2, "0");
    return `${año}-${mes}-${día}`;
  };
  const [fecha, setFecha] = useState(() => formatFecha(new Date()));

  // Helpers para conversión de tiempo
  const timeToMinutes = (timeStr) => {
    if (!timeStr) return 0;
    const [hours, minutes] = timeStr.split(":").map(Number);
    return (hours || 0) * 60 + (minutes || 0);
  };

  const minutesToTime = (minutes) => {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${hours.toString().padStart(2, "0")}:${mins.toString().padStart(2, "0")}`;
  };

  useEffect(() => {
    fetch(`http://localhost/padel-backend/get_reservas.php?cancha_id=${id}&fecha=${fecha}`)
      .then(res => res.json())
      .then(data => {
        const ocupadosSet = new Set();
        
        (data.reservas || []).forEach(reserva => {
          const horaInicio = reserva.hora_inicio || reserva.horaIni;
          const horaFin = reserva.hora_fin || reserva.horaFin;
          
          if (horaInicio && horaFin) {
            const inicioMinutos = timeToMinutes(horaInicio);
            const finMinutos = timeToMinutes(horaFin);
            
            // CORRECCIÓN: Marcar TODOS los horarios desde inicio hasta fin
            // Incluyendo el horario de fin si corresponde a un slot de 30min
            for (let t = inicioMinutos; t <= finMinutos; t += 30) {
              ocupadosSet.add(minutesToTime(t));
            }
          }
        });

        setHorariosOcupados(ocupadosSet);
      })
      .catch(err => console.error("Error al cargar horarios", err));
  }, [fecha, id]);

  const generarHorarios = () => {
    const horarios = [];
    const ahora = new Date();
    const esHoy = fecha === formatFecha(ahora);
    const horaActual = ahora.getHours();
    const minutoActual = ahora.getMinutes();

    for (let h = 7; h <= 23; h++) {
      const horaStr = h.toString().padStart(2, "0");

      if (!esHoy || h > horaActual) {
        horarios.push(`${horaStr}:00`);
      }
      if (!esHoy || h > horaActual || (h === horaActual && minutoActual < 30)) {
        horarios.push(`${horaStr}:30`);
      }
    }

    horarios.push("00:00");
    return horarios;
  };

  const toggleHorario = (h) => {
    setHorariosSeleccionados(prev =>
      prev.includes(h) ? prev.filter(x => x !== h) : [...prev, h]
    );
  };

  const sonConsecutivos = (horarios) => {
    if (horarios.length < 2) return true;
    
    const ordenados = [...horarios].sort();
    
    for (let i = 1; i < ordenados.length; i++) {
      const minutosPrev = timeToMinutes(ordenados[i - 1]);
      const minutosCurr = timeToMinutes(ordenados[i]);
      
      if (minutosCurr - minutosPrev !== 30) {
        return false;
      }
    }
    return true;
  };

  const sugerirHorarioFaltante = (horarios) => {
    if (horarios.length < 2) return [];
    
    const ordenados = [...horarios].sort();
    const sugerencias = [];
    
    for (let i = 1; i < ordenados.length; i++) {
      const minutosPrev = timeToMinutes(ordenados[i - 1]);
      const minutosCurr = timeToMinutes(ordenados[i]);
      
      if (minutosCurr - minutosPrev > 30) {
        for (let minuto = minutosPrev + 30; minuto < minutosCurr; minuto += 30) {
          sugerencias.push(minutesToTime(minuto));
        }
      }
    }
    
    return sugerencias;
  };

  const handleReservar = async () => {
    if (!nombre.trim()) {
      alert("Por favor ingresa tu nombre");
      return;
    }

    if (horariosSeleccionados.length === 0) {
      alert("Por favor selecciona al menos un horario");
      return;
    }

    const horariosOrdenados = [...horariosSeleccionados].sort();

    // Verificar que no se seleccionen horarios ocupados
    const hayOcupados = horariosOrdenados.some(h => horariosOcupados.has(h));
    if (hayOcupados) {
      alert("Algunos horarios seleccionados están ocupados. Por favor actualiza la página.");
      return;
    }

    if (!sonConsecutivos(horariosOrdenados)) {
      const faltantes = sugerirHorarioFaltante(horariosOrdenados);
      if (faltantes.length > 0) {
        alert(`Los horarios deben ser consecutivos. Te faltan seleccionar: ${faltantes.join(", ")}`);
      } else {
        alert("Los horarios seleccionados deben ser consecutivos (ej: 19:00, 19:30, 20:00)");
      }
      return;
    }

    try {
      const res = await fetch("http://localhost/padel-backend/reservar_horarios.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          cancha_id: id,
          nombre,
          fecha,
          horarios: horariosOrdenados
        })
      });

      const data = await res.json();
      if (data.success) {
        alert("Reserva confirmada");
        setHorariosSeleccionados([]);
        // Actualizar horarios ocupados con los nuevos
        setHorariosOcupados(prev => {
          const nuevoSet = new Set(prev);
          horariosOrdenados.forEach(h => nuevoSet.add(h));
          return nuevoSet;
        });
        navigate("/reserva-exitosa", {
          state: {
            cancha: nombreCancha,
            fecha,
            horarios: horariosOrdenados,
            nombre
          }
        });
      } else {
        alert(data.error || "Error al reservar");
      }
    } catch (error) {
      alert("Error de conexión al realizar la reserva");
    }
  };

  // Filtrar horarios: solo mostrar los que NO están ocupados
  const horariosDisponibles = generarHorarios().filter(h => !horariosOcupados.has(h));

  return (
    <div className="reserva-container">
      <h2>Reservar {nombreCancha}</h2>

      <div className="form-group">
        <label>Nombre del que reserva:</label>
        <input
          type="text"
          value={nombre}
          onChange={e => setNombre(e.target.value)}
          placeholder="Tu nombre"
          required
        />
      </div>

      <div className="form-group">
        <label>Fecha:</label>
        <input
          type="date"
          value={fecha}
          onChange={e => setFecha(e.target.value)}
          min={formatFecha(new Date())}
        />
      </div>

      <div className="form-group">
        <label>Horarios disponibles:</label>
        <div className="grid-horarios">
          {horariosDisponibles.map(h => (
            <button
              key={h}
              className={`boton-horario ${horariosSeleccionados.includes(h) ? "seleccionado" : ""}`}
              onClick={() => toggleHorario(h)}
            >
              {h}
            </button>
          ))}
        </div>
      </div>

      {horariosSeleccionados.length > 0 && (
        <div className="resumen-seleccion">
          <h3>Horarios seleccionados:</h3>
          <p>{horariosSeleccionados.sort().join(", ")}</p>
        </div>
      )}

      <button 
        className="confirmar-button" 
        onClick={handleReservar}
      >
        Confirmar Reserva
      </button>
    </div>
  );
};

export default ReservarCancha;