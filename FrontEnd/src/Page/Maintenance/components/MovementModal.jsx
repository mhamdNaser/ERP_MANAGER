import { ArrowLeft, Check, X } from "lucide-react";
import { useState } from "react";
import { useLanguage } from "../../../Provider/LanguageContext";
import { api } from "../../../lib";
import { STATUSES, formatNumber } from "../maintenanceMeta";

const TITLES = {
  move: ["mt_moveTitle", "mt_moveHint"],
  receive: ["mt_receiveTitle", "mt_receiveHint"],
  issue: ["mt_issueTitle", "mt_issueHint"],
};

/** زر حالة كبير: أسهل على غير التقني من قائمة منسدلة، ويعرض المتوفر فيه. */
function StatusChoice({ status, selected, count, disabled, onClick }) {
  const { t } = useLanguage();
  const Icon = status.icon;

  return (
    <button
      type="button"
      disabled={disabled}
      onClick={onClick}
      className={`flex min-w-0 flex-col items-start gap-0.5 rounded border px-2.5 py-2 text-start transition disabled:cursor-not-allowed disabled:opacity-40 ${
        selected ? "border-brand-500 bg-brand-50 ring-1 ring-brand-500" : "border-line bg-surface hover:border-line-strong"
      }`}
    >
      <span className="flex items-center gap-1.5 text-xs font-semibold text-ink">
        <Icon size={14} style={{ color: status.color }} aria-hidden />
        {t(`mt_status_${status.key}`)}
      </span>
      {count != null && <small className="text-[11px] text-muted">{t("mt_available", { count: formatNumber(count) })}</small>}
    </button>
  );
}

export function MovementModal({ item, kind, transitions, notify, close, done }) {
  const { t } = useLanguage();
  const sources = STATUSES.filter((status) => (item.quantities?.[status.key] || 0) > 0);
  const [from, setFrom] = useState(kind === "receive" ? "" : sources[0]?.key || "");
  const allowed = kind === "move" ? STATUSES.filter((status) => transitions?.[from]?.includes(status.key)) : [];
  const [to, setTo] = useState("");
  const [quantity, setQuantity] = useState(kind === "receive" ? "" : 1);
  const [note, setNote] = useState("");
  const [saving, setSaving] = useState(false);
  const available = from ? item.quantities?.[from] || 0 : Infinity;
  const target = kind === "move" && allowed.some((status) => status.key === to) ? to : "";
  const amount = Number(quantity);

  const ready =
    amount >= 1 &&
    amount <= available &&
    (kind === "receive" || from) &&
    (kind !== "move" || target) &&
    (kind !== "issue" || note.trim());

  const submit = async (event) => {
    event.preventDefault();
    if (!ready || saving) return;
    setSaving(true);
    try {
      const payload = { quantity: amount, note: note.trim() || null };
      if (kind !== "receive") payload.from_status = from;
      if (kind === "move") payload.to_status = target;
      await api.maintenanceMovement(item.id, kind, payload);
      notify?.(t("mt_moved"), "success");
      done();
    } catch (error) {
      notify?.(error.message, "error");
    } finally {
      setSaving(false);
    }
  };

  const [title, hint] = TITLES[kind];
  const empty = kind !== "receive" && !sources.length;

  return (
    <div className="overlay grid place-items-center overflow-y-auto">
      <section className="modal max-w-xl">
        <header className="modal-head">
          <div className="min-w-0">
            <small className="eyebrow">{item.name}{item.part_number ? ` · ${item.part_number}` : ""}</small>
            <h2 className="mt-1 text-base font-semibold text-ink">{t(title)}</h2>
            <p className="text-xs text-muted">{t(hint)}</p>
          </div>
          <button className="btn-icon shrink-0" onClick={close} aria-label={t("close")}><X size={16} /></button>
        </header>
        <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
          <div className="modal-body flex flex-col gap-4">
            {empty ? (
              <p className="empty-state">{t("mt_nothingToMove")}</p>
            ) : (
              <>
                {kind !== "receive" && (
                  <div className="flex flex-col gap-1.5">
                    <span className="label">{t("mt_from")}</span>
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                      {STATUSES.map((status) => (
                        <StatusChoice
                          key={status.key}
                          status={status}
                          count={item.quantities?.[status.key] || 0}
                          selected={from === status.key}
                          disabled={!item.quantities?.[status.key]}
                          onClick={() => { setFrom(status.key); setTo(""); }}
                        />
                      ))}
                    </div>
                  </div>
                )}

                {kind === "move" && from && (
                  <div className="flex flex-col gap-1.5">
                    <span className="label flex items-center gap-1">{t("mt_to")} <ArrowLeft size={12} className="text-muted rtl:rotate-0 ltr:rotate-180" /></span>
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                      {allowed.map((status) => (
                        <StatusChoice key={status.key} status={status} selected={target === status.key} onClick={() => setTo(status.key)} />
                      ))}
                    </div>
                  </div>
                )}

                <label className="field mb-0">
                  <span className="label">{t("mt_quantity")} {item.unit ? `(${item.unit})` : ""}</span>
                  <input
                    className="input"
                    type="number"
                    min="1"
                    max={Number.isFinite(available) ? available : undefined}
                    required
                    value={quantity}
                    onChange={(event) => setQuantity(event.target.value)}
                  />
                  {Number.isFinite(available) && <span className="hint">{t("mt_available", { count: formatNumber(available) })}</span>}
                </label>

                <label className="field mb-0">
                  <span className="label">{t("mt_note")}{kind === "issue" ? " *" : ""}</span>
                  <textarea
                    className="textarea min-h-16"
                    placeholder={t(kind === "issue" ? "mt_issueNotePlaceholder" : "mt_notePlaceholder")}
                    value={note}
                    onChange={(event) => setNote(event.target.value)}
                  />
                  {kind === "issue" && !note.trim() && <span className="hint">{t("mt_noteRequired")}</span>}
                </label>
              </>
            )}
          </div>
          <footer className="modal-foot">
            <button type="button" className="btn btn-secondary" onClick={close}>{t("cancel")}</button>
            <button className="btn btn-primary" disabled={!ready || saving || empty}>
              <Check size={16} />
              {saving ? t("saving") : t("mt_confirm")}
            </button>
          </footer>
        </form>
      </section>
    </div>
  );
}
