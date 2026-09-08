import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { History, ArrowLeft, FileDown, Plus, Wallet, Users } from 'lucide-react'
import { PageHeader } from '@/shared/ui/PageHeader'
import { StatCard } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Select } from '@/shared/ui/Field'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { ouvrirDocument } from '@/shared/lib/download'
import { useAuthStore } from '@/shared/store/authStore'
import { fetchClasses, fetchSchools } from '@/features/classes/api'
import { fetchEleves } from '@/features/eleves/api'
import { fetchDettesAnterieuresListe, francs } from '@/features/finance/api'
import { CreerDetteAnterieureModal } from '@/features/finance/CreerDetteAnterieureModal'

/**
 * Vue caisse d'ensemble des reliquats d'années antérieures non soldés — qui
 * doit encore, et combien, indépendamment de la scolarité de l'année en
 * cours. La modale « Dette antérieure » de la caisse n'ouvrait qu'un
 * formulaire de saisie sans jamais montrer ce qui restait déjà en attente ;
 * cette page remplace ce raccourci par une liste exportable.
 */
export function DettesAnterieuresPage() {
  const navigate = useNavigate()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()

  const [schoolId, setSchoolId] = useState<number | ''>('')
  const [classeId, setClasseId] = useState<number | ''>('')
  const [detteModalOuvert, setDetteModalOuvert] = useState(false)

  const { data: schools = [] } = useQuery({ queryKey: ['schools'], queryFn: () => fetchSchools() })
  const { data: classes = [] } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses() })
  const classesDisponibles = schoolId === '' ? classes : classes.filter((c) => (c.school_id ?? c.school?.id) === schoolId)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['finance-dettes-anterieures', schoolId, classeId],
    queryFn: () => fetchDettesAnterieuresListe({ school_id: schoolId || null, classe_id: classeId || null }),
  })

  // Le sélecteur d'élève de la modale doit couvrir tout l'effectif, pas
  // seulement ceux qui ont déjà un reliquat — c'est justement là qu'on en
  // enregistre un nouveau.
  const { data: eleves } = useQuery({
    queryKey: ['eleves-options-dette'],
    queryFn: () => fetchEleves({ per_page: 1000 }),
    enabled: detteModalOuvert,
  })

  const rafraichir = () => queryClient.invalidateQueries({ queryKey: ['finance-dettes-anterieures'] })

  const pdfParams = { ...(schoolId ? { school_id: String(schoolId) } : {}), ...(classeId ? { classe_id: String(classeId) } : {}) }

  return (
    <div className="flex flex-col gap-5">
      <button
        onClick={() => navigate('/caisse')}
        className="inline-flex items-center gap-1.5 self-start text-sm font-medium text-navy-500 hover:text-navy-800"
      >
        <ArrowLeft className="h-4 w-4" />
        Retour à la caisse
      </button>

      <PageHeader
        titre="Dettes antérieures"
        sousTitre="Reliquats d'années antérieures non soldés, indépendamment de la scolarité en cours."
        icon={History}
        actions={
          <>
            {can('finance.manage') && (
              <Button variant="secondary" onClick={() => setDetteModalOuvert(true)}>
                <Plus className="h-4 w-4" />
                Enregistrer une dette
              </Button>
            )}
            <Button variant="secondary" onClick={() => ouvrirDocument('/finance/dettes-anterieures/pdf', pdfParams)}>
              <FileDown className="h-4 w-4" />
              PDF
            </Button>
          </>
        }
      />

      <div className="flex flex-wrap gap-2">
        {schools.length > 1 && (
          <div className="w-full sm:w-56">
            <Select
              value={schoolId}
              onChange={(e) => {
                setSchoolId(e.target.value ? Number(e.target.value) : '')
                setClasseId('')
              }}
            >
              <option value="">Toutes les écoles</option>
              {schools.map((s) => (
                <option key={s.id} value={s.id}>{s.name}</option>
              ))}
            </Select>
          </div>
        )}
        <div className="w-full sm:w-56">
          <Select value={classeId} onChange={(e) => setClasseId(e.target.value ? Number(e.target.value) : '')}>
            <option value="">Toutes les classes</option>
            {classesDisponibles.map((c) => (
              <option key={c.id} value={c.id}>{c.nom}</option>
            ))}
          </Select>
        </div>
      </div>

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : (
        <>
          <div className="grid gap-3 sm:grid-cols-3">
            <StatCard label="Élèves concernés" value={data.totaux.effectif} icon={Users} accent="gold" />
            <StatCard label="Total des reliquats" value={francs(data.totaux.total_montant)} icon={Wallet} accent="navy" />
            <StatCard label="Reste à recouvrer" value={francs(data.totaux.total_reste)} icon={Wallet} accent="red" />
          </div>

          {data.lignes.length === 0 ? (
            <EmptyState label="Aucun reliquat d'année antérieure en attente sur ce périmètre." />
          ) : (
            <div className="overflow-hidden rounded-2xl border border-navy-100/70 bg-white/75 shadow-card">
              <table className="w-full border-collapse text-sm">
                <thead>
                  <tr className="border-b border-navy-100 bg-cream-50 text-xs font-semibold uppercase tracking-wide text-navy-500">
                    <th className="px-4 py-2.5 text-left">Élève</th>
                    <th className="px-3 py-2.5 text-left">École</th>
                    <th className="px-3 py-2.5 text-left">Classe</th>
                    <th className="px-3 py-2.5 text-right">Reliquat</th>
                    <th className="px-3 py-2.5 text-right">Versé</th>
                    <th className="px-3 py-2.5 text-right">Reste</th>
                  </tr>
                </thead>
                <tbody>
                  {data.lignes.map((ligne) => (
                    <tr key={ligne.eleve.id} className="border-b border-navy-50 hover:bg-cream-50/60">
                      <td className="px-4 py-2.5">
                        <div className="font-semibold text-navy-900">{ligne.eleve.nom_complet}</div>
                        <div className="text-xs text-navy-400">{ligne.eleve.matricule ?? '—'}</div>
                      </td>
                      <td className="px-3 py-2.5 text-navy-600">{ligne.school.name}</td>
                      <td className="px-3 py-2.5 text-navy-600">{ligne.eleve.classe ?? '—'}</td>
                      <td className="px-3 py-2.5 text-right tabular-nums text-navy-600">{francs(ligne.montant)}</td>
                      <td className="px-3 py-2.5 text-right tabular-nums text-navy-600">{francs(ligne.paye)}</td>
                      <td className="px-3 py-2.5 text-right">
                        <span className="font-semibold tabular-nums text-red-600">{francs(ligne.reste)}</span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </>
      )}

      {detteModalOuvert && (
        <CreerDetteAnterieureModal
          eleves={(eleves?.items ?? []).map((e) => ({ id: e.id, nom_complet: e.nom_complet, matricule: e.matricule }))}
          onClose={() => setDetteModalOuvert(false)}
          onCreated={rafraichir}
        />
      )}
    </div>
  )
}
