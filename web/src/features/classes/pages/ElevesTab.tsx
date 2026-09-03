import { Fragment, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { ChevronRight, FileDown, FileText, FileSpreadsheet, AlertTriangle, Bus, Clock } from 'lucide-react'
import { fetchEleves, fetchTableauAges, type Eleve } from '@/features/eleves/api'
import { ouvrirBulletin } from '@/features/resultats/api'
import { fetchInsolvables, francs } from '@/features/finance/api'
import { fetchElevesTransport, LIBELLES_OPTION_TRAJET } from '@/features/bus/api'
import { estSecondaire as estSecondaireHelper, type TypeEcole } from '@/shared/lib/ecole'
import { telechargerFichier, ouvrirDocument } from '@/shared/lib/download'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Card } from '@/shared/ui/Card'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { useAuthStore } from '@/shared/store/authStore'

export function ElevesTab({ classeId, ecoleType }: { classeId: number; ecoleType?: TypeEcole | null }) {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const estEnseignant = useAuthStore((s) => s.user?.est_enseignant ?? false)
  const estSecondaire = estSecondaireHelper(ecoleType)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['eleves', { classe_id: classeId }],
    queryFn: () => fetchEleves({ classe_id: classeId, per_page: 1000 }),
  })

  // Qui a souscrit au bus — surtout utile au secondaire, où l'effectif d'une
  // classe n'est pas suivi par un seul titulaire comme au primaire.
  const { data: transport } = useQuery({
    queryKey: ['eleves-transport', { classe_id: classeId }],
    queryFn: () => fetchElevesTransport(classeId),
    enabled: can('bus.view'),
  })
  const busParEleve = new Map((transport ?? []).map((e) => [e.id, e.bus]))

  const colonnes: Colonne<Eleve>[] = [
    {
      cle: 'matricule',
      entete: t('eleves.matricule'),
      valeur: (e) => e.matricule,
      cellule: (e) => <span className="font-mono text-xs">{e.matricule ?? '—'}</span>,
    },
    {
      cle: 'nom',
      entete: t('eleves.nom_complet'),
      valeur: (e) => e.nom_complet,
      cellule: (e) => <span className="font-semibold text-navy-900">{e.nom_complet}</span>,
    },
    {
      cle: 'sexe',
      entete: t('eleves.sexe'),
      valeur: (e) => e.sexe,
      cellule: (e) => (
        <Badge tone={e.sexe === 'F' ? 'gold' : 'neutral'}>{e.sexe === 'F' ? t('eleves.feminin') : t('eleves.masculin')}</Badge>
      ),
    },
    {
      cle: 'tuteur',
      entete: t('eleves.tuteur'),
      valeur: (e) => e.tuteurs?.[0]?.nom_complet,
      cellule: (e) => (
        <span className="text-navy-500">{e.tuteurs?.[0] ? `${e.tuteurs[0].nom_complet} · ${e.tuteurs[0].telephone ?? '—'}` : '—'}</span>
      ),
      masquerMobile: true,
    },
    ...(can('bus.view')
      ? [
        {
          cle: 'bus',
          entete: 'Bus',
          valeur: (e) => busParEleve.get(e.id)?.trajet.nom,
          cellule: (e) => {
            const bus = busParEleve.get(e.id)
            return bus ? (
              <Badge tone="blue">
                <Bus className="h-3 w-3" />
                {bus.trajet.nom}
              </Badge>
            ) : (
              <span className="text-navy-300">—</span>
            )
          },
          masquerMobile: true,
        } satisfies Colonne<Eleve>,
      ]
      : []),
    {
      cle: 'documents',
      entete: t('common.actions'),
      cellule: (e) => (
        <div className="flex items-center gap-1">
          {!estEnseignant && (
            <>
              <button
                onClick={() => ouvrirBulletin(e.id)}
                title={t('resultats.bulletin')}
                className="flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold text-navy-600 transition-colors hover:bg-cream-100"
              >
                <FileDown className="h-3.5 w-3.5" />
                PDF
              </button>
              <button
                onClick={() => telechargerFichier(`/eleves/${e.id}/attestation-scolarite`, undefined, 'attestation.docx')}
                title={t('export.attestation')}
                className="flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold text-navy-600 transition-colors hover:bg-cream-100"
              >
                <FileText className="h-3.5 w-3.5" />
                Word
              </button>
            </>
          )}
        </div>
      ),
    },
  ]

  if (isLoading) return <Spinner />
  if (isError || !data) return <ErrorState />

  return (
    <div className="flex flex-col gap-5">
      <DataTable
        colonnes={colonnes}
        lignes={data.items}
        cleLigne={(e) => e.id}
        placeholderRecherche={t('classes.eleves_tab_search_placeholder')}
        messageVide={t('classes.eleves_tab_empty')}
        largeurMin={760}
        outils={
          <>
            <Button variant="secondary" size="sm" onClick={() => ouvrirDocument(`/classes/${classeId}/eleves/pdf`)}>
              <FileDown className="h-4 w-4" />
              PDF
            </Button>
            <Button
              variant="secondary"
              size="sm"
              onClick={() => telechargerFichier(`/classes/${classeId}/eleves/word`, undefined, 'liste-eleves.docx')}
            >
              <FileText className="h-4 w-4" />
              Word
            </Button>
            <Button
              variant="secondary"
              size="sm"
              onClick={() => telechargerFichier('/eleves/export', { classe_id: classeId }, 'liste-eleves.xlsx')}
            >
              <FileSpreadsheet className="h-4 w-4" />
              Excel
            </Button>
          </>
        }
      />

      <div className="grid gap-5 lg:grid-cols-2">
        <TableauAgesClasse classeId={classeId} />
        <InsolvablesClasse classeId={classeId} />
      </div>

      {/* Détail du transport, réservé au primaire/maternelle : un seul
          titulaire suit sa classe au jour le jour et veut savoir qui part à
          quelle heure — au secondaire, la colonne Bus ci-dessus suffit. */}
      {!estSecondaire && can('bus.view') && <TransportClasse classeId={classeId} />}
    </div>
  )
}

function TableauAgesClasse({ classeId }: { classeId: number }) {
  const [ageOuvert, setAgeOuvert] = useState<string | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['eleves-tableau-ages', { classe_id: classeId }],
    queryFn: () => fetchTableauAges({ classe_id: classeId }),
  })

  return (
    <Card>
      <div className="mb-3 flex items-center justify-between gap-2">
        <h3 className="font-display text-sm font-bold text-navy-900">Pyramide des âges</h3>
        <button
          onClick={() => ouvrirDocument('/eleves/tableau-ages/pdf', { classe_id: classeId })}
          title="Télécharger en PDF"
          className="flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold text-navy-600 transition-colors hover:bg-cream-100"
        >
          <FileDown className="h-3.5 w-3.5" />
          PDF
        </button>
      </div>
      {isLoading ? (
        <Spinner />
      ) : !data || data.length === 0 ? (
        <p className="py-4 text-center text-sm text-navy-400">Aucun élève avec une date de naissance renseignée.</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full min-w-[320px] border-collapse text-sm">
            <thead>
              <tr className="border-b border-navy-100 text-xs font-semibold uppercase tracking-wide text-navy-500">
                <th className="py-2 text-left">Âge</th>
                <th className="py-2 text-right">Garçons</th>
                <th className="py-2 text-right">Filles</th>
                <th className="py-2 text-right">Effectif</th>
              </tr>
            </thead>
            <tbody>
              {data.map((ligne) => {
                const ouvert = ageOuvert === ligne.age

                return (
                  <Fragment key={ligne.age}>
                    {/* La ligne entière déplie : la pyramide appelle
                        immédiatement la question « lesquels ? ». */}
                    <tr
                      onClick={() => setAgeOuvert(ouvert ? null : ligne.age)}
                      className="cursor-pointer border-b border-navy-50 hover:bg-cream-50/70"
                    >
                      <td className="py-1.5 text-left font-semibold text-navy-800">
                        <span className="flex items-center gap-1.5">
                          <ChevronRight
                            className={`h-3.5 w-3.5 flex-none text-navy-300 transition-transform ${ouvert ? 'rotate-90' : ''}`}
                          />
                          {ligne.age} ans
                        </span>
                      </td>
                      <td className="py-1.5 text-right tabular-nums">{ligne.garcons}</td>
                      <td className="py-1.5 text-right tabular-nums">{ligne.filles}</td>
                      <td className="py-1.5 text-right tabular-nums">{ligne.total}</td>
                    </tr>

                    {ouvert && (
                      <tr className="border-b border-navy-50 bg-cream-50/60">
                        <td colSpan={4} className="px-2 py-2">
                          <ul className="flex flex-col divide-y divide-navy-100/60">
                            {ligne.eleves.map((eleve) => (
                              <li
                                key={eleve.id}
                                className="flex flex-wrap items-center justify-between gap-x-3 gap-y-0.5 py-1.5 text-xs"
                              >
                                <span className="font-medium text-navy-800">{eleve.nom_complet}</span>
                                <span className="flex items-center gap-2 text-navy-400">
                                  {eleve.classe && <span>{eleve.classe}</span>}
                                  <span>{eleve.sexe === 'F' ? 'F' : 'M'}</span>
                                  {eleve.date_naissance && <span>{eleve.date_naissance}</span>}
                                </span>
                              </li>
                            ))}
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
    </Card>
  )
}

/**
 * Qui prend le bus dans cette classe, groupé par heure de passage — la
 * question concrète d'un titulaire de primaire/maternelle en fin de journée :
 * quels enfants doivent être prêts, et à quelle heure.
 */
function TransportClasse({ classeId }: { classeId: number }) {
  const { data, isLoading } = useQuery({
    queryKey: ['eleves-transport', { classe_id: classeId }],
    queryFn: () => fetchElevesTransport(classeId),
  })

  const embarques = (data ?? []).filter((e) => e.bus)

  const parHeure = new Map<string, typeof embarques>()
  for (const eleve of embarques) {
    const heure = eleve.bus?.arret?.heure_passage ?? 'Heure non renseignée'
    parHeure.set(heure, [...(parHeure.get(heure) ?? []), eleve])
  }
  const heures = [...parHeure.keys()].sort()

  return (
    <Card>
      <h3 className="mb-3 flex items-center gap-1.5 font-display text-sm font-bold text-navy-900">
        <Bus className="h-4 w-4 text-navy-500" />
        Transport scolaire
      </h3>
      {isLoading ? (
        <Spinner />
      ) : embarques.length === 0 ? (
        <p className="py-4 text-center text-sm text-navy-400">Aucun élève de cette classe n'a souscrit au bus.</p>
      ) : (
        <div className="flex flex-col gap-4">
          {heures.map((heure) => (
            <div key={heure}>
              <div className="mb-1.5 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-navy-500">
                <Clock className="h-3.5 w-3.5" />
                {heure === 'Heure non renseignée' ? heure : heure.slice(0, 5)}
              </div>
              <ul className="flex flex-col divide-y divide-navy-50">
                {parHeure.get(heure)!.map((e) => (
                  <li key={e.id} className="flex flex-wrap items-center justify-between gap-2 py-1.5 text-sm">
                    <span className="font-medium text-navy-800">{e.nom_complet}</span>
                    <span className="text-xs text-navy-400">
                      {e.bus?.trajet.nom} · {e.bus?.arret?.nom ?? '—'} ·{' '}
                      {e.bus && LIBELLES_OPTION_TRAJET[e.bus.option_trajet]}
                    </span>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
      )}
    </Card>
  )
}

/** Réservé à qui voit déjà les chiffres de la caisse : un enseignant n'a pas à connaître le reste à payer de ses élèves. */
function InsolvablesClasse({ classeId }: { classeId: number }) {
  const can = useAuthStore((s) => s.can)
  const { data, isLoading } = useQuery({
    queryKey: ['finance-insolvables', { classe_id: classeId }],
    queryFn: () => fetchInsolvables({ classe_id: classeId }),
    enabled: can('finance.view'),
  })

  if (!can('finance.view')) return null

  return (
    <Card>
      <div className="mb-3 flex items-center justify-between gap-2">
        <h3 className="flex items-center gap-1.5 font-display text-sm font-bold text-navy-900">
          <AlertTriangle className="h-4 w-4 text-gold-600" />
          Élèves insolvables
        </h3>
        <button
          onClick={() => ouvrirDocument('/finance/insolvables/pdf', { classe_id: classeId })}
          title="Télécharger en PDF"
          className="flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold text-navy-600 transition-colors hover:bg-cream-100"
        >
          <FileDown className="h-3.5 w-3.5" />
          PDF
        </button>
      </div>
      {isLoading ? (
        <Spinner />
      ) : !data || data.lignes.length === 0 ? (
        <p className="py-4 text-center text-sm text-navy-400">Aucun élève au-delà du seuil d'insolvabilité.</p>
      ) : (
        <ul className="flex flex-col divide-y divide-navy-50 text-sm">
          {data.lignes.map((ligne) => (
            <li key={ligne.eleve.id} className="flex items-center justify-between gap-2 py-2">
              <span className="font-medium text-navy-800">{ligne.eleve.nom_complet}</span>
              <span className="font-semibold tabular-nums text-red-500">{francs(ligne.reste_a_payer)}</span>
            </li>
          ))}
        </ul>
      )}
    </Card>
  )
}
