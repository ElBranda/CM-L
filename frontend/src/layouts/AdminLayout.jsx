// src/layouts/AdminLayout.jsx
import React from 'react'
import { Outlet } from 'react-router-dom'
import AdminHeader from '../components/AdminHeader'

export default function AdminLayout() {
  return (
    <>
      <header className="page-header">
        <AdminHeader />
      </header>
      <main className="page-content">
        <Outlet />
      </main>
    </>
  )
}
