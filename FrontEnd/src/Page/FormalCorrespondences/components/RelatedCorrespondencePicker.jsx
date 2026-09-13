import { Check, ChevronDown, Link2, Search, X } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import { useLanguage } from "../../../Provider/LanguageContext";

/** حقل واحد يجمع البحث والاختيار الاختياري لمراسلة سابقة. */
export function RelatedCorrespondencePicker({ options = [], value, onChange, excludeId }) {
  const { t } = useLanguage();
  const rootRef = useRef(null);
  const inputRef = useRef(null);
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");

  const available = useMemo(
    () => options.filter((option) => String(option.id) !== String(excludeId)),
    [options, excludeId],
  );
  const selected = useMemo(
    () => available.find((option) => String(option.id) === String(value)),
    [available, value],
  );
  const matches = useMemo(() => {
    const needle = query.trim().toLocaleLowerCase();
    if (!needle) return available.slice(0, 50);
    return available
      .filter((option) =>
        option.subject?.toLocaleLowerCase().includes(needle) ||
        option.reference_code?.toLocaleLowerCase().includes(needle))
      .slice(0, 50);
  }, [available, query]);

  useEffect(() => {
    const closeOutside = (event) => {
      if (!rootRef.current?.contains(event.target)) setOpen(false);
    };
    document.addEventListener("mousedown", closeOutside);
    return () => document.removeEventListener("mousedown", closeOutside);
  }, []);

  const choose = (id) => {
    onChange(id);
    setQuery("");
    setOpen(false);
  };

  return (
    <div ref={rootRef} className="relative flex flex-col gap-2">
      <div className="flex items-center gap-2">
        <label className="label" htmlFor="related-correspondence">
          {t("formal_relatedPickerLabel")}
        </label>
        <span className="rounded bg-canvas px-2 py-0.5 text-[11px] font-medium text-muted">
          {t("formal_optional")}
        </span>
      </div>

      <div className={`relative flex items-center rounded border bg-surface ${open ? "border-brand-500" : "border-line"}`}>
        <Search size={16} className="pointer-events-none absolute start-3 text-muted" />
        <input
          ref={inputRef}
          id="related-correspondence"
          className="h-9 w-full bg-transparent ps-10 pe-20 text-[13px] text-ink outline-none"
          role="combobox"
          aria-expanded={open}
          aria-controls="related-correspondence-options"
          autoComplete="off"
          value={open ? query : selected ? `${selected.reference_code} — ${selected.subject}` : query}
          placeholder={t("formal_relatedSearchPlaceholder")}
          onFocus={() => setOpen(true)}
          onChange={(event) => {
            setQuery(event.target.value);
            setOpen(true);
            if (selected) onChange("");
          }}
          onKeyDown={(event) => {
            if (event.key === "Escape") setOpen(false);
            if (event.key === "ArrowDown") {
              event.preventDefault();
              setOpen(true);
            }
          }}
        />
        <div className="absolute end-1 flex items-center">
          {(selected || query) && (
            <button
              type="button"
              className="inline-grid h-7 w-7 place-items-center rounded text-muted hover:bg-canvas hover:text-ink"
              onClick={() => choose("")}
              aria-label={t("formal_relatedClear")}
            >
              <X size={14} />
            </button>
          )}
          <button
            type="button"
            className="inline-grid h-7 w-7 place-items-center rounded text-muted hover:bg-canvas hover:text-ink"
            onClick={() => {
              setOpen((current) => !current);
              inputRef.current?.focus();
            }}
            aria-label={t("formal_relatedOpen")}
          >
            <ChevronDown size={16} className={open ? "rotate-180" : ""} />
          </button>
        </div>
      </div>

      {open && (
        <div
          id="related-correspondence-options"
          role="listbox"
          className="absolute inset-x-0 top-full z-50 mt-1 max-h-64 overflow-y-auto rounded border border-line bg-surface p-1 shadow-lg"
        >
          <button
            type="button"
            role="option"
            aria-selected={!value}
            className="flex w-full items-center gap-2 rounded px-3 py-2 text-start text-[13px] hover:bg-canvas"
            onClick={() => choose("")}
          >
            <span className="inline-grid h-5 w-5 place-items-center">{!value && <Check size={14} />}</span>
            <span>{t("formal_relatedNone")}</span>
          </button>
          {matches.map((option) => (
            <button
              key={option.id}
              type="button"
              role="option"
              aria-selected={String(option.id) === String(value)}
              className="flex w-full items-start gap-2 rounded px-3 py-2 text-start hover:bg-canvas"
              onClick={() => choose(option.id)}
            >
              <span className="inline-grid h-5 w-5 shrink-0 place-items-center text-brand-500">
                {String(option.id) === String(value) ? <Check size={14} /> : <Link2 size={14} />}
              </span>
              <span className="min-w-0">
                <b className="block text-[13px] font-medium text-ink">{option.subject}</b>
                <small className="block text-xs text-muted">{option.reference_code}</small>
              </span>
            </button>
          ))}
          {!matches.length && (
            <p className="px-3 py-4 text-center text-xs text-muted">{t("formal_relatedNoResults")}</p>
          )}
        </div>
      )}
      <small className="text-xs text-muted">{t("formal_relatedHint")}</small>
    </div>
  );
}
