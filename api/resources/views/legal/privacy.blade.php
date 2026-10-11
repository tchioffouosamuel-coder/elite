@php
    $app = config('legal.app_name');
    $editeur = config('legal.editeur');
    $email = config('legal.email');
    $telephone = config('legal.telephone');
    $adresse = config('legal.adresse');
    $maj = config('legal.maj');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Politique de confidentialité — {{ $app }}</title>
    <meta name="description" content="Politique de confidentialité de l'application de gestion scolaire {{ $app }} : données collectées, finalités, partage, conservation, sécurité et droits des utilisateurs.">
    <meta name="robots" content="index, follow">
    <style>
        :root {
            --violet: #5d2985;
            --violet-clair: #f6effa;
            --bleu: #1985cc;
            --texte: #1f2430;
            --bord: #e6e2ee;
            --fond: #ffffff;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #faf8fc;
            color: var(--texte);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: 16px;
            line-height: 1.7;
        }
        header {
            background: linear-gradient(135deg, #481e67 0%, #5d2985 60%, #1985cc 160%);
            color: #fff;
            padding: 48px 16px 40px;
        }
        .wrap { max-width: 820px; margin: 0 auto; }
        header .eyebrow {
            text-transform: uppercase;
            letter-spacing: .12em;
            font-size: 12px;
            font-weight: 700;
            opacity: .85;
            margin: 0 0 10px;
        }
        header h1 { margin: 0 0 12px; font-size: 32px; line-height: 1.25; }
        header p { margin: 0; opacity: .9; font-size: 15px; }
        main {
            background: var(--fond);
            max-width: 820px;
            margin: -24px auto 48px;
            padding: 32px 28px 40px;
            border: 1px solid var(--bord);
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(72, 30, 103, .08);
        }
        h2 {
            font-size: 20px;
            margin: 36px 0 12px;
            padding-top: 4px;
            color: var(--violet);
        }
        h2:first-of-type { margin-top: 8px; }
        h3 { font-size: 16px; margin: 22px 0 8px; }
        ul { padding-left: 22px; }
        li { margin-bottom: 6px; }
        a { color: var(--bleu); }
        .encadre {
            background: var(--violet-clair);
            border-left: 4px solid var(--violet);
            border-radius: 0 8px 8px 0;
            padding: 14px 18px;
            margin: 20px 0;
        }
        .encadre p { margin: 0; font-size: 15px; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
            font-size: 15px;
        }
        th, td {
            border: 1px solid var(--bord);
            padding: 10px 12px;
            text-align: left;
            vertical-align: top;
        }
        th { background: #f6effa; color: var(--violet); font-weight: 600; }
        footer {
            max-width: 820px;
            margin: 0 auto 48px;
            padding: 0 28px;
            color: #6b7280;
            font-size: 14px;
        }
        @media (max-width: 640px) {
            header { padding: 36px 16px 32px; }
            header h1 { font-size: 25px; }
            main { margin: -20px 16px 32px; padding: 24px 18px 32px; border-radius: 12px; }
            footer { padding: 0 20px; }
            table, tbody, th, td, tr { display: block; }
            thead { display: none; }
            td { border: none; border-bottom: 1px solid var(--bord); padding: 8px 0; }
            tr { border: 1px solid var(--bord); border-radius: 8px; padding: 10px 12px; margin-bottom: 12px; }
            td::before { content: attr(data-label); display: block; font-weight: 600; color: var(--violet); font-size: 13px; }
        }
    </style>
</head>
<body>
    <header>
        <div class="wrap">
            <p class="eyebrow">{{ $app }} — Gestion scolaire</p>
            <h1>Politique de confidentialité</h1>
            <p>Dernière mise à jour : {{ $maj }}</p>
        </div>
    </header>

    <main>
        <p>
            La présente politique décrit la manière dont {{ $editeur }} (« nous ») collecte, utilise,
            conserve et protège les données personnelles traitées dans l'application mobile et la
            plateforme web <strong>{{ $app }}</strong>, destinées à la gestion administrative,
            pédagogique et financière des établissements scolaires.
        </p>

        <div class="encadre">
            <p>
                <strong>En résumé :</strong> nous ne vendons aucune donnée, nous n'affichons aucune
                publicité et nous n'utilisons pas les données des élèves à des fins publicitaires.
                Les informations saisies dans l'application appartiennent à l'établissement scolaire
                et ne servent qu'au fonctionnement du service.
            </p>
        </div>

        <h2>1. Responsable du traitement</h2>
        <p>
            Chaque établissement scolaire utilisant {{ $app }} est <strong>responsable du traitement</strong>
            des données de ses élèves, parents et personnels. {{ $editeur }} intervient en qualité de
            <strong>sous-traitant</strong> : nous hébergeons et exploitons le service pour le compte de
            l'établissement, selon ses instructions.
        </p>
        <p>
            Contact de l'éditeur : <a href="mailto:{{ $email }}">{{ $email }}</a> — {{ $telephone }} — {{ $adresse }}.
        </p>

        <h2>2. Données que nous collectons</h2>
        <table>
            <thead>
                <tr><th>Catégorie</th><th>Exemples de données</th><th>Origine</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td data-label="Catégorie">Compte utilisateur</td>
                    <td data-label="Exemples">Nom, prénom, identifiant de connexion, adresse e-mail, numéro de téléphone, rôle (parent, enseignant, administrateur), mot de passe chiffré</td>
                    <td data-label="Origine">Établissement / utilisateur</td>
                </tr>
                <tr>
                    <td data-label="Catégorie">Données scolaires</td>
                    <td data-label="Exemples">Identité de l'élève, classe, notes, bulletins, absences et retards, appréciations, emploi du temps, documents pédagogiques</td>
                    <td data-label="Origine">Établissement</td>
                </tr>
                <tr>
                    <td data-label="Catégorie">Données financières</td>
                    <td data-label="Exemples">Frais de scolarité, paiements, reçus, échéanciers, références de transactions Mobile Money</td>
                    <td data-label="Origine">Établissement / utilisateur</td>
                </tr>
                <tr>
                    <td data-label="Catégorie">Photos et fichiers</td>
                    <td data-label="Exemples">Photo de profil, pièces jointes et documents importés depuis l'appareil</td>
                    <td data-label="Origine">Utilisateur (appareil photo / galerie)</td>
                </tr>
                <tr>
                    <td data-label="Catégorie">Données techniques</td>
                    <td data-label="Exemples">Jeton de notification push, modèle d'appareil et version du système, langue, journaux d'activité et de connexion (horodatage, adresse IP)</td>
                    <td data-label="Origine">Application</td>
                </tr>
            </tbody>
        </table>
        <p>
            L'application ne collecte <strong>pas</strong> votre position géographique, vos contacts,
            vos SMS, votre micro ni votre historique de navigation.
        </p>

        <h3>Autorisations demandées par l'application mobile</h3>
        <ul>
            <li><strong>Appareil photo</strong> : prendre une photo de profil et scanner les QR codes (badges, reçus).</li>
            <li><strong>Photos / fichiers</strong> : joindre un document ou une image depuis l'appareil.</li>
            <li><strong>Notifications</strong> : recevoir les alertes de l'établissement (notes, absences, paiements, annonces).</li>
            <li><strong>Internet</strong> : synchroniser les données avec le serveur de l'établissement.</li>
        </ul>
        <p>
            Chaque autorisation est demandée au moment de l'usage et peut être refusée ou retirée dans les
            réglages du téléphone ; seules les fonctions concernées deviennent alors indisponibles.
        </p>

        <h2>3. Finalités et bases légales</h2>
        <ul>
            <li><strong>Fournir le service</strong> : authentification, consultation des notes, bulletins, absences, paiements et communications (exécution du contrat conclu avec l'établissement).</li>
            <li><strong>Notifications</strong> : informer les parents et personnels des événements de la vie scolaire (intérêt légitime de l'établissement ; consentement pour les notifications système).</li>
            <li><strong>Sécurité</strong> : prévention des accès frauduleux, journalisation et traçabilité des actions sensibles (intérêt légitime).</li>
            <li><strong>Support et amélioration</strong> : diagnostic des incidents techniques et correction des anomalies (intérêt légitime).</li>
            <li><strong>Obligations légales</strong> : conservation des pièces comptables et des documents scolaires.</li>
        </ul>
        <p>
            Aucune décision entièrement automatisée produisant des effets juridiques n'est prise à partir de
            ces données, et aucun profilage publicitaire n'est réalisé.
        </p>

        <h2>4. Partage des données</h2>
        <p>Les données ne sont ni vendues, ni louées, ni échangées. Elles sont uniquement accessibles :</p>
        <ul>
            <li>aux personnes habilitées de l'établissement, selon leur rôle et leurs permissions ;</li>
            <li>aux parents ou tuteurs, pour les données de leurs enfants uniquement ;</li>
            <li>à nos prestataires techniques, strictement pour faire fonctionner le service :
                <ul>
                    <li><strong>Firebase Cloud Messaging (Google)</strong> — envoi des notifications push ;</li>
                    <li><strong>Hébergeur du serveur</strong> — stockage de la base de données et des documents ;</li>
                    <li><strong>Opérateurs SMS et Mobile Money</strong> — envoi des messages et traitement des paiements ;</li>
                    <li><strong>Service d'envoi d'e-mails</strong> — messages transactionnels (réinitialisation de mot de passe, notifications).</li>
                </ul>
            </li>
            <li>aux autorités compétentes, lorsque la loi l'exige.</li>
        </ul>
        <p>
            Ces prestataires sont liés par des engagements de confidentialité et n'utilisent les données que
            pour la prestation demandée.
        </p>

        <h2>5. Données des mineurs</h2>
        <p>
            {{ $app }} est un outil de gestion scolaire : les données des élèves mineurs y sont saisies par
            l'établissement dans le cadre de sa mission éducative, et consultées par les parents ou tuteurs
            légaux. L'application n'est pas destinée à une inscription libre par un enfant, ne contient
            aucune publicité et n'exploite aucune donnée d'élève à des fins commerciales.
        </p>

        <h2>6. Conservation</h2>
        <ul>
            <li><strong>Dossiers scolaires</strong> : pendant la scolarité de l'élève, puis archivage selon les obligations de l'établissement.</li>
            <li><strong>Pièces comptables et reçus</strong> : durée légale de conservation applicable.</li>
            <li><strong>Journaux techniques et de connexion</strong> : 12 mois au maximum.</li>
            <li><strong>Jetons de notification</strong> : supprimés à la déconnexion ou à la désinstallation de l'application.</li>
            <li><strong>Compte supprimé</strong> : données effacées ou anonymisées sous 30 jours, hors obligations légales de conservation.</li>
        </ul>

        <h2>7. Sécurité</h2>
        <ul>
            <li>Chiffrement des échanges entre l'application et le serveur (HTTPS/TLS).</li>
            <li>Mots de passe stockés sous forme de condensats non réversibles.</li>
            <li>Jetons d'authentification conservés dans le stockage sécurisé du système (Keystore Android / Keychain iOS).</li>
            <li>Contrôle d'accès par rôles et permissions, journalisation des actions sensibles.</li>
            <li>Sauvegardes régulières de la base de données.</li>
        </ul>
        <p>
            L'application peut conserver une copie locale des données sur l'appareil afin de permettre la
            consultation hors ligne. Cette copie est supprimée à la déconnexion ou à la désinstallation.
        </p>

        <h2>8. Vos droits</h2>
        <p>
            Conformément à la réglementation applicable en matière de protection des données personnelles,
            vous disposez des droits d'accès, de rectification, d'effacement, de limitation, d'opposition et
            de portabilité sur vos données.
        </p>
        <p>
            Ces demandes doivent en priorité être adressées à <strong>votre établissement scolaire</strong>,
            responsable du traitement. Vous pouvez également nous écrire à
            <a href="mailto:{{ $email }}">{{ $email }}</a> ; nous répondons sous 30 jours et relayons la
            demande à l'établissement concerné lorsque cela est nécessaire.
        </p>

        <h2>9. Suppression du compte et des données</h2>
        <p>
            Pour demander la suppression de votre compte et des données associées, envoyez un message à
            <a href="mailto:{{ $email }}">{{ $email }}</a> depuis l'adresse e-mail liée au compte, en
            précisant le nom de l'établissement et votre identifiant. La suppression est effectuée sous
            30 jours, sous réserve des données que l'établissement doit légalement conserver (dossiers
            scolaires, pièces comptables).
        </p>

        <h2>10. Modifications</h2>
        <p>
            Cette politique peut être mise à jour pour refléter des évolutions du service ou de la
            réglementation. La date de dernière mise à jour figure en haut de la page ; en cas de
            changement important, les utilisateurs sont informés dans l'application.
        </p>

        <h2>11. Contact</h2>
        <p>
            {{ $editeur }}<br>
            E-mail : <a href="mailto:{{ $email }}">{{ $email }}</a><br>
            Téléphone : {{ $telephone }}<br>
            Adresse : {{ $adresse }}
        </p>
    </main>

    <footer>
        <p>&copy; {{ date('Y') }} {{ $editeur }} — {{ $app }}. Tous droits réservés.</p>
    </footer>
</body>
</html>
