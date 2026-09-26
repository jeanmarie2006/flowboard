import { useState } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import Logo from '../components/Logo.jsx'
import { ApiError } from '../lib/api.js'
import { useAuth } from '../lib/auth.jsx'
import { InstallButton } from '../lib/pwa.jsx'
import { Field } from '../lib/ui.jsx'

export function Landing() {
  const { user, login } = useAuth()
  const nav = useNavigate()
  const [busy, setBusy] = useState(false)
  const demo = async () => { setBusy(true); try { await login({ email: 'demo@flowboard.bj', password: 'demo1234' }); nav('/tableaux') } finally { setBusy(false) } }
  const cols = [['À faire', ['Rédiger la page « À propos »', 'Configurer le domaine'], '#94a3b8'], ['En cours', ['Maquette de l’accueil', 'Formulaire de contact'], '#3b82f6'], ['Terminé', ['Cahier des charges', 'Charte graphique'], '#10b981']]
  return (
    <div className="min-h-screen bg-gradient-to-b from-brand-50 via-white to-slate-50">
      <header className="mx-auto flex max-w-6xl items-center justify-between px-5 py-5"><Logo /><nav className="flex items-center gap-2"><InstallButton className="btn-ghost hidden md:inline-flex" label="⬇ Installer" />{user ? <Link to="/tableaux" className="btn-primary">Mes tableaux</Link> : <><Link to="/connexion" className="btn-ghost hidden sm:inline-flex">Connexion</Link><Link to="/inscription" className="btn-primary">Commencer</Link></>}</nav></header>
      <main>
        <section className="mx-auto grid max-w-6xl items-center gap-12 px-5 pb-16 pt-8 lg:grid-cols-2 lg:pt-14">
          <div><p className="mb-4 inline-flex rounded-full border border-brand-100 bg-white px-3 py-1 text-xs font-bold text-brand-700 shadow-sm">Gratuit · Pour les équipes, les étudiants et les freelances</p>
            <h1 className="text-4xl font-extrabold leading-[1.08] tracking-tight text-slate-900 sm:text-5xl">Organisez votre projet. <span className="text-brand-700">Glissez, déposez, avancez.</span></h1>
            <p className="mt-5 max-w-xl text-lg text-slate-600">Des tableaux, des listes et des cartes que l’on déplace d’un geste pour suivre l’avancement de chaque tâche, avec toute l’équipe.</p>
            <div className="mt-8 flex flex-wrap gap-3"><button className="btn-primary px-6 py-3 text-base" onClick={demo} disabled={busy}>{busy ? 'Ouverture…' : 'Essayer la démo →'}</button><Link to="/inscription" className="btn-ghost px-6 py-3 text-base">Créer un compte</Link></div></div>
          <div className="relative" aria-hidden="true"><div className="absolute -inset-4 -z-10 rounded-[2rem] bg-gradient-to-tr from-brand-500/25 to-transparent blur-2xl" />
            <div className="rounded-2xl bg-slate-800 p-4 shadow-2xl"><div className="grid grid-cols-3 gap-3">{cols.map(([t, cs, c], i) => <div key={t} className="rounded-xl bg-slate-900/60 p-2.5"><p className="mb-2 flex items-center gap-1.5 text-xs font-bold text-slate-200"><i className="h-2 w-2 rounded-full" style={{ background: c }} />{t}</p><div className="space-y-2">{cs.map((x, k) => <div key={x} className={`rounded-lg bg-white p-2.5 text-[11px] font-semibold text-slate-700 shadow ${i === 1 && k === 0 ? '-rotate-3 translate-x-1 scale-105 shadow-xl ring-2 ring-brand-500' : ''}`}>{x}<div className="mt-1.5 flex gap-1"><i className="h-1.5 w-6 rounded-full" style={{ background: ['#8b5cf6', '#3b82f6', '#10b981'][(i + k) % 3] }} /></div></div>)}</div></div>)}</div></div></div>
        </section>
        <section className="mx-auto grid max-w-6xl gap-5 px-5 pb-20 sm:grid-cols-2 lg:grid-cols-3">{[['🧩', 'Glisser-déposer', 'Déplacez une tâche d’une liste à l’autre, réordonnez-la, tout est enregistré instantanément.'], ['👥', 'Travail d’équipe', 'Invitez des membres, assignez les tâches, commentez et joignez des fichiers.'], ['🏷️', 'Étiquettes et échéances', 'Repérez l’urgent d’un coup d’œil grâce aux couleurs et aux dates limites.'], ['🔄', 'Mises à jour en direct', 'Les changements de vos coéquipiers apparaissent automatiquement, sans recharger.'], ['📜', 'Historique d’activité', 'Qui a fait quoi et quand, sur chaque tableau et chaque carte.'], ['✅', 'Vue « Mes tâches »', 'Toutes vos tâches assignées, tous tableaux confondus, classées par échéance.']].map(([i, t, d]) => <article key={t} className="card p-6 transition hover:-translate-y-1 hover:shadow-lg"><div className="mb-3 grid h-11 w-11 place-items-center rounded-xl bg-brand-50 text-xl">{i}</div><h3 className="font-bold text-slate-900">{t}</h3><p className="mt-1 text-sm text-slate-600">{d}</p></article>)}</section>
      </main>
      <footer className="border-t border-slate-200 bg-white py-8 text-center text-sm text-slate-500"><Link to="/installer" className="font-semibold text-brand-700 hover:underline">Installer l’application</Link> · Projet de démonstration · Réalisé par <a className="font-semibold text-brand-700 hover:underline" href="https://sedjame-vianney.vercel.app" target="_blank" rel="noopener">Sedjame Vianney</a></footer>
    </div>
  )
}

export function AuthPage({ mode }) {
  const isReg = mode === 'register'
  const { user, login, register } = useAuth()
  const nav = useNavigate()
  const [f, setF] = useState({ name: '', email: '', password: '' })
  const [err, setErr] = useState({})
  const [msg, setMsg] = useState('')
  const [busy, setBusy] = useState(false)
  if (user) return <Navigate to="/tableaux" replace />
  const set = (k) => (e) => setF({ ...f, [k]: e.target.value })
  const enter = async (creds) => {
    setErr({}); setMsg(''); setBusy(true)
    try { creds ? await login(creds) : isReg ? await register(f) : await login(f); nav('/tableaux') }
    catch (x) { if (x instanceof ApiError) { setErr(Object.fromEntries(Object.entries(x.errors).map(([k, v]) => [k, v[0]]))); setMsg(x.message) } else setMsg('Erreur inattendue.') } finally { setBusy(false) }
  }
  return (
    <div className="grid min-h-screen place-items-center bg-gradient-to-b from-brand-50 to-slate-50 px-4 py-10"><div className="w-full max-w-md"><div className="mb-6 flex justify-center"><Logo /></div>
      <form onSubmit={(e) => { e.preventDefault(); enter() }} className="card space-y-4 p-7" noValidate>
        <div><h1 className="text-2xl font-extrabold text-slate-900">{isReg ? 'Créer mon compte' : 'Connexion'}</h1><p className="text-sm text-slate-500">{isReg ? 'Gratuit, en 30 secondes.' : 'Retrouvez vos tableaux.'}</p></div>
        {msg && !Object.keys(err).length && <div role="alert" className="rounded-xl bg-rose-50 px-3.5 py-2.5 text-sm font-medium text-rose-700">{msg}</div>}
        {isReg && <Field label="Nom complet" error={err.name}><input className="input" value={f.name} onChange={set('name')} autoComplete="name" /></Field>}
        <Field label="E-mail" error={err.email}><input type="email" className="input" value={f.email} onChange={set('email')} autoComplete="email" /></Field>
        <Field label="Mot de passe" error={err.password} hint={isReg ? '8 caractères minimum' : undefined}><input type="password" className="input" value={f.password} onChange={set('password')} autoComplete={isReg ? 'new-password' : 'current-password'} /></Field>
        <button className="btn-primary w-full" disabled={busy}>{busy ? 'Patientez…' : isReg ? 'Créer mon compte' : 'Se connecter'}</button>
        {!isReg && <button type="button" className="btn-ghost w-full" disabled={busy} onClick={() => enter({ email: 'demo@flowboard.bj', password: 'demo1234' })}>Essayer avec le compte démo</button>}
        <p className="text-center text-sm text-slate-500">{isReg ? <>Déjà inscrit ? <Link className="font-semibold text-brand-700 hover:underline" to="/connexion">Connexion</Link></> : <>Nouveau ? <Link className="font-semibold text-brand-700 hover:underline" to="/inscription">Créer un compte</Link></>}</p>
      </form></div></div>
  )
}
