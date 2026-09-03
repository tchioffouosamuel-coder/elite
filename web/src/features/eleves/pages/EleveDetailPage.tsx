import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useParams, useNavigate, useSearchParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import {
  Pencil,
  FileDown,
  FileText,
  Phone,
  Mail,
  Briefcase,
  UserRound,
  KeyRound,
  Camera,
  Wallet,
  HeartPulse,
  ShieldAlert,
  Bus,
  ArrowRightLeft,
  Building2,
  Archive,
  RotateCcw,
  Trash2,
  IdCard,
  BookOpen,
} from 'lucide-react'
import {
  fetchEleve,
  creerCompteParent,
  creerCompteEleve,
  archiveEleve,
  reactivateEleve,
  deleteEleve,
  uploadElevePhoto,
  fetchParcoursEleve,
  type ParcoursAnnee,
} from '@/features/eleves/api'
import { identifiantsOuverts, erreur, succes, confirmer } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'
import { ouvrirBulletin } from '@/features/resultats/api'
import { DossierDisciplinaireCard } from '@/features/discipline/pages/DossierDisciplinaireCard'
import { SanctionFormModal } from '@/features/discipline/SanctionFormModal'
import { SanteEleveCard } from '@/features/infirmerie/pages/SanteEleveCard'
import { StatutFinancierCard } from '@/features/finance/pages/StatutFinancierCard'
import { TransportEleveCard } from '@/features/bus/pages/TransportEleveCard'
import { TransfererClasseModal } from '@/features/eleves/TransfererClasseModal'
import { TransfererEcoleModal } from '@/features/eleves/TransfererEcoleModal'
import { telechargerFichier, ouvrirDocument } from '@/shared/lib/download'
import { useAuthStore } from '@/shared/store/authStore'
import { estSecondaire } from '@/shared/lib/ecole'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Tabs } from '@/shared/ui/Tabs'
import { EntityHeader, Avatar } from '@/shared/ui/EntityHeader'
import type { ActionGroup } from '@/shared/ui/ActionsMenu'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'

function Champ({ label, valeur }: { label: string; valeur: string | null | undefined }) {
  return (
    <div className="flex flex-col gap-0.5">
      <span className="text-xs font-semibold uppercase tracking-wide text-navy-400">{label}</span>
      <span className="text-sm font-medium text-navy-800">{valeur || '—'}</span>
    </div>
  )
}

/**
 * Fiche élève : le point unique d'où l'on voit et fait tout ce qui concerne un
 * élève, quel que soit le module propriétaire de l'opération. Encaisser sa
 * scolarité, lui ouvrir un accès parent, l'inscrire au bus, consigner une
 * visite à l'infirmerie ou prononcer une sanction se font depuis ici — au lieu
 * de rouvrir « Caisse », « Transport » ou « Discipline » et d'y rechercher
 * l'élève à chaque fois.
 *
 * L'onglet actif vit dans l'URL (`?onglet=`) : les écrans annexes (encaissement,
 * souscription, visite) savent ainsi ramener l'utilisateur exactement là d'où
 * il est parti.
 */
export function EleveDetailPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const can = useAuthStore((s) => s.can)
  const isSuperAdmin = useAuthStore((s) => s.user?.is_super_admin ?? false)
  const { id } = useParams<{ id: string }>()
  const eleveId = Number(id)
  const [searchParams, setSearchParams] = useSearchParams()

  const [ouvertureEnCours, setOuvertureEnCours] = useState<number | null>(null)
  const [ouvertureCompteEleveEnCours, setOuvertureCompteEleveEnCours] = useState(false)
  const [transfertClasse, setTransfertClasse] = useState(false)
  const [transfertEcole, setTransfertEcole] = useState(false)
  const [sanctionOuverte, setSanctionOuverte] = useState(false)
  const [photoEnCours, setPhotoEnCours] = useState(false)
  const photoInputRef = useRef<HTMLInputElement>(null)

  const { data: eleve, isLoading, isError } = useQuery({
    queryKey: ['eleve', eleveId],
    queryFn: () => fetchEleve(eleveId),
  })
  const secondaire = estSecondaire(eleve?.school?.type)

  const rafraichir = () => {
    queryClient.invalidateQueries({ queryKey: ['eleve', eleveId] })
    queryClient.invalidateQueries({ queryKey: ['eleves'] })
  }

  const ouvrirAccesParent = async (tuteurId: number) => {
    setOuvertureEnCours(tuteurId)
    try {
      const { identifiant, mot_de_passe_provisoire } = await creerCompteParent(tuteurId)
      identifiantsOuverts(identifiant, mot_de_passe_provisoire)
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setOuvertureEnCours(null)
    }
  }

  const ouvrirAccesEleve = async () => {
    setOuvertureCompteEleveEnCours(true)
    try {
      const { identifiant, mot_de_passe_provisoire } = await creerCompteEleve(eleveId)
      identifiantsOuverts(identifiant, mot_de_passe_provisoire)
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setOuvertureCompteEleveEnCours(false)
    }
  }

  const changerPhoto = async (fichier: File | undefined) => {
    if (!fichier) return
    setPhotoEnCours(true)
    try {
      await uploadElevePhoto(eleveId, fichier)
      rafraichir()
      succes(t('eleves.photo_updated'))
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setPhotoEnCours(false)
    }
  }

  if (isLoading) return <Spinner />
  if (isError || !eleve) return <ErrorState />

  // Les onglets suivent les privilèges : inutile de proposer « Finance » à un
  // surveillant, il n'obtiendrait qu'une carte en erreur.
  const onglets = [
    { key: 'profil', label: t('hub.tab.profil') },
    can('finance.view') && { key: 'finance', label: t('hub.tab.finance') },
    can('infirmerie.view') && { key: 'sante', label: t('hub.tab.sante') },
    secondaire && can('discipline.view') && { key: 'discipline', label: t('hub.tab.discipline') },
    can('bus.view') && { key: 'transport', label: t('hub.tab.transport') },
    { key: 'documents', label: t('hub.tab.documents') },
    can('conseil_classe.view') && { key: 'parcours', label: t('hub.tab.parcours') },
  ].filter(Boolean) as { key: string; label: string }[]

  const ongletDemande = searchParams.get('onglet')
  const onglet = onglets.some((o) => o.key === ongletDemande) ? (ongletDemande as string) : 'profil'
  const changerOnglet = (cle: string) => {
    const suivant = new URLSearchParams(searchParams)
    suivant.set('onglet', cle)
    setSearchParams(suivant, { replace: true })
  }

  /** Où les écrans annexes doivent ramener une fois l'opération terminée. */
  const retourVers = (cle: string) => `/eleves/${eleveId}?onglet=${cle}`

  const encaisser = () => navigate(`/caisse/encaisser/${eleveId}`, { state: { retour: retourVers('finance') } })
  const nouvelleVisite = () =>
    navigate(`/infirmerie/nouvelle?eleve_id=${eleveId}&retour=${encodeURIComponent(retourVers('sante'))}`)
  const souscrireBus = () => navigate(`/bus/souscription/${eleveId}`, { state: { retour: retourVers('transport') } })

  const archiverOuReactiver = async () => {
    if (eleve.statut === 'actif') {
      const confirme = await confirmer({
        titre: t('eleves.archive_title', { nom: eleve.nom_complet }),
        message: t('eleves.archive_message'),
        action: t('common.archive'),
      })
      if (!confirme) return
      await archiveEleve(eleve.id)
      rafraichir()
      succes(t('eleves.archived'))
      return
    }

    await reactivateEleve(eleve.id)
    rafraichir()
    succes(t('eleves.reactivated'))
  }

  const supprimer = async () => {
    const confirme = await confirmer({
      titre: t('eleves.delete_title', { nom: eleve.nom_complet }),
      message: t('eleves.delete_message'),
      action: t('common.delete'),
    })
    if (!confirme) return
    try {
      await deleteEleve(eleve.id)
      succes(t('eleves.deleted'))
      navigate('/eleves')
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const menu: ActionGroup[] = [
    {
      titre: t('hub.groupe.dossier'),
      items: [
        can('eleves.manage') && {
          label: t('eleves.edit'),
          icon: Pencil,
          onClick: () => navigate(`/eleves/${eleve.id}/edit`),
        },
        can('eleves.manage') && {
          label: t('eleves.photo_title'),
          icon: Camera,
          onClick: () => photoInputRef.current?.click(),
          disabled: photoEnCours,
        },
        can('eleves.manage') && {
          label: t('eleves.changer_classe'),
          icon: ArrowRightLeft,
          aide: eleve.classe?.nom ?? undefined,
          onClick: () => setTransfertClasse(true),
        },
        can('eleves.manage') &&
          isSuperAdmin && {
            label: t('eleves.transferer_ecole'),
            icon: Building2,
            aide: eleve.school?.name ?? undefined,
            onClick: () => setTransfertEcole(true),
          },
        can('eleves.manage') && {
          label: t('hub.eleve.acces_eleve'),
          icon: KeyRound,
          onClick: ouvrirAccesEleve,
          disabled: ouvertureCompteEleveEnCours,
        },
      ],
    },
    {
      titre: t('hub.groupe.operations'),
      items: [
        can('finance.encaisser') && {
          label: t('hub.eleve.encaisser'),
          icon: Wallet,
          onClick: encaisser,
        },
        can('infirmerie.manage') && {
          label: t('hub.eleve.nouvelle_visite'),
          icon: HeartPulse,
          onClick: nouvelleVisite,
        },
        secondaire &&
          can('discipline.manage') && {
            label: t('hub.eleve.nouvelle_sanction'),
            icon: ShieldAlert,
            onClick: () => setSanctionOuverte(true),
          },
        can('bus.manage') && {
          label: t('hub.eleve.souscrire_bus'),
          icon: Bus,
          onClick: souscrireBus,
        },
      ],
    },
    {
      titre: t('hub.groupe.documents'),
      items: [
        !!eleve.classe && {
          label: t('resultats.bulletin'),
          icon: FileDown,
          onClick: () => ouvrirBulletin(eleve.id, undefined, eleve.school?.type),
        },
        !!eleve.classe && {
          label: t('export.attestation'),
          icon: FileText,
          onClick: () => telechargerFichier(`/eleves/${eleve.id}/attestation-scolarite`, undefined, 'attestation.docx'),
        },
        !!eleve.classe &&
          can('eleves.view') && {
            label: t('hub.eleve.carte_classe'),
            icon: IdCard,
            aide: eleve.classe.nom,
            onClick: () => ouvrirDocument(`/classes/${eleve.classe?.id}/cartes-scolaires`),
          },
        !!eleve.classe &&
          can('classes.view') && {
            label: t('hub.eleve.voir_classe'),
            icon: BookOpen,
            aide: eleve.classe.nom,
            onClick: () => navigate(`/classes/${eleve.classe?.id}`),
          },
      ],
    },
    {
      titre: t('hub.groupe.danger'),
      items: [
        can('eleves.manage') && {
          label: eleve.statut === 'actif' ? t('common.archive') : t('common.reactivate'),
          icon: eleve.statut === 'actif' ? Archive : RotateCcw,
          onClick: archiverOuReactiver,
          danger: eleve.statut === 'actif',
        },
        can('eleves.manage') && {
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
        retour={{ to: '/eleves', label: t('common.back') }}
        avatar={<Avatar url={eleve.photo_url} nom={eleve.nom_complet} />}
        titre={eleve.nom_complet}
        sousTitre={[eleve.matricule, eleve.classe?.nom, eleve.school?.name].filter(Boolean).join(' · ') || '—'}
        badges={
          <>
            <Badge tone={eleve.statut === 'actif' ? 'green' : 'neutral'}>{t(`eleves.${eleve.statut}`)}</Badge>
            <Badge tone={eleve.sexe === 'F' ? 'gold' : 'neutral'}>
              {eleve.sexe === 'F' ? t('eleves.feminin') : t('eleves.masculin')}
            </Badge>
            {eleve.redoublant && <Badge tone="red">{t('eleves.redoublant')}</Badge>}
          </>
        }
        actions={
          can('eleves.manage') ? (
            <Button variant="secondary" onClick={() => navigate(`/eleves/${eleve.id}/edit`)}>
              <Pencil className="h-4 w-4" />
              {t('common.edit')}
            </Button>
          ) : undefined
        }
        menu={menu}
        menuLabel={t('hub.actions')}
      />

      <input
        ref={photoInputRef}
        type="file"
        accept="image/jpeg,image/jpg,image/png"
        className="hidden"
        onChange={(e) => changerPhoto(e.target.files?.[0])}
      />

      <Tabs tabs={onglets} active={onglet} onChange={changerOnglet} />

      {onglet === 'profil' && (
        <>
          <Card>
            <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">
              {t('eleves.informations_personnelles')}
            </h2>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <Champ label={t('eleves.matricule')} valeur={eleve.matricule} />
              <Champ label={t('eleves.date_naissance')} valeur={eleve.date_naissance} />
              <Champ label={t('eleves.lieu_naissance')} valeur={eleve.lieu_naissance} />
              <Champ label={t('eleves.nationalite')} valeur={eleve.nationalite} />
              <Champ label={t('eleves.classe')} valeur={eleve.classe?.nom} />
              <Champ label={t('classes.ecole')} valeur={eleve.school?.name} />
            </div>
          </Card>

          <Card>
            <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">{t('eleves.tuteur')}</h2>
            {eleve.tuteurs.length === 0 ? (
              <p className="text-sm text-navy-400">{t('eleves.aucun_tuteur')}</p>
            ) : (
              <div className="flex flex-col divide-y divide-navy-100">
                {eleve.tuteurs.map((tuteur) => (
                  <div
                    key={tuteur.id}
                    className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                  >
                    <div className="flex items-center gap-3">
                      <span className="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-navy-50 ring-1 ring-navy-100">
                        <UserRound className="h-4 w-4 text-navy-400" />
                      </span>
                      <div>
                        <p className="text-sm font-semibold text-navy-800">
                          {tuteur.nom_complet}
                          {tuteur.is_principal && (
                            <span className="ml-2 text-xs font-medium text-gold-600">{t('eleves.principal')}</span>
                          )}
                        </p>
                        <p className="text-xs text-navy-400">{tuteur.lien_parente || '—'}</p>
                      </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-navy-500">
                      {tuteur.telephone && (
                        <span className="flex items-center gap-1">
                          <Phone className="h-3.5 w-3.5" />
                          {tuteur.telephone}
                        </span>
                      )}
                      {tuteur.email && (
                        <span className="flex items-center gap-1">
                          <Mail className="h-3.5 w-3.5" />
                          {tuteur.email}
                        </span>
                      )}
                      {tuteur.profession && (
                        <span className="flex items-center gap-1">
                          <Briefcase className="h-3.5 w-3.5" />
                          {tuteur.profession}
                        </span>
                      )}
                      {can('eleves.manage') && (
                        <button
                          onClick={() => ouvrirAccesParent(tuteur.id)}
                          disabled={ouvertureEnCours === tuteur.id}
                          title={t('hub.eleve.acces_parent_aide')}
                          className="flex items-center gap-1 rounded-lg px-2 py-1 font-semibold text-navy-600 transition-colors hover:bg-cream-100 disabled:opacity-50"
                        >
                          <KeyRound className="h-3.5 w-3.5" />
                          {ouvertureEnCours === tuteur.id ? t('common.loading') : t('hub.eleve.acces_parent')}
                        </button>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </Card>
        </>
      )}

      {onglet === 'finance' && (
        <>
          {can('finance.encaisser') && (
            <div className="flex flex-wrap gap-2">
              <Button onClick={encaisser}>
                <Wallet className="h-4 w-4" />
                {t('hub.eleve.encaisser')}
              </Button>
            </div>
          )}
          <StatutFinancierCard eleveId={eleve.id} />
        </>
      )}

      {onglet === 'sante' && (
        <>
          {can('infirmerie.manage') && (
            <div className="flex flex-wrap gap-2">
              <Button onClick={nouvelleVisite}>
                <HeartPulse className="h-4 w-4" />
                {t('hub.eleve.nouvelle_visite')}
              </Button>
            </div>
          )}
          <SanteEleveCard eleveId={eleve.id} />
        </>
      )}

      {onglet === 'discipline' && (
        <>
          {can('discipline.manage') && (
            <div className="flex flex-wrap gap-2">
              <Button onClick={() => setSanctionOuverte(true)}>
                <ShieldAlert className="h-4 w-4" />
                {t('hub.eleve.nouvelle_sanction')}
              </Button>
            </div>
          )}
          <DossierDisciplinaireCard eleveId={eleve.id} />
        </>
      )}

      {onglet === 'transport' && (
        <TransportEleveCard eleveId={eleve.id} classeId={eleve.classe?.id} retour={retourVers('transport')} />
      )}

      {onglet === 'documents' && (
        <Card>
          <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">{t('hub.tab.documents')}</h2>
          {!eleve.classe ? (
            <p className="text-sm text-navy-400">{t('hub.eleve.documents_sans_classe')}</p>
          ) : (
            <div className="flex flex-wrap gap-2">
              <Button variant="secondary" onClick={() => ouvrirBulletin(eleve.id, undefined, eleve.school?.type)}>
                <FileDown className="h-4 w-4" />
                {t('resultats.bulletin')}
              </Button>
              <Button
                variant="secondary"
                onClick={() =>
                  telechargerFichier(`/eleves/${eleve.id}/attestation-scolarite`, undefined, 'attestation.docx')
                }
              >
                <FileText className="h-4 w-4" />
                {t('export.attestation')}
              </Button>
              {can('eleves.view') && (
                <Button variant="secondary" onClick={() => ouvrirDocument(`/classes/${eleve.classe?.id}/cartes-scolaires`)}>
                  <IdCard className="h-4 w-4" />
                  {t('hub.eleve.carte_classe')}
                </Button>
              )}
            </div>
          )}
        </Card>
      )}

      {onglet === 'parcours' && <ParcoursCard eleveId={eleve.id} />}

      {transfertClasse && (
        <TransfererClasseModal
          eleve={eleve}
          onClose={() => setTransfertClasse(false)}
          onDone={() => {
            setTransfertClasse(false)
            rafraichir()
          }}
        />
      )}

      {transfertEcole && (
        <TransfererEcoleModal
          eleve={eleve}
          onClose={() => setTransfertEcole(false)}
          onDone={() => {
            setTransfertEcole(false)
            rafraichir()
          }}
        />
      )}

      {sanctionOuverte && (
        <SanctionFormModal
          eleve={eleve}
          onClose={() => setSanctionOuverte(false)}
          onCreated={() => {
            setSanctionOuverte(false)
            queryClient.invalidateQueries({ queryKey: ['dossier-disciplinaire', eleve.id] })
            queryClient.invalidateQueries({ queryKey: ['sanctions'] })
            succes(t('discipline.sanction_created'))
          }}
        />
      )}
    </div>
  )
}

const DECISION_TONE: Record<ParcoursAnnee['decision'], 'green' | 'gold' | 'red' | 'blue'> = {
  admis: 'green',
  redouble: 'gold',
  exclu: 'red',
  diplome: 'blue',
}

/** Historique année par année : classe fréquentée, moyenne/rang annuels, décision du conseil de classe — alimenté à chaque conseil validé. */
function ParcoursCard({ eleveId }: { eleveId: number }) {
  const { t } = useTranslation()
  const { data: parcours, isLoading } = useQuery({ queryKey: ['eleve-parcours', eleveId], queryFn: () => fetchParcoursEleve(eleveId) })

  return (
    <Card>
      <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">{t('hub.tab.parcours')}</h2>
      {isLoading ? (
        <Spinner />
      ) : !parcours || parcours.length === 0 ? (
        <p className="text-sm text-navy-400">{t('hub.eleve.parcours_vide')}</p>
      ) : (
        <div className="flex flex-col divide-y divide-navy-100">
          {parcours.map((ligne) => (
            <div key={ligne.annee_scolaire.id} className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
              <div>
                <p className="text-sm font-semibold text-navy-800">{ligne.annee_scolaire.libelle}</p>
                <p className="text-xs text-navy-400">{[ligne.classe_nom, ligne.niveau_libelle].filter(Boolean).join(' · ')}</p>
                {ligne.motif && <p className="mt-1 text-xs text-navy-500">{ligne.motif}</p>}
              </div>
              <div className="flex items-center gap-3">
                {ligne.moyenne_annuelle !== null && (
                  <span className="text-xs text-navy-500">
                    {t('hub.eleve.moyenne_annuelle')} <span className="font-semibold text-navy-800">{ligne.moyenne_annuelle}</span>
                    {ligne.rang_annuel !== null && <> — {t('hub.eleve.rang_annuel')} <span className="font-semibold text-navy-800">{ligne.rang_annuel}</span></>}
                  </span>
                )}
                <Badge tone={DECISION_TONE[ligne.decision]}>
                  {t(`hub.eleve.decision_${ligne.decision}`)}
                  {ligne.gracie ? ` (${t('hub.eleve.gracie')})` : ''}
                </Badge>
              </div>
            </div>
          ))}
        </div>
      )}
    </Card>
  )
}
