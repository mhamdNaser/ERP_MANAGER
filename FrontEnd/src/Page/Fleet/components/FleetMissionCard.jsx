import { Check, FileText, Paperclip, X } from "lucide-react";
import { useState } from "react";
import { useLanguage } from "../../../Provider/LanguageContext";
import {
  fleetDuration,
  fleetStageLabelKeys,
  fleetStatusLabelKeys,
  fleetStatusTone,
  fleetTypeLabelKeys,
} from "../fleetUtils";

/** بطاقة مهمة: بياناتها وسجل قراراتها، وأزرار البتّ لمن يملك المرحلة. */
export function FleetMissionCard({ item, canDecide = false, onDecide, onCancel, onOpenDocument, showOwner = true }) {
  const { t } = useLanguage();
  const [note, setNote] = useState("");
  const [busy, setBusy] = useState(false);

  const decide = async (action) => {
    setBusy(true);
    try {
      await onDecide(item, action, note);
      setNote("");
    } finally {
      setBusy(false);
    }
  };

  return (
    <article className="flex flex-col gap-3 rounded border border-line bg-surface p-4">
      <header className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0">
          <small className="eyebrow">{item.reference_code}</small>
          <b className="mt-1 block text-[13px] font-semibold text-ink">
            {t(fleetTypeLabelKeys[item.type] || item.type)}
          </b>
          {showOwner && item.user && (
            <small className="block text-xs text-muted">{item.user.name}{item.user.job_title ? ` — ${item.user.job_title}` : ""}</small>
          )}
          <small className="block text-xs text-muted">{fleetDuration(t, item)}</small>
          {item.destination && <small className="block text-xs text-muted">{t("fleet_destination")}: {item.destination}</small>}
        </div>
        <div className="flex flex-col items-end gap-1">
          <span className={`badge ${fleetStatusTone(item.status)}`}>
            {t(fleetStatusLabelKeys[item.status] || item.status)}
          </span>
          {item.status?.startsWith("pending") && (
            <small className="text-xs text-muted">{t(fleetStageLabelKeys[item.stage] || item.stage)}</small>
          )}
        </div>
      </header>

      <p className="text-[13px] leading-relaxed whitespace-pre-wrap text-ink">{item.reason}</p>

      {item.attachment_url && (
        <a className="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-500 hover:underline" href={item.attachment_url} target="_blank" rel="noreferrer">
          <Paperclip size={13} />
          {item.attachment_name || t("fleet_attachment")}
        </a>
      )}

      {onOpenDocument && item.status === "approved" && (
        <div className="flex flex-wrap items-center gap-1.5">
          <button type="button" className="btn btn-secondary btn-sm" onClick={() => onOpenDocument(item)}>
            <FileText size={14} />
            {t("fleet_openDocument")}
          </button>
          {item.pdf_url && (
            <a className="btn btn-secondary btn-sm no-underline" href={item.pdf_url} target="_blank" rel="noreferrer" download>PDF</a>
          )}
          {item.word_url && (
            <a className="btn btn-secondary btn-sm no-underline" href={item.word_url} target="_blank" rel="noreferrer" download>Word</a>
          )}
        </div>
      )}

      {!!item.actions?.length && (
        <ol className="flex flex-col gap-1 rounded border border-line bg-subtle p-2">
          {item.actions.map((action) => (
            <li key={action.id} className="text-xs text-muted">
              <b className="text-ink">{t(fleetStageLabelKeys[action.stage] || action.stage)}</b>
              {" — "}
              {t(`fleet_action_${action.action}`)}
              {action.actor ? ` · ${action.actor.name}` : ""}
              {action.note ? ` · ${action.note}` : ""}
            </li>
          ))}
        </ol>
      )}

      {canDecide && (
        <div className="flex flex-col gap-2 border-t border-line pt-3">
          <input
            className="input"
            placeholder={t("fleet_decisionNote")}
            value={note}
            onChange={(event) => setNote(event.target.value)}
          />
          <div className="flex items-center justify-end gap-2">
            <button type="button" className="btn btn-danger btn-sm" disabled={busy} onClick={() => decide("reject")}>
              <X size={14} />
              {t("fleet_reject")}
            </button>
            <button type="button" className="btn btn-primary btn-sm" disabled={busy} onClick={() => decide("approve")}>
              <Check size={14} />
              {t("fleet_approve")}
            </button>
          </div>
        </div>
      )}

      {onCancel && item.status?.startsWith("pending") && (
        <div className="flex justify-end border-t border-line pt-3">
          <button type="button" className="btn btn-secondary btn-sm" onClick={() => onCancel(item)}>
            {t("fleet_cancelMission")}
          </button>
        </div>
      )}
    </article>
  );
}
