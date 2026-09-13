import { Languages } from "lucide-react";
import { useLanguage } from "../Provider/LanguageContext";
export function LanguageToggle() {
  const { t, toggle } = useLanguage();
  return (
    <button
      className="btn-icon"
      onClick={toggle}
      title={t("ui_toggleLanguageTitle")}
    >
      <Languages size={17} />
      <span className="sr-only">{t("ui_toggleLanguageLabel")}</span>
    </button>
  );
}
