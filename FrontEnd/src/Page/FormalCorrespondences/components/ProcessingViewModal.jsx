import { Download, FileText, Route, X } from "lucide-react";
import { useLanguage } from "../../../Provider/LanguageContext";
import { actionLabelKey, decisionLabel, statusLabelKeys } from "./formalUtils";

function InfoRow({ label, children }) {
  return (
    <p className="flex flex-col gap-1 rounded border border-line bg-surface p-3">
      <small className="text-xs font-semibold text-muted">{label}</small>
      <b className="text-[13px] font-semibold break-words text-ink">{children}</b>
    </p>
  );
}

/** معاينة كتاب المعالجة: الملف في المنتصف وبيانات المعالجة في الشريط الجانبي. */
export function ProcessingViewModal({ stage, correspondence, close }) {
  const { t } = useLanguage();
  const attachments = stage.documents || [];
  // معالجة التسجيل لا كتاب لها؛ محتواها هو ملف المراسلة الوارد.
  const previewUrl = stage.pdf_url
    || (stage.event === "created" ? correspondence.attachment_url : null);
  const previewLabel = stage.pdf_url
    ? stage.letter_title || t("formal_processingLetterTitle")
    : correspondence.attachment_name || t("formal_attachmentPreviewTitle");

  return (
    <div className="overlay grid place-items-center overflow-y-auto">
      <section className="modal max-w-6xl">
        <header className="modal-head">
          <div className="min-w-0">
            <small className="eyebrow">{t("formal_processingLetterTitle")}</small>
            <h2 className="mt-1 text-base font-semibold break-words text-ink">{stage.letter_title || stage.target_label}</h2>
            <p className="text-xs text-muted">{new Date(stage.created_at).toLocaleDateString()}</p>
          </div>
          <button className="btn-icon shrink-0" onClick={close} aria-label={t("formal_close")}><X size={16} /></button>
        </header>
        <div className="modal-body">
          <div className="grid grid-cols-1 items-start gap-4 lg:grid-cols-[minmax(230px,300px)_minmax(0,1fr)]">
            <aside className="flex flex-col gap-3 rounded border border-line bg-subtle p-3">
              <div className="flex items-center gap-3 rounded bg-brand-800 p-3 text-white">
                <Route size={18} className="shrink-0 text-white/70" />
                <div className="min-w-0">
                  <span className="block text-[11px] text-white/60">{t("formal_correspondenceLabel")}</span>
                  <b className="block text-[13px] font-semibold break-words">{correspondence.reference_code}</b>
                </div>
              </div>
              <div className="flex flex-col gap-2">
                <InfoRow label={t("formal_sourcePartyLabel")}>{stage.source_label || t("formal_sourceUnspecified")}</InfoRow>
                <InfoRow label={t("formal_addressedPartyLabel")}>{stage.target_label || t("formal_targetUnspecified")}</InfoRow>
                <InfoRow label={t("formal_requiredLabel")}>{t(actionLabelKey(stage.action_required))}</InfoRow>
                <InfoRow label={t("formal_registryNumberLabel")}>{stage.registry_number || t("formal_unspecifiedM")}</InfoRow>
                <InfoRow label={t("formal_qrNumberLabel")}>{stage.book_number || t("formal_notGenerated")}</InfoRow>
                <InfoRow label={t("formal_statusLabel")}>
                  {statusLabelKeys[stage.to_status] ? t(statusLabelKeys[stage.to_status]) : stage.to_status || t("formal_unspecifiedF")}
                </InfoRow>
                <InfoRow label={t("formal_decisionFieldLabel")}>{decisionLabel(t, stage.decision_type, stage.decision_status)}</InfoRow>
                {stage.assigned_user && (
                  <InfoRow label={t("formal_assignEmployeeTitle")}>{stage.assigned_user.name}</InfoRow>
                )}
              </div>
            </aside>

            <main className="flex min-w-0 flex-col gap-4">
              {previewUrl ? (
                <iframe
                  className="min-h-[600px] w-full rounded border border-line bg-surface"
                  src={previewUrl}
                  title={previewLabel}
                />
              ) : (
                <div className="empty-state">
                  <FileText size={28} className="text-muted" />
                  <b className="text-sm font-semibold text-ink">{t("formal_pdfNotReady")}</b>
                  <p>{t("formal_pdfNotReadyHint")}</p>
                </div>
              )}

              {!!attachments.length && (
                <section className="flex flex-col gap-2">
                  <h4 className="text-sm font-semibold text-ink">{t("formal_processingAttachments")}</h4>
                  {attachments.map((document) => (
                    <article key={document.id} className="flex items-center gap-3 rounded border border-line bg-subtle p-3">
                      <FileText size={17} className="shrink-0 text-muted" />
                      <b className="min-w-0 flex-1 truncate text-[13px] font-semibold text-ink">
                        {document.attachment_name || document.title}
                      </b>
                      {document.attachment_url && (
                        <a
                          className="btn btn-secondary btn-sm no-underline"
                          href={document.attachment_url}
                          target="_blank"
                          rel="noreferrer"
                          download={document.attachment_name}
                        >
                          <Download size={14} />
                          {t("formal_download")}
                        </a>
                      )}
                    </article>
                  ))}
                </section>
              )}
            </main>
          </div>
        </div>
      </section>
    </div>
  );
}
