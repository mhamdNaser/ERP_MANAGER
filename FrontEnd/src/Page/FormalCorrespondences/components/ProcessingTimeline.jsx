import { Download, Edit3, Eye, FileText, Search, Trash2 } from "lucide-react";
import { useState } from "react";
import { useConfirm } from "../../../Provider/ConfirmContext";
import { useLanguage } from "../../../Provider/LanguageContext";
import { api } from "../../../lib";
import { stageTone } from "./formalUtils";

const stageToneClass = {
  decision: "bg-warn-500",
  study: "bg-info-500",
  execution: "bg-ok-500",
  reply: "bg-brand-500",
  hold: "bg-danger-500",
  route: "bg-brand-800",
};

/** سلسلة المعالجات: ما يظهر منها محكوم بتسلسل الجهات في الخادم. */
export function ProcessingTimeline({ item, events, canDelete, notify, changed, onViewStage, onPreviewProcessing, onEditProcessing }) {
  const { t } = useLanguage();
  const confirm = useConfirm();
  const [search, setSearch] = useState("");

  const deleteStage = async (stage) => {
    if (
      !(await confirm({
        title: t("formal_deleteProcessingTitle"),
        message: t("formal_deleteProcessingMsg", { place: stage.target_label || stage.meta?.place || stage.event }),
        confirmLabel: t("formal_deleteProcessingConfirm"),
      }))
    ) return;

    try {
      const updated = await api.deleteFormalCorrespondenceEvent(stage.id);
      changed(updated);
      notify?.(t("formal_processingDeletedToast"), "success");
    } catch (error) {
      notify?.(error.message, "error");
    }
  };

  const query = search.trim().toLowerCase();
  const filtered = events.filter((event) => {
    if (!query) return true;
    return (
      event.target_label?.toLowerCase().includes(query) ||
      event.source_label?.toLowerCase().includes(query) ||
      event.documents?.some((doc) =>
        doc.title?.toLowerCase().includes(query) ||
        doc.source_label?.toLowerCase().includes(query) ||
        doc.target_label?.toLowerCase().includes(query))
    );
  });

  return (
    <div className="flex min-h-0 flex-col">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-3">
        <div className="min-w-0">
          <h3 className="section-title">{t("formal_processingTimelineTitle")}</h3>
          <p className="text-xs text-muted">{item.visible_events_count === item.events?.length ? t("formal_timelineHintAll") : t("formal_timelineHintFiltered")}</p>
        </div>
        <div className="inline-flex items-center gap-2 rounded border border-line bg-subtle px-3 py-2">
          <Search size={15} className="shrink-0 text-muted" />
          <input
            className="w-[200px] min-w-0 border-0 bg-transparent text-[13px] text-ink outline-none"
            placeholder={t("formal_timelineSearchPlaceholder")}
            value={search}
            onChange={(event) => setSearch(event.target.value)}
          />
        </div>
      </div>
      <div className="flex max-h-[560px] flex-col gap-4 overflow-y-auto p-4">
        {filtered.map((event, index) => (
          <article key={event.id} className="grid grid-cols-[32px_minmax(0,1fr)] gap-3">
            <i className={`inline-grid h-8 w-8 place-items-center rounded text-xs font-bold text-white not-italic ${stageToneClass[stageTone(event)] || "bg-brand-800"}`}>{index + 1}</i>
            <div className="min-w-0">
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0">
                  <b className="block text-[13px] font-semibold break-words text-ink">{event.target_label || event.meta?.place || event.event}</b>
                  <small className="block text-xs text-muted">
                    {event.source_label || t("formal_sourceUnspecified")} ← {event.target_label || t("formal_targetUnspecified")}
                  </small>
                  <small className="text-xs text-muted">{new Date(event.created_at).toLocaleDateString()}</small>
                  {event.assigned_user && (
                    <small className="mt-1 block text-xs font-semibold text-brand-500">
                      {t("formal_assignedTo", { name: event.assigned_user.name })}
                    </small>
                  )}
                </div>
                <div className="flex flex-wrap items-center gap-1.5">
                  <button type="button" className="btn btn-secondary btn-sm" onClick={() => onViewStage(event)} title={t("formal_viewProcessingTitle")}>
                    <Eye size={14} />
                    <span>{t("formal_stageDetailsTitle")}</span>
                  </button>
                  {canDelete && event.event !== "created" && (
                    <button type="button" className="btn btn-danger btn-sm" onClick={() => deleteStage(event)} title={t("formal_deleteProcessingConfirm")}>
                      <Trash2 size={14} />
                      <span>{t("delete")}</span>
                    </button>
                  )}
                </div>
              </div>
              <div className="mt-3 flex flex-wrap items-center gap-1.5">
                {(event.pdf_url || (event.event === "created" && item.attachment_url)) && (
                  <button
                    type="button"
                    className="btn btn-secondary btn-sm"
                    onClick={() => onPreviewProcessing(event)}
                    title={t("formal_previewProcessingTitle")}
                  >
                    <Eye size={14} />
                    <span>{t("formal_preview")}</span>
                  </button>
                )}
                {event.can_edit === true && (
                  <button
                    type="button"
                    className="btn btn-secondary btn-sm"
                    onClick={() => onEditProcessing(event)}
                    title={t("formal_editProcessingTitle")}
                  >
                    <Edit3 size={14} />
                    <span>{t("edit")}</span>
                  </button>
                )}
                {event.pdf_url && (
                  <a className="btn btn-secondary btn-sm no-underline" href={event.pdf_url} target="_blank" rel="noreferrer" download>
                    <Download size={14} />
                    <span>PDF</span>
                  </a>
                )}
                {event.word_url && (
                  <a className="btn btn-secondary btn-sm no-underline" href={event.word_url} target="_blank" rel="noreferrer" download>
                    <Download size={14} />
                    <span>Word</span>
                  </a>
                )}
              </div>

              {!!event.documents?.length && (
                <div className="mt-3 flex flex-col gap-2">
                  {event.documents.map((document) => (
                    <article key={document.id} className="flex items-center gap-3 rounded border border-line bg-subtle p-3">
                      <FileText size={17} className="shrink-0 text-muted" />
                      <b className="min-w-0 flex-1 truncate text-[13px] font-semibold text-ink">
                        {document.attachment_name || document.title}
                      </b>
                      {document.attachment_url && (
                        <a
                          className="btn-icon shrink-0 no-underline"
                          title={t("formal_download")}
                          href={document.attachment_url}
                          target="_blank"
                          rel="noreferrer"
                          download={document.attachment_name}
                        >
                          <Download size={15} />
                        </a>
                      )}
                    </article>
                  ))}
                </div>
              )}
            </div>
          </article>
        ))}
      </div>
    </div>
  );
}
