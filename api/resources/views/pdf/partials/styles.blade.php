{{--
    Palette alignée sur celle des documents rendus par mPDF
    (App\Support\Pdf\Concerns\RenduDocument) : vert #39b54a en accent, ardoise
    #292F36 en texte. Ces vues-ci passent par dompdf et vivaient sur une palette
    orange héritée, si bien qu'un PV de conseil ne ressemblait pas au bulletin
    remis le même jour. Toute retouche ici doit suivre ce trait, pas l'inverse.
--}}
<style>
    @page {
        margin: 16px 22px 20px 22px;
    }

    body {
        font-family: Montserrat, sans-serif;
        font-size: 10px;
        color: #292F36;
        background: #F9FBF9;
    }

    p {
        margin: 2px 0;
    }

    /*
     * Filigrane : logo de l'établissement, centré et répété sur chaque page.
     * dompdf ne gère ni `opacity` ni les transformations — le logo est donc
     * atténué en le posant derrière le contenu (`z-index` négatif) sur le fond
     * de page. Les documents rendus par mPDF utilisent son filigrane natif
     * (cf. MpdfFactory::appliquerFiligrane) et n'incluent pas ce partiel.
     */
    .filigrane {
        position: fixed;
        top: 40%;
        left: 25%;
        width: 50%;
        text-align: center;
        z-index: -1000;
    }

    .filigrane img {
        width: 100%;
        opacity: 0.07;
    }

    hr {
        border: none;
        border-top: 2px solid #39b54a;
        margin: 8px 0;
    }

    table.header-table {
        width: 100%;
        table-layout: fixed;
        margin-bottom: 6px;
    }

    table.header-table td {
        vertical-align: top;
        border: none;
        padding: 0 4px;
    }

    /*
     * Les trois colonnes portent chacune une largeur explicite : sans ça,
     * mPDF (contrairement à un navigateur ou à dompdf) ne répartit pas
     * correctement la largeur restante entre les colonnes non contraintes,
     * et écrase les deux blocs de texte au profit du logo.
     */
    table.header-table .fr {
        width: 38%;
        text-align: left;
        font-size: 8px;
        line-height: 1.35;
    }

    table.header-table .en {
        width: 38%;
        text-align: right;
        font-size: 8px;
        line-height: 1.35;
        font-style: italic;
        color: #555;
    }

    table.header-table .logo-cell {
        width: 24%;
        text-align: center;
    }

    /* `object-fit` n'est pas supporté par mPDF : la mise à l'échelle passe
       par une largeur fixe et une hauteur automatique, comme pour le logo
       des autres documents (cf. RenduDocument::stylesBase). */
    table.header-table .logo-cell img {
        width: 100px;
        height: auto;
        max-height: 100px;
    }

    table.header-table .monogram {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        margin: 0 auto;
        background: #292F36;
        color: #39b54a;
        font-weight: bold;
        font-size: 18px;
        line-height: 56px;
        text-align: center;
        border: 2px solid #39b54a;
    }

    table.header-table .school-name {
        font-weight: bold;
        text-transform: uppercase;
        color: #292F36;
    }

    .doc-title {
        text-align: center;
        margin: 8px 0 4px;
    }

    .doc-title .fr {
        font-size: 13px;
        font-weight: bold;
        color: #39b54a;
        text-transform: uppercase;
    }

    .doc-title .en {
        font-size: 9.5px;
        font-style: italic;
        color: #39b54a;
        text-transform: uppercase;
    }

    .doc-title .meta {
        font-size: 9px;
        color: #555;
        margin-top: 2px;
    }

    table.datatable {
        width: 100%;
        border-collapse: collapse;
        margin-top: 5px;
        margin-bottom: 10px;
    }

    table.datatable th {
        background: #39b54a;
        color: #fff;
        font-weight: bold;
        font-size: 9px;
        border: 1px solid #2e9a3e;
        padding: 4px 5px;
        text-align: center;
    }

    table.datatable td {
        background: #F9FBF9;
        border: 1px solid #bdc3c7;
        padding: 3px 5px;
        font-size: 9px;
        color: #292F36;
        text-align: center;
    }

    table.datatable tbody tr:nth-child(even) td {
        background: #f1f5f1;
    }

    table.datatable tfoot th,
    table.datatable tfoot td {
        background: #292F36 !important;
        color: #F9FBF9 !important;
        font-weight: bold;
        border: 1px solid #3a424c;
    }

    .text-left {
        text-align: left !important;
    }

    .text-right {
        text-align: right !important;
    }

    .info-box {
        background: #f1f5f1;
        border: 2px solid #39b54a;
        border-radius: 6px;
        padding: 8px 10px;
        margin: 8px 0;
    }

    .info-box .box-title {
        display: block;
        font-weight: bold;
        font-size: 7.5px;
        text-transform: uppercase;
        color: #292F36;
        margin-bottom: 3px;
        letter-spacing: 0.4px;
    }

    .info-box .box-value {
        font-size: 9.5px;
        color: #292F36;
    }

    .accent-value {
        font-size: 15px;
        font-weight: bold;
        color: #39b54a;
    }

    .high-absence {
        background: #fee2e2 !important;
        color: #dc2626;
        font-weight: bold;
    }

    .medium-absence {
        background: #fef3c7 !important;
        color: #b45309;
        font-weight: bold;
    }

    .low-absence {
        background: #dcfce7 !important;
        color: #15803d;
    }

    table.signatures {
        width: 100%;
        margin-top: 26px;
        border-collapse: collapse;
    }

    table.signatures td {
        width: 33.33%;
        text-align: center;
        border: none;
        padding-top: 34px;
        border-top: 1px solid #292F36;
        font-size: 8.5px;
    }

    table.signatures .role-fr {
        font-weight: bold;
        color: #292F36;
    }

    table.signatures .role-en {
        font-style: italic;
        color: #666;
    }

    .stats-banner {
        background: #292F36;
        color: #F9FBF9;
        padding: 6px 10px;
        border-radius: 5px;
        text-align: center;
        font-size: 9.5px;
        font-weight: bold;
        margin: 8px 0;
    }
</style>
