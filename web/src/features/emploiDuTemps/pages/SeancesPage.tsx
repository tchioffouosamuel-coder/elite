import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { ClipboardCheck, UserCheck } from 'lucide-react'
import { fetchClasses, fetchMaClasse } from '@/features/classes/api'
import { fetchSeances, type Seance } from '@/features/emploiDuTemps/api'
import { useAuthStore } from '@/shared/store/authStore'
import { estSecondaire } from '@/shared/lib/ecole'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { Select } from '@/shared/ui/Field'
import { EmptyState, Spinner } from '@/shared/ui/Feedback'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { PageHeader } from '@/shared/ui/PageHeader'

export function SeancesPage() {
  const can = useAuthStore((s) => s.can)
  const estEnseignant = useAuthStore((s) => s.user?.est_enseignant ?? false)
  const navigate = useNavigate()
  const [classeId, setClasseId] = useState<number | ''>('')

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
          <Button size="sm" variant="secondary" onClick={() => navigate(`/seances/${s.id}/appel`)}>
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

    </div>
  )
}
