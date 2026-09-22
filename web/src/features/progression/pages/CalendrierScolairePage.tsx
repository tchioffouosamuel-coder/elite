import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { CalendarDays, RotateCcw, Save, Trash2 } from 'lucide-react'
import { fetchClasses } from '@/features/classes/api'
import { enregistrerRegleCalendrier, fetchCalendrierScolaire, supprimerRegleCalendrier } from '@/features/progression/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Button } from '@/shared/ui/Button'
import { EmptyState, Spinner } from '@/shared/ui/Feedback'
import { succes, erreur as alerteErreur } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

export function CalendrierScolairePage() {
  const queryClient = useQueryClient()
  const [date, setDate] = useState('')
  const [motif, setMotif] = useState('')
  const [portee, setPortee] = useState('ecole')
  const [cible, setCible] = useState('')
  const [ouvert, setOuvert] = useState(false)
  const [enregistrement, setEnregistrement] = useState(false)
  const { data, isLoading } = useQuery({ queryKey: ['calendrier-scolaire'], queryFn: fetchCalendrierScolaire })
  const { data: classes = [] } = useQuery({ queryKey: ['classes'], queryFn: fetchClasses })

  const niveaux = classes.filter((classe, index, liste) => classe.niveau_id && liste.findIndex((item) => item.niveau_id === classe.niveau_id) === index)
  const sousSystemes = classes.filter((classe, index, liste) => classe.sous_systeme_id && liste.findIndex((item) => item.sous_systeme_id === classe.sous_systeme_id) === index)

  const enregistrer = async () => {
    if (!date) return
    setEnregistrement(true)
    try {
      await enregistrerRegleCalendrier({
        date,
        est_ouvert: ouvert,
        motif: motif || null,
        classe_id: portee === 'classe' ? Number(cible) : null,
        niveau_id: portee === 'niveau' ? Number(cible) : null,
        sous_systeme_id: portee === 'sous_systeme' ? Number(cible) : null,
      })
      await queryClient.invalidateQueries({ queryKey: ['calendrier-scolaire'] })
      succes(ouvert ? 'Jour rouvert et progression réajustée.' : 'Jour déclaré sans classe et progression réajustée.')
      setMotif('')
    } catch (error) {
      alerteErreur((error as ApiError).message)
    } finally {
      setEnregistrement(false)
    }
  }

  const supprimer = async (id: number) => {
    try {
      await supprimerRegleCalendrier(id)
      await queryClient.invalidateQueries({ queryKey: ['calendrier-scolaire'] })
      succes('Règle supprimée : le jour est rouvert.')
    } catch (error) {
      alerteErreur((error as ApiError).message)
    }
  }

  return (
    <div className="flex flex-col gap-5">
      <PageHeader titre="Calendrier annuel" sousTitre="Jours ouverts et jours sans classe pour la progression pédagogique." icon={CalendarDays} />
      {isLoading ? <Spinner /> : (
        <>
          <section className="grid gap-4 rounded-2xl border border-navy-100 bg-white p-5 shadow-soft lg:grid-cols-[1.2fr_1fr_1fr_1.2fr_auto] lg:items-end">
            <label className="flex flex-col gap-1.5 text-sm font-semibold text-navy-700">Date
              <input type="date" min={data?.annee.date_debut} max={data?.annee.date_fin} value={date} onChange={(event) => setDate(event.target.value)} className="rounded-xl border border-navy-200 px-3 py-2.5 font-normal text-navy-800 focus:border-navy-400 focus:outline-none" />
            </label>
            <label className="flex flex-col gap-1.5 text-sm font-semibold text-navy-700">Portée
              <select value={portee} onChange={(event) => { setPortee(event.target.value); setCible('') }} className="rounded-xl border border-navy-200 bg-white px-3 py-2.5 font-normal text-navy-800 focus:border-navy-400 focus:outline-none">
                <option value="ecole">Toute l’école</option><option value="sous_systeme">Sous-système</option><option value="niveau">Niveau</option><option value="classe">Classe</option>
              </select>
            </label>
            <label className="flex flex-col gap-1.5 text-sm font-semibold text-navy-700">Cible
              <select disabled={portee === 'ecole'} value={cible} onChange={(event) => setCible(event.target.value)} className="rounded-xl border border-navy-200 bg-white px-3 py-2.5 font-normal text-navy-800 disabled:bg-navy-50 focus:border-navy-400 focus:outline-none">
                <option value="">Choisir…</option>
                {portee === 'classe' && classes.map((classe) => <option key={classe.id} value={classe.id}>{classe.nom}</option>)}
                {portee === 'niveau' && niveaux.map((classe) => <option key={classe.niveau_id} value={classe.niveau_id}>{classe.niveau?.name_fr ?? classe.niveau?.code}</option>)}
                {portee === 'sous_systeme' && sousSystemes.map((classe) => <option key={classe.sous_systeme_id} value={classe.sous_systeme_id!}>{classe.sous_systeme?.nom}</option>)}
              </select>
            </label>
            <label className="flex flex-col gap-1.5 text-sm font-semibold text-navy-700">Motif
              <input value={motif} onChange={(event) => setMotif(event.target.value)} placeholder="Fête, examen, fermeture…" className="rounded-xl border border-navy-200 px-3 py-2.5 font-normal text-navy-800 focus:border-navy-400 focus:outline-none" />
            </label>
            <div className="flex gap-2">
              <Button type="button" variant={ouvert ? 'secondary' : 'danger'} onClick={enregistrer} disabled={!date || (portee !== 'ecole' && !cible) || enregistrement}><Save className="h-4 w-4" />{ouvert ? 'Rouvrir' : 'Fermer'}</Button>
              <Button type="button" variant="ghost" onClick={() => setOuvert(!ouvert)} title="Basculer entre fermeture et réouverture"><RotateCcw className="h-4 w-4" /></Button>
            </div>
          </section>
          <div className="flex items-center justify-between"><h2 className="font-display text-lg font-bold text-navy-900">{data?.annee.libelle}</h2><span className="text-sm text-navy-400">Les changements recalculent les dates prévues.</span></div>
          {data?.regles.length ? <div className="overflow-hidden rounded-2xl border border-navy-100 bg-white shadow-soft"><table className="w-full text-left text-sm"><thead className="bg-navy-50 text-xs uppercase tracking-wide text-navy-500"><tr><th className="px-4 py-3">Date</th><th className="px-4 py-3">Portée</th><th className="px-4 py-3">État</th><th className="px-4 py-3">Motif</th><th className="px-4 py-3" /></tr></thead><tbody className="divide-y divide-navy-100">{data.regles.map((regle) => <tr key={regle.id}><td className="px-4 py-3 font-semibold text-navy-800">{regle.date}</td><td className="px-4 py-3 text-navy-600">{regle.classe ?? regle.niveau ?? regle.sous_systeme ?? 'Toute l’école'}</td><td className="px-4 py-3">{regle.est_ouvert ? <span className="text-emerald-600">Ouvert</span> : <span className="text-red-600">Sans classe</span>}</td><td className="px-4 py-3 text-navy-500">{regle.motif ?? '—'}</td><td className="px-4 py-3 text-right"><Button variant="ghost" size="sm" onClick={() => supprimer(regle.id)} title="Supprimer la règle"><Trash2 className="h-4 w-4 text-red-500" /></Button></td></tr>)}</tbody></table></div> : <EmptyState label="Aucune exception : les créneaux de l’emploi du temps sont ouverts." />}
        </>
      )}
    </div>
  )
}
