import { useEffect, useRef, useState } from 'react'
import { ApiError, api, del, get, post, put } from '../lib/api.js'
import { Field, Modal, Spinner, useToast } from '../lib/ui.jsx'
import { Avatar } from '../components/Layout.jsx'

const taille = (n) => (n > 1048576 ? `${(n / 1048576).toFixed(1)} Mo` : `${Math.max(1, Math.round(n / 1024))} Ko`)
const COULEURS = ['#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899', '#64748b']

export default function CardModal({ cardId, board, me, onClose, onChanged }) {
  const toast = useToast()
  const [c, setC] = useState(null)
  const [f, setF] = useState(null)
  const [comment, setComment] = useState('')
  const [busy, setBusy] = useState(false)
  const file = useRef(null)

  const load = () => get(`cartes/${cardId}`).then((d) => { setC(d); setF((old) => old ?? { titre: d.titre, description: d.description || '', echeance: d.echeance || '', assignee_id: d.assignee_id || '' }) }).catch(() => { toast('Carte introuvable.', 'err'); onClose() })
  useEffect(() => { setC(null); setF(null); load() }, [cardId])
  if (!c || !f) return <Modal title="Carte" onClose={onClose}><Spinner /></Modal>

  const dirty = f.titre !== c.titre || (f.description || '') !== (c.description || '') || (f.echeance || '') !== (c.echeance || '') || String(f.assignee_id || '') !== String(c.assignee_id || '')
  const wrap = async (fn, ok) => { setBusy(true); try { const r = await fn(); if (ok) toast(ok); return r } catch (e) { toast(e instanceof ApiError ? e.all() : e.message, 'err') } finally { setBusy(false) } }
  const save = async () => {
    const d = await wrap(() => put(`cartes/${cardId}`, { titre: f.titre, description: f.description || null, echeance: f.echeance || null, assignee_id: f.assignee_id ? Number(f.assignee_id) : null, etiquettes: c.etiquettes.map((e) => e.id) }), 'Carte enregistrée.')
    if (d) { setC(d); onChanged() }
  }
  const toggleLabel = async (e) => {
    const ids = c.etiquettes.some((x) => x.id === e.id) ? c.etiquettes.filter((x) => x.id !== e.id).map((x) => x.id) : [...c.etiquettes.map((x) => x.id), e.id]
    const d = await wrap(() => put(`cartes/${cardId}`, { etiquettes: ids })); if (d) { setC({ ...c, etiquettes: d.etiquettes, historique: d.historique }); onChanged() }
  }
  const nouvelleEtiquette = async () => {
    const nom = prompt('Nom de la nouvelle étiquette'); if (!nom?.trim()) return
    await wrap(() => post(`boards/${board.id}/etiquettes`, { nom, couleur: COULEURS[Math.floor(Math.random() * COULEURS.length)] }), 'Étiquette créée.'); onChanged()
  }
  const commenter = async (e) => { e.preventDefault(); if (!comment.trim()) return; await wrap(() => post(`cartes/${cardId}/commentaires`, { contenu: comment })); setComment(''); load(); onChanged() }
  const supprimerCommentaire = async (m) => { await wrap(() => del(`commentaires/${m.id}`)); load(); onChanged() }
  const joindre = async (e) => {
    const fichier = e.target.files[0]; e.target.value = ''; if (!fichier) return
    const fd = new FormData(); fd.append('fichier', fichier)
    await wrap(() => api(`cartes/${cardId}/fichiers`, { method: 'POST', body: fd }), 'Fichier joint.'); load(); onChanged()
  }
  const telecharger = async (x) => { try { const b = await api(`fichiers/${x.id}`, { blob: true }); const a = document.createElement('a'); a.href = URL.createObjectURL(b); a.download = x.nom; a.click() } catch (e) { toast(e.message, 'err') } }
  const supprimerFichier = async (x) => { if (!confirm(`Supprimer « ${x.nom} » ?`)) return; await wrap(() => del(`fichiers/${x.id}`)); load(); onChanged() }
  const supprimer = async () => { if (!confirm('Supprimer cette carte ?')) return; await wrap(() => del(`cartes/${cardId}`), 'Carte supprimée.'); onChanged(); onClose() }
  const ago = (d) => new Date(d).toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' })

  return (
    <Modal title={`Dans la liste « ${c.liste.nom} »`} onClose={onClose} wide>
      <div className="space-y-5">
        <Field label="Titre"><input className="input text-lg font-extrabold" value={f.titre} onChange={(e) => setF({ ...f, titre: e.target.value })} /></Field>
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Échéance"><input type="date" className="input" value={f.echeance} onChange={(e) => setF({ ...f, echeance: e.target.value })} /></Field>
          <Field label="Assignée à"><select className="input" value={f.assignee_id} onChange={(e) => setF({ ...f, assignee_id: e.target.value })}><option value="">Personne</option>{board.membres.map((m) => <option key={m.id} value={m.id}>{m.name}{m.id === me.id ? ' (moi)' : ''}</option>)}</select></Field>
        </div>
        <div><span className="label">Étiquettes</span><div className="flex flex-wrap gap-1.5">{board.etiquettes.map((e) => { const on = c.etiquettes.some((x) => x.id === e.id); return <button key={e.id} type="button" onClick={() => toggleLabel(e)} aria-pressed={on} className={`rounded-full px-3 py-1 text-xs font-bold text-white transition ${on ? 'ring-2 ring-slate-900 ring-offset-1' : 'opacity-50 hover:opacity-90'}`} style={{ background: e.couleur }}>{on ? '✓ ' : ''}{e.nom}</button> })}<button type="button" onClick={nouvelleEtiquette} className="rounded-full border border-dashed border-slate-300 px-3 py-1 text-xs font-bold text-slate-500 hover:bg-slate-50">＋ Créer</button></div></div>
        <Field label="Description"><textarea className="input min-h-28" value={f.description} onChange={(e) => setF({ ...f, description: e.target.value })} placeholder="Détails, critères d’acceptation, liens…" /></Field>
        <div className="flex flex-wrap items-center gap-2"><button className="btn-primary" disabled={!dirty || busy} onClick={save}>Enregistrer</button>{dirty && <span className="text-xs font-semibold text-amber-600">Modifications non enregistrées</span>}<button className="btn-ghost ml-auto text-rose-600" onClick={supprimer}>🗑 Supprimer la carte</button></div>

        <section aria-labelledby="pj"><h3 id="pj" className="mb-2 text-sm font-extrabold text-slate-800">📎 Pièces jointes ({c.fichiers.length})</h3>
          <ul className="space-y-1.5">{c.fichiers.map((x) => <li key={x.id} className="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-2 text-sm"><button className="min-w-0 flex-1 truncate text-left font-semibold text-brand-700 hover:underline" onClick={() => telecharger(x)}>{x.nom}</button><span className="text-xs text-slate-400">{taille(x.taille)} · {x.date}</span><button className="text-slate-400 hover:text-rose-600" onClick={() => supprimerFichier(x)} aria-label={`Supprimer ${x.nom}`}>✕</button></li>)}</ul>
          <input ref={file} type="file" className="sr-only" onChange={joindre} accept=".pdf,.png,.jpg,.jpeg,.gif,.txt,.docx,.xlsx,.pptx,.csv,.zip" aria-label="Ajouter un fichier" />
          <button className="btn-ghost mt-2 !py-1.5 text-xs" onClick={() => file.current.click()}>＋ Ajouter un fichier (2 Mo max)</button></section>

        <section aria-labelledby="cm"><h3 id="cm" className="mb-2 text-sm font-extrabold text-slate-800">💬 Commentaires ({c.commentaires.length})</h3>
          <form onSubmit={commenter} className="flex gap-2"><input className="input" placeholder="Écrire un commentaire…" value={comment} onChange={(e) => setComment(e.target.value)} maxLength={1000} aria-label="Nouveau commentaire" /><button className="btn-primary" disabled={!comment.trim() || busy}>Envoyer</button></form>
          <ul className="mt-3 space-y-3">{[...c.commentaires].reverse().map((m) => <li key={m.id} className="flex gap-3 text-sm"><Avatar name={m.auteur} size={30} /><div className="min-w-0 flex-1 rounded-xl bg-slate-50 px-3.5 py-2.5"><div className="flex items-center gap-2"><b className="text-slate-900">{m.auteur}</b><span className="text-xs text-slate-400">{ago(m.date)}</span>{(m.user_id === me.id || board.proprietaire) && <button className="ml-auto text-xs text-slate-400 hover:text-rose-600" onClick={() => supprimerCommentaire(m)}>Supprimer</button>}</div><p className="mt-0.5 whitespace-pre-line text-slate-700">{m.contenu}</p></div></li>)}</ul></section>

        {c.historique.length > 0 && <section><h3 className="mb-2 text-sm font-extrabold text-slate-800">📜 Historique</h3><ul className="space-y-1 text-xs text-slate-500">{c.historique.map((h, i) => <li key={i}><b className="text-slate-700">{h.auteur}</b> {h.texte} · {ago(h.date)}</li>)}</ul></section>}
      </div>
    </Modal>
  )
}
