import UserHeader from "./components/UserHeader";
import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import "./Home.css";

const Home = () => {
  const [canchas, setCanchas] = useState([]);
  const navigate = useNavigate();

  useEffect(() => {
    fetch("http://localhost/padel-backend/get_canchas.php")
      .then(res => res.json())
      .then(data => setCanchas(data))
      .catch(err => console.error("Error al cargar canchas", err));
  }, []);

  const verDisponibilidad = (id) => {
    navigate(`/reservar/${id}`);
  };

  return (
    <>
        <div>
          <h2>Reservá Tu Turno</h2>
          <div className="grid-canchas">
            {canchas.map(cancha => (
              <div key={cancha.id} className="card-cancha">
                <h3>{cancha.nombre_cancha}</h3>
                <p>{cancha.deporte}</p>
                <button
                  onClick={() => navigate(`/reservar/${cancha.id}`, {
                    state: {
                      nombreCancha: cancha.nombre_cancha
                    }
                  })}
                >
                  Ver disponibilidad y reservar
                </button>
              </div>
            ))}
          </div>
        </div>
    </>
  );
};

export default Home;
