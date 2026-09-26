import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { ApiError, get, post } from '../lib/api.js'
import { Empty, Field, Modal, Spinner, dateFr, useLoad, useToast } from '../lib/ui.jsx'

const COULEURS = ['#0d9488', '#2563eb', '#7c3aed', '#db2777', '#ea580c', '#059669', '#b45309', '#334155']

export function Boards() {
  const b = useLoad(() => get('boards'), [])
  const toast = useToast()
  const nav = useNavigate()
  const [open, setOpen] = useState(false)
  const [f, setF] = useState({ nom: '', couleur: COULEURS[0] })
  const [err, setErr] = useState('')
  const creer = async (e) => {
    e.preventDefault(); setErr('')
    try { const x = await post('boards', f); toast('Tableau créé.'); nav(`/tableau/${x.id}`) } catch (x) { setErr(x instanceof ApiError ? x.first('nom') || x.message : x.message) }
  }
  return (
    <div className="mx-auto w-full max-w-6xl px-4 py-8">
      <div className="mb-6 flex flex-wrap items-center justify-between gap-3"><div><h1 className="text-2xl font-extrabold text-slate-900">Mes tableaux</h1><p className="text-sm text-slate-500">Un tableau = un projet. Créez-en un ou ouvrez ceux de votre équipe.</p></div><button className="btn-primary" onClick={() => setOpen(true)}>＋ Nouveau tableau</button></div>
      {b.loading && !b.data ? <Spinner /> : (b.data || []).length === 0 ? <Empty icon="🗂️" title="Aucun tableau">Créez votre premier tableau pour organiser un projet.</Empty> : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">{b.data.map((x) => (
          <Link key={x.id} to={`/tableau/${x.id}`} className="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg"><div className="h-20 p-4" style={{ background: `linear-gradient(135deg, ${x.couleur}, ${x.couleur}bb)` }}><span className="rounded-full bg-black/20 px-2.5 py-0.5 text-[11px] font-bold text-white">{x.proprietaire ? 'Propriétaire' : 'Membre'}</span></div>
            <div className="p-4"><h2 className="truncate font-extrabold text-slate-900 group-hover:text-brand-700">{x.nom}</h2><p className="mt-1 text-xs text-slate-500">{x.cartes} carte{x.cartes > 1 ? 's' : ''} · {x.listes} listes · {x.membres} membre{x.membres > 1 ? 's' : ''}</p></div></Link>))}</div>
      )}
      {open && (
        <Modal title="Nouveau tableau" onClose={() => setOpen(false)}>
          <form onSubmit={creer} className="space-y-4"><Field label="Nom du projet" error={err}><input className="input" autoFocus value={f.nom} onChange={(e) => setF({ ...f, nom: e.target.value })} placeholder="Ex. Lancement du site web" /></Field>
            <div><span className="label">Couleur</span><div className="flex flex-wrap gap-2">{COULEURS.map((c) => <button type="button" key={c} onClick={() => setF({ ...f, couleur: c })} aria-label={`Couleur ${c}`} aria-pressed={f.couleur === c} className={`h-9 w-9 rounded-full ring-2 ring-offset-2 ${f.couleur === c ? 'ring-slate-900' : 'ring-transparent'}`} style={{ background: c }} />)}</div></div>
            <p className="text-xs text-slate-500">Trois listes sont créées pour vous : À faire, En cours, Terminé. Vous pourrez en ajouter.</p>
            <div className="flex gap-2"><button type="button" className="btn-ghost flex-1" onClick={() => setOpen(false)}>Annuler</button><button className="btn-primary flex-1">Créer</button></div></form>
        </Modal>
      )}
    </div>
  )
}

export function MesTaches() {
  const t = useLoad(() => get('mes-taches'), [])
  const today = new Date().toISOString().slice(0, 10)
  const list = t.data || []
  const groupes = [['En retard', list.filter((x) => !x.terminee && x.echeance && x.echeance < today), 'text-rose-600'], ["Aujourd'hui", list.filter((x) => !x.terminee && x.echeance === today), 'text-amber-600'], ['À venir', list.filter((x) => !x.terminee && x.echeance && x.echeance > today), 'text-slate-700'], ['Sans échéance', list.filter((x) => !x.terminee && !x.echeance), 'text-slate-500'], ['Terminées', list.filter((x) => x.terminee), 'text-emerald-600']]
  return (
    <div className="mx-auto w-full max-w-3xl px-4 py-8">
      <h1 className="text-2xl font-extrabold text-slate-900">Mes tâches</h1><p className="mb-6 text-sm text-slate-500">Toutes les cartes qui vous sont assignées, tous tableaux confondus.</p>
      {t.loading && !t.data ? <Spinner /> : list.length === 0 ? <Empty icon="✅" title="Aucune tâche assignée">Les cartes qui vous sont assignées apparaîtront ici.</Empty> : groupes.filter(([, g]) => g.length).map(([titre, g, c]) => (
        <section key={titre} className="mb-6"><h2 className={`mb-2 text-sm font-extrabold uppercase tracking-wide ${c}`}>{titre} ({g.length})</h2>
          <ul className="space-y-2">{g.map((x) => (
            <li key={x.id}><Link to={`/tableau/${x.board.id}?carte=${x.id}`} className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3.5 transition hover:shadow-md"><span className="h-8 w-1.5 rounded-full" style={{ background: x.board.couleur }} /><span className="min-w-0 flex-1"><b className={`block truncate text-slate-900 ${x.terminee ? 'line-through opacity-60' : ''}`}>{x.titre}</b><span className="text-xs text-slate-500">{x.board.nom} · {x.liste}</span></span>{x.etiquettes.slice(0, 2).map((e) => <span key={e.id} className="hidden rounded-full px-2 py-0.5 text-[11px] font-bold text-white sm:inline" style={{ background: e.couleur }}>{e.nom}</span>)}{x.echeance && <span className={`text-xs font-bold ${!x.terminee && x.echeance < today ? 'text-rose-600' : 'text-slate-500'}`}>{dateFr(x.echeance, { day: 'numeric', month: 'short' })}</span>}</Link></li>))}</ul></section>))}
    </div>
  )
}

export function Notifications() {
  const n = useLoad(() => get('notifications'), [])
  const ICON = { echeance: '⏰', assigne: '📌', invitation: '👋', info: '💬' }
  useState(() => { post('notifications/lues').then(() => setTimeout(() => window.dispatchEvent(new Event('notifs:changed')), 400)).catch(() => {}) })
  return (
    <div className="mx-auto w-full max-w-2xl px-4 py-8">
      <h1 className="text-2xl font-extrabold text-slate-900">Notifications</h1><p className="mb-5 text-sm text-slate-500">Assignations, commentaires, invitations et échéances proches.</p>
      {n.loading && !n.data ? <Spinner /> : (n.data || []).length === 0 ? <Empty icon="🔔" title="Aucune notification" /> : <ul className="space-y-2">{n.data.map((x) => (
        <li key={x.id}><Link to={x.carte_id ? `/tableau/${x.board_id}?carte=${x.carte_id}` : x.board_id ? `/tableau/${x.board_id}` : '/tableaux'} className="flex gap-3 rounded-xl border border-slate-200 bg-white p-4 transition hover:shadow-md"><span className="text-2xl">{ICON[x.type]}</span><span><b className="block text-sm text-slate-900">{x.texte}</b><span className="text-xs text-slate-400">{new Date(x.date).toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' })}</span></span></Link></li>))}</ul>}
    </div>
  )
}
