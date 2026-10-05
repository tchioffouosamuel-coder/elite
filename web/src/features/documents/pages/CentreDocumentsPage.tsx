import { useMemo, useRef, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Calculator, ChevronDown, Download, FileStack, Search, X } from 'lucide-react'
import { clsx } from 'clsx'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Input, Select } from '@/shared/ui/Field'
import { Modal } from '@/shared/ui/Modal'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { telechargerFichier } from '@/shared/lib/download'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'
import {
  ENTETES_TOUTES_LES_ECOLES,
  fetchCatalogueDocuments,
  fetchCiblesDocuments,
  preparerPaquet,
  supprimerPaquet,
  traiterPaquet,
  type ConfigListe,
  type DocumentCatalogue,
  type EtatPaquet,
  type FormatDocument,
  type ParametreDocument,
  type ParametresDocuments,
  type Perimetre,
  type ResumePaquet,
} from '@/features/documents/api'
import { ConfigListeField } from '@/features/documents/pages/ConfigListeField'

/** Au-delà, une confirmation est demandée avant de lancer la génération. */
const SEUIL_CONFIRMATION = 300

const LISTE_VIDE: ConfigListe = { titre_fr: '', titre_en: '', colonnes: [] }

const MOIS = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre']

function aujourdhui(): string {
  const d = new Date()
  const p = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`
}

function premierDuMois(): string {
  return aujourdhui().slice(0, 8) + '01'
}

/**
 * Centre de documents (super administrateur) : n'importe quel document de
 * la plateforme — listes, listes personnalisées, bulletins, rapports,
 * bilans, fiches, reçus, modèles d'import… — dans tous ses formats, à
 * l'unité ou en paquet ZIP, sur un périmètre choisi dans la hiérarchie
 * Toutes les écoles › École › Sous-système › Niveau › Classe.
 *
 * Le paquet est produit côté serveur par lots successifs (cf.
 * PaquetDocumentsService) : la page enchaîne les appels et affiche la
 * progression, puis télécharge le ZIP — ou le fichier seul s'il n'y en a
 * qu'un.
 */
export function CentreDocumentsPage() {
  const catalogue = useQuery({ queryKey: ['documents-catalogue'], queryFn: fetchCatalogueDocuments, refetchOnWindowFocus: false })

  const [perimetre, setPerimetre] = useState<Perimetre>({})
  const [selection, setSelection] = useState<Record<string, FormatDocument[]>>({})
  const [recherche, setRecherche] = useState('')
  const [replies, setReplies] = useState<Record<string, boolean>>({})
  const [parametres, setParametres] = useState<ParametresDocuments>({
    trimestre: 'actif',
    annee: 'active',
    du: premierDuMois(),
    au: aujourdhui(),
    mois: new Date().getMonth() + 1,
    annee_paie: new Date().getFullYear(),
    date: aujourdhui(),
    semaine: aujourdhui(),
    liste_classe: LISTE_VIDE,
    liste_personnel: LISTE_VIDE,
    liste_transport: LISTE_VIDE,
  })
  const [resume, setResume] = useState<ResumePaquet | null>(null)
  const [estimation, setEstimation] = useState(false)
  const [progression, setProgression] = useState<{ token: string; etat: EtatPaquet } | null>(null)
  const annule = useRef(false)

  const data = catalogue.data
  const ecole = data?.ecoles.find((e) => e.id === perimetre.school_id)
  const ecolesDuPerimetre = useMemo(() => (data ? (ecole ? [ecole] : data.ecoles) : []), [data, ecole])

  const niveaux = ecole?.niveaux.filter((n) => !perimetre.sous_systeme_id || n.sous_systeme_ids.includes(perimetre.sous_systeme_id)) ?? []
  const classes =
    ecole?.classes.filter(
      (c) => (!perimetre.sous_systeme_id || c.sous_systeme_id === perimetre.sous_systeme_id) && (!perimetre.niveau_id || c.niveau_id === perimetre.niveau_id),
    ) ?? []

  const docsSelectionnes = useMemo(() => (data ? data.documents.filter((d) => selection[d.code]?.length) : []), [data, selection])
  const besoins = useMemo(() => new Set<ParametreDocument>(docsSelectionnes.flatMap((d) => d.parametres)), [docsSelectionnes])
  const unites = new Set(docsSelectionnes.map((d) => d.unite))

  const eleves = useQuery({
    queryKey: ['documents-cibles', 'eleve', perimetre.classe_id],
    queryFn: () => fetchCiblesDocuments('eleve', perimetre.classe_id!),
    enabled: Boolean(perimetre.classe_id) && (unites.has('eleve') || unites.has('archive_eleve')),
  })
  const agents = useQuery({
    queryKey: ['documents-cibles', 'personnel', perimetre.school_id],
    queryFn: () => fetchCiblesDocuments('personnel', perimetre.school_id!),
    enabled: Boolean(perimetre.school_id) && (unites.has('personnel') || unites.has('bulletin_paie')),
  })

  /** Document produit par au moins une école du périmètre (bulletins primaire/secondaire…). */
  const applicable = (d: DocumentCatalogue) => !d.types_ecole || ecolesDuPerimetre.some((e) => d.types_ecole!.includes(e.type))

  const changerPerimetre = (modif: Perimetre) => {
    setPerimetre(modif)
    setResume(null)
  }

  const basculerDocument = (d: DocumentCatalogue) => {
    setSelection((s) => {
      const copie = { ...s }
      if (copie[d.code]?.length) delete copie[d.code]
      else copie[d.code] = [d.formats.includes('pdf') ? 'pdf' : d.formats[0]]
      return copie
    })
    setResume(null)
  }

  const basculerFormat = (d: DocumentCatalogue, f: FormatDocument) => {
    setSelection((s) => {
      const actuels = s[d.code] ?? []
      const formats = actuels.includes(f) ? actuels.filter((x) => x !== f) : [...actuels, f]
      const copie = { ...s }
      if (formats.length) copie[d.code] = formats
      else delete copie[d.code]
      return copie
    })
    setResume(null)
  }

  const documentsFiltres = useMemo(() => {
    if (!data) return []
    const terme = recherche.trim().toLowerCase()
    return data.documents.filter((d) => !terme || d.libelle.toLowerCase().includes(terme) || d.unite_libelle.includes(terme))
  }, [data, recherche])

  const parCategorie = useMemo(() => {
    const groupes = new Map<string, DocumentCatalogue[]>()
    for (const d of documentsFiltres) groupes.set(d.categorie, [...(groupes.get(d.categorie) ?? []), d])
    return groupes
  }, [documentsFiltres])

  const toutSelectionner = (docs: DocumentCatalogue[], tousFormats: boolean) => {
    setSelection((s) => {
      const copie = { ...s }
      for (const d of docs.filter(applicable)) copie[d.code] = tousFormats ? [...d.formats] : [d.formats.includes('pdf') ? 'pdf' : d.formats[0]]
      return copie
    })
    setResume(null)
  }

  const deselectionner = (docs: DocumentCatalogue[]) => {
    setSelection((s) => {
      const copie = { ...s }
      for (const d of docs) delete copie[d.code]
      return copie
    })
    setResume(null)
  }

  const requete = () => ({
    documents: docsSelectionnes.map((d) => ({ code: d.code, formats: selection[d.code] })),
    // Seuls les réglages utiles partent : une liste personnalisée vide ne
    // doit pas faire échouer un paquet qui n'en contient pas.
    parametres: Object.fromEntries(
      Object.entries(parametres).filter(([cle]) => !cle.startsWith('liste_') || besoins.has(cle as ParametreDocument)),
    ) as ParametresDocuments,
  })

  const estimer = async () => {
    setEstimation(true)
    try {
      const { documents, parametres: p } = requete()
      setResume(await preparerPaquet(documents, perimetre, p, true))
    } catch (e) {
      erreur((e as ApiError).message)
    } finally {
      setEstimation(false)
    }
  }

  const generer = async () => {
    const { documents, parametres: p } = requete()
    let token: string | undefined

    try {
      const estime = resume ?? (await preparerPaquet(documents, perimetre, p, true))
      if (estime.total === 0) {
        erreur('Aucun document à produire pour ce périmètre.')
        return
      }
      if (
        estime.total > SEUIL_CONFIRMATION &&
        !(await confirmer({
          titre: `${estime.total} fichiers à générer`,
          message: 'La génération peut prendre plusieurs minutes. Gardez cette page ouverte jusqu’au téléchargement.',
          action: 'Lancer la génération',
          destructif: false,
        }))
      ) {
        return
      }

      const paquet = await preparerPaquet(documents, perimetre, p, false)
      token = paquet.token!
      annule.current = false
      let etat: EtatPaquet = { total: paquet.total, traites: 0, fichiers: 0, erreurs: 0, termine: false, derniere_erreur: null }
      setProgression({ token, etat })

      while (!etat.termine) {
        if (annule.current) {
          await supprimerPaquet(token)
          setProgression(null)
          return
        }
        etat = await traiterPaquet(token)
        setProgression({ token, etat })
      }

      if (etat.fichiers === 0) {
        erreur(`Aucun fichier n'a pu être produit${etat.derniere_erreur ? ` : ${etat.derniere_erreur}` : '.'}`)
        await supprimerPaquet(token)
        setProgression(null)
        return
      }

      await telechargerFichier(`/documents/paquets/${token}/telecharger`, undefined, 'documents.zip', ENTETES_TOUTES_LES_ECOLES)
      setProgression(null)
      succes(
        etat.erreurs > 0
          ? `${etat.fichiers} fichier(s) générés, ${etat.erreurs} non générés (détail dans « _documents-non-generes.txt »).`
          : `${etat.fichiers} fichier(s) générés.`,
      )
    } catch (e) {
      setProgression(null)
      erreur((e as ApiError).message)
    }
  }

  if (catalogue.isLoading) return <Spinner />
  if (catalogue.isError || !data) return <ErrorState />

  const nbFichiersParDoc = (code: string) => resume?.par_document[code]
  const totalSelectionnes = docsSelectionnes.length

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Centre de documents"
        sousTitre={`${data.documents.length} documents disponibles — à l'unité ou en paquet ZIP`}
        icon={FileStack}
      />

      {/* ─── Périmètre ─── */}
      <Card>
        <h2 className="mb-3 text-sm font-semibold text-navy-800">1. Périmètre</h2>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <Select
            label="École"
            value={perimetre.school_id ? String(perimetre.school_id) : ''}
            onChange={(e) => changerPerimetre(e.target.value ? { school_id: Number(e.target.value) } : {})}
          >
            <option value="">Toutes les écoles</option>
            {data.ecoles.map((e) => (
              <option key={e.id} value={String(e.id)}>
                {e.nom}
              </option>
            ))}
          </Select>
          <Select
            label="Sous-système"
            disabled={!ecole || ecole.sous_systemes.length === 0}
            value={perimetre.sous_systeme_id ? String(perimetre.sous_systeme_id) : ''}
            onChange={(e) => changerPerimetre({ school_id: perimetre.school_id, sous_systeme_id: Number(e.target.value) || undefined })}
          >
            <option value="">Tous les sous-systèmes</option>
            {ecole?.sous_systemes.map((s) => (
              <option key={s.id} value={String(s.id)}>
                {s.nom}
              </option>
            ))}
          </Select>
          <Select
            label="Niveau"
            disabled={!ecole || niveaux.length === 0}
            value={perimetre.niveau_id ? String(perimetre.niveau_id) : ''}
            onChange={(e) =>
              changerPerimetre({ school_id: perimetre.school_id, sous_systeme_id: perimetre.sous_systeme_id, niveau_id: Number(e.target.value) || undefined })
            }
          >
            <option value="">Tous les niveaux</option>
            {niveaux.map((n) => (
              <option key={n.id} value={String(n.id)}>
                {n.nom}
              </option>
            ))}
          </Select>
          <Select
            label="Classe"
            disabled={!ecole || classes.length === 0}
            value={perimetre.classe_id ? String(perimetre.classe_id) : ''}
            onChange={(e) => changerPerimetre({ ...perimetre, eleve_id: undefined, classe_id: Number(e.target.value) || undefined })}
          >
            <option value="">Toutes les classes</option>
            {classes.map((c) => (
              <option key={c.id} value={String(c.id)}>
                {c.nom}
              </option>
            ))}
          </Select>
        </div>

        {(eleves.data || agents.data) && (
          <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
            {eleves.data && (
              <Select
                label="Élève (documents individuels)"
                value={perimetre.eleve_id ? String(perimetre.eleve_id) : ''}
                onChange={(e) => changerPerimetre({ ...perimetre, eleve_id: Number(e.target.value) || undefined })}
              >
                <option value="">Tous les élèves de la classe</option>
                {eleves.data.map((e) => (
                  <option key={e.id} value={String(e.id)}>
                    {e.nom}
                    {e.matricule ? ` — ${e.matricule}` : ''}
                  </option>
                ))}
              </Select>
            )}
            {agents.data && (
              <Select
                label="Agent (documents individuels)"
                value={perimetre.personnel_id ? String(perimetre.personnel_id) : ''}
                onChange={(e) => changerPerimetre({ ...perimetre, personnel_id: Number(e.target.value) || undefined })}
              >
                <option value="">Tout le personnel de l'école</option>
                {agents.data.map((a) => (
                  <option key={a.id} value={String(a.id)}>
                    {a.nom}
                  </option>
                ))}
              </Select>
            )}
          </div>
        )}

        <p className="mt-3 text-xs text-navy-400">
          {[
            ecole?.nom ?? 'Toutes les écoles',
            ecole?.sous_systemes.find((s) => s.id === perimetre.sous_systeme_id)?.nom,
            ecole?.niveaux.find((n) => n.id === perimetre.niveau_id)?.nom,
            ecole?.classes.find((c) => c.id === perimetre.classe_id)?.nom,
          ]
            .filter(Boolean)
            .join(' › ')}
          {' — '}Un document « par école » restreint à une classe est produit pour chaque classe visée ; sinon pour l'école entière.
        </p>
      </Card>

      {/* ─── Documents ─── */}
      <Card>
        <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
          <h2 className="text-sm font-semibold text-navy-800">2. Documents {totalSelectionnes > 0 && <span className="text-gold-600">({totalSelectionnes} sélectionné{totalSelectionnes > 1 ? 's' : ''})</span>}</h2>
          <div className="flex flex-wrap items-center gap-2">
            <div className="w-64">
              <Input icon={Search} placeholder="Rechercher un document…" value={recherche} onChange={(e) => setRecherche(e.target.value)} />
            </div>
            <Button type="button" size="sm" variant="secondary" onClick={() => toutSelectionner(documentsFiltres, true)}>
              Tout, tous formats
            </Button>
            {totalSelectionnes > 0 && (
              <Button type="button" size="sm" variant="ghost" onClick={() => deselectionner(data.documents)}>
                <X className="h-3.5 w-3.5" />
                Vider
              </Button>
            )}
          </div>
        </div>

        <div className="flex flex-col gap-3">
          {[...parCategorie.entries()].map(([categorie, docs]) => {
            const replie = replies[categorie] && !recherche
            const nbChoisis = docs.filter((d) => selection[d.code]?.length).length
            return (
              <section key={categorie} className="rounded-xl border border-navy-100">
                <div className="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                  <button
                    type="button"
                    className="flex items-center gap-2 text-sm font-semibold text-navy-800"
                    onClick={() => setReplies((r) => ({ ...r, [categorie]: !r[categorie] }))}
                  >
                    <ChevronDown className={clsx('h-4 w-4 transition-transform', replie && '-rotate-90')} />
                    {data.categories[categorie] ?? categorie}
                    <span className="text-xs font-normal text-navy-400">
                      {nbChoisis > 0 ? `${nbChoisis}/` : ''}
                      {docs.length}
                    </span>
                  </button>
                  <div className="flex gap-1">
                    <Button type="button" size="sm" variant="ghost" onClick={() => toutSelectionner(docs, false)}>
                      Tout cocher
                    </Button>
                    {nbChoisis > 0 && (
                      <Button type="button" size="sm" variant="ghost" onClick={() => deselectionner(docs)}>
                        Décocher
                      </Button>
                    )}
                  </div>
                </div>

                {!replie && (
                  <ul className="divide-y divide-navy-50 border-t border-navy-50">
                    {docs.map((d) => {
                      const formats = selection[d.code] ?? []
                      const ok = applicable(d)
                      const nb = nbFichiersParDoc(d.code)
                      return (
                        <li key={d.code} className={clsx('flex flex-wrap items-center gap-3 px-3 py-2', !ok && 'opacity-50')}>
                          <label className="flex min-w-0 flex-1 cursor-pointer items-center gap-2.5">
                            <input
                              type="checkbox"
                              disabled={!ok}
                              checked={formats.length > 0}
                              onChange={() => basculerDocument(d)}
                              className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500"
                            />
                            <span className="min-w-0">
                              <span className="block truncate text-sm text-navy-800">{d.libelle}</span>
                              <span className="text-xs text-navy-400">
                                {d.unite_libelle}
                                {d.filtres.includes('classe_id') && ' (ou par classe)'}
                                {!ok && ` — ${d.types_ecole?.join(' / ')} uniquement`}
                                {nb !== undefined && <span className="font-semibold text-gold-600"> · {nb} fichier{nb > 1 ? 's' : ''}</span>}
                              </span>
                            </span>
                          </label>
                          <div className="flex gap-1">
                            {d.formats.map((f) => (
                              <button
                                key={f}
                                type="button"
                                disabled={!ok}
                                onClick={() => basculerFormat(d, f)}
                                className={clsx(
                                  'rounded-lg px-2 py-1 text-xs font-semibold ring-1 ring-inset transition-colors',
                                  formats.includes(f) ? 'bg-navy-800 text-cream-50 ring-navy-800' : 'bg-white text-navy-500 ring-navy-200 hover:bg-cream-50',
                                )}
                              >
                                {data.formats[f]}
                              </button>
                            ))}
                          </div>
                        </li>
                      )
                    })}
                  </ul>
                )}
              </section>
            )
          })}
        </div>
      </Card>

      {/* ─── Paramètres ─── */}
      {besoins.size > 0 && (
        <Card>
          <h2 className="mb-3 text-sm font-semibold text-navy-800">3. Paramètres des documents choisis</h2>
          <div className="flex flex-col gap-4">
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
              {besoins.has('trimestre') && (
                <Select label="Trimestre" value={parametres.trimestre ?? 'actif'} onChange={(e) => setParametres((p) => ({ ...p, trimestre: e.target.value }))}>
                  <option value="actif">Trimestre en cours de chaque école</option>
                  {data.trimestres.map((t) => (
                    <option key={t.ordre} value={String(t.ordre)}>
                      {t.libelle}
                    </option>
                  ))}
                </Select>
              )}
              {(besoins.has('annee') || besoins.has('trimestre')) && (
                <Select label="Année scolaire" value={parametres.annee ?? 'active'} onChange={(e) => setParametres((p) => ({ ...p, annee: e.target.value }))}>
                  <option value="active">Année active de chaque école</option>
                  {data.annees.map((a) => (
                    <option key={a.libelle} value={a.libelle}>
                      {a.libelle}
                      {a.archivee ? ' (archivée)' : ''}
                    </option>
                  ))}
                </Select>
              )}
              {besoins.has('periode') && (
                <>
                  <Input label="Du" type="date" value={parametres.du ?? ''} onChange={(e) => setParametres((p) => ({ ...p, du: e.target.value }))} />
                  <Input label="Au" type="date" value={parametres.au ?? ''} onChange={(e) => setParametres((p) => ({ ...p, au: e.target.value }))} />
                </>
              )}
              {besoins.has('paie') && (
                <>
                  <Select label="Mois de paie" value={String(parametres.mois ?? '')} onChange={(e) => setParametres((p) => ({ ...p, mois: Number(e.target.value) }))}>
                    {MOIS.map((m, i) => (
                      <option key={m} value={String(i + 1)}>
                        {m}
                      </option>
                    ))}
                  </Select>
                  <Input
                    label="Année de paie"
                    type="number"
                    value={String(parametres.annee_paie ?? '')}
                    onChange={(e) => setParametres((p) => ({ ...p, annee_paie: Number(e.target.value) }))}
                  />
                </>
              )}
              {besoins.has('date') && (
                <Input label="Date (fiche de présence)" type="date" value={parametres.date ?? ''} onChange={(e) => setParametres((p) => ({ ...p, date: e.target.value }))} />
              )}
              {besoins.has('semaine') && (
                <Input label="Semaine du (fiche d'appel)" type="date" value={parametres.semaine ?? ''} onChange={(e) => setParametres((p) => ({ ...p, semaine: e.target.value }))} />
              )}
            </div>

            {besoins.has('liste_classe') && (
              <ConfigListeField
                titre="Liste de classe personnalisée"
                valeur={parametres.liste_classe ?? LISTE_VIDE}
                onChange={(v) => setParametres((p) => ({ ...p, liste_classe: v }))}
                colonnes={data.colonnes.liste_classe}
                modeles={data.modeles_listes.liste_classe}
                avecMoyenne
              />
            )}
            {besoins.has('liste_personnel') && (
              <ConfigListeField
                titre="Liste du personnel personnalisée"
                valeur={parametres.liste_personnel ?? LISTE_VIDE}
                onChange={(v) => setParametres((p) => ({ ...p, liste_personnel: v }))}
                colonnes={data.colonnes.liste_personnel}
                modeles={data.modeles_listes.liste_personnel}
              />
            )}
            {besoins.has('liste_transport') && (
              <ConfigListeField
                titre="Liste du transport personnalisée"
                valeur={parametres.liste_transport ?? LISTE_VIDE}
                onChange={(v) => setParametres((p) => ({ ...p, liste_transport: v }))}
                colonnes={data.colonnes.liste_transport}
                modeles={data.modeles_listes.liste_transport}
              />
            )}
          </div>
        </Card>
      )}

      {/* ─── Estimation ─── */}
      {resume && (
        <Card>
          <h2 className="mb-2 text-sm font-semibold text-navy-800">
            {resume.total} fichier{resume.total > 1 ? 's' : ''} {resume.total > 1 ? 'seront produits (paquet ZIP)' : 'sera produit'}
          </h2>
          {resume.apercu.length > 0 && (
            <ul className="font-mono text-xs text-navy-500">
              {resume.apercu.map((ligne) => (
                <li key={ligne} className="truncate">
                  {ligne}
                </li>
              ))}
              {resume.total > resume.apercu.length && <li>… et {resume.total - resume.apercu.length} autre(s)</li>}
            </ul>
          )}
        </Card>
      )}

      {/* ─── Barre d'action ─── */}
      <div className="sticky bottom-3 z-20 rounded-2xl border border-navy-100 bg-white/95 px-4 py-3 shadow-lifted backdrop-blur-sm">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <p className="text-sm text-navy-600">
            {totalSelectionnes === 0
              ? 'Cochez au moins un document.'
              : `${totalSelectionnes} document${totalSelectionnes > 1 ? 's' : ''}, ${docsSelectionnes.reduce((n, d) => n + selection[d.code].length, 0)} format(s)`}
          </p>
          <div className="flex gap-2">
            <Button type="button" variant="secondary" disabled={totalSelectionnes === 0 || estimation} onClick={estimer}>
              <Calculator className="h-4 w-4" />
              {estimation ? 'Calcul…' : 'Estimer'}
            </Button>
            <Button type="button" disabled={totalSelectionnes === 0 || progression !== null} onClick={generer}>
              <Download className="h-4 w-4" />
              Générer
            </Button>
          </div>
        </div>
      </div>

      {progression && <ProgressionModal etat={progression.etat} onAnnuler={() => (annule.current = true)} />}
    </div>
  )
}

function ProgressionModal({ etat, onAnnuler }: { etat: EtatPaquet; onAnnuler: () => void }) {
  const pourcentage = etat.total > 0 ? Math.round((etat.traites / etat.total) * 100) : 0

  return (
    <Modal title="Génération des documents" onClose={onAnnuler}>
      <div className="flex flex-col gap-4">
        <div className="h-3 overflow-hidden rounded-full bg-navy-50">
          <div className="h-full rounded-full bg-gold-500 transition-all duration-500" style={{ width: `${pourcentage}%` }} />
        </div>
        <p className="text-sm text-navy-700">
          {etat.termine ? 'Préparation du téléchargement…' : `${etat.traites} / ${etat.total} traités (${pourcentage} %)`}
        </p>
        <div className="flex flex-wrap gap-2">
          <Badge tone="green">{etat.fichiers} générés</Badge>
          {etat.erreurs > 0 && <Badge tone="red">{etat.erreurs} non générés</Badge>}
        </div>
        {etat.derniere_erreur && <p className="text-xs text-red-600">Dernier échec : {etat.derniere_erreur}</p>}
        <p className="text-xs text-navy-400">Gardez cette page ouverte. Les documents non générés sont détaillés dans le ZIP.</p>
        {!etat.termine && (
          <div className="flex justify-end">
            <Button type="button" variant="secondary" onClick={onAnnuler}>
              Annuler
            </Button>
          </div>
        )}
      </div>
    </Modal>
  )
}
