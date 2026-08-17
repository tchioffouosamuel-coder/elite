import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ClipboardCheck, UserCheck } from 'lucide-react'
import { fetchClasses, fetchMaClasse } from '@/features/classes/api'
import {
  enregistrerAppel,
  fetchAppel,
  fetchSeances,
  type LigneAppel,
  type Seance,
} from '@/features/emploiDuTemps/api'
import { useAuthStore } from '@/shared/store/authStore'
import { estSecondaire } from '@/shared/lib/ecole'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { Select } from '@/shared/ui/Field'
import { Modal } from '@/shared/ui/Modal'
import { EmptyState, Spinner } from '@/shared/ui/Feedback'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { PageHeader } from '@/shared/ui/PageHeader'
import { erreur, succes } from '@/shared/lib/alertes'

const STATUTS: { valeur: LigneAppel['statut']; libelle: string }[] = [
  { valeur: 'present', libelle: 'Présent' },
  { valeur: 'absent', libelle: 'Absent' },
  { valeur: 'retard', libelle: 'Retard' },
  { valeur: 'renvoye', libelle: 'Renvoyé' },
]

export function SeancesPage() {
  const can = useAuthStore((s) => s.can)
  const estEnseignant = useAuthStore((s) => s.user?.est_enseignant ?? false)
  const [classeId, setClasseId] = useState<number | ''>('')
  const [seanceAppel, setSeanceAppel] = useState<Seance | null>(null)

  // Au primaire et à la maternelle, un enseignant est titulaire d'une seule
  // classe : pas de sélecteur à parcourir, les séances de sa classe uniquement.
  const restreintATitulaire = estEnseignant && !estSecondaire()

  const { data: classes } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses(), enabled: !restreintATitulaire })
  const { data: maClasse, isLoading: maClasseEnChargement } = useQuery({
    queryKey: ['ma-classe'],
    queryFn: fetchMaClasse,
    enabled: restreintATitulaire,
  })

  useEffect(() => {
    if (restreintATitulaire && maClasse && classeId === '') {
      setClasseId(maClasse.id)
    }
  }, [restreintATitulaire, maClasse, classeId])

  const classeActive = classeId ? Number(classeId) : null

  const { data: seances, isLoading } = useQuery({
    queryKey: ['seances', classeActive],
    queryFn: () => fetchSeances(classeActive!),
    enabled: classeActive !== null,
  })

  const colonnes: Colonne<Seance>[] = [
    {
      cle: 'date',
      entete: 'Date',
      valeur: (s) => s.date_seance,
      cellule: (s) => <span className="font-medium">{new Date(s.date_seance).toLocaleDateString('fr-FR')}</span>,
    },
    {
      cle: 'horaire',
      entete: 'Horaire',
      valeur: (s) => s.heure_debut,
      cellule: (s) => `${s.heure_debut}–${s.heure_fin}`,
    },
    {
      cle: 'matiere',
      entete: 'Matière',
      valeur: (s) => s.matiere,
      cellule: (s) => <span className="font-semibold text-navy-900">{s.matiere}</span>,
    },
    {
      cle: 'enseignant',
      entete: 'Enseignant',
      valeur: (s) => s.enseignant,
      cellule: (s) => s.enseignant ?? '—',
      masquerMobile: true,
    },
    {
      cle: 'statut',
      entete: 'Statut',
      valeur: (s) => s.statut,
      cellule: (s) => (
        <Badge tone={s.statut === 'effectuee' ? 'green' : s.statut === 'annulee' ? 'red' : 'neutral'}>
          {s.statut === 'effectuee' ? 'Effectuée' : s.statut === 'annulee' ? 'Annulée' : 'Prévue'}
        </Badge>
      ),
    },
    {
      cle: 'absents',
      entete: 'Absents',
      valeur: (s) => s.absents,
      cellule: (s) => (s.absents > 0 ? <Badge tone="red">{s.absents}</Badge> : '—'),
    },
    {
      cle: 'actions',
      entete: '',
      cellule: (s) =>
        can('appel.manage') ? (
          <Button size="sm" variant="secondary" onClick={() => setSeanceAppel(s)}>
            <UserCheck className="h-4 w-4" />
            Faire l'appel
          </Button>
        ) : null,
    },
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader titre="Séances & appel" icon={ClipboardCheck} />

      {restreintATitulaire ? (
        maClasse && (
          <div className="flex flex-col gap-1.5">
            <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">Classe</span>
            <span className="text-sm font-semibold text-navy-800">{maClasse.nom}</span>
          </div>
        )
      ) : (
        <Select
          label="Classe"
          value={classeId}
          onChange={(e) => setClasseId(e.target.value ? Number(e.target.value) : '')}
          className="max-w-xs"
        >
          <option value="">Sélectionner une classe…</option>
          {classes?.map((c) => (
            <option key={c.id} value={c.id}>
              {c.nom}
            </option>
          ))}
        </Select>
      )}

      {restreintATitulaire && maClasseEnChargement ? (
        <Spinner />
      ) : restreintATitulaire && !maClasse ? (
        <Card>
          <EmptyState label="Aucune classe ne vous est confiée pour le moment." />
        </Card>
      ) : !classeActive ? (
        <Card>
          <EmptyState label="Choisissez une classe pour afficher ses séances." />
        </Card>
      ) : isLoading ? (
        <Spinner />
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={seances ?? []}
          cleLigne={(s) => s.id}
          placeholderRecherche="Rechercher une matière, une date…"
          messageVide="Aucune séance. Générez-les depuis l'emploi du temps de la classe."
          largeurMin={820}
        />
      )}

      {seanceAppel && (
        <AppelModal seance={seanceAppel} classeId={classeActive!} onClose={() => setSeanceAppel(null)} />
      )}
    </div>
  )
}

function AppelModal({ seance, classeId, onClose }: { seance: Seance; classeId: number; onClose: () => void }) {
  const queryClient = useQueryClient()
  const { data, isLoading } = useQuery({ queryKey: ['appel', seance.id], queryFn: () => fetchAppel(seance.id) })
  const [lignes, setLignes] = useState<LigneAppel[]>([])

  useEffect(() => {
    if (data) setLignes(data.lignes)
  }, [data])

  const enregistrement = useMutation({
    mutationFn: () =>
      enregistrerAppel(
        seance.id,
        lignes.map((l) => ({
          eleve_id: l.eleve_id,
          statut: l.statut,
          justifie: l.justifie,
          remarque: l.remarque,
        })),
      ),
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ['seances', classeId] })
      queryClient.invalidateQueries({ queryKey: ['appel', seance.id] })
      succes(`Appel enregistré (${data.enregistres} élève(s)).`)
      onClose()
    },
    onError: (e: { message?: string }) => erreur(e.message ?? "Enregistrement de l'appel impossible."),
  })

  const modifier = (eleveId: number, champs: Partial<LigneAppel>) =>
    setLignes((actuel) => actuel.map((l) => (l.eleve_id === eleveId ? { ...l, ...champs } : l)))

  const absents = lignes.filter((l) => l.statut === 'absent' || l.statut === 'renvoye').length

  return (
    <Modal title={`Appel — ${seance.matiere} du ${new Date(seance.date_seance).toLocaleDateString('fr-FR')}`} onClose={onClose}>
      {isLoading ? (
        <Spinner />
      ) : (
        <div className="flex flex-col gap-4">
          <p className="text-sm text-navy-500">
            {lignes.length} élève(s) · {absents} absent(s). Ne modifiez que les élèves concernés.
          </p>

          <ul className="flex max-h-96 flex-col gap-2 overflow-y-auto">
            {lignes.map((ligne) => (
              <li key={ligne.eleve_id} className="rounded-xl border border-navy-100 px-3 py-2">
                <div className="flex items-center justify-between gap-3">
                  <span className="min-w-0 flex-1 truncate text-sm font-medium text-navy-800">{ligne.nom_complet}</span>
                  <select
                    value={ligne.statut}
                    onChange={(e) => modifier(ligne.eleve_id, { statut: e.target.value as LigneAppel['statut'] })}
                    className="rounded-lg border border-navy-200 px-2 py-1 text-xs font-semibold text-navy-700"
                  >
                    {STATUTS.map((s) => (
                      <option key={s.valeur} value={s.valeur}>
                        {s.libelle}
                      </option>
                    ))}
                  </select>
                </div>
                {(ligne.statut === 'absent' || ligne.statut === 'renvoye') && (
                  <label className="mt-1.5 flex items-center gap-2 text-xs text-navy-500">
                    <input
                      type="checkbox"
                      checked={ligne.justifie}
                      onChange={(e) => modifier(ligne.eleve_id, { justifie: e.target.checked })}
                    />
                    Absence justifiée
                  </label>
                )}
              </li>
            ))}
          </ul>

          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={onClose}>
              Annuler
            </Button>
            <Button onClick={() => enregistrement.mutate()} disabled={enregistrement.isPending || lignes.length === 0}>
              Enregistrer l'appel
            </Button>
          </div>
        </div>
      )}
    </Modal>
  )
}
