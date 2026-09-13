import { Edit3, Plus, Save, Trash2 } from "lucide-react";
import { useMemo, useState } from "react";
import { useConfirm } from "../../../Provider/ConfirmContext";
import { useLanguage } from "../../../Provider/LanguageContext";
import { api } from "../../../lib";
import { AddProcessingDrawer } from "./AddProcessingDrawer";
import { ProcessingTimeline } from "./ProcessingTimeline";
import { ProcessingViewModal } from "./ProcessingViewModal";
import { StageDetailsModal } from "./StageDetailsModal";
import {
  canEditCorrespondence,
  canProcessCorrespondence,
  correspondenceParty,
  statusLabelKeys,
  typeLabelKeys,
} from "./formalUtils";

export function FormalDetails({ item, changed, deleted, notify, user, directory, canDelete }) {
  const { t } = useLanguage();
  const confirm = useConfirm();
  const events = [...(item.events || [])].sort(
    (a, b) => new Date(a.created_at) - new Date(b.created_at),
  );
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editingStage, setEditingStage] = useState(null);
  const [previewStage, setPreviewStage] = useState(null);
  const [editorOpen, setEditorOpen] = useState(false);
  const [stageViewer, setStageViewer] = useState(null);
  const canAddProcessing = canProcessCorrespondence(user, item);
  const canEditItem = canEditCorrespondence(user, item);
  const [itemDraft, setItemDraft] = useState({
    subject: item.subject || "",
    summary: item.summary || "",
    body: item.body || "",
    attachment: null,
  });
  const placeOptions = useMemo(
    () => [
      ...(directory?.places || []),
      ...(directory?.external_entities || []).map((entity) => entity.name),
      ...(directory?.branches || []).flatMap((branch) => [
        branch.name,
        ...(branch.departments || []).map((department) => department.name),
      ]),
    ].filter(Boolean),
    [directory],
  );
  // الجهة التي تحتفظ بالمراسلة حالياً — تصبح الجهة المصدرة للمعالجة التالية.
  const currentHolder = useMemo(() => {
    const latestEvent = events[events.length - 1];
    return latestEvent?.target_label ||
      latestEvent?.meta?.place ||
      correspondenceParty(item, "target") ||
      item.first_place ||
      t("formal_unspecifiedF");
  }, [events, item, t]);

  const saveItemEdit = async (event) => {
    event.preventDefault();
    try {
      const updated = await api.updateFormalCorrespondence(item.id, itemDraft);
      setEditorOpen(false);
      changed(updated);
      notify?.(t("formal_itemEditedToast"), "success");
    } catch (error) {
      notify?.(error.message, "error");
    }
  };

  const deleteItem = async () => {
    if (
      !(await confirm({
        title: t("formal_deleteTitle"),
        message: t("formal_deleteMsg", { subject: item.subject }),
        confirmLabel: t("formal_deleteConfirm"),
      }))
    ) return;

    try {
      await api.deleteFormalCorrespondence(item.id);
      deleted();
      notify?.(t("formal_deletedToast"), "success");
    } catch (error) {
      notify?.(error.message, "error");
    }
  };

  return (
    <section className="card flex flex-col">
      <header className="card-head">
        <div className="min-w-0">
          <small className="eyebrow">{item.reference_code}</small>
          <h2 className="mt-1 text-base font-semibold break-words text-ink">{item.subject}</h2>
          <p className="text-xs text-muted">{t(typeLabelKeys[item.direction])}</p>
        </div>
        <div className="flex items-center gap-2">
          <span className="badge badge-warn">{statusLabelKeys[item.status] ? t(statusLabelKeys[item.status]) : item.status}</span>
          <div className="flex items-center gap-1.5">
            {canEditItem && (
              <button
                type="button"
                className="btn-icon"
                title={t("formal_editItemTitle")}
                onClick={() => {
                  setItemDraft({
                    subject: item.subject || "",
                    summary: item.summary || "",
                    body: item.body || "",
                    attachment: null,
                  });
                  setEditorOpen((open) => !open);
                }}
              >
                <Edit3 size={15} />
              </button>
            )}
            {canDelete && (
              <button
                type="button"
                className="btn-icon border-danger-500/25 bg-danger-50 text-danger-500 hover:bg-danger-50/70 hover:text-danger-500"
                title={t("formal_deleteConfirm")}
                onClick={deleteItem}
              >
                <Trash2 size={15} />
              </button>
            )}
          </div>
        </div>
      </header>

      {editorOpen && (
        <form className="flex flex-col gap-4 border-b border-line p-4" onSubmit={saveItemEdit}>
          <label className="field mb-0">
            <span className="label">{t("formal_itemSubjectLabel")}</span>
            <input
              className="input"
              required
              value={itemDraft.subject}
              onChange={(event) => setItemDraft({ ...itemDraft, subject: event.target.value })}
            />
          </label>
          <label className="field mb-0">
            <span className="label">{t("formal_summaryLabel")}</span>
            <textarea
              className="textarea"
              value={itemDraft.summary}
              onChange={(event) => setItemDraft({ ...itemDraft, summary: event.target.value })}
            />
          </label>
          <label className="field mb-0">
            <span className="label">{t("formal_extraBodyLabel")}</span>
            <textarea
              className="textarea"
              value={itemDraft.body}
              onChange={(event) => setItemDraft({ ...itemDraft, body: event.target.value })}
            />
          </label>
          <label className="field mb-0">
            <span className="label">{t("formal_replaceFileLabel")}</span>
            <input
              className="input h-auto py-2 file:me-3 file:rounded file:border file:border-line file:bg-canvas file:px-2 file:py-1 file:text-xs file:font-semibold file:text-ink"
              type="file"
              accept=".pdf,.doc,.docx,.png,.jpg,.jpeg"
              onChange={(event) => setItemDraft({ ...itemDraft, attachment: event.target.files?.[0] ?? null })}
            />
          </label>
          <footer className="flex items-center justify-end gap-2">
            <button type="button" className="btn btn-secondary" onClick={() => setEditorOpen(false)}>{t("cancel")}</button>
            <button className="btn btn-primary">
              <Save size={16} />
              {t("formal_saveEditBtn")}
            </button>
          </footer>
        </form>
      )}

      <ProcessingTimeline
        item={item}
        events={events}
        user={user}
        canDelete={canDelete}
        notify={notify}
        changed={changed}
        onViewStage={setStageViewer}
        onPreviewProcessing={setPreviewStage}
        onEditProcessing={setEditingStage}
      />

      {canAddProcessing && (
        <div className="border-t border-line p-4">
          <button type="button" className="btn btn-primary w-full" onClick={() => setDrawerOpen(true)}>
            <Plus size={16} />
            {t("formal_addProcessingBtn")}
          </button>
        </div>
      )}

      {(drawerOpen || editingStage) && (
        <AddProcessingDrawer
          key={editingStage?.id || "new"}
          item={item}
          stage={editingStage}
          sourceLabel={editingStage?.source_label || currentHolder}
          directory={directory}
          placeOptions={placeOptions}
          close={() => {
            setDrawerOpen(false);
            setEditingStage(null);
          }}
          done={(updated) => {
            setDrawerOpen(false);
            setEditingStage(null);
            changed(updated);
          }}
          notify={notify}
        />
      )}

      {previewStage && (
        <ProcessingViewModal
          stage={previewStage}
          correspondence={item}
          close={() => setPreviewStage(null)}
        />
      )}

      {stageViewer && (
        <StageDetailsModal
          stage={stageViewer}
          correspondence={item}
          user={user}
          directory={directory}
          close={() => setStageViewer(null)}
          done={(updated) => {
            setStageViewer(null);
            changed(updated);
          }}
          notify={notify}
        />
      )}
    </section>
  );
}
