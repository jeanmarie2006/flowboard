import { useEffect } from 'react'
import { Navigate, Route, Routes, useLocation } from 'react-router-dom'
import AppLayout from './components/Layout.jsx'
import { AuthPage, Landing } from './pages/Home.jsx'
import { Boards, MesTaches, Notifications } from './pages/Boards.jsx'
import Board from './pages/Board.jsx'
import Installer from './pages/Installer.jsx'

function ScrollTop() {
  const { pathname } = useLocation()
  useEffect(() => { window.scrollTo(0, 0) }, [pathname])
  return null
}

export default function App() {
  return (
    <>
      <ScrollTop />
      <Routes>
        <Route path="/" element={<Landing />} />
        <Route path="/connexion" element={<AuthPage mode="login" />} />
        <Route path="/inscription" element={<AuthPage mode="register" />} />
        <Route path="/installer" element={<Installer />} />
        <Route element={<AppLayout />}>
          <Route path="/tableaux" element={<Boards />} />
          <Route path="/tableau/:id" element={<Board />} />
          <Route path="/mes-taches" element={<MesTaches />} />
          <Route path="/notifications" element={<Notifications />} />
        </Route>
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </>
  )
}
