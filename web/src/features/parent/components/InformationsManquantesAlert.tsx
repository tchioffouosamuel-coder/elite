import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { AlertTriangle } from 'lucide-react'
import { fetchChampsManquants } from '@/features/parent/api'
import { CLE_ALERTE_MASQUEE, libelleChamp } from '@/features/parent/champsManquants'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'

/**
 * Alerte affichée à l'ouverture du portail parent quand le dossier d'un
 * enfant (ou la fiche du tuteur lui-même) comporte des champs vides —
 * indépendante de `soumettreModification()` : il s'agit ici de renseigner un
 * blanc, pas de corriger une valeur déjà connue de l'établissement.
 *
 * "Plus tard" ne masque l'alerte que pour la session en cours (onglet) —
 * elle réapparaît à la prochaine connexion tant que le dossier reste
 * incomplet.
 */
export function InformationsManquantesAlert() {
  const navigate = useNavigate()
  const [masquee, setMasquee] = useState(() => sessionStorage.getItem(CLE_ALERTE_MASQUEE) === '1')

  const { data } = useQuery({ queryKey: ['parent-champs-manquants'], queryFn: fetchChampsManquants })

  if (masquee || !data || data.total === 0) return null

  const plusTard = () => {
    sessionStorage.setItem(CLE_ALERTE_MASQUEE, '1')
    setMasquee(true)
  }

  const nomsEnfants = data.enfants.map((e) => e.nom_complet)

  return (
    <Modal title="Informations manquantes / Missing information" onClose={plusTard}>
      <div className="flex flex-col gap-4">
        <div className="flex items-start gap-3 rounded-xl bg-gold-50 px-3.5 py-3 text-sm text-gold-800">
          <AlertTriangle className="h-5 w-5 flex-none" />
          <p>
            Le dossier {data.enfants.length > 0 ? `de ${nomsEnfants.join(', ')}` : ''}
            {data.tuteur && data.enfants.length > 0 ? ' et vos coordonnées ' : data.tuteur ? 'Vos coordonnées ' : ' '}
            comporte{data.total > 1 ? 'nt' : ''} des informations manquantes. Complétez-les pour que l'école dispose d'un dossier à jour.
            <br />
            <span className="text-gold-700/80">
              The record{data.enfants.length > 1 ? 's' : ''}
              {data.enfants.length > 0 ? ` for ${nomsEnfants.join(', ')}` : ''}
              {data.tuteur ? ' and your contact details' : ''} {data.total > 1 ? 'are' : 'is'} missing some information.
              Complete it so the school has an up-to-date record.
            </span>
          </p>
        </div>

        <ul className="flex flex-col gap-2 text-sm">
          {data.tuteur && data.tuteur.champs.length > 0 && (
            <li className="rounded-lg border border-navy-100 px-3 py-2">
              <p className="font-semibold text-navy-800">Vos coordonnées / Your contact details</p>
              <p className="text-xs text-navy-400">{data.tuteur.champs.map(libelleChamp).join(' · ')}</p>
            </li>
          )}
          {data.enfants.map((e) => (
            <li key={e.id} className="rounded-lg border border-navy-100 px-3 py-2">
              <p className="font-semibold text-navy-800">{e.nom_complet}</p>
              <p className="text-xs text-navy-400">{e.champs.map(libelleChamp).join(' · ')}</p>
            </li>
          ))}
        </ul>

        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={plusTard}>
            Plus tard / Later
          </Button>
          <Button
            onClick={() => {
              setMasquee(true)
              navigate('/parent/completer')
            }}
          >
            Compléter maintenant / Complete now
          </Button>
        </div>
      </div>
    </Modal>
  )
}
