import { useNavigate } from "react-router-dom";
import { useState } from "react";
import "./Canchas.css";

const NuevaCancha = () => {
    const navigate = useNavigate();
    const [form, setForm] = useState({
        nombre_cancha: "",
        deporte: "futbol5",
        descripcion: "",
        ubicacion: "",
        activa: 1
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

        try {
            const res = await fetch("http://localhost/padel-backend/create_cancha.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(form)
            });
            
            const data = await res.json();

            if (data.success) {
                alert("Cancha creada con éxito");
                setForm({
                    nombre_cancha: "",
                    deporte: "Fútbol 5",
                    descripcion: "",
                    ubicacion: "",
                    activa: 1
                });
                navigate(-1);
            } else {
                alert(data.error || "Error al crear cancha");
            }
        } catch (err) {
            console.error(err);
            alert("Error de conexión");
        }
    };

    return (
        <>
            <form onSubmit={handleSubmit} className="form-cancha">
                <div className="form-cancha-container">
                    <h2>Nueva Cancha</h2>
                    <div>
                        <label htmlFor="nombre_cancha">Nombre de la Cancha</label>
                        <input
                            name="nombre_cancha"
                            value={form.nombre_cancha}
                            onChange={handleChange}
                        /> 
                    </div>
                    <div>
                        <label htmlFor="deporte">Deporte Principal</label>
                        <select name="deporte" id={form.deporte} onChange={handleChange}>
                            <option value="Fútbol 5">Fútbol 5</option>
                            <option value="Fútbol 7">Fútbol 7</option>
                            <option value="Fútbol 11">Fútbol 11</option>
                            <option value="Tenis">Tenis</option>
                            <option value="Pádel">Pádel</option>
                            <option value="Básquetbol">Básquetbol</option>
                            <option value="Vóley">Vóley</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    <div>
                        <label htmlFor="descripcion">Descripción</label>
                        <textarea name="descripcion" id={form.descripcion}></textarea>
                    </div>
                    <div>
                        <label htmlFor="ubicacion">Ubicación</label>
                        <input
                            name="ubicacion"
                            value={form.ubicacion}
                            onChange={handleChange}
                        /> 
                    </div>
                    <div className="form-cancha-activa">
                        <label htmlFor="activa">¿Está activa?</label>
                        <input type="checkbox" name="activa" id="activa" checked={form.activa === 1} onChange={e => setForm(prev => ({ ...prev, activa: e.target.checked ? 1 : 0 }))} />
                    </div>
                    <button type="submit">Crear Cancha</button>
                    <button type="button" onClick={cancelButton} className="cancelar-button">Cancelar</button>
                </div>
            </form>
        </>
    );
}

export default NuevaCancha;