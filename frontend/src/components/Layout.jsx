import { useEffect, useState } from 'react'
import { Link, NavLink, Navigate, Outlet, useNavigate } from 'react-router-dom'
import Logo from './Logo.jsx'
import { get } from '../lib/api.js'
import { useAuth } from '../lib/auth.jsx'
import { InstallButton } from '../lib/pwa.jsx'
import { Spinner } from '../lib/ui.jsx'

const HUES = ['#0d9488', '#7c3aed', '#db2777', '#2563eb', '#ea580c', '#059669', '#b45309']
export const hue = (s) => HUES[[...String(s || '')].reduce((a, c) => a + c.charCodeAt(0), 0) % HUES.length]
export function Avatar({ name, size = 28, ring = false }) {
  return <span title={name} className={`grid shrink-0 place-items-center rounded-full font-bold text-white ${ring ? 'ring-2 ring-white' : ''}`} style={{ width: size, height: size, background: hue(name), fontSize: size * 0.42 }}>{(name || '?').trim()[0]?.toUpperCase()}</span>
}

export default function AppLayout() {
  const { user, ready, logout } = useAuth()
  const nav = useNavigate()
  const [unread, setUnread] = useState(0)
  useEffect(() => {
    if (!user) return
    const load = () => get('notifications').then((n) => setUnread(n.filter((x) => !x.lu).length)).catch(() => {})
    load(); const t = setInterval(load, 30000); window.addEventListener('notifs:changed', load)
    return () => { clearInterval(t); window.removeEventListener('notifs:changed', load) }
  }, [user?.id])
  if (!ready) return <Spinner />
  if (!user) return <Navigate to="/connexion" replace />
  const link = ({ isActive }) => `rounded-lg px-3 py-2 text-sm font-semibold transition ${isActive ? 'bg-white/20 text-white' : 'text-white/80 hover:bg-white/10'}`
  return (
    <div className="flex min-h-screen flex-col bg-slate-100">
      <header className="no-print sticky top-0 z-40 bg-slate-900 text-white shadow">
        <div className="mx-auto flex max-w-[1600px] items-center gap-3 px-4 py-2.5"><Logo to="/tableaux" light />
          <nav className="ml-2 flex items-center gap-1" aria-label="Navigation principale"><NavLink to="/tableaux" className={link}>Tableaux</NavLink><NavLink to="/mes-taches" className={link}>Mes tâches</NavLink></nav>
          <div className="ml-auto flex items-center gap-2">
            <InstallButton className="btn-ghost hidden !border-white/20 !bg-white/10 !py-1.5 !text-white hover:!bg-white/20 lg:inline-flex" label="⬇ Installer" />
            <Link to="/notifications" className="relative rounded-lg px-3 py-1.5 hover:bg-white/10" aria-label={`Notifications (${unread} non lues)`}>🔔{unread > 0 && <span className="absolute -right-0.5 -top-0.5 grid h-5 min-w-5 place-items-center rounded-full bg-rose-500 px-1 text-[11px] font-bold">{unread}</span>}</Link>
            <Avatar name={user.name} size={30} /><span className="hidden text-sm font-semibold sm:inline">{user.name.split(' ')[0]}</span>
            <button className="rounded-lg px-3 py-1.5 text-sm font-semibold hover:bg-white/10" onClick={async () => { await logout(); nav('/') }}>Quitter</button>
          </div></div>
      </header>
      <main className="flex flex-1 flex-col"><Outlet /></main>
    </div>
  )
}
