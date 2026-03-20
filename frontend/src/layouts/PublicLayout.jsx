// src/layouts/PublicLayout.jsx
import UserHeader from "../components/UserHeader";
import { Outlet } from "react-router-dom";

const PublicLayout = ({ children }) => {
  return (
    <>
      <UserHeader />
      <main>
        <Outlet />
      </main>
    </>
  );
};

export default PublicLayout;
