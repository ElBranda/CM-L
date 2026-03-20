import { useLocation, useNavigate } from "react-router-dom";
import "./ReservaExitosa.css";
import { useEffect } from "react";

const ReservaExitosa = () => {
  const { state } = useLocation();
  const navigate = useNavigate();

  useEffect(() => {
    if (!state || !state.cancha || !state.fecha || !state.horarios || !state.nombre) {
      navigate("/"); // redirige si no hay datos válidos
    }
  }, [state, navigate]);

  if (!state) {
    return <p>No hay datos de reserva disponibles.</p>;
  }

  const { cancha, fecha, horarios, nombre } = state;

  const mensajeWhatsapp = `Hola, soy ${nombre}. Quiero confirmar mi reserva en la cancha ${cancha} para el día ${fecha} en los siguientes horarios: ${horarios.join(", ")}.`;
  const linkWhatsapp = `https://wa.me/543815169310?text=${encodeURIComponent(mensajeWhatsapp)}`;

  return (
    <div className="reserva-exitosa-container">
      <h2>Reserva Exitosa 🎉</h2>

      <div className="resumen-reserva">
        <p><strong>Cancha:</strong> {cancha}</p>
        <p><strong>Fecha:</strong> {fecha}</p>
        <p><strong>Horarios:</strong> {horarios.join(", ")}</p>
        <p><strong>A nombre de:</strong> {nombre}</p>
      </div>

      <div className="whatsapp-confirmacion">
        <h3>Confirmar por WhatsApp</h3>
        <textarea readOnly value={mensajeWhatsapp} />
        <a
          href={linkWhatsapp}
          target="_blank"
          rel="noopener noreferrer"
          className="whatsapp-button"
        >
          Enviar mensaje
        </a>
      </div>

      <button className="volver-button" onClick={() => navigate("/")}>
        Volver al inicio
      </button>
    </div>
  );
};

export default ReservaExitosa;
