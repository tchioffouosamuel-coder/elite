import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { IdCard, Pencil, Search, Trash2 } from 'lucide-react'
import {
  fetchMatriculesNationaux,
  majMatriculeNational,
  type MatriculeNationalLigne,
} from '@/features/matriculesNationaux/api'
import { rechercherMatriculeNational, type MatriculeNationalResult } from '@/features/eleves/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { ImportExportBar } from '@/shared/ui/ImportExportBar'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { Modal } from '@/shared/ui/Modal'
import { Input } from '@/shared/ui/Field'
import { confirmer, succes, erreur } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

/** Modifier ou effacer (valeur vide) le matricule national d'un seul élève. */
function ModifierMatriculeModal({
  ligne,
  onClose,
  onSaved,
}: {
  ligne: MatriculeNationalLigne
  onClose: () => void
  onSaved: () => void
}) {
  const [valeur, setValeur] = useState(ligne.matricule_national ?? '')
  const [enCours, setEnCours] = useState(false)

  const enregistrer = async () => {
    setEnCours(true)
    try {
      await majMatriculeNational(ligne.id, valeur.trim())
      succes(valeur.trim() ? 'Matricule national mis à jour.' : 'Matricule national effacé.')
      onSaved()
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setEnCours(false)
    }
  }

  return (
    <Modal title={`Matricule national — ${ligne.nom_complet}`} onClose={onClose}>
      <div className="flex flex-col gap-4">
        <Input
          label="Matricule national"
          value={valeur}
          onChange={(e) => setValeur(e.target.value)}
          placeholder="Laisser vide pour effacer"
          autoFocus
        />
        <div className="flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Annuler
          </Button>
          <Button type="button" onClick={enregistrer} disabled={enCours}>
            Enregistrer
          </Button>
        </div>
      </div>
    </Modal>
  )
}

/** Rechercher le matricule national d'un élève dans la base du ministère et l'appliquer. */
function RechercherMatriculeModal({
  ligne,
  onClose,
  onApplique,
}: {
  ligne: MatriculeNationalLigne
  onClose: () => void
  onApplique: () => void
}) {
  const [recherche, setRecherche] = useState(ligne.nom_complet)
  const [resultats, setResultats] = useState<MatriculeNationalResult[]>([])
  const [erreurMsg, setErreurMsg] = useState<string | null>(null)
  const [enCours, setEnCours] = useState(false)

  const rechercher = async () => {
    const nom = recherche.trim()
    if (!nom) {
      setErreurMsg("Saisissez le nom complet de l'élève pour lancer la recherche.")
      return
    }

    setErreurMsg(null)
    setEnCours(true)
    try {
      const trouves = await rechercherMatriculeNational(nom)
      setResultats(trouves)
      if (trouves.length === 0) setErreurMsg('Aucun résultat trouvé pour ce nom.')
    } catch (err) {
      setResultats([])
      setErreurMsg((err as ApiError).message || 'Impossible de rechercher le matricule national pour le moment.')
    } finally {
      setEnCours(false)
    }
  }

  const appliquer = async (resultat: MatriculeNationalResult) => {
    if (!resultat.matricule_national) return
    try {
      await majMatriculeNational(ligne.id, resultat.matricule_national)
      succes('Matricule national enregistré.')
      onApplique()
    } catch (err) {
      setErreurMsg((err as ApiError).message)
    }
  }

  return (
    <Modal title={`Rechercher le matricule national — ${ligne.nom_complet}`} onClose={onClose}>
      <div className="space-y-4">
        <div className="flex items-end gap-2">
          <div className="flex-1">
            <Input
              label="Nom complet"
              icon={Search}
              value={recherche}
              onChange={(e) => setRecherche(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter') void rechercher()
              }}
              autoFocus
            />
          </div>
          <Button type="button" onClick={() => void rechercher()} disabled={enCours}>
            {enCours ? 'Recherche…' : 'Rechercher'}
          </Button>
        </div>
        {erreurMsg && <p className="text-xs font-medium text-red-500">{erreurMsg}</p>}
        {resultats.length > 0 && (
          <div className="space-y-2">
            <p className="text-xs font-semibold uppercase tracking-wide text-navy-500">Résultats trouvés</p>
            {resultats.map((resultat, index) => (
              <button
                type="button"
                key={`${resultat.matricule_national ?? 'sans-matricule'}-${index}`}
                onClick={() => void appliquer(resultat)}
                className="flex w-full items-start justify-between gap-3 rounded-lg border border-navy-200 bg-white px-3 py-2 text-left hover:bg-cream-100"
              >
                <span className="min-w-0">
                  <span className="block font-medium text-navy-900">{resultat.fullname || resultat.etablissement || 'Résultat'}</span>
                  <span className="mt-1 block text-xs text-navy-500">{[resultat.classe, resultat.date_naissance, resultat.sexe].filter(Boolean).join(' · ')}</span>
                </span>
                <span className="rounded-lg bg-navy-100 px-2 py-1 text-[11px] font-semibold text-navy-700">{resultat.matricule_national || '—'}</span>
              </button>
            ))}
          </div>
        )}
      </div>
    </Modal>
  )
}

export function MatriculesNationauxPage() {
  const queryClient = useQueryClient()
  const [enCoursId, setEnCoursId] = useState<number | null>(null)
  const [editionPour, setEditionPour] = useState<MatriculeNationalLigne | null>(null)
  const [recherchePour, setRecherchePour] = useState<MatriculeNationalLigne | null>(null)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['matricules-nationaux'],
    queryFn: () => fetchMatriculesNationaux(),
  })

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['matricules-nationaux'] })

  const supprimer = async (ligne: MatriculeNationalLigne) => {
    const confirme = await confirmer({
      titre: `Effacer le matricule national de ${ligne.nom_complet} ?`,
      message: 'Le matricule national de cet élève sera effacé — sa fiche élève reste intacte, il pourra en recevoir un nouveau plus tard.',
      action: 'Effacer',
    })
    if (!confirme) return

    setEnCoursId(ligne.id)
    try {
      await majMatriculeNational(ligne.id, '')
      succes('Matricule national effacé.')
      invalidate()
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setEnCoursId(null)
    }
  }

  const colonnes: Colonne<MatriculeNationalLigne>[] = [
    {
      cle: 'matricule',
      entete: 'Matricule',
      largeur: '120px',
      valeur: (l) => l.matricule ?? '',
      cellule: (l) => <span className="font-mono text-xs text-navy-500">{l.matricule ?? '—'}</span>,
    },
    {
      cle: 'nom_complet',
      entete: 'Nom complet',
      valeur: (l) => l.nom_complet,
      cellule: (l) => <span className="font-semibold text-navy-900">{l.nom_complet}</span>,
    },
    {
      cle: 'classe',
      entete: 'Classe',
      largeur: '130px',
      valeur: (l) => l.classe ?? '',
      cellule: (l) => <span className="text-navy-600">{l.classe ?? '—'}</span>,
      masquerMobile: true,
    },
    {
      cle: 'school',
      entete: 'École',
      largeur: '180px',
      valeur: (l) => l.school ?? '',
      cellule: (l) => <span className="text-navy-600">{l.school ?? '—'}</span>,
      masquerMobile: true,
    },
    {
      cle: 'matricule_national',
      entete: 'Matricule national',
      largeur: '160px',
      valeur: (l) => l.matricule_national ?? '',
      cellule: (l) =>
        l.matricule_national ? (
          <span className="font-mono text-sm font-semibold text-navy-900">{l.matricule_national}</span>
        ) : (
          <Badge tone="gold">Manquant</Badge>
        ),
    },
    {
      cle: 'actions',
      entete: '',
      sticky: 'right',
      largeur: '140px',
      cellule: (l) => (
        <div className="flex justify-end gap-1.5">
          <Button size="sm" variant="secondary" title="Rechercher le matricule national" onClick={() => setRecherchePour(l)}>
            <Search className="h-3.5 w-3.5" />
          </Button>
          <Button size="sm" variant="secondary" title="Modifier" onClick={() => setEditionPour(l)}>
            <Pencil className="h-3.5 w-3.5" />
          </Button>
          <Button
            size="sm"
            variant="danger"
            title="Effacer le matricule national"
            disabled={!l.matricule_national || enCoursId === l.id}
            onClick={() => supprimer(l)}
          >
            <Trash2 className="h-3.5 w-3.5" />
          </Button>
        </div>
      ),
    },
  ]

  const manquants = data?.filter((l) => !l.matricule_national).length ?? 0

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Matricules nationaux"
        sousTitre="Numéro de matricule attribué par le ministère à chaque élève du secondaire."
        icon={IdCard}
        actions={
          <ImportExportBar
            titreImport="Importer les matricules nationaux"
            importUrl="matricules-nationaux/import"
            exportUrl="matricules-nationaux/export"
            modeleUrl="matricules-nationaux/modele"
            colonnes={['IDEleves', 'Nom complet', 'Classe', 'École', 'Matricule national']}
            nomFichier="matricules-nationaux"
            onImported={invalidate}
          />
        }
      />

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : (
        <>
          {manquants > 0 && (
            <p className="text-sm text-navy-500">
              <span className="font-semibold text-gold-700">{manquants}</span> élève(s) sans matricule national —
              téléchargez le modèle « à remplir » pour ne lister que ceux-là.
            </p>
          )}

          <DataTable
            colonnes={colonnes}
            lignes={data}
            cleLigne={(l) => l.id}
            placeholderRecherche="Rechercher un nom, un matricule…"
            messageVide="Aucun élève du secondaire."
            largeurMin={860}
          />
        </>
      )}

      {editionPour && (
        <ModifierMatriculeModal
          ligne={editionPour}
          onClose={() => setEditionPour(null)}
          onSaved={() => {
            setEditionPour(null)
            invalidate()
          }}
        />
      )}

      {recherchePour && (
        <RechercherMatriculeModal
          ligne={recherchePour}
          onClose={() => setRecherchePour(null)}
          onApplique={() => {
            setRecherchePour(null)
            invalidate()
          }}
        />
      )}
    </div>
  )
}
