export const getToken = () => localStorage.getItem("token");
export const getRol = () => localStorage.getItem("rol");
export const getNombre = () => localStorage.getItem("nombre");
export const logout = () => {
    localStorage.removeItem("token");
    localStorage.removeItem("rol");
    localStorage.removeItem("nombre");
};