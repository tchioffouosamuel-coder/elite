import Swal from "sweetalert2";
import type { SweetAlertOptions } from "sweetalert2";
import i18n from "@/shared/i18n";

/**
 * SweetAlert habillé aux couleurs de la charte. Les classes Tailwind sont
 * appliquées via `customClass` plutôt qu'en surchargeant le CSS de la
 * bibliothèque : la mise en forme reste lisible depuis le code appelant.
 */
const base: SweetAlertOptions = {
  buttonsStyling: false,
  reverseButtons: true,
  customClass: {
    popup: "rounded-2xl border border-navy-100 shadow-lifted font-sans",
    title: "font-display text-lg font-bold text-navy-900",
    htmlContainer: "text-sm text-navy-500",
    actions: "gap-2",
    confirmButton:
      "rounded-xl bg-navy-800 px-4 py-2.5 text-sm font-semibold text-cream-50 shadow-soft transition-colors hover:bg-navy-700 focus:outline-none focus:ring-4 focus:ring-navy-100",
    denyButton:
      "rounded-xl bg-red-500 px-4 py-2.5 text-sm font-semibold text-white shadow-soft transition-colors hover:bg-red-600 focus:outline-none focus:ring-4 focus:ring-red-50",
    cancelButton:
      "rounded-xl border border-navy-200 bg-white px-4 py-2.5 text-sm font-semibold text-navy-700 transition-colors hover:bg-cream-50 focus:outline-none focus:ring-4 focus:ring-navy-100",
  },
};

const toast = Swal.mixin({
  ...base,
  toast: true,
  position: "top-end",
  showConfirmButton: false,
  timer: 3200,
  timerProgressBar: true,
  customClass: {
    ...base.customClass,
    popup: "rounded-xl border border-navy-100 shadow-lifted font-sans",
  },
});

/**
 * Message du dernier refus de privilège affiché, et jusqu'à quand l'ignorer.
 *
 * Un 403 est présenté par l'intercepteur HTTP sous forme de fenêtre modale,
 * puis la promesse est rejetée et le `onError` de l'appelant affiche son toast
 * habituel — soit deux fois la même phrase à l'écran. On retient donc
 * brièvement le message pour que `erreur()` le laisse passer.
 */
let refusRecent: { message: string; jusqua: number } | null = null;

export function succes(message: string): void {
  void toast.fire({ icon: "success", title: message });
}

export function erreur(message: string): void {
  if (
    refusRecent &&
    refusRecent.message === message &&
    Date.now() < refusRecent.jusqua
  )
    return;

  // Pas de minuteur sur une erreur : elle porte souvent une consigne à lire.
  void toast.fire({ icon: "error", title: message, timer: 6000 });
}

/**
 * Refus d'autorisation renvoyé par l'API. Fenêtre modale et non toast : c'est
 * une impasse, pas un incident passager — l'utilisateur doit comprendre qu'il
 * n'obtiendra rien en réessayant et qu'il lui faut passer par son
 * administrateur.
 */
export function permissionManquante(message: string): void {
  refusRecent = { message, jusqua: Date.now() + 4000 };

  void Swal.fire({
    ...base,
    icon: "error",
    iconColor: "#ba2e2c",
    title: i18n.t("alerts.missing_permission_title"),
    text: message,
    confirmButtonText: i18n.t("alerts.understood"),
  });
}

export function info(message: string): void {
  void toast.fire({ icon: "info", title: message });
}

/**
 * Popup de notification (titre + message), façon WhatsApp Web : reste plus
 * longtemps qu'un toast ordinaire et se ferme au clic — avec une action
 * optionnelle (ouvrir la notification concernée) plutôt qu'une simple lecture.
 *
 * `actionLabel` ajoute en plus un bouton explicite ("Traiter", par exemple) :
 * le toast entier reste cliquable, mais certaines notifications (une demande
 * qui attend un traitement) gagnent à nommer l'action plutôt qu'à compter sur
 * le seul réflexe de cliquer le popup.
 */
export function notification({
  titre,
  message,
  onClick,
  actionLabel,
}: {
  titre: string;
  message: string;
  onClick?: () => void;
  actionLabel?: string;
}): void {
  void Swal.fire({
    ...base,
    toast: true,
    position: "top-end",
    showConfirmButton: !!(onClick && actionLabel),
    confirmButtonText: actionLabel,
    showCloseButton: true,
    timer: 8000,
    timerProgressBar: true,
    icon: "info",
    title: titre,
    text: message,
    customClass: {
      ...base.customClass,
      popup: `rounded-xl border border-navy-100 shadow-lifted font-sans${onClick ? " cursor-pointer" : ""}`,
      confirmButton: `${(base.customClass as Record<string, string>).confirmButton} !text-xs !px-3 !py-1.5`,
    },
    didOpen: (popup) => {
      if (!onClick) return;
      popup.addEventListener("click", (e) => {
        // Le bouton de fermeture a son propre clic : ne pas le doubler d'une navigation.
        if ((e.target as HTMLElement).closest(".swal2-close")) return;
        onClick();
        void Swal.close();
      });
    },
  });
}

interface ConfirmationOptions {
  titre: string;
  message: string;
  /** Libellé du bouton d'action, à l'impératif : « Supprimer », « Transférer ». */
  action: string;
  /** true pour une action destructrice — bouton rouge et icône d'avertissement. */
  destructif?: boolean;
}

/**
 * Demande une confirmation avant une action lourde de conséquences.
 * Résout à `true` seulement si l'utilisateur confirme explicitement.
 */
export async function confirmer({
  titre,
  message,
  action,
  destructif = true,
}: ConfirmationOptions): Promise<boolean> {
  const { isConfirmed } = await Swal.fire({
    ...base,
    icon: destructif ? "warning" : "question",
    iconColor: destructif ? "#ba2e2c" : "#1985cc",
    title: titre,
    text: message,
    showCancelButton: true,
    confirmButtonText: action,
    cancelButtonText: i18n.t("common.cancel"),
    customClass: {
      ...base.customClass,
      confirmButton: destructif
        ? (base.customClass as Record<string, string>).denyButton
        : (base.customClass as Record<string, string>).confirmButton,
    },
  });

  return isConfirmed;
}

/**
 * Identifiants provisoires d'un compte fraîchement ouvert (parent, agent…) —
 * fenêtre modale plutôt qu'un toast : l'administrateur doit avoir le temps de
 * les recopier avant qu'ils disparaissent de l'écran.
 */
export function identifiantsOuverts(
  identifiant: string,
  motDePasse: string | null,
): void {
  void Swal.fire({
    ...base,
    icon: "success",
    title: "Accès ouvert",
    html: motDePasse
      ? `Identifiant : <b>${identifiant}</b><br>Mot de passe provisoire : <b>${motDePasse}</b><br><br><span class="text-xs">À remettre en main propre — à changer dès la première connexion.</span>`
      : `Identifiant : <b>${identifiant}</b><br><br><span class="text-xs">Ce compte a déjà personnalisé son mot de passe.</span>`,
    confirmButtonText: i18n.t("alerts.understood"),
  });
}

/** Confirmation de suppression, formulée à partir de ce qui est supprimé. */
export function confirmerSuppression(
  quoi: string,
  precision?: string,
): Promise<boolean> {
  return confirmer({
    titre: i18n.t("alerts.delete_title", { quoi }),
    message: precision ?? i18n.t("alerts.irreversible"),
    action: i18n.t("common.delete"),
  });
}
