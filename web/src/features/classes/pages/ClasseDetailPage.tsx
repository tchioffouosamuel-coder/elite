import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import {
  IdCard,
  QrCode,
  Pencil,
  Users,
  FileDown,
  FileSpreadsheet,
  CalendarClock,
  GitBranch,
  ScanFace,
  Trophy,
  UserPlus,
  Trash2,
  School,
  Gavel,
} from 'lucide-react'
import { fetchClasse, deleteClasse } from '@/features/classes/api'
import { ouvrirBulletinsClasse } from '@/features/resultats/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { EntityHeader } from '@/shared/ui/EntityHeader'
import type { ActionGroup } from '@/shared/ui/ActionsMenu'
import { ouvrirDocument, telechargerFichier } from '@/shared/lib/download'
import { ClasseTabs } from '@/features/classes/pages/ClasseTabs'
import { ClasseQrModal } from '@/features/classes/pages/ClasseQrModal'
import { ClasseFormModal } from '@/features/classes/pages/ClasseFormModal'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

/**
 * Fiche classe : les onglets montrent ce que la classe contient (matières,
 * élèves, notes, discipline, résultats) et le menu d'actions rassemble tout ce
 * qu'on peut en faire — emploi du temps, progression, bulletins, PV de
 * conseil, cartes scolaires, identification — sans repasser par le module
 * correspondant dans le menu latéral.
 */
export function ClasseDetailPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const can = useAuthStore((s) => s.can)
  const { id } = useParams<{ id: string }>()
  const classeId = Number(id)
  const [qrOuvert, setQrOuvert] = useState(false)
  const [editionOuverte, setEditionOuverte] = useState(false)

  const { data: classe, isLoading, isError } = useQuery({
    queryKey: ['classe', classeId],
    queryFn: () => fetchClasse(classeId),
  })

  if (isLoading) return <Spinner />
  if (isError || !classe) return <ErrorState />

  const supprimer = async () => {
    const confirme = await confirmer({
      titre: t('hub.classe.delete_titre', { nom: classe.nom }),
      message: t('hub.classe.delete_message'),
      action: t('common.delete'),
    })
    if (!confirme) return
    try {
      await deleteClasse(classe.id)
      queryClient.invalidateQueries({ queryKey: ['classes'] })
      succes(t('hub.classe.delete_ok'))
      navigate('/classes')
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const menu: ActionGroup[] = [
    {
      titre: t('hub.groupe.dossier'),
      items: [
        can('classes.manage') && {
          label: t('classes.edit'),
          icon: Pencil,
          onClick: () => setEditionOuverte(true),
        },
        can('eleves.manage') && {
          label: t('hub.classe.inscrire_eleve'),
          icon: UserPlus,
          onClick: () => navigate('/eleves/nouveau'),
        },
        can('eleves.view') && {
          label: t('hub.classe.voir_eleves'),
          icon: Users,
          onClick: () => navigate(`/eleves?classe=${classe.id}`),
        },
      ],
    },
    {
      titre: t('hub.groupe.operations'),
      items: [
        can('emploi_du_temps.view') && {
          label: t('nav.emploiDuTemps'),
          icon: CalendarClock,
          onClick: () => navigate(`/emploi-du-temps?classe=${classe.id}`),
        },
        can('pedagogie.view') && {
          label: t('nav.progression'),
          icon: GitBranch,
          onClick: () => navigate(`/progression/classes/${classe.id}`),
        },
        can('eleves.view') && {
          label: t('nav.identification'),
          icon: ScanFace,
          onClick: () => navigate(`/identification/classes/${classe.id}`),
        },
        can('bulletins.view') && {
          label: t('nav.palmares'),
          icon: Trophy,
          onClick: () => navigate('/palmares'),
        },
        can('conseil_classe.view') && {
          label: t('hub.classe.conseil_fin_annee'),
          icon: Gavel,
          onClick: () => navigate(`/conseil-classe/${classe.id}`),
        },
        can('emploi_du_temps.manage') &&
          !!classe.qr_token && {
            label: t('classes.qr_button'),
            icon: QrCode,
            onClick: () => setQrOuvert(true),
          },
      ],
    },
    {
      titre: t('hub.groupe.documents'),
      items: [
        can('bulletins.view') && {
          label: t('hub.classe.bulletins'),
          icon: FileDown,
          onClick: () => ouvrirBulletinsClasse(classe.id, undefined, classe.school?.type),
        },
        can('classes.view') && {
          label: t('export.carte'),
          icon: IdCard,
          onClick: () => ouvrirDocument(`/classes/${classe.id}/cartes-scolaires`),
        },
        can('classes.view') && {
          label: t('hub.classe.liste_pdf'),
          icon: FileDown,
          onClick: () => ouvrirDocument(`/classes/${classe.id}/eleves/pdf`),
        },
        can('classes.view') && {
          label: t('hub.classe.liste_word'),
          icon: FileSpreadsheet,
          onClick: () =>
            telechargerFichier(`/classes/${classe.id}/eleves/word`, undefined, `${classe.nom}.docx`),
        },
      ],
    },
    {
      titre: t('hub.groupe.danger'),
      items: [
        can('classes.manage') && {
          label: t('common.delete'),
          icon: Trash2,
          onClick: supprimer,
          danger: true,
        },
      ],
    },
  ]

  return (
    <div className="flex flex-col gap-5">
      <EntityHeader
        retour={{ to: '/classes', label: t('common.back') }}
        icon={School}
        titre={classe.nom}
        sousTitre={
          [
            classe.niveau_scolaire?.libelle ?? classe.niveau?.name_fr,
            classe.filiere,
            classe.titulaire ? `${t('classes.titulaire')} : ${classe.titulaire.nom_complet}` : null,
          ]
            .filter(Boolean)
            .join(' · ') || undefined
        }
        actions={
          can('classes.manage') ? (
            <Button variant="secondary" onClick={() => setEditionOuverte(true)}>
              <Pencil className="h-4 w-4" />
              {t('common.edit')}
            </Button>
          ) : undefined
        }
        menu={menu}
        menuLabel={t('hub.actions')}
      />

      <ClasseTabs classe={classe} />

      {qrOuvert && <ClasseQrModal classe={classe} onClose={() => setQrOuvert(false)} />}

      {editionOuverte && (
        <ClasseFormModal
          classe={classe}
          onClose={() => setEditionOuverte(false)}
          onCreated={() => {
            setEditionOuverte(false)
            queryClient.invalidateQueries({ queryKey: ['classe', classeId] })
            queryClient.invalidateQueries({ queryKey: ['classes'] })
          }}
        />
      )}
    </div>
  )
}
