import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { DndContext, DragOverlay, KeyboardSensor, PointerSensor, TouchSensor, closestCorners, useDroppable, useSensor, useSensors } from '@dnd-kit/core'
import { SortableContext, arrayMove, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { ApiError, del, get, post, put } from '../lib/api.js'
import { useAuth } from '../lib/auth.jsx'
import { Field, Modal, Spinner, dateFr, useToast } from '../lib/ui.jsx'
import { Avatar } from '../components/Layout.jsx'
import CardModal from './CardModal.jsx'

const cid = (id) => `c${id}`
const lid = (id) => `l${id}`
const today = () => new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10)

function Due({ d, done }) {
  if (!d) return null
  const t = today()
  const cls = done ? 'bg-emerald-50 text-emerald-700' : d < t ? 'bg-rose-100 text-rose-700' : d === t ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600'
  return <span className={`inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-bold ${cls}`}>🕐 {dateFr(d, { day: 'numeric', month: 'short' })}</span>
}

function CardView({ c, onOpen, overlay = false, dragging = false, done = false }) {
  return (
    <div onClick={onOpen} role="button" tabIndex={0} onKeyDown={(e) => e.key === 'Enter' && onOpen?.()} aria-label={`Carte ${c.titre}`}
      className={`cursor-grab select-none rounded-xl border border-slate-200 bg-white p-3 text-left shadow-sm transition hover:border-brand-500 hover:shadow-md active:cursor-grabbing ${overlay ? 'rotate-2 shadow-2xl ring-2 ring-brand-500' : ''} ${dragging ? 'opacity-30' : ''}`}>
      {c.etiquettes.length > 0 && <div className="mb-2 flex flex-wrap gap-1">{c.etiquettes.map((e) => <span key={e.id} className="rounded-full px-2 py-0.5 text-[10px] font-bold text-white" style={{ background: e.couleur }}>{e.nom}</span>)}</div>}
      <p className="text-sm font-semibold leading-snug text-slate-800">{c.titre}</p>
      <div className="mt-2 flex flex-wrap items-center gap-2 text-[11px] text-slate-500"><Due d={c.echeance} done={done} />{c.has_description && <span title="Description">≡</span>}{c.commentaires > 0 && <span>💬 {c.commentaires}</span>}{c.fichiers > 0 && <span>📎 {c.fichiers}</span>}{c.assignee && <span className="ml-auto"><Avatar name={c.assignee.name} size={22} /></span>}</div>
    </div>
  )
}

function SortableCard({ c, onOpen, done }) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: cid(c.id) })
  return <div ref={setNodeRef} style={{ transform: CSS.Transform.toString(transform), transition }} {...attributes} {...listeners}><CardView c={c} onOpen={onOpen} dragging={isDragging} done={done} /></div>
}

function Column({ l, ids, cards, onOpen, onAdd, onRename, onDelete, onMove, first, last }) {
  const { setNodeRef, isOver } = useDroppable({ id: lid(l.id) })
  const [adding, setAdding] = useState(false)
  const [titre, setTitre] = useState('')
  const submit = async (e) => { e.preventDefault(); if (!titre.trim()) return; await onAdd(l, titre.trim()); setTitre('') }
  return (
    <section className={`flex max-h-full w-72 shrink-0 flex-col rounded-2xl bg-slate-200/80 p-2.5 transition ${isOver ? 'ring-2 ring-brand-500' : ''}`} aria-label={`Liste ${l.nom}`}>
      <header className="mb-2 flex items-center gap-1 px-1.5"><h2 className="flex-1 truncate text-sm font-extrabold text-slate-700">{l.nom} <span className="ml-1 rounded-full bg-white/70 px-1.5 text-[11px] text-slate-500">{ids.length}</span></h2>
        <ListMenu onRename={() => onRename(l)} onDelete={() => onDelete(l)} onLeft={first ? null : () => onMove(l, 'gauche')} onRight={last ? null : () => onMove(l, 'droite')} /></header>
      <div ref={setNodeRef} className="min-h-[3rem] flex-1 space-y-2 overflow-y-auto px-0.5 pb-1">
        <SortableContext items={ids} strategy={verticalListSortingStrategy}>{ids.map((id) => cards[id] && <SortableCard key={id} c={cards[id]} done={l.nom === 'Terminé'} onOpen={() => onOpen(cards[id].id)} />)}</SortableContext>
      </div>
      {adding ? (
        <form onSubmit={submit} className="mt-2 space-y-2"><textarea autoFocus className="input min-h-16 resize-none" placeholder="Titre de la carte…" value={titre} onChange={(e) => setTitre(e.target.value)} onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submit(e) } if (e.key === 'Escape') setAdding(false) }} aria-label="Titre de la nouvelle carte" />
          <div className="flex gap-2"><button className="btn-primary !py-1.5 text-xs">Ajouter</button><button type="button" className="btn-ghost !py-1.5 text-xs" onClick={() => setAdding(false)}>✕</button></div></form>
      ) : <button className="mt-2 rounded-lg px-2 py-2 text-left text-sm font-semibold text-slate-600 hover:bg-white/60" onClick={() => setAdding(true)}>＋ Ajouter une carte</button>}
    </section>
  )
}

function ListMenu({ onRename, onDelete, onLeft, onRight }) {
  const [open, setOpen] = useState(false)
  return (
    <div className="relative"><button className="rounded-lg px-2 py-1 text-slate-500 hover:bg-white/70" onClick={() => setOpen(!open)} aria-label="Options de la liste" aria-expanded={open}>⋯</button>
      {open && <><div className="fixed inset-0 z-10" onClick={() => setOpen(false)} /><ul className="absolute right-0 z-20 mt-1 w-44 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 text-sm shadow-xl" onClick={() => setOpen(false)}>
        <li><button className="w-full px-3 py-2 text-left hover:bg-slate-50" onClick={onRename}>✎ Renommer</button></li>
        {onLeft && <li><button className="w-full px-3 py-2 text-left hover:bg-slate-50" onClick={onLeft}>← Déplacer à gauche</button></li>}
        {onRight && <li><button className="w-full px-3 py-2 text-left hover:bg-slate-50" onClick={onRight}>→ Déplacer à droite</button></li>}
        <li><button className="w-full px-3 py-2 text-left text-rose-600 hover:bg-rose-50" onClick={onDelete}>🗑 Supprimer</button></li></ul></>}
    </div>
  )
}

export default function Board() {
  const { id } = useParams()
  const { user } = useAuth()
  const toast = useToast()
  const nav = useNavigate()
  const [sp, setSp] = useSearchParams()
  const [board, setBoard] = useState(null)
  const [error, setError] = useState(false)
  const [cols, setCols] = useState({})       // { l<id>: ['c<id>', …] }
  const [active, setActive] = useState(null) // carte en cours de glissement
  const [panel, setPanel] = useState(null)   // 'membres' | 'activite'
  const dragging = useRef(false)
  const version = useRef('')

  const apply = useCallback((b) => {
    setBoard(b); version.current = b.version
    setCols(Object.fromEntries(b.listes.map((l) => [lid(l.id), l.cartes.map((c) => cid(c.id))])))
  }, [])
  const load = useCallback(() => get(`boards/${id}`).then(apply).catch(() => setError(true)), [id, apply])
  useEffect(() => { setBoard(null); load() }, [id])

  // Mises à jour en direct : on interroge régulièrement la « version » du tableau (requête très légère)
  useEffect(() => {
    const t = setInterval(async () => {
      if (dragging.current || document.hidden) return
      try { const v = await get(`boards/${id}/version`); if (v.version !== version.current && !dragging.current) load() } catch { /* réseau indisponible */ }
    }, 4000)
    return () => clearInterval(t)
  }, [id, load])

  const cards = useMemo(() => Object.fromEntries((board?.listes || []).flatMap((l) => l.cartes.map((c) => [cid(c.id), c]))), [board])
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }), useSensor(TouchSensor, { activationConstraint: { delay: 180, tolerance: 8 } }), useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }))
  const findContainer = (key, from = cols) => (key in from ? key : Object.keys(from).find((k) => from[k].includes(key)))

  const onDragStart = ({ active: a }) => { dragging.current = true; setActive(cards[a.id]) }
  const onDragOver = ({ active: a, over }) => {
    if (!over) return
    setCols((prev) => {
      const from = findContainer(a.id, prev), to = findContainer(over.id, prev)
      if (!from || !to || from === to) return prev
      const overItems = prev[to]
      const overIndex = overItems.indexOf(over.id)
      let idx
      if (over.id in prev) idx = overItems.length
      else { const below = a.rect.current.translated && a.rect.current.translated.top > over.rect.top + over.rect.height; idx = overIndex >= 0 ? overIndex + (below ? 1 : 0) : overItems.length }
      return { ...prev, [from]: prev[from].filter((x) => x !== a.id), [to]: [...overItems.slice(0, idx), a.id, ...overItems.slice(idx)] }
    })
  }
  const onDragEnd = async ({ active: a, over }) => {
    setActive(null)
    let next = cols
    const from = findContainer(a.id), to = over ? findContainer(over.id) : null
    if (from && to && from === to) {
      const i = cols[from].indexOf(a.id), j = over.id in cols ? i : cols[to].indexOf(over.id)
      if (i !== j) { next = { ...cols, [to]: arrayMove(cols[to], i, j) }; setCols(next) }
    }
    const list = findContainer(a.id, next)
    const index = next[list].indexOf(a.id)
    const original = board.listes.find((l) => l.cartes.some((c) => cid(c.id) === a.id))
    const moved = original && (lid(original.id) !== list || original.cartes.findIndex((c) => cid(c.id) === a.id) !== index)
    dragging.current = false
    if (!moved) return
    try { await post(`cartes/${a.id.slice(1)}/deplacer`, { liste_id: Number(list.slice(1)), index }) } catch (e) { toast(e.message, 'err') }
    load()
  }
  const onDragCancel = () => { dragging.current = false; setActive(null); load() }

  const run = async (fn, ok) => { try { await fn(); if (ok) toast(ok); await load() } catch (e) { toast(e instanceof ApiError ? e.all() : e.message, 'err') } }
  const ajouterCarte = (l, titre) => run(() => post(`listes/${l.id}/cartes`, { titre }))
  const ajouterListe = async () => { const nom = prompt('Nom de la nouvelle liste'); if (nom?.trim()) run(() => post(`boards/${id}/listes`, { nom }), 'Liste ajoutée.') }
  const renommerListe = (l) => { const nom = prompt('Nouveau nom de la liste', l.nom); if (nom?.trim() && nom !== l.nom) run(() => put(`listes/${l.id}`, { nom })) }
  const supprimerListe = (l) => confirm(`Supprimer la liste « ${l.nom} » et ses cartes ?`) && run(() => del(`listes/${l.id}`), 'Liste supprimée.')
  const deplacerListe = (l, direction) => run(() => put(`listes/${l.id}`, { direction }))
  const renommerTableau = () => { const nom = prompt('Nom du tableau', board.nom); if (nom?.trim() && nom !== board.nom) run(() => put(`boards/${id}`, { nom, couleur: board.couleur })) }

  const openId = Number(sp.get('carte')) || null
  const open = (cardId) => setSp({ carte: String(cardId) })
  const close = () => { setSp({}); load() }

  if (error) return <div className="grid flex-1 place-items-center p-8 text-center"><div><p className="text-5xl">🔒</p><h1 className="mt-2 text-xl font-extrabold text-slate-900">Tableau introuvable ou accès refusé</h1><Link to="/tableaux" className="btn-primary mt-4">Mes tableaux</Link></div></div>
  if (!board) return <Spinner />

  return (
    <div className="flex min-h-0 flex-1 flex-col" style={{ background: `linear-gradient(180deg, ${board.couleur}, ${board.couleur}cc)` }}>
      <div className="flex flex-wrap items-center gap-3 bg-black/20 px-4 py-2.5 text-white backdrop-blur">
        <Link to="/tableaux" className="rounded-lg px-2 py-1 text-sm font-semibold hover:bg-white/15">←</Link>
        <h1 className={`text-lg font-extrabold ${board.proprietaire ? 'cursor-pointer hover:underline' : ''}`} onClick={board.proprietaire ? renommerTableau : undefined} title={board.proprietaire ? 'Cliquer pour renommer' : ''}>{board.nom}</h1>
        <div className="ml-2 flex -space-x-2" aria-label="Membres">{board.membres.slice(0, 6).map((m) => <Avatar key={m.id} name={m.name} size={30} ring />)}</div>
        <button className="rounded-lg bg-white/15 px-3 py-1.5 text-sm font-semibold hover:bg-white/25" onClick={() => setPanel('membres')}>👥 Inviter</button>
        <div className="ml-auto flex items-center gap-2"><span className="hidden items-center gap-1.5 text-xs text-white/80 sm:flex" title="Les modifications de l'équipe apparaissent automatiquement"><i className="h-2 w-2 animate-pulse rounded-full bg-emerald-300" />En direct</span><button className="rounded-lg bg-white/15 px-3 py-1.5 text-sm font-semibold hover:bg-white/25" onClick={() => setPanel('activite')}>📜 Activité</button></div>
      </div>

      <DndContext sensors={sensors} collisionDetection={closestCorners} onDragStart={onDragStart} onDragOver={onDragOver} onDragEnd={onDragEnd} onDragCancel={onDragCancel}>
        <div className="flex min-h-0 flex-1 items-start gap-3 overflow-x-auto p-4" style={{ height: 'calc(100vh - 110px)' }}>
          {board.listes.map((l, i) => <Column key={l.id} l={l} ids={cols[lid(l.id)] || []} cards={cards} onOpen={open} onAdd={ajouterCarte} onRename={renommerListe} onDelete={supprimerListe} onMove={deplacerListe} first={i === 0} last={i === board.listes.length - 1} />)}
          <button className="w-72 shrink-0 rounded-2xl bg-white/25 p-3.5 text-left text-sm font-bold text-white transition hover:bg-white/35" onClick={ajouterListe}>＋ Ajouter une liste</button>
        </div>
        <DragOverlay>{active ? <CardView c={active} overlay /> : null}</DragOverlay>
      </DndContext>

      {openId && <CardModal cardId={openId} board={board} me={user} onClose={close} onChanged={load} />}
      {panel === 'membres' && <MembersPanel board={board} me={user} onClose={() => setPanel(null)} onChanged={load} />}
      {panel === 'activite' && <ActivityPanel boardId={board.id} onClose={() => setPanel(null)} />}
    </div>
  )
}

function MembersPanel({ board, me, onClose, onChanged }) {
  const toast = useToast()
  const [email, setEmail] = useState('')
  const [busy, setBusy] = useState(false)
  const inviter = async (e) => {
    e.preventDefault(); setBusy(true)
    try { const r = await post(`boards/${board.id}/membres`, { email }); toast(r.message); setEmail(''); onChanged() } catch (x) { toast(x instanceof ApiError ? x.all() : x.message, 'err') } finally { setBusy(false) }
  }
  const retirer = async (m) => { if (!confirm(m.id === me.id ? 'Quitter ce tableau ?' : `Retirer ${m.name} du tableau ?`)) return; try { await del(`boards/${board.id}/membres/${m.id}`); toast('Membre retiré.'); if (m.id === me.id) location.hash = '#/tableaux'; else onChanged() } catch (x) { toast(x.message, 'err') } }
  return (
    <Modal title="Membres du tableau" onClose={onClose}>
      <form onSubmit={inviter} className="flex gap-2"><input className="input" type="email" placeholder="E-mail de la personne à inviter" value={email} onChange={(e) => setEmail(e.target.value)} aria-label="E-mail à inviter" autoFocus /><button className="btn-primary" disabled={busy || !email}>Inviter</button></form>
      <p className="mt-1.5 text-xs text-slate-400">Si la personne n’a pas encore de compte, elle rejoindra le tableau dès son inscription.</p>
      <ul className="mt-4 divide-y divide-slate-100">{board.membres.map((m) => <li key={m.id} className="flex items-center gap-3 py-2.5"><Avatar name={m.name} size={34} /><span className="min-w-0 flex-1"><b className="block truncate text-sm text-slate-900">{m.name}{m.id === me.id ? ' (vous)' : ''}</b><span className="text-xs text-slate-500">{m.email}</span></span><span className={`badge ${m.role === 'owner' ? 'bg-brand-100 text-brand-700' : 'bg-slate-100 text-slate-600'}`}>{m.role === 'owner' ? 'Propriétaire' : 'Membre'}</span>{m.role !== 'owner' && (board.proprietaire || m.id === me.id) && <button className="text-slate-400 hover:text-rose-600" onClick={() => retirer(m)} aria-label={`Retirer ${m.name}`}>✕</button>}</li>)}
        {board.invitations.map((e) => <li key={e} className="flex items-center gap-3 py-2.5 text-sm text-slate-500"><span className="grid h-[34px] w-[34px] place-items-center rounded-full bg-slate-100">✉</span><span className="flex-1">{e}</span><span className="badge bg-amber-100 text-amber-800">Invitation en attente</span></li>)}</ul>
    </Modal>
  )
}

function ActivityPanel({ boardId, onClose }) {
  const [a, setA] = useState(null)
  useEffect(() => { get(`boards/${boardId}/activites`).then(setA) }, [boardId])
  return (
    <Modal title="Activité du tableau" onClose={onClose}>{!a ? <Spinner /> : <ul className="max-h-[60vh] space-y-3 overflow-y-auto pr-1">{a.map((x) => <li key={x.id} className="flex gap-3 text-sm"><Avatar name={x.auteur} size={28} /><span><b className="text-slate-900">{x.auteur}</b> <span className="text-slate-600">{x.texte}</span><span className="block text-xs text-slate-400">{new Date(x.date).toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' })}</span></span></li>)}</ul>}</Modal>
  )
}
