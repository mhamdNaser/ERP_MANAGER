import { Search, X } from "lucide-react";
import { useEffect, useState } from "react";
import { useLanguage } from "../Provider/LanguageContext";

/**
 * حقل بحث بجانب عنوان القائمة.
 *
 * يُمهل 300ms قبل أن يُبلّغ المستدعي: البحث يجري في الخادم، وطلبٌ لكل
 * ضغطة حرف إغراقٌ بلا طائل. والقيمة المعروضة محلية كي لا يتأخر ظهور الحرف.
 */
export function ListSearch({ value, onChange, placeholder, delay = 300 }) {
  const { t } = useLanguage();
  const [draft, setDraft] = useState(value ?? "");
  const [applied, setApplied] = useState(value ?? "");

  // إعادة ضبط خارجية (مسح الفلاتر مثلاً) تنعكس على الحقل. المزامنة تجري
  // أثناء العرض لا داخل effect: الأخيرة ترسم مرةً بقيمة قديمة ثم تعيد الرسم.
  if ((value ?? "") !== applied) {
    setApplied(value ?? "");
    setDraft(value ?? "");
  }

  useEffect(() => {
    if (draft === (value ?? "")) return undefined;
    const timer = setTimeout(() => onChange(draft), delay);
    return () => clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [draft, delay]);

  return (
    <div className="relative min-w-40 flex-1 sm:max-w-56">
      <Search
        size={14}
        className="pointer-events-none absolute inset-y-0 start-2.5 my-auto text-muted"
      />
      <input
        type="search"
        className="input h-9 ps-8 pe-8 text-xs"
        value={draft}
        placeholder={placeholder || t("ui_searchPlaceholder")}
        onChange={(event) => setDraft(event.target.value)}
        aria-label={placeholder || t("ui_searchPlaceholder")}
      />
      {draft && (
        <button
          className="absolute inset-y-0 end-2 my-auto grid h-5 w-5 place-items-center rounded text-muted hover:text-ink"
          onClick={() => setDraft("")}
          aria-label={t("ui_clearSearch")}
          title={t("ui_clearSearch")}
        >
          <X size={13} />
        </button>
      )}
    </div>
  );
}
