import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, Search, Receipt, UserPlus } from 'lucide-react'
import { http } from '@/shared/lib/http'
import type { ApiResponse } from '@/shared/types/api'
import { francs, fetchDossier, MODES, type ModePaiement } from '@/features/finance/api'
import { rechercheGlobaleEleves, type Eleve } from '@/features/eleves/api'
import { fetchClasses } from '@/features/classes/api'
import { CHAMPS_ELEVE, type PreinscriptionResume } from '@/features/eleves/pages/PreinscriptionsAdminPage'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Input, MontantInput, Select } from '@/shared/ui/Field'
import { ouvrirDocument } from '@/shared/lib/download'
import { succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

interface TuteurForm {
  nom_complet: string
  telephone: string
  email: string
  profession: string
  lien_parente: string
  is_principal: boolean
}

function tuteurDepuisEleve(t: Eleve['tuteurs'][number]): TuteurForm {
  const telephonePrincipal = t.telephones.find((tel) => tel.is_principal)?.numero ?? t.telephones[0]?.numero ?? t.telephone ?? ''
  return {
    nom_complet: t.nom_complet,
    telephone: telephonePrincipal,
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
function EleveAutocomplete({ onChoisir }: { onChoisir: (eleve: Eleve) => void }) {
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

  const afficherSuggestions = ouvert && termeDebounce.length >= 2 && ((suggestions?.length ?? 0) > 0 || isFetching)

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
  const [champs, setChamps] = useState<Record<string, string>>({})
  const [tuteurs, setTuteurs] = useState<TuteurForm[]>([])
  const [montant, setMontant] = useState(0)
  const [mode, setMode] = useState<ModePaiement>('especes')
  const [reference, setReference] = useState('')
  const [classeId, setClasseId] = useState<number | null>(null)
  const [envoi, setEnvoi] = useState(false)
  const [erreurMsg, setErreurMsg] = useState<string | null>(null)

  const { data: classes } = useQuery({ queryKey: ['classes', 'select'], queryFn: () => fetchClasses() })

  const choisirEleve = (choix: Eleve) => {
    setEleve(choix)
    setChamps(Object.fromEntries(CHAMPS_ELEVE.map(([cle]) => [cle, String((choix as unknown as Record<string, unknown>)[cle] ?? '')])))
    setTuteurs(choix.tuteurs.length > 0 ? choix.tuteurs.map(tuteurDepuisEleve) : [])
    setClasseId(choix.classe?.id ?? null)
    setErreurMsg(null)
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

  const enregistrer = async () => {
    if (!eleve) return
    setEnvoi(true)
    setErreurMsg(null)
    try {
      const { data } = await http.post<ApiResponse<PreinscriptionResume>>('/preinscriptions', {
        eleve_id: eleve.id,
        donnees_eleve: champs,
        donnees_tuteurs: tuteurs
          .filter((t) => t.nom_complet.trim() !== '')
          .map((t) => ({
            nom_complet: t.nom_complet,
            telephone: t.telephone || undefined,
            email: t.email || undefined,
            profession: t.profession || undefined,
            lien_parente: t.lien_parente || undefined,
            is_principal: t.is_principal,
          })),
        classe_id: classeId ?? undefined,
        montant_verser: montantNombre > 0 ? montantNombre : undefined,
        mode_versement: montantNombre > 0 ? mode : undefined,
        reference_externe: reference || undefined,
      })

      succes('Préinscription enregistrée et validée.')
      if (data.data.versement_id) {
        ouvrirDocument(`/versements/${data.data.versement_id}/recu`)
      }
      navigate('/preinscriptions')
    } catch (err) {
      setErreurMsg((err as ApiError).message)
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
          {!eleve ? (
            <>
              <p className="rounded-lg bg-cream-100 px-3 py-2 text-xs text-navy-600">
                Recherchez l'élève à réinscrire : ses informations et ses tuteurs se rechargeront automatiquement, prêts à être
                complétés.
              </p>
              <EleveAutocomplete onChoisir={choisirEleve} />
            </>
          ) : (
            <>
              <div className="flex items-center justify-between rounded-lg bg-green-50 px-3 py-2">
                <div>
                  <p className="text-sm font-semibold text-navy-900">{eleve.nom_complet}</p>
                  <p className="text-xs text-navy-500">{[eleve.matricule, eleve.classe?.nom].filter(Boolean).join(' · ')}</p>
                </div>
                <button type="button" onClick={() => setEleve(null)} className="text-xs font-medium text-navy-500 hover:text-navy-800">
                  Changer d'élève
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
                  <Select
                    label="Classe"
                    value={classeId ?? ''}
                    onChange={(e) => setClasseId(e.target.value ? Number(e.target.value) : null)}
                  >
                    <option value="">Sélectionner…</option>
                    {classes?.map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.nom}
                      </option>
                    ))}
                  </Select>
                </div>
              </div>

              <div>
                <h3 className="mb-2 text-xs font-bold uppercase tracking-wide text-navy-500">Tuteurs</h3>
                {tuteurs.length === 0 ? (
                  <p className="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    Aucun tuteur enregistré pour cet élève. Ajoutez-en un depuis sa fiche avant de le réinscrire.
                  </p>
                ) : (
                  <div className="flex flex-col gap-3">
                    {tuteurs.map((t, i) => (
                      <div key={i} className="grid grid-cols-2 gap-2.5 rounded-lg bg-cream-50 p-3">
                        <Input label="Nom complet" value={t.nom_complet} onChange={(e) => majTuteur(i, { nom_complet: e.target.value })} />
                        <Input label="Téléphone" value={t.telephone} onChange={(e) => majTuteur(i, { telephone: e.target.value })} />
                        <Input label="Email" value={t.email} onChange={(e) => majTuteur(i, { email: e.target.value })} />
                        <Input label="Profession" value={t.profession} onChange={(e) => majTuteur(i, { profession: e.target.value })} />
                        <Input
                          label="Lien de parenté"
                          value={t.lien_parente}
                          onChange={(e) => majTuteur(i, { lien_parente: e.target.value })}
                        />
                        <label className="flex items-center gap-2 self-end pb-2 text-sm">
                          <input
                            type="checkbox"
                            checked={t.is_principal}
                            onChange={() => setTuteurs((ts) => ts.map((tut, j) => ({ ...tut, is_principal: j === i })))}
                            className="rounded border-navy-300"
                          />
                          <span className="text-navy-700">Tuteur principal</span>
                        </label>
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
                  <Select label="Mode" value={mode} onChange={(e) => setMode(e.target.value as ModePaiement)}>
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
