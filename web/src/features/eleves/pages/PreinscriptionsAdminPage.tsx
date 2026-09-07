import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ClipboardCheck, Search, Plus, UserX } from 'lucide-react'
import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'
import { francs } from '@/features/finance/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Input, Select } from '@/shared/ui/Field'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { ImportExportBar } from '@/shared/ui/ImportExportBar'
import { Tabs } from '@/shared/ui/Tabs'

type Statut = 'en_attente' | 'validee' | 'rejetee'
type Type = 'existant' | 'nouveau'

export interface PreinscriptionResume {
  id: number
  type: Type
  statut: Statut
  tuteur: { id: number; nom_complet: string; telephone: string | null; email: string | null } | null
  eleve: { id: number; nom_complet: string; matricule: string | null } | null
  nom_propose: string | null
  montant_verser: number | null
  versement_id: number | null
  motif_rejet: string | null
  created_at: string
  traite_le: string | null
}

async function fetchPreinscriptions(statut: Statut | ''): Promise<PreinscriptionResume[]> {
  const { data } = await http.get<ApiResponse<PreinscriptionResume[]>>('/preinscriptions', { params: { statut: statut || undefined } })
  return data.data
}

export interface EleveNonInscrit {
  id: number
  matricule: string | null
  nom_complet: string
  classe: string | null
  tuteur: string | null
  telephone: string | null
}

async function fetchNonInscrits(): Promise<EleveNonInscrit[]> {
  const { data } = await http.get<ApiResponse<EleveNonInscrit[]>>('/preinscriptions/non-inscrits')
  return data.data
}

export const STATUT_TONE = { en_attente: 'gold', validee: 'green', rejetee: 'red' } as const
export const STATUT_LABEL = { en_attente: 'En attente', validee: 'Validée', rejetee: 'Rejetée' } as const

export const CHAMPS_ELEVE: [string, string][] = [
  ['nom_complet', 'Nom complet'],
  ['sexe', 'Sexe'],
  ['date_naissance', 'Date de naissance'],
  ['lieu_naissance', 'Lieu de naissance'],
  ['adresse', 'Adresse'],
  ['numero_acte_naissance', "N° acte de naissance"],
  ['lieu_delivrance_acte', 'Lieu de délivrance'],
  ['officier_etat_civil', "Officier d'état civil"],
  ['groupe_sanguin', 'Groupe sanguin'],
  ['aptitude', 'Aptitude'],
  ['allergies', 'Allergies'],
  ['situation_sanitaire', 'Situation sanitaire'],
]

/** File d'attente des préinscriptions déposées par les parents — à examiner, valider ou rejeter (détail sur sa propre page, cf. `PreinscriptionDetailPage`). */
export function PreinscriptionsAdminPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [onglet, setOnglet] = useState<'preinscriptions' | 'non-inscrits'>('preinscriptions')
  const [statut, setStatut] = useState<Statut | ''>('en_attente')
  const [recherche, setRecherche] = useState('')

  const { data, isLoading, isError } = useQuery({
    queryKey: ['preinscriptions-admin', statut],
    queryFn: () => fetchPreinscriptions(statut),
    enabled: onglet === 'preinscriptions',
  })

  const { data: nonInscrits, isLoading: nonInscritsLoading, isError: nonInscritsError } = useQuery({
    queryKey: ['preinscriptions-non-inscrits'],
    queryFn: fetchNonInscrits,
    enabled: onglet === 'non-inscrits',
  })

  const donneesFiltrees = (data ?? []).filter((p) => {
    const q = recherche.trim().toLowerCase()
    if (!q) return true
    return [p.eleve?.nom_complet, p.nom_propose, p.eleve?.matricule, p.tuteur?.nom_complet, p.tuteur?.telephone, p.tuteur?.email]
      .filter(Boolean)
      .some((v) => String(v).toLowerCase().includes(q))
  })

  const nonInscritsFiltres = (nonInscrits ?? []).filter((e) => {
    const q = recherche.trim().toLowerCase()
    if (!q) return true
    return [e.nom_complet, e.matricule, e.tuteur, e.telephone].filter(Boolean).some((v) => String(v).toLowerCase().includes(q))
  })

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Préinscriptions"
        sousTitre="Confirmation de présence pour l'année scolaire en cours."
        icon={ClipboardCheck}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <Input
              value={recherche}
              onChange={(e) => setRecherche(e.target.value)}
              placeholder="Rechercher un élève, un tuteur…"
              className="w-56"
              icon={Search}
            />
            {onglet === 'preinscriptions' && (
              <Select value={statut} onChange={(e) => setStatut(e.target.value as Statut | '')} className="w-48">
                <option value="en_attente">En attente</option>
                <option value="validee">Validées</option>
                <option value="rejetee">Rejetées</option>
                <option value="">Toutes</option>
              </Select>
            )}
            <ImportExportBar
              titreImport="Importer des préinscriptions"
              importUrl="preinscriptions/import"
              exportUrl={onglet === 'non-inscrits' ? 'preinscriptions/non-inscrits/export' : 'preinscriptions/export'}
              modeleUrl="preinscriptions/modele"
              colonnes={[
                'IDEleves',
                'nom_eleves',
                'sexe_eleves',
                'ddn_eleves',
                'Nom_classe',
                'nationalité',
                'lieu_naiss',
                'nom_parents',
                'tel_pere',
                'nom_mere',
                'tel_mere',
                'tel_autre',
                'frais_scolarite',
                'MONTANT_SCOLARITE',
                'remise_scol',
                'DEBTS',
              ]}
              nomFichier={onglet === 'non-inscrits' ? 'eleves-non-inscrits' : 'preinscriptions'}
              onImported={() => {
                queryClient.invalidateQueries({ queryKey: ['preinscriptions-admin'] })
                queryClient.invalidateQueries({ queryKey: ['preinscriptions-non-inscrits'] })
              }}
            />
            <Link to="/preinscriptions/nouvelle">
              <Button type="button">
                <Plus className="h-4 w-4" />
                Nouvelle préinscription
              </Button>
            </Link>
          </div>
        }
      />

      <Tabs
        active={onglet}
        onChange={(cle) => setOnglet(cle as 'preinscriptions' | 'non-inscrits')}
        tabs={[
          { key: 'preinscriptions', label: 'Préinscriptions' },
          { key: 'non-inscrits', label: nonInscrits?.length ? `Non inscrits (${nonInscrits.length})` : 'Non inscrits' },
        ]}
      />

      {onglet === 'non-inscrits' ? (
        nonInscritsLoading ? (
          <Spinner />
        ) : nonInscritsError || !nonInscrits ? (
          <ErrorState />
        ) : nonInscrits.length === 0 ? (
          <EmptyState label="Tous les anciens élèves se sont réinscrits." />
        ) : nonInscritsFiltres.length === 0 ? (
          <EmptyState label="Aucun élève ne correspond à cette recherche." />
        ) : (
          <div className="flex flex-col gap-3">
            {nonInscritsFiltres.map((e) => (
              <Card key={e.id}>
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <p className="font-display text-base font-bold text-navy-900">{e.nom_complet}</p>
                    <p className="mt-0.5 text-xs text-navy-400">
                      {e.matricule} · {e.classe ?? 'Sans classe'} · {e.tuteur ?? 'Aucun tuteur'} {e.telephone ? `(${e.telephone})` : ''}
                    </p>
                  </div>
                  <Badge tone="gold">
                    <UserX className="h-3.5 w-3.5" />
                    Non réinscrit
                  </Badge>
                </div>
              </Card>
            ))}
          </div>
        )
      ) : isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : data.length === 0 ? (
        <EmptyState label="Aucune préinscription dans cet état." />
      ) : donneesFiltrees.length === 0 ? (
        <EmptyState label="Aucune préinscription ne correspond à cette recherche." />
      ) : (
        <div className="flex flex-col gap-3">
          {donneesFiltrees.map((p) => (
            <div key={p.id} onClick={() => navigate(`/preinscriptions/${p.id}`)} className="cursor-pointer">
              <Card className="transition-shadow hover:shadow-lifted">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <p className="font-display text-base font-bold text-navy-900">{p.eleve?.nom_complet || p.nom_propose}</p>
                    <p className="mt-0.5 text-xs text-navy-400">
                      {p.type === 'nouveau' ? 'Nouvelle inscription' : 'Révision de fiche'} · {p.tuteur?.nom_complet} (
                      {p.tuteur?.telephone || p.tuteur?.email}) · {new Date(p.created_at).toLocaleDateString('fr-FR')}
                    </p>
                    {p.montant_verser ? <p className="mt-1 text-xs text-navy-500">Versement proposé : {francs(p.montant_verser)}</p> : null}
                  </div>
                  <Badge tone={STATUT_TONE[p.statut]}>{STATUT_LABEL[p.statut]}</Badge>
                </div>
              </Card>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
