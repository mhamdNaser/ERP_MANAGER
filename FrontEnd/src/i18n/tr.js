// Module-level translator for non-React code (API layer, plain modules) that
// cannot use the useLanguage() hook. Reads the current language from storage
// at call time, so it always reflects the active toggle.
import { language } from "../Api/storage";
import { translations } from "./translations";

export function tr(key, values = {}) {
  const dict = translations[language.get()] || translations.ar;
  const text = dict[key] ?? translations.ar[key] ?? key;
  return Object.entries(values).reduce(
    (acc, [name, value]) => acc.replace(`:${name}`, String(value)),
    text,
  );
}
