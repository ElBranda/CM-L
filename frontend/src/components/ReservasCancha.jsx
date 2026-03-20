import { useState, useEffect } from "react";
import { useParams } from "react-router-dom";
import "./ReservasCancha.css";

const ReservasCancha = () => {
  const { canchaId } = useParams();
  const [cancha, setCancha] = useState(null);
  //const [fecha, setFecha] = useState(() => new Date().toISOString().split("T")[0]);
  const [reservas, setReservas] = useState([]);
  const [loading, setLoading] = useState(true);

  const formatFecha = (date) => {
    const año = date.getFullYear();
    const mes = String(date.getMonth() + 1).padStart(2, "0");
    const día = String(date.getDate()).padStart(2, "0");
    return `${año}-${mes}-${día}`;
  };
  const [fecha, setFecha] = useState(() => formatFecha(new Date()));

  // Estados para nueva reserva
  const [horariosSeleccionados, setHorariosSeleccionados] = useState([]);
  const [nombreReservante, setNombreReservante] = useState("");

  // Helpers
  const generarHorarios = () => {
    const horarios = [];
    for (let h = 7; h <= 23; h++) {
      horarios.push(`${h.toString().padStart(2, "0")}:00`);
      horarios.push(`${h.toString().padStart(2, "0")}:30`);
    }
    horarios.push("00:00");
    return horarios;
  };

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

  // Cargar datos
  useEffect(() => {
    const cargarDatos = async () => {
      try {
        setLoading(true);
        
        const canchaRes = await fetch(`http://localhost/padel-backend/get_canchas.php?id=${canchaId}`);
        const canchaData = await canchaRes.json();
        setCancha(canchaData);
        
        const reservasRes = await fetch(`http://localhost/padel-backend/get_reservas.php?cancha_id=${canchaId}&fecha=${fecha}`);
        const reservasData = await reservasRes.json();
        setReservas(Array.isArray(reservasData.reservas) ? reservasData.reservas : []);
        
      } catch (error) {
        console.error("Error cargando datos:", error);
      } finally {
        setLoading(false);
      }
    };

    cargarDatos();
  }, [canchaId, fecha]);

  // CORRECCIÓN: Función para crear bloques con rango visual correcto
  const crearBloques = () => {
    const horarios = generarHorarios();
    const bloques = [];
    
    // Mapa de horarios ocupados y inicios de reservas
    const ocupados = new Set();
    const iniciosReservas = new Map();
    
    // Procesar reservas para marcar horarios ocupados
    reservas.forEach(reserva => {
      const inicio = reserva.hora_inicio || reserva.horaIni;
      const fin = reserva.hora_fin || reserva.horaFin;
      
      if (inicio && fin) {
        const inicioMin = timeToMinutes(inicio);
        const finMin = timeToMinutes(fin);
        
        // Guardar información de la reserva por su horario de inicio
        iniciosReservas.set(inicio, {
          reserva: reserva,
          inicio: inicio,
          fin: fin,
          inicioMin: inicioMin,
          finMin: finMin
        });
        
        // Marcar todos los horarios ocupados por esta reserva
        for (let t = inicioMin; t <= finMin; t += 30) {
          ocupados.add(minutesToTime(t));
        }
      }
    });

    // Crear bloques
    for (let i = 0; i < horarios.length; i++) {
      const hora = horarios[i];
      
      if (iniciosReservas.has(hora)) {
        // CORRECCIÓN: Es el inicio de una reserva - calcular fin visual
        const infoReserva = iniciosReservas.get(hora);
        const reserva = infoReserva.reserva;
        
        // Calcular cuántos slots de 30 minutos ocupa la reserva
        const duracionMinutos = infoReserva.finMin - infoReserva.inicioMin;
        const cantidadSlots = duracionMinutos / 30;
        
        // El fin visual es el inicio + cantidad de slots + 1 (para mostrar hasta el próximo horario)
        const finVisualMinutos = infoReserva.inicioMin + (cantidadSlots * 30);
        const finVisual = minutesToTime(finVisualMinutos+30);
        
        bloques.push({
          tipo: "reservado",
          inicio: reserva.hora_inicio || reserva.horaIni,
          fin: reserva.hora_fin || reserva.horaFin, // Fin real
          finVisual: finVisual, // Fin visual (para mostrar)
          nombre: reserva.nombre_usuario || reserva.nombre || "Sin nombre",
          tipoReserva: reserva.tipo || "diario",
          pagado: reserva.pagado || false,
          reservaId: reserva.id,
          duracionSlots: cantidadSlots
        });
        
        // Saltar los horarios que están dentro de esta reserva
        const horariosASaltar = cantidadSlots - 1;
        i += horariosASaltar;
        
      } else if (ocupados.has(hora)) {
        // Horario ocupado pero no es inicio (ya está cubierto por otro bloque)
        continue;
      } else {
        // Horario disponible
        bloques.push({ tipo: "disponible", hora });
      }
    }

    return bloques;
  };

  const bloques = crearBloques();

  // Manejar selección de horarios
  const manejarClickHorario = (hora) => {
    setHorariosSeleccionados(prev => {
      if (prev.includes(hora)) {
        return prev.filter(h => h !== hora);
      } else {
        return [...prev, hora].sort();
      }
    });
  };

  // Verificar si los horarios seleccionados son consecutivos
  const sonHorariosConsecutivos = (horarios) => {
    if (horarios.length < 2) return true;
    
    for (let i = 1; i < horarios.length; i++) {
      const horaAnterior = timeToMinutes(horarios[i - 1]);
      const horaActual = timeToMinutes(horarios[i]);
      
      if (horaActual - horaAnterior !== 30) {
        return false;
      }
    }
    return true;
  };

  // Crear reserva
  const crearReserva = async () => {
    if (!nombreReservante.trim()) {
      alert("Por favor ingresa el nombre del reservante");
      return;
    }

    if (horariosSeleccionados.length === 0) {
      alert("Por favor selecciona al menos un horario");
      return;
    }

    if (!sonHorariosConsecutivos(horariosSeleccionados)) {
      alert("Los horarios deben ser consecutivos (ej: 19:00, 19:30, 20:00)");
      return;
    }

    try {
      const respuesta = await fetch("http://localhost/padel-backend/reservar_horarios.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          cancha_id: parseInt(canchaId),
          nombre: nombreReservante,
          fecha: fecha,
          horarios: horariosSeleccionados,
          tipo: "diario",
          pagado: 0
        })
      });

      const datos = await respuesta.json();

      if (datos.success) {
        // Recargar las reservas
        const reservasRes = await fetch(`http://localhost/padel-backend/get_reservas.php?cancha_id=${canchaId}&fecha=${fecha}`);
        const reservasData = await reservasRes.json();
        setReservas(Array.isArray(reservasData.reservas) ? reservasData.reservas : []);
        
        // Limpiar formulario
        setHorariosSeleccionados([]);
        setNombreReservante("");
        
        alert("Reserva creada exitosamente");
      } else {
        alert(datos.error || "Error al crear la reserva");
      }
    } catch (error) {
      alert("Error de conexión al crear la reserva");
    }
  };

  // Cancelar reserva
  const cancelarReserva = async (reservaId) => {
    if (!confirm("¿Estás seguro de que quieres cancelar esta reserva?")) {
      return;
    }

    try {
      const respuesta = await fetch("http://localhost/padel-backend/cancelar_reserva.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ reserva_id: reservaId })
      });

      const datos = await respuesta.json();

      if (datos.success) {
        // Recargar las reservas
        const reservasRes = await fetch(`http://localhost/padel-backend/get_reservas.php?cancha_id=${canchaId}&fecha=${fecha}`);
        const reservasData = await reservasRes.json();
        setReservas(Array.isArray(reservasData.reservas) ? reservasData.reservas : []);
        
        alert("Reserva cancelada exitosamente");
      } else {
        alert(datos.error || "Error al cancelar la reserva");
      }
    } catch (error) {
      alert("Error de conexión al cancelar la reserva");
    }
  };

  if (loading) {
    return (
      <div className="reservas-page">
        <div className="cargando">Cargando...</div>
      </div>
    );
  }

  return (
    <div className="reservas-page">
      <div className="reservas-container">
        <header className="reservas-header">
          <h1>Reservas - {cancha?.nombre_cancha || "Cancha"}</h1>
          <div className="fecha-selector">
            <label>Fecha: </label>
            <input 
              type="date" 
              value={fecha} 
              onChange={(e) => setFecha(e.target.value)}
            />
          </div>
        </header>

        {/* Grid de horarios - 5 columnas */}
        <div className="grid-horarios">
          {bloques.map((bloque, index) => (
            <div key={index} className={`bloque-horario ${bloque.tipo}`}>
              {bloque.tipo === "disponible" ? (
                <div 
                  className={`bloque-disponible ${
                    horariosSeleccionados.includes(bloque.hora) ? "seleccionado" : ""
                  }`}
                  onClick={() => manejarClickHorario(bloque.hora)}
                >
                  <div className="hora">{bloque.hora}</div>
                  <div className="estado">Disponible</div>
                </div>
              ) : (
                <div className="bloque-reservado">
                  {/* CORRECCIÓN: Mostrar fin visual en lugar del fin real */}
                  <div className="rango-horario">
                    {bloque.inicio} - {bloque.finVisual}
                  </div>
                  <div className="nombre-reservante">{bloque.nombre}</div>
                  <div className="tipo-reserva">
                    {bloque.tipoReserva} {bloque.pagado ? "(Pagado)" : "(Pendiente)"}
                  </div>
                  <div className="acciones-reserva">
                    <button className="btn-gestionar">Gestionar</button>
                    <button 
                      className="btn-cancelar"
                      onClick={() => cancelarReserva(bloque.reservaId)}
                    >
                      Cancelar Reserva
                    </button>
                  </div>
                </div>
              )}
            </div>
          ))}
        </div>

        {/* Formulario de nueva reserva */}
        {horariosSeleccionados.length > 0 && (
          <footer className="footer-reserva">
            <div className="info-seleccion">
              <strong>Horarios seleccionados:</strong> {horariosSeleccionados.join(", ") || "—"}
            </div>
            <div className="formulario-footer">
              <input
                type="text"
                placeholder="Nombre del reservante"
                value={nombreReservante}
                onChange={(e) => setNombreReservante(e.target.value)}
              />
              <label>
                <input
                  type="radio"
                  name="tipoReserva"
                  value="diario"
                  checked={true}
                  readOnly
                />
                Diario
              </label>
              <label>
                <input
                  type="radio"
                  name="tipoReserva"
                  value="mensual"
                  disabled
                />
                Mensual
              </label>
              <button
                className="btn-confirmar"
                onClick={crearReserva}
                disabled={!nombreReservante.trim() || horariosSeleccionados.length === 0}
              >
                Confirmar Reserva
              </button>
            </div>
          </footer>
        )}
      </div>
    </div>
  );
};

export default ReservasCancha;