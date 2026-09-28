import logo from "../assets/logo.jpg";
import { useLanguage } from "../Provider/LanguageContext";

/** رقم الإصدار يُحقن وقت البناء من ملف VERSION في جذر المشروع. */
const version = import.meta.env.VITE_APP_VERSION;

export function BrandLogo({ compact = false }) {
  const { t } = useLanguage();
  return (
    <div className="flex min-w-0 items-center gap-2.5">
      <img
        src={logo}
        alt="CND"
        className={`shrink-0 rounded object-cover ${compact ? "h-14 w-14" : "h-9 w-9"}`}
      />
      {!compact && (
        <div className="min-w-0 leading-tight">
          <b className="block text-[15px] font-semibold">CND</b>
          <small className="block truncate text-[10px] opacity-60">
            {t("ui_brandSubtitle")}
          </small>
          {version && (
            <small
              className="block font-mono text-[10px] opacity-45"
              title={t("ui_appVersion")}
            >
              v{version}
            </small>
          )}
        </div>
      )}
    </div>
  );
}
