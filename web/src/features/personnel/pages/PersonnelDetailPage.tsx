import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import {
  Pencil,
  KeyRound,
  FileText,
  IdCard,
  Archive,
  RotateCcw,
  Trash2,
  Building2,
  HandCoins,
  Banknote,
  Phone,
  Mail,
  MapPin,
} from 'lucide-react'
import {
  fetchPersonnel,
  archivePersonnel,
  reactivatePersonnel,
  deletePersonnel,
  type Personnel,
} from '@/features/personnel/api'
import {
  fetchAvancesSalaire,
  fetchHistoriqueRemunerations,
  francs,
  GAINS,
} from '@/features/finance/api'
import { AccorderAvanceModal } from '@/features/finance/AccorderAvanceModal'
import { RemunerationModal } from '@/features/finance/pages/RemunerationModal'
import { CreateAccountModal } from '@/features/personnel/pages/CreateAccountModal'
import { telechargerFichier, ouvrirDocument } from '@/shared/lib/download'
import { useAuthStore } from '@/shared/store/authStore'
import { estSecondaire } from '@/shared/lib/ecole'
import { Card } from '@/shared/ui/Card'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { Tabs } from '@/shared/ui/Tabs'
import { EntityHeader, Avatar } from '@/shared/ui/EntityHeader'
import type { ActionGroup } from '@/shared/ui/ActionsMenu'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

function Champ({ label, valeur }: { label: string; valeur: string | number | null | undefined }) {
  return (
    <div className="flex flex-col gap-0.5">
      <span className="text-xs font-semibold uppercase tracking-wide text-navy-400">{label}</span>
      <span className="text-sm font-medium text-navy-800">{valeur === 0 ? '0' : valeur || '—'}</span>
    </div>
  )
}

/**
 * Fiche d'un membre du personnel : dossier administratif, rémunération et
 * avances réunis sur un écran, avec les gestes qui vont avec (ouvrir un compte
 * de connexion, accorder une avance, éditer l'attestation d'employeur).
 *
 * Ces opérations existaient déjà mais chacune dans son module — « Personnel »
 * pour la fiche, « Salaires » pour la rémunération, « Avances » pour les
 * avances : suivre un agent obligeait à traverser trois écrans et à l'y
 * rechercher chaque fois.
 */
export function PersonnelDetailPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const can = useAuthStore((s) => s.can)
  const { id } = useParams<{ id: string }>()
  const personnelId = Number(id)
  const [searchParams, setSearchParams] = useSearchParams()

  const [compteOuvert, setCompteOuvert] = useState(false)
  const [avanceOuverte, setAvanceOuverte] = useState(false)
  const [remunerationOuverte, setRemunerationOuverte] = useState(false)

  const { data: personnel, isLoading, isError } = useQuery({
    queryKey: ['personnel', personnelId],
    queryFn: () => fetchPersonnel(personnelId),
  })

  const rafraichir = () => {
    queryClient.invalidateQueries({ queryKey: ['personnel', personnelId] })
    queryClient.invalidateQueries({ queryKey: ['personnels'] })
  }

  if (isLoading) return <Spinner />
  if (isError || !personnel) return <ErrorState />

  const secondaire = estSecondaire(personnel.school?.type)

  const onglets = [
    { key: 'profil', label: t('hub.tab.profil') },
    can('finance.paie') && { key: 'remuneration', label: t('hub.tab.remuneration') },
    can('finance.paie') && { key: 'avances', label: t('hub.tab.avances') },
  ].filter(Boolean) as { key: string; label: string }[]

  const ongletDemande = searchParams.get('onglet')
  const onglet = onglets.some((o) => o.key === ongletDemande) ? (ongletDemande as string) : 'profil'
  const changerOnglet = (cle: string) => {
    const suivant = new URLSearchParams(searchParams)
    suivant.set('onglet', cle)
    setSearchParams(suivant, { replace: true })
  }

  const archiverOuReactiver = async () => {
    if (personnel.statut === 'actif') {
      const confirme = await confirmer({
        titre: t('hub.personnel.archive_titre', { nom: personnel.nom_complet }),
        message: t('hub.personnel.archive_message'),
        action: t('common.archive'),
      })
      if (!confirme) return
      await archivePersonnel(personnel.id)
      rafraichir()
      succes(t('hub.personnel.archive_ok'))
      return
    }

    await reactivatePersonnel(personnel.id)
    rafraichir()
    succes(t('hub.personnel.reactivate_ok'))
  }

  const supprimer = async () => {
    const confirme = await confirmer({
      titre: t('hub.personnel.delete_titre', { nom: personnel.nom_complet }),
      message: t('hub.personnel.delete_message'),
      action: t('common.delete'),
    })
    if (!confirme) return
    try {
      await deletePersonnel(personnel.id)
      succes(t('hub.personnel.delete_ok'))
      navigate('/personnel')
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const menu: ActionGroup[] = [
    {
      titre: t('hub.groupe.dossier'),
      items: [
        can('personnel.manage') && {
          label: t('personnel.edit'),
          icon: Pencil,
          onClick: () => navigate(`/personnel/${personnel.id}/edit`),
        },
        can('personnel.manage') &&
        !personnel.a_un_compte && {
          label: t('personnel.create_account'),
          icon: KeyRound,
          aide: t('hub.personnel.compte_aide'),
          onClick: () => setCompteOuvert(true),
        },
        secondaire &&
        personnel.departement && {
          label: t('hub.personnel.voir_departement'),
          icon: Building2,
          aide: personnel.departement.nom,
          onClick: () => navigate(`/departements/${personnel.departement?.id}`),
        },
      ],
    },
    {
      titre: t('hub.groupe.operations'),
      items: [
        can('finance.paie') && {
          label: t('hub.personnel.accorder_avance'),
          icon: HandCoins,
          onClick: () => setAvanceOuverte(true),
        },
        can('finance.paie') && {
          label: t('hub.personnel.definir_remuneration'),
          icon: Banknote,
          onClick: () => setRemunerationOuverte(true),
        },
      ],
    },
    {
      titre: t('hub.groupe.documents'),
      items: [
        can('personnel.manage') && {
          label: t('hub.personnel.attestation'),
          icon: FileText,
          onClick: () => {
            void telechargerFichier(
              `/personnels/${personnel.id}/attestation-employeur`,
              undefined,
              'attestation-employeur.docx',
            ).catch((err) => erreur((err as ApiError).message ?? t('hub.personnel.attestation_error')))
          },
        },
        {
          label: t('hub.personnel.fiche_pdf'),
          icon: IdCard,
          onClick: () => {
            void ouvrirDocument(`/personnels/${personnel.id}/fiche-identification/pdf`).catch(
              (err) => erreur((err as ApiError).message ?? t('hub.personnel.fiche_error')),
            )
          },
        },
        {
          label: t('hub.personnel.fiche_word'),
          icon: IdCard,
          onClick: () => {
            void telechargerFichier(
              `/personnels/${personnel.id}/fiche-identification/word`,
              undefined,
              'fiche-identification.docx',
            ).catch((err) => erreur((err as ApiError).message ?? t('hub.personnel.fiche_error')))
          },
        },
      ],
    },
    {
      titre: t('hub.groupe.danger'),
      items: [
        can('personnel.manage') && {
          label: personnel.statut === 'actif' ? t('common.archive') : t('common.reactivate'),
          icon: personnel.statut === 'actif' ? Archive : RotateCcw,
          onClick: archiverOuReactiver,
          danger: personnel.statut === 'actif',
        },
        can('personnel.manage') && {
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
        retour={{ to: '/personnel', label: t('common.back') }}
        avatar={<Avatar nom={personnel.nom_complet} />}
        titre={personnel.nom_complet}
        sousTitre={[personnel.matricule, personnel.fonction, personnel.school?.name].filter(Boolean).join(' · ') || '—'}
        badges={
          <>
            <Badge tone={personnel.statut === 'actif' ? 'green' : 'neutral'}>{t(`personnel.${personnel.statut}`)}</Badge>
            {personnel.departement && <Badge tone="blue">{personnel.departement.nom}</Badge>}
            <Badge tone={personnel.a_un_compte ? 'green' : 'gold'}>
              {personnel.a_un_compte ? t('hub.personnel.compte_ouvert') : t('hub.personnel.compte_absent')}
            </Badge>
          </>
        }
        actions={
          can('personnel.manage') ? (
            <Button variant="secondary" onClick={() => navigate(`/personnel/${personnel.id}/edit`)}>
              <Pencil className="h-4 w-4" />
              {t('common.edit')}
            </Button>
          ) : undefined
        }
        menu={menu}
        menuLabel={t('hub.actions')}
      />

      <Tabs tabs={onglets} active={onglet} onChange={changerOnglet} />

      {onglet === 'profil' && (
        <>
          <Card>
            <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">
              {t('hub.personnel.identite')}
            </h2>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <Champ label={t('personnel.matricule')} valeur={personnel.matricule} />
              <Champ label={t('personnel.fonction')} valeur={personnel.fonction} />
              <Champ
                label={t('personnel.sexe')}
                valeur={personnel.sexe ? (personnel.sexe === 'F' ? t('eleves.feminin') : t('eleves.masculin')) : null}
              />
              <Champ label={t('eleves.date_naissance')} valeur={personnel.date_naissance} />
              <Champ label={t('hub.personnel.cni')} valeur={personnel.numero_cni} />
              <Champ label={t('hub.personnel.cnps')} valeur={personnel.numero_cnps} />
            </div>
          </Card>

          <Card>
            <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">
              {t('hub.personnel.contact')}
            </h2>
            <div className="flex flex-wrap gap-x-6 gap-y-2 text-sm text-navy-600">
              <span className="flex items-center gap-1.5">
                <Phone className="h-4 w-4 text-navy-300" />
                {[personnel.telephone, personnel.telephone_2].filter(Boolean).join(' · ') || '—'}
              </span>
              <span className="flex items-center gap-1.5">
                <Mail className="h-4 w-4 text-navy-300" />
                {personnel.email || '—'}
              </span>
              <span className="flex items-center gap-1.5">
                <MapPin className="h-4 w-4 text-navy-300" />
                {personnel.residence || '—'}
              </span>
            </div>
          </Card>

          <Card>
            <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">
              {t('hub.personnel.carriere')}
            </h2>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <Champ label={t('hub.personnel.date_embauche')} valeur={personnel.date_embauche} />
              <Champ label={t('hub.personnel.date_fin')} valeur={personnel.date_fin} />
              <Champ label={t('hub.personnel.date_retraite')} valeur={personnel.date_retraite} />
              <Champ label={t('hub.personnel.affectation')} valeur={personnel.affectation} />
              <Champ label={t('hub.personnel.diplome_academique')} valeur={personnel.diplome_academique} />
              <Champ label={t('hub.personnel.diplome_professionnel')} valeur={personnel.diplome_professionnel} />
            </div>
          </Card>

          <Card>
            <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">
              {t('hub.personnel.famille')}
            </h2>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <Champ
                label={t('hub.personnel.situation_matrimoniale')}
                valeur={personnel.situation_matrimoniale}
              />
              <Champ label={t('hub.personnel.nombre_enfants')} valeur={personnel.nombre_enfants} />
              <Champ label={t('hub.personnel.pere')} valeur={personnel.pere_nom_complet} />
              <Champ label={t('hub.personnel.mere')} valeur={personnel.mere_nom_complet} />
            </div>
            {(personnel.enfants?.length ?? 0) > 0 && (
              <ul className="mt-4 flex flex-col divide-y divide-navy-50 text-sm">
                {personnel.enfants.map((enfant, index) => (
                  <li key={index} className="flex flex-wrap gap-x-4 py-2 first:pt-0 last:pb-0">
                    <span className="font-medium text-navy-800">{enfant.nom_complet || '—'}</span>
                    <span className="text-navy-400">{enfant.date_naissance || '—'}</span>
                  </li>
                ))}
              </ul>
            )}
          </Card>
        </>
      )}

      {onglet === 'remuneration' && <RemunerationTab personnelId={personnel.id} />}

      {onglet === 'avances' && (
        <AvancesTab personnelId={personnel.id} onAccorder={() => setAvanceOuverte(true)} />
      )}

      {compteOuvert && (
        <CreateAccountModal
          personnelId={personnel.id}
          onClose={() => setCompteOuvert(false)}
          onCreated={() => {
            setCompteOuvert(false)
            rafraichir()
          }}
        />
      )}

      {avanceOuverte && (
        <AccorderAvanceModal
          personnel={personnel}
          onClose={() => setAvanceOuverte(false)}
          onSaved={() => {
            setAvanceOuverte(false)
            queryClient.invalidateQueries({ queryKey: ['avances-salaire'] })
          }}
        />
      )}

      {remunerationOuverte && (
        <RemunerationPourPersonnel
          personnel={personnel}
          onClose={() => setRemunerationOuverte(false)}
          onEnregistre={() => {
            setRemunerationOuverte(false)
            queryClient.invalidateQueries({ queryKey: ['remunerations-historique', personnel.id] })
          }}
        />
      )}
    </div>
  )
}

function RemunerationPourPersonnel({
  personnel,
  onClose,
  onEnregistre,
}: {
  personnel: Personnel
  onClose: () => void
  onEnregistre: () => void
}) {
  const { data } = useQuery({
    queryKey: ['remunerations-historique', personnel.id],
    queryFn: () => fetchHistoriqueRemunerations(personnel.id),
  })

  const remuneration = data?.historique[0] ?? null

  return (
    <RemunerationModal
      personnel={{
        id: personnel.id,
        nom_complet: personnel.nom_complet,
        matricule: personnel.matricule,
        fonction: personnel.fonction,
        statut: personnel.statut,
        school: personnel.school,
        remuneration,
      }}
      onClose={onClose}
      onEnregistre={onEnregistre}
    />
  )
}

/** Historique des rémunérations successives de l'agent, la plus récente en tête. */
function RemunerationTab({ personnelId }: { personnelId: number }) {
  const { t } = useTranslation()
  const { data, isLoading } = useQuery({
    queryKey: ['remunerations-historique', personnelId],
    queryFn: () => fetchHistoriqueRemunerations(personnelId),
  })

  if (isLoading) return <Spinner />
  if (!data || data.historique.length === 0) return <EmptyState label={t('hub.personnel.remuneration_vide')} />

  return (
    <div className="flex flex-col gap-4">
      {data.historique.map((remuneration) => (
        <Card key={remuneration.id}>
          <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h3 className="text-sm font-bold text-navy-800">
              {t('hub.personnel.effet_au', { date: remuneration.date_effet })}
            </h3>
            <div className="flex items-center gap-2">
              {remuneration.categorie && <Badge tone="neutral">{remuneration.categorie}</Badge>}
              <Badge tone="blue">{t(`hub.personnel.mode_${remuneration.mode}`)}</Badge>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            {GAINS.map((gain) => (
              <Champ key={gain.champ} label={gain.libelle} valeur={francs(remuneration[gain.champ] ?? 0)} />
            ))}
          </div>

          <div className="mt-4 grid grid-cols-2 gap-4 border-t border-navy-50 pt-4 sm:grid-cols-4">
            <Champ label={t('hub.personnel.brut')} valeur={francs(remuneration.brut)} />
            <Champ label={t('hub.personnel.charges_salariales')} valeur={francs(remuneration.charges_salariales)} />
            <Champ label={t('hub.personnel.net')} valeur={francs(remuneration.net)} />
            <Champ label={t('hub.personnel.cout_employeur')} valeur={francs(remuneration.cout_employeur)} />
          </div>
        </Card>
      ))}
    </div>
  )
}

/** Avances sur salaire de l'agent et leur reste à rembourser. */
function AvancesTab({ personnelId, onAccorder }: { personnelId: number; onAccorder: () => void }) {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const { data, isLoading } = useQuery({
    queryKey: ['avances-salaire', { personnel_id: personnelId }],
    queryFn: () => fetchAvancesSalaire({ personnel_id: personnelId }),
  })

  return (
    <div className="flex flex-col gap-4">
      {can('finance.paie') && (
        <div className="flex flex-wrap gap-2">
          <Button onClick={onAccorder}>
            <HandCoins className="h-4 w-4" />
            {t('hub.personnel.accorder_avance')}
          </Button>
        </div>
      )}

      {isLoading ? (
        <Spinner />
      ) : !data || data.avances.length === 0 ? (
        <EmptyState label={t('hub.personnel.avances_vide')} />
      ) : (
        <>
          <Card>
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
              <Champ label={t('hub.personnel.total_accorde')} valeur={francs(data.totaux.total_accorde)} />
              <Champ label={t('hub.personnel.total_rembourse')} valeur={francs(data.totaux.total_rembourse)} />
              <Champ label={t('hub.personnel.total_restant')} valeur={francs(data.totaux.total_restant)} />
            </div>
          </Card>

          {data.avances.map((avance) => (
            <Card key={avance.id}>
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <p className="text-sm font-semibold text-navy-800">
                    {francs(avance.montant)}
                    <span className="ml-2 text-xs font-normal text-navy-400">{avance.date_avance}</span>
                  </p>
                  <p className="text-xs text-navy-400">{avance.motif || '—'}</p>
                </div>
                <div className="flex items-center gap-3">
                  <span className="text-sm tabular-nums text-navy-600">
                    {t('hub.personnel.solde')} : <span className="font-bold">{francs(avance.solde)}</span>
                  </span>
                  <Badge tone={avance.statut === 'remboursee' ? 'green' : 'gold'}>
                    {t(`hub.personnel.avance_${avance.statut}`)}
                  </Badge>
                </div>
              </div>
            </Card>
          ))}
        </>
      )}
    </div>
  )
}
