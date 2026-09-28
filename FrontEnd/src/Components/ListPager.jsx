import { ChevronLeft, ChevronRight } from "lucide-react";
import { useLanguage } from "../Provider/LanguageContext";

/**
 * تنقّل بين صفحات قائمة مرقَّمة في الخادم.
 *
 * الاتجاه في الواجهة من اليمين إلى اليسار، فسهم «السابق» يشير يميناً
 * و«التالي» يساراً — عكس ما تفعله الواجهات اللاتينية.
 */
export function ListPager({ meta, onPage, busy = false }) {
  const { t } = useLanguage();

  if (!meta || meta.last_page <= 1) return null;

  const page = meta.current_page;
  const last = meta.last_page;

  return (
    <div className="flex items-center justify-between gap-2 border-t border-line px-4 py-2">
      <small className="text-xs text-muted">
        {t("ui_pageOf", { page: String(page), last: String(last) })}
        {" · "}
        {t("ui_totalItems", { total: String(meta.total) })}
      </small>
      <div className="flex items-center gap-1">
        <button
          className="btn-icon h-8 w-8"
          disabled={busy || page <= 1}
          onClick={() => onPage(page - 1)}
          aria-label={t("ui_previousPage")}
          title={t("ui_previousPage")}
        >
          <ChevronRight size={16} />
        </button>
        <button
          className="btn-icon h-8 w-8"
          disabled={busy || page >= last}
          onClick={() => onPage(page + 1)}
          aria-label={t("ui_nextPage")}
          title={t("ui_nextPage")}
        >
          <ChevronLeft size={16} />
        </button>
      </div>
    </div>
  );
}
