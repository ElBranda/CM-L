// src/layouts/AdminLayout.jsx
import React from 'react'
import { Outlet } from 'react-router-dom'
import EmpleadoHeader from '../components/EmpleadoHeader'

export default function EmpleadoLayout() {
  return (
    <>
      <header className="page-header">
        <EmpleadoHeader />
      </header>
      <main className="page-content">
        <Outlet />
      </main>
    </>
  )
}
