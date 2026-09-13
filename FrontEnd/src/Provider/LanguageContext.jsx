import { createContext, useContext, useEffect, useMemo, useState } from "react";
import { language } from "../lib";
import { translations } from "../i18n/translations";
const Context = createContext(null);
export function LanguageProvider({ children }) {
  const [lang, setLang] = useState(language.get());
  useEffect(() => language.set(lang), [lang]);
  const value = useMemo(
    () => ({
      lang,
      toggle: () => setLang((v) => (v === "ar" ? "en" : "ar")),
      t: (key, values = {}) =>
        Object.entries(values).reduce(
          (text, [name, value]) => text.replace(`:${name}`, String(value)),
          translations[lang][key],
        ),
    }),
    [lang],
  );
  return <Context.Provider value={value}>{children}</Context.Provider>;
}
// eslint-disable-next-line react-refresh/only-export-components
export function useLanguage() {
  const value = useContext(Context);
  if (!value) throw new Error("LanguageProvider missing");
  return value;
}
