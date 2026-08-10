<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Carte scolaire — {{ $eleve->nomComplet() }}</title>
<style>
    @page { margin: 0; }
    body { font-family: DejaVu Sans, sans-serif; margin: 0; padding: 10px; background: #fff; }

    .carte {
        width: 320px;
        border: 2px solid #FFAB02;
        border-radius: 10px;
        overflow: hidden;
        background: #F9FBF9;
    }
    .bandeau {
        background: #292F36; color: #F9FBF9; padding: 6px 10px; text-align: center;
    }
    .bandeau .school { font-size: 10px; font-weight: bold; text-transform: uppercase; }
    .bandeau .titre { font-size: 8px; letter-spacing: 1px; color: #FFAB02; font-weight: bold; margin-top: 1px; }

    .corps { width: 100%; padding: 10px; }
    .avatar {
        width: 60px; height: 60px; border-radius: 50%;
        background: #292F36; color: #FFAB02; text-align: center;
        font-size: 15px; font-weight: bold;
        line-height: 58px; border: 2px solid #FFAB02;
    }
    .infos { padding-left: 10px; font-size: 9px; color: #292F36; }
    .infos .nom { font-size: 12px; font-weight: bold; color: #292F36; margin-bottom: 3px; }
    .infos .ligne { margin-bottom: 2px; }
    .infos .label { color: #666; font-size: 7.5px; text-transform: uppercase; }

    .pied {
        border-top: 1px solid #d1dbd1; margin-top: 4px; padding: 5px 10px;
        text-align: center; font-size: 7px; color: #888; background: #f1f5f1;
    }
    .pied .en { font-style: italic; }
</style>
</head>
<body>

<div class="carte">
    <div class="bandeau">
        <div class="school">{{ $school->name }}</div>
        <div class="titre">Carte Scolaire {{ $anneeScolaire->libelle ?? '' }}</div>
    </div>

    <table class="corps">
        <tr>
            <td style="width:60px; vertical-align: top;">
                <div class="avatar">{{ mb_strtoupper(mb_substr($eleve->prenom, 0, 1) . mb_substr($eleve->nom, 0, 1)) }}</div>
            </td>
            <td class="infos" style="vertical-align: top;">
                <div class="nom">{{ $eleve->nomComplet() }}</div>
                <div class="ligne"><span class="label">Matricule / ID :</span> {{ $eleve->matricule ?? '—' }}</div>
                <div class="ligne"><span class="label">Classe / Class :</span> {{ $classe->nom ?? '—' }}</div>
                <div class="ligne"><span class="label">Sexe / Sex :</span> {{ $eleve->sexe === 'F' ? 'F' : 'M' }}</div>
                <div class="ligne"><span class="label">Né(e) le / DOB :</span> {{ $eleve->date_naissance?->format('d/m/Y') ?? '—' }}</div>
            </td>
        </tr>
    </table>

    <div class="pied">
        En cas de perte, prière de retourner à l'établissement<br>
        <span class="en">If found, please return to the school</span>
    </div>
</div>

</body>
</html>
