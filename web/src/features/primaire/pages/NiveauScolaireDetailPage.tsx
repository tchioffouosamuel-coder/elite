import { useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Layers, ListPlus } from 'lucide-react'
import { fetchNiveauxScolaires } from '@/features/primaire/api'
import { fetchClasses, updateClasse, type Classe } from '@/features/classes/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { PageHeader } from '@/shared/ui/PageHeader'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Modal } from '@/shared/ui/Modal'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

/** Classes rattachées à un niveau d'enseignement du primaire (SIL, CP, CE1…). */
export function NiveauScolaireDetailPage() {
  const { t } = useTranslation()
  const { id } = useParams<{ id: string }>()
  const niveauId = Number(id)
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const [affectationOuverte, setAffectationOuverte] = useState(false)
  const [classesSelectionnees, setClassesSelectionnees] = useState<Set<number>>(new Set())
  const [enregistrement, setEnregistrement] = useState(false)

  const { data: niveaux, isLoading: chargementNiveaux, isError: erreurNiveaux } = useQuery({
    queryKey: ['niveaux-scolaires'],
    queryFn: fetchNiveauxScolaires,
  })
  const { data: classes, isLoading: chargementClasses, isError: erreurClasses } = useQuery({
    queryKey: ['classes'],
    queryFn: () => fetchClasses(),
  })

  const niveau = niveaux?.find((n) => n.id === niveauId)
  const classesDuNiveau = (classes ?? []).filter((c) => c.niveau_scolaire_id === niveauId)

  const ouvrirAffectation = () => {
    setClassesSelectionnees(new Set(classesDuNiveau.map((c) => c.id)))
    setAffectationOuverte(true)
  }

  const enregistrerAffectation = async () => {
    if (!classes) return
    setEnregistrement(true)
    try {
      await Promise.all(
        classes
          .filter((classe) => classe.school_id === niveau?.school_id)
          .filter((classe) => classe.niveau_scolaire_id === niveauId !== classesSelectionnees.has(classe.id))
          .map((classe) => updateClasse(classe.id, {
            niveau_id: classe.niveau_id,
            niveau_scolaire_id: classesSelectionnees.has(classe.id) ? niveauId : null,
            sous_systeme_id: classe.sous_systeme_id,
            nom: classe.nom,
            sigle: classe.sigle,
            niveau_classe: classe.niveau_classe,
            filiere: classe.filiere,
            code_examen: classe.code_examen,
            capacite: classe.capacite,
            professeur_principal_id: classe.professeur_principal_id,
            titulaire_id: classe.titulaire_id,
            surveillant_general_id: classe.surveillant_general_id,
            censeur_id: classe.censeur_id,
            conseiller_orientation_id: classe.conseiller_orientation_id,
          })),
      )
      setAffectationOuverte(false)
      await queryClient.invalidateQueries({ queryKey: ['classes'] })
      succes(t('niveaux.classes_updated'))
    } catch (err) {
      erreur((err as ApiError).message ?? t('niveaux.classes_update_error'))
    } finally {
      setEnregistrement(false)
    }
  }

  if (chargementNiveaux || chargementClasses) return <Spinner />
  if (erreurNiveaux || erreurClasses || !niveau) return <ErrorState />

  const colonnes: Colonne<Classe>[] = [
    {
      cle: 'nom',
      entete: t('classes.nom'),
      valeur: (c) => c.nom,
      cellule: (c) => <span className="font-semibold text-navy-900">{c.nom}</span>,
    },
    {
      cle: 'school',
      entete: t('classes.ecole'),
      valeur: (c) => c.school?.name,
      cellule: (c) => <span className="text-navy-600">{c.school?.name ?? '—'}</span>,
      masquerMobile: true,
    },
    {
      cle: 'effectif',
      entete: t('classes.effectif'),
      valeur: (c) => c.effectif ?? 0,
      cellule: (c) => <span className="tabular-nums">{c.effectif ?? 0}</span>,
    },
    {
      cle: 'titulaire',
      entete: t('classes.titulaire'),
      valeur: (c) => c.titulaire?.nom_complet,
      cellule: (c) => c.titulaire?.nom_complet ?? '—',
      masquerMobile: true,
    },
  ]

  return (
    <div className="flex flex-col gap-5">
      <div>
        <Link to="/niveaux" className="mb-2 flex items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-700">
          <ArrowLeft className="h-4 w-4" />
          {t('common.back')}
        </Link>
        <PageHeader
          titre={t('niveaux.classes_title', { code: niveau.code, libelle: niveau.libelle })}
          icon={Layers}
          actions={can('pedagogie.manage') && (
            <Button onClick={ouvrirAffectation}>
              <ListPlus className="h-4 w-4" />
              {t('niveaux.assign_classes')}
            </Button>
          )}
        />
      </div>

      <DataTable
        colonnes={colonnes}
        lignes={classesDuNiveau}
        cleLigne={(c) => c.id}
        placeholderRecherche={t('classes.search_placeholder')}
        messageVide={t('niveaux.empty_classes')}
        largeurMin={640}
      />

      {affectationOuverte && (
        <Modal title={t('niveaux.assign_classes')} onClose={() => setAffectationOuverte(false)}>
          <div className="flex flex-col gap-2">
            {(classes ?? [])
              .filter((classe) => classe.school_id === niveau.school_id)
              .map((classe) => (
                <label key={classe.id} className="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 hover:bg-cream-50">
                  <input
                    type="checkbox"
                    checked={classesSelectionnees.has(classe.id)}
                    onChange={() => setClassesSelectionnees((actuels) => {
                      const suivants = new Set(actuels)
                      if (suivants.has(classe.id)) suivants.delete(classe.id)
                      else suivants.add(classe.id)
                      return suivants
                    })}
                    className="h-4 w-4 rounded border-navy-300"
                  />
                  <span className="font-medium text-navy-800">{classe.nom}</span>
                  {classe.niveau_scolaire && <span className="text-xs text-navy-400">{classe.niveau_scolaire.code}</span>}
                </label>
              ))}
          </div>
          <div className="mt-5 flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setAffectationOuverte(false)}>{t('common.cancel')}</Button>
            <Button onClick={enregistrerAffectation} disabled={enregistrement}>
              <ListPlus className="h-4 w-4" />
              {enregistrement ? t('common.loading') : t('common.save')}
            </Button>
          </div>
        </Modal>
      )}
    </div>
  )
}
