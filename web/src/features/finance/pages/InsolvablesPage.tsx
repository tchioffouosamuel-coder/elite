import { Fragment, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { AlertTriangle, ArrowLeft, FileDown, Wallet, Settings2, ChevronDown, ChevronUp } from 'lucide-react'
import { PageHeader } from '@/shared/ui/PageHeader'
import { StatCard } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Select } from '@/shared/ui/Field'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { ouvrirDocument } from '@/shared/lib/download'
import { fetchClasses, fetchSchools } from '@/features/classes/api'
import { fetchInsolvables, francs, type Insolvable } from '@/features/finance/api'
import { GestionInsolvableModal } from '@/features/finance/GestionInsolvableModal'

/**
 * Liste des insolvables, tous établissements confondus si le compte y a
 * accès — le seuil qui définit « insolvable » est celui réglé par école
 * (Paramètres > Finance), pas un chiffre fixe décidé ici.
 */
export function InsolvablesPage() {
  const navigate = useNavigate()
  const [schoolId, setSchoolId] = useState<number | ''>('')
  const [classeId, setClasseId] = useState<number | ''>('')
  const [detailOuvert, setDetailOuvert] = useState<number | null>(null)
  const [gestionEleve, setGestionEleve] = useState<Insolvable['eleve'] | null>(null)

  const { data: schools = [] } = useQuery({ queryKey: ['schools'], queryFn: fetchSchools })
  const { data: classes = [] } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses() })
  const classesDisponibles = schoolId === '' ? classes : classes.filter((c) => (c.school_id ?? c.school?.id) === schoolId)

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['finance-insolvables', schoolId, classeId],
    queryFn: () => fetchInsolvables({ school_id: schoolId || null, classe_id: classeId || null }),
  })

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
        titre="Élèves insolvables"
        sousTitre="Reste à payer au-delà du seuil réglé par école, détaillé par rubrique."
        icon={AlertTriangle}
        actions={
          <Button variant="secondary" onClick={() => ouvrirDocument('/finance/insolvables/pdf', pdfParams)}>
            <FileDown className="h-4 w-4" />
            PDF
          </Button>
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
            <StatCard label="Insolvables" value={data.totaux.effectif} icon={AlertTriangle} accent="gold" />
            <StatCard label="Total dû" value={francs(data.totaux.total_du)} icon={Wallet} accent="navy" />
            <StatCard label="Reste à recouvrer" value={francs(data.totaux.total_reste)} icon={Wallet} accent="red" />
          </div>

          {data.lignes.length === 0 ? (
            <EmptyState label="Aucun élève au-delà du seuil d'insolvabilité sur ce périmètre." />
          ) : (
            <div className="overflow-hidden rounded-2xl border border-navy-100/70 bg-white/75 shadow-card">
              <table className="w-full border-collapse text-sm">
                <thead>
                  <tr className="border-b border-navy-100 bg-cream-50 text-xs font-semibold uppercase tracking-wide text-navy-500">
                    <th className="px-4 py-2.5 text-left">Élève</th>
                    <th className="px-3 py-2.5 text-left">École</th>
                    <th className="px-3 py-2.5 text-left">Classe</th>
                    <th className="px-3 py-2.5 text-right">Reste à payer</th>
                    <th className="px-3 py-2.5 text-left">Moratoire</th>
                    <th className="px-3 py-2.5"></th>
                  </tr>
                </thead>
                <tbody>
                  {data.lignes.map((ligne) => {
                    const ouvert = detailOuvert === ligne.eleve.id
                    return (
                      <Fragment key={ligne.eleve.id}>
                        <tr className="border-b border-navy-50 hover:bg-cream-50/60">
                          <td className="px-4 py-2.5">
                            <div className="font-semibold text-navy-900">{ligne.eleve.nom_complet}</div>
                            <div className="text-xs text-navy-400">{ligne.eleve.matricule ?? '—'}</div>
                          </td>
                          <td className="px-3 py-2.5 text-navy-600">{ligne.school.name}</td>
                          <td className="px-3 py-2.5 text-navy-600">{ligne.eleve.classe ?? '—'}</td>
                          <td className="px-3 py-2.5 text-right font-semibold tabular-nums text-red-500">{francs(ligne.reste_a_payer)}</td>
                          <td className="px-3 py-2.5">
                            {ligne.moratoire ? (
                              <span className="rounded-full bg-green-50 px-2 py-0.5 text-xs font-semibold text-green-700 ring-1 ring-green-100">
                                Jusqu'au {new Date(ligne.moratoire.date_expiration).toLocaleDateString('fr-FR')}
                              </span>
                            ) : (
                              <span className="text-xs text-navy-300">—</span>
                            )}
                          </td>
                          <td className="px-3 py-2.5">
                            <div className="flex items-center justify-end gap-1">
                              <button
                                title="Détail par rubrique"
                                onClick={() => setDetailOuvert(ouvert ? null : ligne.eleve.id)}
                                className="rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-navy-700"
                              >
                                {ouvert ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                              </button>
                              <Button size="sm" variant="secondary" onClick={() => setGestionEleve(ligne.eleve)}>
                                <Settings2 className="h-3.5 w-3.5" />
                                Gérer
                              </Button>
                            </div>
                          </td>
                        </tr>
                        {ouvert && (
                          <tr className="border-b border-navy-50 bg-cream-50/40">
                            <td colSpan={6} className="px-4 py-3">
                              <ul className="flex flex-wrap gap-x-6 gap-y-1 text-xs text-navy-600">
                                {ligne.rubriques.filter((r) => r.reste > 0).map((r) => (
                                  <li key={r.cle + (r.dossier_frais_annexe_id ?? '')}>
                                    <span className="font-semibold">{r.libelle}</span> : {francs(r.reste)}
                                  </li>
                                ))}
                                {ligne.rubriques.every((r) => r.reste <= 0) && <li className="text-navy-400">Rien en attente sur ce dossier.</li>}
                              </ul>
                            </td>
                          </tr>
                        )}
                      </Fragment>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}
        </>
      )}

      {gestionEleve && (
        <GestionInsolvableModal
          eleveId={gestionEleve.id}
          eleveNom={gestionEleve.nom_complet}
          onClose={() => setGestionEleve(null)}
          onChange={() => refetch()}
        />
      )}
    </div>
  )
}
