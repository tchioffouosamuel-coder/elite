import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, Search, Receipt, UserPlus, Plus, Trash2 } from 'lucide-react'
import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'
import { francs, fetchDossier, MODES, type ModePaiement } from '@/features/finance/api'
import { rechercheGlobaleEleves, rechercherMatriculeNational, type Eleve, type MatriculeNationalResult } from '@/features/eleves/api'
import { fetchClasses, fetchNiveaux } from '@/features/classes/api'
import { fetchTrajets, tarifPourOption, LIBELLES_OPTION_TRAJET, type OptionTrajet } from '@/features/bus/api'
import { CHAMPS_ELEVE, type PreinscriptionResume } from '@/features/eleves/pages/PreinscriptionsAdminPage'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Input, MontantInput, Select } from '@/shared/ui/Field'
import { ouvrirDocument } from '@/shared/lib/download'
import { succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'
import { completerTelephones, telephonesParDefaut, type TelephoneEntry } from '@/features/eleves/lib/telephones'
import { TelephonesEditor } from '@/features/eleves/components/TelephonesEditor'
import { ClasseNiveauPicker } from '@/features/eleves/components/ClasseNiveauPicker'
import { useAuthStore } from '@/shared/store/authStore'

interface TuteurForm {
  nom_complet: string
  telephones: TelephoneEntry[]
  email: string
  profession: string
  lien_parente: string
  is_principal: boolean
}

function tuteurDepuisEleve(t: Eleve['tuteurs'][number]): TuteurForm {
  const telephones =
    t.telephones.length > 0
      ? t.telephones.map((tel) => ({ numero: tel.numero, is_principal: tel.is_principal }))
      : t.telephone
        ? [{ numero: t.telephone, is_principal: true }]
        : []
  return {
    nom_complet: t.nom_complet,
    telephones: completerTelephones(telephones),
    email: t.email ?? '',
    profession: t.profession ?? '',
    lien_parente: t.lien_parente ?? '',
    is_principal: t.is_principal,
  }
}

/**
 * Autocomplétion d'élève déjà scolarisé, pour la réinscription au guichet :
 * même principe que `TuteurNomAutocomplete` d'`EleveInscriptionPage`
 * (debounce 300ms, `rechercheGlobaleEleves`), mais choisir une suggestion ne
 * renseigne pas qu'un identifiant — tout le formulaire se recharge avec la
 * fiche actuelle de l'élève, prête à être complétée ou corrigée.
 */
function EleveAutocomplete({ onChoisir, onNouveau }: { onChoisir: (eleve: Eleve) => void; onNouveau: (nom: string) => void }) {
  const [terme, setTerme] = useState('')
  const [termeDebounce, setTermeDebounce] = useState('')
  const [ouvert, setOuvert] = useState(false)

  useEffect(() => {
    const minuteur = setTimeout(() => setTermeDebounce(terme.trim()), 300)
    return () => clearTimeout(minuteur)
  }, [terme])

  const { data: suggestions, isFetching } = useQuery({
    queryKey: ['eleves-recherche-globale', termeDebounce],
    queryFn: () => rechercheGlobaleEleves(termeDebounce),
    enabled: ouvert && termeDebounce.length >= 2,
  })

  const rechercheTerminee = ouvert && termeDebounce.length >= 2 && !isFetching
  const afficherSuggestions = ouvert && termeDebounce.length >= 2 && ((suggestions?.length ?? 0) > 0 || isFetching || rechercheTerminee)

  return (
    <div className="relative">
      <Input
        label="Nom de l'élève"
        placeholder="Commencez à taper le nom de l'élève…"
        icon={Search}
        autoComplete="off"
        value={terme}
        onChange={(e) => {
          setTerme(e.target.value)
          setOuvert(true)
        }}
        onFocus={() => setOuvert(true)}
        onBlur={() => setTimeout(() => setOuvert(false), 150)}
      />
      {afficherSuggestions && (
        <ul className="absolute z-20 mt-1 w-full overflow-hidden rounded-xl border border-navy-100 bg-white py-1 shadow-lifted">
          {suggestions?.map((eleve) => (
            <li key={eleve.id}>
              <button
                type="button"
                onMouseDown={(e) => e.preventDefault()}
                onClick={() => {
                  onChoisir(eleve)
                  setTerme(eleve.nom_complet)
                  setOuvert(false)
                }}
                className="flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left text-sm hover:bg-cream-100"
              >
                <span className="font-medium text-navy-900">{eleve.nom_complet}</span>
                <span className="text-xs text-navy-400">
                  {[eleve.matricule, eleve.classe?.nom].filter(Boolean).join(' · ') || 'Non affecté à une classe'}
                </span>
              </button>
            </li>
          ))}
          {isFetching && <li className="px-3 py-2 text-xs text-navy-300">Recherche…</li>}
          {rechercheTerminee && suggestions?.length === 0 && (
            <li className="border-t border-navy-50 px-3 py-2">
              <button
                type="button"
                onMouseDown={(e) => {
                  e.preventDefault()
                  onNouveau(terme.trim())
                  setOuvert(false)
                }}
                className="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left text-sm font-semibold text-navy-700 hover:bg-cream-100"
              >
                <UserPlus className="h-4 w-4" />
                Aucun élève trouvé : créer une nouvelle préinscription
              </button>
            </li>
          )}
        </ul>
      )}
    </div>
  )
}

/**
 * Préinscription saisie directement par l'admin pour un élève déjà connu du
 * système (réinscription au guichet) : contrairement à la file d'attente, la
 * demande est validée du même geste qu'elle est créée — élève et tuteurs mis
 * à jour, versement éventuel encaissé et reçu affiché immédiatement.
 */
export function PreinscriptionCreerPage() {
  const navigate = useNavigate()
  const [eleve, setEleve] = useState<Eleve | null>(null)
  const [modeSaisie, setModeSaisie] = useState<'recherche' | 'nouveau'>('recherche')
  const [champs, setChamps] = useState<Record<string, string>>({})
  const [tuteurs, setTuteurs] = useState<TuteurForm[]>([])
  const [montant, setMontant] = useState(0)
  const [modePaiement, setModePaiement] = useState<ModePaiement>('especes')
  const [reference, setReference] = useState('')
  const [classeId, setClasseId] = useState<number | null>(null)
  const [niveauId, setNiveauId] = useState<number | undefined>(undefined)
  const [busActif, setBusActif] = useState(false)
  const [busTrajetId, setBusTrajetId] = useState<number | null>(null)
  const [busArretId, setBusArretId] = useState<number | null>(null)
  const [busOption, setBusOption] = useState<OptionTrajet>('aller_retour')
  const [busMontant, setBusMontant] = useState(0)
  const [envoi, setEnvoi] = useState(false)
  const [erreurMsg, setErreurMsg] = useState<string | null>(null)
  const [matriculeNationalResultats, setMatriculeNationalResultats] = useState<MatriculeNationalResult[]>([])
  const [matriculeNationalErreur, setMatriculeNationalErreur] = useState<string | null>(null)
  const [matriculeNationalRechercheEnCours, setMatriculeNationalRechercheEnCours] = useState(false)
  const ecoleActive = useAuthStore((state) => state.activeSchool())

  const { data: classes } = useQuery({ queryKey: ['classes', 'select'], queryFn: () => fetchClasses() })
  const { data: niveaux } = useQuery({ queryKey: ['niveaux'], queryFn: () => fetchNiveaux() })
  const { data: trajets } = useQuery({ queryKey: ['bus-trajets', 'preinscription'], queryFn: fetchTrajets, enabled: busActif })
  const busTrajet = trajets?.find((trajet) => trajet.id === busTrajetId)

  const choisirEleve = (choix: Eleve) => {
    setModeSaisie('recherche')
    setEleve(choix)
    setChamps(Object.fromEntries(CHAMPS_ELEVE.map(([cle]) => [cle, String((choix as unknown as Record<string, unknown>)[cle] ?? '')])))
    setTuteurs(choix.tuteurs.length > 0 ? choix.tuteurs.map(tuteurDepuisEleve) : [])
    setClasseId(choix.classe?.id ?? null)
    setNiveauId(choix.classe ? classes?.find((c) => c.id === choix.classe!.id)?.niveau_id : undefined)
    setErreurMsg(null)
    setMatriculeNationalResultats([])
    setMatriculeNationalErreur(null)
  }

  const commencerNouveau = (nom: string) => {
    setModeSaisie('nouveau')
    setEleve(null)
    setChamps(Object.fromEntries(CHAMPS_ELEVE.map(([cle]) => [cle, cle === 'nom_complet' ? nom : ''])))
    setTuteurs([])
    setClasseId(null)
    setNiveauId(undefined)
    setErreurMsg(null)
    setMatriculeNationalResultats([])
    setMatriculeNationalErreur(null)
  }

  const matriculeNationalDisponible = eleve?.school?.type === 'secondaire' || (!eleve && (!ecoleActive || ecoleActive.type === 'secondaire'))

  const rechercherMatriculeNationalActuel = async () => {
    const nom = (champs.nom_complet ?? '').trim()
    if (!nom) {
      setMatriculeNationalErreur("Saisissez le nom complet de l'élève pour lancer la recherche.")
      return
    }

    setMatriculeNationalErreur(null)
    setMatriculeNationalRechercheEnCours(true)
    try {
      const resultats = await rechercherMatriculeNational(nom)
      setMatriculeNationalResultats(resultats)
      if (resultats.length === 0) setMatriculeNationalErreur('Aucun résultat trouvé pour ce nom.')
    } catch (err) {
      setMatriculeNationalResultats([])
      setMatriculeNationalErreur((err as ApiError).message || 'Impossible de rechercher le matricule national.')
    } finally {
      setMatriculeNationalRechercheEnCours(false)
    }
  }

  const appliquerMatriculeNational = (resultat: MatriculeNationalResult) => {
    if (!resultat.matricule_national) return
    setChamps((valeurs) => ({ ...valeurs, matricule_national: resultat.matricule_national ?? '' }))
    setMatriculeNationalResultats([])
    setMatriculeNationalErreur(null)
  }

  // Ce qui est réellement dû, pas seulement ce que le parent a annoncé — pour
  // que le montant à collecter reste visible même quand c'est quelqu'un
  // d'autre que le parent qui vient payer au guichet.
  const { data: dossier, isFetching: dossierEnChargement } = useQuery({
    queryKey: ['dossier-scolarite', eleve?.id],
    queryFn: () => fetchDossier(eleve!.id),
    enabled: !!eleve,
  })
  const montantNombre = montant || 0
  const resteApresPaiement = dossier ? dossier.reste_a_payer - montantNombre : null

  const majTuteur = (index: number, patch: Partial<TuteurForm>) => {
    setTuteurs((t) => t.map((tut, i) => (i === index ? { ...tut, ...patch } : tut)))
  }

  const ajouterTuteur = () => {
    setTuteurs((t) => [
      ...t,
      { nom_complet: '', telephones: telephonesParDefaut(), email: '', profession: '', lien_parente: '', is_principal: t.length === 0 },
    ])
  }

  const supprimerTuteur = (index: number) => {
    setTuteurs((t) => t.filter((_, i) => i !== index))
  }

  const enregistrer = async () => {
    if (!eleve && modeSaisie !== 'nouveau') return
    setEnvoi(true)
    setErreurMsg(null)
    try {
      const endpoint = eleve ? '/preinscriptions' : '/preinscriptions/nouveau'
      const { data } = await http.post<ApiResponse<PreinscriptionResume>>(endpoint, {
        ...(eleve ? { eleve_id: eleve.id } : {}),
        donnees_eleve: champs,
        donnees_tuteurs: tuteurs
          .filter((t) => t.nom_complet.trim() !== '')
          .map((t) => ({
            nom_complet: t.nom_complet,
            telephones: t.telephones.filter((tel) => tel.numero.trim() !== ''),
            email: t.email || undefined,
            profession: t.profession || undefined,
            lien_parente: t.lien_parente || undefined,
            is_principal: t.is_principal,
          })),
        classe_id: classeId ?? undefined,
        montant_verser: montantNombre > 0 ? montantNombre : undefined,
        mode_versement: montantNombre > 0 ? modePaiement : undefined,
        reference_externe: reference || undefined,
        bus: busActif && busTrajetId
          ? {
            trajet_id: busTrajetId,
            arret_id: busArretId ?? undefined,
            option_trajet: busOption,
            montant: busMontant > 0 ? busMontant : undefined,
          }
          : undefined,
      })

      succes('Préinscription enregistrée et validée.')
      if (data.data.id && (data.data.versement_id || data.data.bus_versement_id)) {
        ouvrirDocument(`/preinscriptions/${data.data.id}/recu`)
      }
      navigate('/preinscriptions')
    } catch (err) {
      const apiError = err as ApiError
      const eleveDejaPreinscrit = apiError.errors?.eleve_id?.[0]

      if (eleveDejaPreinscrit) {
        succes('Cet enfant est déjà préinscrit pour l’année active. Redirection vers le paiement des frais.')
        navigate(`/caisse/encaisser/${eleveDejaPreinscrit}`)
        return
      }

      setErreurMsg(apiError.message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Nouvelle préinscription"
        sousTitre="Réinscription d'un élève déjà connu, saisie et validée directement au guichet."
        icon={UserPlus}
        actions={
          <Button type="button" variant="secondary" onClick={() => navigate('/preinscriptions')}>
            <ArrowLeft className="h-4 w-4" />
            Retour
          </Button>
        }
      />

      <Card>
        <div className="flex flex-col gap-4">
          {!eleve && modeSaisie === 'recherche' ? (
            <>
              <p className="rounded-lg bg-cream-100 px-3 py-2 text-xs text-navy-600">
                Recherchez l'élève à réinscrire : ses informations et ses tuteurs se rechargeront automatiquement, prêts à être
                complétés.
              </p>
              <EleveAutocomplete onChoisir={choisirEleve} onNouveau={commencerNouveau} />
            </>
          ) : (
            <>
              <div className="flex items-center justify-between rounded-lg bg-green-50 px-3 py-2">
                {eleve ? (
                  <div>
                    <p className="text-sm font-semibold text-navy-900">{eleve.nom_complet}</p>
                    <p className="text-xs text-navy-500">{[eleve.matricule, eleve.classe?.nom].filter(Boolean).join(' · ')}</p>
                  </div>
                ) : (
                  <p className="text-sm font-semibold text-navy-900">Nouvel élève</p>
                )}
                <button
                  type="button"
                  onClick={() => {
                    setEleve(null)
                    setModeSaisie('recherche')
                  }}
                  className="text-xs font-medium text-navy-500 hover:text-navy-800"
                >
                  {eleve ? "Changer d'élève" : 'Rechercher un élève existant'}
                </button>
              </div>

              <div>
                <h3 className="mb-2 text-xs font-bold uppercase tracking-wide text-navy-500">Informations de l'élève</h3>
                <div className="grid grid-cols-2 gap-2.5">
                  {CHAMPS_ELEVE.map(([cle, libelle]) => (
                    <Input
                      key={cle}
                      label={libelle}
                      value={champs[cle] ?? ''}
                      onChange={(e) => setChamps((c) => ({ ...c, [cle]: e.target.value }))}
                    />
                  ))}
                </div>
                {matriculeNationalDisponible && (
                  <div className="mt-3 space-y-2">
                    <div className="flex items-end gap-2">
                      <div className="flex-1">
                        <Input
                          label="Matricule national"
                          placeholder="Identifiant officiel du secondaire"
                          value={champs.matricule_national ?? ''}
                          onChange={(e) => setChamps((c) => ({ ...c, matricule_national: e.target.value }))}
                        />
                      </div>
                      <Button
                        type="button"
                        variant="secondary"
                        onClick={rechercherMatriculeNationalActuel}
                        disabled={matriculeNationalRechercheEnCours}
                        className="shrink-0"
                      >
                        {matriculeNationalRechercheEnCours ? 'Recherche…' : 'Rechercher le matricule national'}
                      </Button>
                    </div>
                    {matriculeNationalErreur && <p className="text-xs font-medium text-red-500">{matriculeNationalErreur}</p>}
                    {matriculeNationalResultats.length > 0 && (
                      <div className="rounded-xl border border-navy-200 bg-cream-50 p-3">
                        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-navy-500">Résultats trouvés</p>
                        <div className="space-y-2">
                          {matriculeNationalResultats.map((resultat, index) => (
                            <button
                              type="button"
                              key={`${resultat.matricule_national ?? 'sans-matricule'}-${index}`}
                              onClick={() => appliquerMatriculeNational(resultat)}
                              className="flex w-full items-start justify-between gap-3 rounded-lg border border-navy-200 bg-white px-3 py-2 text-left hover:bg-cream-100"
                            >
                              <span className="min-w-0">
                                <span className="block font-medium text-navy-900">{resultat.fullname || resultat.etablissement || 'Résultat'}</span>
                                <span className="mt-1 block text-xs text-navy-500">
                                  {[resultat.classe, resultat.date_naissance, resultat.sexe].filter(Boolean).join(' · ')}
                                </span>
                              </span>
                              <span className="rounded-lg bg-navy-100 px-2 py-1 text-[11px] font-semibold text-navy-700">
                                {resultat.matricule_national || '—'}
                              </span>
                            </button>
                          ))}
                        </div>
                      </div>
                    )}
                  </div>
                )}
                <div className="mt-3">
                  <ClasseNiveauPicker
                    niveaux={niveaux}
                    classes={classes}
                    niveauId={niveauId}
                    onChangeNiveauId={(id) => {
                      setNiveauId(id)
                      setClasseId(null)
                    }}
                    classeId={classeId ?? undefined}
                    onChangeClasseId={(id) => setClasseId(id ?? null)}
                  />
                </div>
              </div>

              <div>
                <div className="mb-2 flex items-center justify-between">
                  <h3 className="text-xs font-bold uppercase tracking-wide text-navy-500">Tuteurs</h3>
                  <button
                    type="button"
                    onClick={ajouterTuteur}
                    className="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold text-navy-600 hover:bg-navy-50 hover:text-navy-800"
                  >
                    <Plus className="h-3.5 w-3.5" />
                    Ajouter un tuteur
                  </button>
                </div>

                {tuteurs.length === 0 && (
                  <p className="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    Aucun tuteur enregistré pour cet élève. Ajoutez-en un ci-dessus pour pouvoir le réinscrire.
                  </p>
                )}

                {tuteurs.length > 0 && (
                  <div className="flex flex-col gap-3">
                    {tuteurs.map((t, i) => (
                      <div key={i} className="grid grid-cols-2 gap-2.5 rounded-lg bg-cream-50 p-3">
                        <Input label="Nom complet" value={t.nom_complet} onChange={(e) => majTuteur(i, { nom_complet: e.target.value })} />
                        <Input label="Email" value={t.email} onChange={(e) => majTuteur(i, { email: e.target.value })} />
                        <Input label="Profession" value={t.profession} onChange={(e) => majTuteur(i, { profession: e.target.value })} />
                        <Input
                          label="Lien de parenté"
                          value={t.lien_parente}
                          onChange={(e) => majTuteur(i, { lien_parente: e.target.value })}
                        />
                        <div className="col-span-2">
                          <TelephonesEditor
                            telephones={t.telephones}
                            onChange={(telephones) => majTuteur(i, { telephones })}
                          />
                        </div>
                        <div className="col-span-2 flex items-center justify-between border-t border-navy-100 pt-2">
                          <label className="flex items-center gap-2 text-sm">
                            <input
                              type="checkbox"
                              checked={t.is_principal}
                              onChange={() => setTuteurs((ts) => ts.map((tut, j) => ({ ...tut, is_principal: j === i })))}
                              className="rounded border-navy-300"
                            />
                            <span className="text-navy-700">Tuteur principal</span>
                          </label>
                          <button
                            type="button"
                            onClick={() => supprimerTuteur(i)}
                            className="rounded-lg p-2 text-navy-400 hover:bg-red-100 hover:text-red-500"
                            title="Supprimer ce tuteur"
                          >
                            <Trash2 className="h-4 w-4" />
                          </button>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              <div>
                <h3 className="mb-2 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-navy-500">
                  <Receipt className="h-3.5 w-3.5" />
                  Versement (facultatif)
                </h3>

                {/* Le montant réellement dû, pas seulement ce que le parent a pu annoncer — indispensable
                    quand ce n'est pas lui qui vient payer, mais quelqu'un envoyé au guichet à sa place. */}
                {dossierEnChargement ? (
                  <p className="text-xs text-navy-400">Calcul du montant dû…</p>
                ) : dossier ? (
                  <dl className="mb-3 grid grid-cols-3 gap-2 rounded-xl bg-cream-100 p-3 text-center">
                    <div>
                      <dt className="text-[11px] uppercase tracking-wide text-navy-400">Total dû</dt>
                      <dd className="text-sm font-bold tabular-nums text-navy-700">{francs(dossier.total_du)}</dd>
                    </div>
                    <div>
                      <dt className="text-[11px] uppercase tracking-wide text-navy-400">Déjà versé</dt>
                      <dd className="text-sm font-bold tabular-nums text-green-600">{francs(dossier.total_paye)}</dd>
                    </div>
                    <div>
                      <dt className="text-[11px] uppercase tracking-wide text-navy-400">Reste à payer</dt>
                      <dd className="text-sm font-bold tabular-nums text-red-500">{francs(dossier.reste_a_payer)}</dd>
                    </div>
                  </dl>
                ) : null}

                <div className="grid grid-cols-3 gap-2.5">
                  <MontantInput label="Montant à encaisser" value={montant} onChange={setMontant} />
                  <Select label="Mode" value={modePaiement} onChange={(e) => setModePaiement(e.target.value as ModePaiement)}>
                    {MODES.map((m) => (
                      <option key={m.valeur} value={m.valeur}>
                        {m.libelle}
                      </option>
                    ))}
                  </Select>
                  <Input label="Référence" value={reference} onChange={(e) => setReference(e.target.value)} />
                </div>

                {montantNombre > 0 && resteApresPaiement !== null && (
                  <p className="mt-2 text-xs text-navy-500">
                    {resteApresPaiement > 0 ? (
                      <>Reste après ce versement : <span className="font-semibold">{francs(resteApresPaiement)}</span></>
                    ) : resteApresPaiement === 0 ? (
                      <span className="font-semibold text-green-600">Solde entièrement soldé.</span>
                    ) : (
                      <span className="font-semibold text-blue-600">Avance de {francs(-resteApresPaiement)}.</span>
                    )}
                    {' '}Un reçu sera généré et affiché à l'impression dès l'enregistrement.
                  </p>
                )}
              </div>

              <div>
                <label className="flex items-center gap-2 text-sm font-semibold text-navy-700">
                  <input type="checkbox" checked={busActif} onChange={(e) => setBusActif(e.target.checked)} className="rounded border-navy-300" />
                  Souscrire au transport scolaire
                </label>
                {busActif && (
                  <div className="mt-3 grid grid-cols-2 gap-2.5 rounded-lg bg-blue-50 p-3">
                    <Select
                      label="Trajet"
                      value={busTrajetId ?? ''}
                      onChange={(e) => {
                        const id = Number(e.target.value) || null
                        setBusTrajetId(id)
                        setBusArretId(null)
                      }}
                    >
                      <option value="">Sélectionner un trajet</option>
                      {trajets?.map((trajet) => <option key={trajet.id} value={trajet.id}>{trajet.nom}</option>)}
                    </Select>
                    <Select label="Option" value={busOption} onChange={(e) => setBusOption(e.target.value as OptionTrajet)}>
                      {Object.entries(LIBELLES_OPTION_TRAJET).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </Select>
                    <Select label="Arrêt" value={busArretId ?? ''} onChange={(e) => setBusArretId(Number(e.target.value) || null)}>
                      <option value="">Aucun arrêt précisé</option>
                      {busTrajet?.arrets.map((arret) => <option key={arret.id} value={arret.id}>{arret.nom}</option>)}
                    </Select>
                    <MontantInput
                      label={`Paiement bus (${busTrajet ? `${francs(tarifPourOption(busTrajet, busOption) ?? 0)} / mois` : 'tarif du trajet'})`}
                      value={busMontant}
                      onChange={setBusMontant}
                    />
                  </div>
                )}
              </div>

              {erreurMsg && <p className="text-sm text-red-500">{erreurMsg}</p>}

              <div className="mt-2 flex justify-end gap-2">
                <Button type="button" variant="secondary" onClick={() => navigate('/preinscriptions')} disabled={envoi}>
                  Annuler
                </Button>
                <Button type="button" onClick={enregistrer} disabled={envoi || tuteurs.length === 0}>
                  <UserPlus className="h-4 w-4" />
                  Enregistrer et valider
                </Button>
              </div>
            </>
          )}
        </div>
      </Card>
    </div>
  )
}
