import { Bell, CheckCircle2, FileText, Trash2, X, XCircle } from "lucide-react";
import { useEffect, useRef } from "react";
import { sound } from "../lib";
import { useLanguage } from "../Provider/LanguageContext";
export function Toast({ text, kind, close }) {
  const { t } = useLanguage();
  const played = useRef(false);
  useEffect(() => {
    if (!played.current) {
      sound(kind);
      played.current = true;
    }
    const id = setTimeout(close, 4200);
    return () => clearTimeout(id);
  }, [close, kind]);
  const Icon =
    kind === "error"
      ? XCircle
      : kind === "success"
        ? CheckCircle2
        : kind === "delete"
          ? Trash2
          : Bell;
  return (
    <div
      className={`fixed bottom-4 start-4 z-[60] flex max-w-sm items-center gap-2.5 rounded border px-3 py-2.5 text-[13px] ${tones[kind] || tones.info}`}
    >
      <Icon size={18} className="shrink-0" />
      <span className="flex-1">{text}</span>
      <button
        onClick={close}
        aria-label={t("ui_close")}
        className="shrink-0 opacity-60 hover:opacity-100"
      >
        <X size={15} />
      </button>
    </div>
  );
}
const tones = {
  error: "border-danger-500/25 bg-danger-50 text-danger-500",
  delete: "border-danger-500/25 bg-danger-50 text-danger-500",
  success: "border-ok-500/25 bg-ok-50 text-ok-500",
  info: "border-line bg-surface text-ink",
};
export function Empty({ text }) {
  return (
    <div className="empty-state">
      <FileText size={22} className="text-line-strong" />
      <p>{text}</p>
    </div>
  );
}
