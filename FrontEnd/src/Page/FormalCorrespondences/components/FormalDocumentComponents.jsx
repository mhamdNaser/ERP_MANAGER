import { FileText, Save, UploadCloud, X } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { useLanguage } from "../../../Provider/LanguageContext";
import { api } from "../../../lib";
import { recipientLine } from "./formalUtils";

export function DocumentPreviewBlock({ document, compact = false }) {
  if (document.document_type === "internal_letter") {
    return <OfficialDocumentPanel document={document} compact={compact} />;
  }

  return <ExternalDocumentPanel document={document} compact={compact} />;
}

export function OfficialDocumentPanel({ document, compact = false }) {
  const { t } = useLanguage();
  const hasWord = Boolean(document.attachment_url);
  const hasPdf = Boolean(document.pdf_url);
  const openInWordUrl = hasWord ? `ms-word:ofe|u|${document.attachment_url}` : "";

  return (
    <article className="grid grid-cols-[36px_minmax(0,1fr)] items-start gap-3 rounded border border-line bg-surface p-4">
      <div className="inline-grid h-9 w-9 place-items-center rounded border border-line bg-canvas text-brand-500">
        <FileText size={16} />
      </div>
      <div className="flex min-w-0 flex-col gap-1.5">
        <small className="text-xs text-muted">{t("formal_wordFromTemplate")}</small>
        <b className="text-[13px] font-semibold break-words text-ink">{document.title || t("formal_outgoingLetter")}</b>
        <p className="text-[13px] font-semibold text-ink">{recipientLine(t, document.target_label || t("formal_theRecipient"))}</p>
        <div className="flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted">
          <span>{t("formal_registryNumberLine", { value: document.registry_number || t("formal_notEntered") })}</span>
          <span>{t("formal_qrNumberLine", { value: document.book_number || document.qr_payload || t("formal_notGenerated") })}</span>
        </div>
      </div>
      <div className="col-span-2 flex flex-wrap items-center justify-end gap-2">
        {hasPdf && (
          <a className="btn btn-secondary btn-sm no-underline" href={document.pdf_url} target="_blank" rel="noreferrer">
            {t("formal_previewPdf")}
          </a>
        )}
        {hasWord && (
          <>
            {!hasPdf && (
              <a className="btn btn-primary btn-sm no-underline" href={openInWordUrl}>
                {t("formal_openInWord")}
              </a>
            )}
            <a className="btn btn-secondary btn-sm no-underline" href={document.attachment_url} download={document.attachment_name || "official-letter.docx"}>
              {t("formal_downloadWord")}
            </a>
          </>
        )}
      </div>
      {!compact && hasPdf ? (
        <iframe className="col-span-2 min-h-[540px] w-full rounded border border-line bg-surface" src={document.pdf_url} title={document.title || "official document"} />
      ) : !compact ? (
        <div className="empty-state col-span-2">
          <FileText size={28} className="text-muted" />
          <b className="text-sm font-semibold text-ink">{hasWord ? t("formal_wordReady") : t("formal_wordNotReady")}</b>
          <p>
            {hasWord
              ? t("formal_wordFallbackHint")
              : t("formal_saveOrRegenerateHint")}
          </p>
        </div>
      ) : null}
    </article>
  );
}

export function ExternalDocumentPanel({ document, compact = false }) {
  const { t } = useLanguage();
  const hasAttachment = document.attachment_url && document.attachment_url.trim();
  const attachmentName = document.attachment_name || document.title || "document";
  const isImage =
    document.attachment_mime?.startsWith("image/") ||
    /\.(png|jpe?g|gif|webp|bmp)$/i.test(attachmentName);
  const isPdf =
    document.attachment_mime === "application/pdf" ||
    /\.pdf$/i.test(attachmentName);

  if (!hasAttachment) {
    return (
      <div className="empty-state">
        <FileText size={28} className="text-muted" />
        <b className="text-sm font-semibold text-ink">{t("formal_noAttachment")}</b>
        <p>{t("formal_externalAttachHint")}</p>
      </div>
    );
  }

  return (
    <article className="flex items-start gap-3 rounded border border-line bg-surface p-3">
      <FileText size={18} className="mt-0.5 shrink-0 text-muted" />
      <div className="min-w-0 flex-1">
        <b className="block text-[13px] font-semibold break-words text-ink">{document.title}</b>
        <small className="block text-xs text-muted">{document.source_label || t("formal_sourceUnspecified")} ← {document.target_label || t("formal_targetUnspecified")}</small>
        <div className="mt-2 flex flex-wrap items-center gap-2">
          <a className="btn btn-secondary btn-sm no-underline" href={document.attachment_url} target="_blank" rel="noreferrer">{t("formal_view")}</a>
          <a className="btn btn-secondary btn-sm no-underline" href={document.attachment_url} download={document.attachment_name}>{t("formal_download")}</a>
        </div>
        {!compact && isImage && <img className="mt-3 max-h-64 w-full rounded border border-line bg-canvas object-contain" src={document.attachment_url} alt={attachmentName} />}
        {!compact && isPdf && !isImage && <iframe className="mt-3 h-80 w-full rounded border border-line bg-canvas" src={document.attachment_url} title={attachmentName} />}
      </div>
    </article>
  );
}

export function RichTextField({ value, onChange, placeholder, disabled = false }) {
  const { t } = useLanguage();
  const editorRef = useRef(null);

  useEffect(() => {
    if (editorRef.current && editorRef.current.innerText !== value) {
      editorRef.current.innerText = value || "";
    }
  }, [value]);

  const format = (command) => {
    editorRef.current?.focus();
    document.execCommand(command, false, null);
    onChange(editorRef.current?.innerText || "");
  };

  return (
    <div className="overflow-hidden rounded border border-line bg-white">
      {!disabled && (
      <div className="flex items-center gap-1.5 border-b border-line bg-subtle p-1.5">
        <button type="button" className="inline-grid h-7 w-7 place-items-center rounded border border-line bg-white text-[13px] font-bold text-ink hover:bg-canvas" onClick={() => format("bold")} title={t("formal_bold")}>B</button>
        <button type="button" className="inline-grid h-7 w-7 place-items-center rounded border border-line bg-white text-[13px] font-bold text-ink hover:bg-canvas" onClick={() => format("italic")} title={t("formal_italic")}>I</button>
        <button type="button" className="inline-grid h-7 w-7 place-items-center rounded border border-line bg-white text-[13px] font-bold text-ink hover:bg-canvas" onClick={() => format("insertUnorderedList")} title={t("formal_list")}>•</button>
      </div>
      )}
      <div
        ref={editorRef}
        className={`min-h-36 p-3 text-[13px] leading-loose whitespace-pre-wrap outline-none empty:before:text-muted empty:before:content-[attr(data-placeholder)] ${disabled ? "cursor-not-allowed bg-subtle text-muted" : "text-ink"}`}
        contentEditable={!disabled}
        dir="rtl"
        role="textbox"
        aria-multiline="true"
        data-placeholder={placeholder}
        suppressContentEditableWarning
        onInput={(event) => onChange(event.currentTarget.innerText)}
      />
    </div>
  );
}

export function ViewDocumentModal({ document, close, done, notify, canEdit = false }) {
  const { t } = useLanguage();
  const [attachment, setAttachment] = useState(null);
  const [uploading, setUploading] = useState(false);
  const [savingEdit, setSavingEdit] = useState(false);
  const [editDraft, setEditDraft] = useState({
    title: document.title || "",
    registry_number: document.registry_number || "",
    document_target: document.target_label || "",
    document_body: document.body || "",
  });

  const uploadAttachment = async (event) => {
    event.preventDefault();
    if (!attachment) return;

    setUploading(true);
    try {
      const formData = new FormData();
      formData.append("attachment", attachment);
      
      const updated = await api.updateFormalCorrespondenceDocument(document.id, formData);
      setAttachment(null);
      done(updated);
      notify?.(t("formal_paperAttachedToast"), "success");
    } catch (error) {
      notify?.(error.message, "error");
    } finally {
      setUploading(false);
    }
  };

  const saveDocumentEdits = async (event) => {
    event.preventDefault();
    setSavingEdit(true);
    try {
      const updated = await api.updateFormalCorrespondenceDocument(document.id, editDraft);
      notify?.(t("formal_docEditedToast"), "success");
      done(updated);
    } catch (error) {
      notify?.(error.message, "error");
    } finally {
      setSavingEdit(false);
    }
  };

  const hasAttachment = document.attachment_url && document.attachment_url.trim();
  const attachmentName = document.attachment_name || document.title || "document";
  const isImage =
    document.attachment_mime?.startsWith("image/") ||
    /\.(png|jpe?g|gif|webp|bmp)$/i.test(attachmentName);
  const isPdf =
    document.attachment_mime === "application/pdf" ||
    /\.pdf$/i.test(attachmentName);
  return (
    <div className="overlay grid place-items-center overflow-y-auto">
      <section className="modal max-w-6xl">
        <header className="modal-head">
          <div className="min-w-0">
            <small className="eyebrow">{document.document_type === "internal_letter" ? t("formal_outgoingFromOrg") : t("formal_uploadedFile")}</small>
            <h2 className="mt-1 text-base font-semibold break-words text-ink">{document.title || t("formal_docDetailsTitle")}</h2>
            <p className="text-xs text-muted">{document.document_type === "internal_letter" ? t("formal_outgoingFromOrg") : t("formal_uploadedFile")}</p>
          </div>
          <button className="btn-icon shrink-0" onClick={close} aria-label={t("formal_close")}><X size={16} /></button>
        </header>

        <div className="modal-body">
          <div className="grid grid-cols-1 items-start gap-4 lg:grid-cols-[minmax(230px,300px)_minmax(0,1fr)]">
            <aside className="flex flex-col gap-3 rounded border border-line bg-subtle p-3">
              <div className="flex items-center gap-3 rounded bg-brand-800 p-3 text-white">
                <FileText size={18} className="shrink-0 text-white/70" />
                <div className="min-w-0">
                  <span className="block text-[11px] text-white/60">{t("formal_docTypeLabel")}</span>
                  <b className="block text-[13px] font-semibold break-words">{document.document_type === "internal_letter" ? t("formal_outgoingLetter") : t("formal_fileInChain")}</b>
                </div>
              </div>
              <div className="flex flex-col gap-2">
                <p className="flex flex-col gap-1 rounded border border-line bg-surface p-3"><small className="text-xs font-semibold text-muted">{t("formal_titleLabel")}</small><b className="text-[13px] font-semibold break-words text-ink">{document.title || t("formal_unspecifiedM")}</b></p>
                <p className="flex flex-col gap-1 rounded border border-line bg-surface p-3"><small className="text-xs font-semibold text-muted">{t("formal_sourceLabel")}</small><b className="text-[13px] font-semibold break-words text-ink">{document.source_label || t("formal_unspecifiedM")}</b></p>
                <p className="flex flex-col gap-1 rounded border border-line bg-surface p-3"><small className="text-xs font-semibold text-muted">{t("formal_targetLabel")}</small><b className="text-[13px] font-semibold break-words text-ink">{document.target_label || t("formal_unspecifiedM")}</b></p>
                <p className="flex flex-col gap-1 rounded border border-line bg-surface p-3"><small className="text-xs font-semibold text-muted">{t("formal_registryNumberLabel")}</small><b className="text-[13px] font-semibold break-words text-ink">{document.registry_number || t("formal_notEntered")}</b></p>
                <p className="flex flex-col gap-1 rounded border border-line bg-surface p-3"><small className="text-xs font-semibold text-muted">{t("formal_qrNumberLabel")}</small><b className="text-[13px] font-semibold break-words text-ink">{document.book_number || document.qr_payload || t("formal_notGenerated")}</b></p>
                <p className="flex flex-col gap-1 rounded border border-line bg-surface p-3"><small className="text-xs font-semibold text-muted">{t("formal_fileNameLabel")}</small><b className="text-[13px] font-semibold break-words text-ink">{hasAttachment ? attachmentName : t("formal_noAttachmentShort")}</b></p>
              </div>
            </aside>

            <main className="flex min-w-0 flex-col gap-4">
              {document.document_type === "internal_letter" && canEdit && (
                <section className="flex flex-col gap-3">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                      <h4 className="text-sm font-semibold text-ink">{t("formal_officialLetterFile")}</h4>
                      <p className="text-xs text-muted">{t("formal_officialLetterFileHint")}</p>
                    </div>
                  </div>
                  <OfficialDocumentPanel document={document} />
                </section>
              )}

              {document.document_type === "internal_letter" && (
                <form className="flex flex-col gap-3" onSubmit={saveDocumentEdits}>
                  <section className="flex flex-col gap-3">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div className="min-w-0">
                        <h4 className="text-sm font-semibold text-ink">{t(canEdit ? "formal_editLetterData" : "formal_letterDataTitle")}</h4>
                        <p className="text-xs text-muted">{t(canEdit ? "formal_editLetterHint" : "formal_letterDataReadOnlyHint")}</p>
                      </div>
                    </div>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                      <label className="field mb-0">
                        <span className="label">{t("formal_registryNumberLabel")}</span>
                        <input
                          className="input"
                          disabled={!canEdit}
                          value={editDraft.registry_number}
                          onChange={(event) => setEditDraft({ ...editDraft, registry_number: event.target.value })}
                        />
                      </label>
                      <label className="field mb-0">
                        <span className="label">{t("formal_letterTitleLabel")}</span>
                        <input
                          className="input"
                          disabled={!canEdit}
                          value={editDraft.title}
                          onChange={(event) => setEditDraft({ ...editDraft, title: event.target.value })}
                        />
                      </label>
                      <label className="field mb-0 sm:col-span-2">
                        <span className="label">{t("formal_toLabel")}</span>
                        <input
                          className="input"
                          disabled={!canEdit}
                          value={editDraft.document_target}
                          onChange={(event) => setEditDraft({ ...editDraft, document_target: event.target.value })}
                        />
                      </label>
                      <label className="field mb-0 sm:col-span-2">
                        <span className="label">{t("formal_letterBodyLabel")}</span>
                        <RichTextField
                          value={editDraft.document_body}
                          onChange={(value) => setEditDraft({ ...editDraft, document_body: value })}
                          placeholder={t("formal_letterBodyPlaceholder")}
                          disabled={!canEdit}
                        />
                      </label>
                    </div>
                    {canEdit && (
                      <footer className="flex items-center justify-end gap-2">
                        <button className="btn btn-primary" disabled={savingEdit || !editDraft.document_body.trim()}>
                          <Save size={16} />
                          {savingEdit ? t("formal_saving") : t("formal_saveAndRegenerate")}
                        </button>
                      </footer>
                    )}
                  </section>
                </form>
              )}

              {document.document_type !== "internal_letter" && hasAttachment ? (
                <section className="flex flex-col gap-3">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                      <h4 className="text-sm font-semibold text-ink">{t("formal_filePreview")}</h4>
                      <p className="text-xs break-words text-muted">{attachmentName}</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                      {document.pdf_url && (
                        <a className="btn btn-secondary btn-sm no-underline" href={document.pdf_url} target="_blank" rel="noreferrer">
                          {t("formal_previewPdf")}
                        </a>
                      )}
                      <a className="btn btn-secondary btn-sm no-underline" href={document.attachment_url} target="_blank" rel="noreferrer">
                        {t("formal_view")}
                      </a>
                      <a className="btn btn-secondary btn-sm no-underline" href={document.attachment_url} download={document.attachment_name}>
                        {t("formal_download")}
                      </a>
                    </div>
                  </div>
                  {isImage && (
                    <a className="block overflow-hidden rounded border border-line bg-canvas" href={document.attachment_url} target="_blank" rel="noreferrer">
                      <img className="mx-auto block max-h-[560px] w-full object-contain" src={document.attachment_url} alt={attachmentName} />
                    </a>
                  )}
                  {isPdf && !isImage && (
                    <iframe
                      className="h-[560px] w-full rounded border border-line bg-canvas"
                      src={document.attachment_url}
                      title={attachmentName}
                    />
                  )}
                  {!isImage && !isPdf && (
                    <div className="empty-state">
                      <FileText size={28} className="text-muted" />
                      <b className="text-sm font-semibold text-ink">{t("formal_noInlinePreview")}</b>
                      <p>{t("formal_openOrDownloadHint")}</p>
                    </div>
                  )}
                </section>
              ) : document.document_type !== "internal_letter" && canEdit ? (
                <form className="flex flex-col gap-4" onSubmit={uploadAttachment}>
                  <div className="flex flex-col gap-3">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div className="min-w-0">
                        <h4 className="text-sm font-semibold text-ink">{t("formal_attachPaper")}</h4>
                        <p className="text-xs text-muted">{t("formal_attachPaperHint")}</p>
                      </div>
                    </div>
                    <label className="flex cursor-pointer flex-col items-center gap-2 rounded border border-dashed border-line bg-subtle p-5 text-center hover:border-line-strong">
                      <UploadCloud size={26} className="text-brand-500" />
                      <span className="text-[13px] font-medium text-ink">
                        {attachment ? attachment.name : t("formal_pickImageOrPdf")}
                      </span>
                      <input
                        className="hidden"
                        type="file"
                        accept=".pdf,.png,.jpg,.jpeg"
                        onChange={(event) => setAttachment(event.target.files?.[0] ?? null)}
                        disabled={uploading}
                      />
                    </label>
                  </div>
                  <footer className="flex items-center justify-end gap-2">
                    <button type="button" className="btn btn-secondary" onClick={close}>{t("formal_close")}</button>
                    <button className="btn btn-primary" disabled={!attachment || uploading}>
                      <UploadCloud size={16} />
                      {uploading ? t("formal_uploading") : t("formal_savePaper")}
                    </button>
                  </footer>
                </form>
              ) : null}

              {hasAttachment && (
                <footer className="flex items-center justify-end gap-2">
                  <button type="button" className="btn btn-secondary" onClick={close}>{t("formal_close")}</button>
                </footer>
              )}
            </main>
          </div>
        </div>
      </section>
    </div>
  );
}

