import { telephonesTuteur, type Tuteur } from '@/features/eleves/api'

export function EleveParentsPhones({ tuteurs }: { tuteurs?: Tuteur[] | null }) {
    const contacts = (tuteurs ?? []).flatMap((tuteur) =>
        telephonesTuteur(tuteur).map((telephone) => `${tuteur.nom_complet} : ${telephone}`),
    )

    if (contacts.length === 0) return null

    return <span className="block text-xs font-normal text-navy-500">{contacts.join(' · ')}</span>
}