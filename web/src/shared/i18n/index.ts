import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'
import fr from './locales/fr.json'
import en from './locales/en.json'
import { useUiStore } from '@/shared/store/uiStore'

i18n.use(initReactI18next).init({
  resources: {
    fr: { translation: fr },
    en: { translation: en },
  },
  lng: useUiStore.getState().locale,
  fallbackLng: 'fr',
  interpolation: { escapeValue: false },
})

useUiStore.subscribe((state) => {
  if (i18n.language !== state.locale) i18n.changeLanguage(state.locale)
})

export default i18n
