import { UploadCloud, X } from "lucide-react";
import { useMemo, useState } from "react";
import { AutocompleteInput } from "../../../Components/AutocompleteInput";
import { useLanguage } from "../../../Provider/LanguageContext";
import { api } from "../../../lib";
import { RichTextField } from "./FormalDocumentComponents";
import { defaultActionForTarget, selectedTargetLabel } from "./formalUtils";

const emptyStep = () => ({
  place: "",
  date: "",
  status: "routed",
  target_type: "general_manager",
  target_id: "",
  action_required: "decision",
  document_type: "internal_letter",
  document_title: "",
  registry_number: "",
  document_body: "",
  attachments: [],
});

// يملأ الفورم من معالجة قائمة عند التعديل.
function stepFromStage(stage, t) {
  return {
    ...emptyStep(),
    place: stage.target_label || stage.meta?.place || "",
    date: stage.meta?.date || "",
    status: stage.to_status || "routed",
    target_type: stage.target_type || "free_text",
    target_id: stage.target_id || "",
    action_required: stage.action_required || "route",
    document_title: stage.letter_title || t("formal_internalReferralLetter"),
    registry_number: stage.registry_number || "",
    document_body: stage.letter_body || "",
  };
}

/**
 * المعالجة هي الكتاب الصادر نفسه. الجهة المصدرة مثبتة على الجهة التي تحتفظ بالمراسلة.
 * تمرير `stage` يفتح النموذج في وضع التعديل.
 */
export function AddProcessingDrawer({ item, stage = null, sourceLabel, directory, placeOptions, close, done, notify }) {
  const { t } = useLanguage();
  const editing = Boolean(stage);
  const [step, setStep] = useState(() => (stage
    ? stepFromStage(stage, t)
    : { ...emptyStep(), document_title: t("formal_internalReferralLetter") }));
  const [saving, setSaving] = useState(false);
  const addressedOptions = useMemo(() => [
    ...(directory?.general_managers || []).map((person) => ({
      value: `general_manager:${person.id}`,
      type: "general_manager",
      id: person.id,
      name: person.name,
      group: t("formal_optGeneralManager"),
    })),
    {
      value: "diwan:",
      type: "diwan",
      id: "",
      name: t("formal_partyDiwan"),
      group: t("formal_optOffice"),
    },
    ...(directory?.branches || []).map((branch) => ({
      value: `branch:${branch.id}`,
      type: "branch",
      id: branch.id,
      name: branch.name,
      group: t("formal_optBranch"),
    })),
    ...(directory?.branches || []).flatMap((branch) =>
      (branch.departments || []).map((department) => ({
        value: `department:${department.id}`,
        type: "department",
        id: department.id,
        name: `${branch.name} / ${department.name}`,
        group: t("formal_optDepartment"),
      }))),
    ...(directory?.offices || [])
      .filter((office) => office.code !== "REGISTRY")
      .map((office) => ({
        value: `office:${office.id}`,
        type: "office",
        id: office.id,
        name: office.name,
        group: t("formal_optOffice"),
      })),
    ...(directory?.external_entities || []).map((entity) => ({
      value: `external_entity:${entity.id}`,
      type: "external_entity",
      id: entity.id,
      name: entity.name,
      group: t("formal_optExternalEntity"),
    })),
    {
      value: "free_text:",
      type: "free_text",
      id: "",
      name: t("formal_optOther"),
      group: t("formal_optOther"),
    },
  ], [directory, t]);
  const targetLabel = selectedTargetLabel(directory, step) || step.place;
  const addressedValue = `${step.target_type}:${step.target_id || ""}`;
  const ready = targetLabel.trim() && step.document_title.trim() && step.document_body.trim();

  const submit = async (event) => {
    event.preventDefault();
    if (!ready || saving) return;
    setSaving(true);
    try {
      const payload = {
        ...step,
        place: targetLabel,
        document_source: sourceLabel,
        document_target: targetLabel,
      };
      const updated = editing
        ? await api.updateFormalCorrespondenceEvent(stage.id, payload)
        : await api.addFormalCorrespondenceEvent(item.id, payload);
      done(updated);
      notify?.(t(editing ? "formal_processingUpdatedToast" : "formal_processingAddedToast"), "success");
    } catch (error) {
      notify?.(error.message, "error");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="overlay" onClick={close}>
      <div className="drawer max-w-2xl" onClick={(event) => event.stopPropagation()}>
        <header className="flex items-start justify-between gap-3 border-b border-line px-4 py-3">
          <div className="min-w-0">
            <span className="eyebrow">{t(editing ? "formal_editProcessingEyebrow" : "formal_newProcessingEyebrow")}</span>
            <h2 className="mt-1 text-base font-semibold text-ink">{t(editing ? "formal_editProcessingTitle" : "formal_addProcessingBtn")}</h2>
            <p className="text-xs text-muted">{t(editing ? "formal_editProcessingHint" : "formal_newProcessingHint")}</p>
          </div>
          <button className="btn-icon shrink-0" onClick={close} aria-label={t("formal_close")}><X size={16} /></button>
        </header>

        <article className="min-h-0 flex-1 overflow-y-auto p-4">
          <form onSubmit={submit} className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <label className="field mb-0 sm:col-span-2">
              <span className="label">{t("formal_sourcePartyLabel")}</span>
              <input className="input" value={sourceLabel} readOnly />
              <small className="text-xs text-muted">{t("formal_sourceLockedHint")}</small>
            </label>
            <label className="field mb-0 sm:col-span-2">
              <span className="label">{t("formal_addressedPartyLabel")}</span>
              <select
                className="select"
                value={addressedValue}
                onChange={(event) => {
                  const selected = addressedOptions.find((option) => option.value === event.target.value);
                  if (!selected) return;
                  setStep({
                    ...step,
                    target_type: selected.type,
                    target_id: selected.id,
                    place: selected.type === "free_text" ? "" : selected.name,
                    action_required: defaultActionForTarget(selected.type),
                  });
                }}
              >
                <option value="general_manager:">{t("formal_optGeneralManager")}</option>
                {[...new Set(addressedOptions.map((option) => option.group))].map((group) => (
                  <optgroup key={group} label={group}>
                    {addressedOptions
                      .filter((option) => option.group === group)
                      .map((option) => (
                        <option key={option.value} value={option.value}>{option.name}</option>
                      ))}
                  </optgroup>
                ))}
              </select>
            </label>
            {step.target_type === "free_text" && (
              <label className="field mb-0 sm:col-span-2">
                <AutocompleteInput
                  value={step.place}
                  onChange={(value) => setStep({ ...step, place: value })}
                  options={placeOptions}
                  placeholder={t("formal_pickOrTypeTarget")}
                />
              </label>
            )}
            <label className="field mb-0">
              <span className="label">{t("formal_actionRequiredLabel")}</span>
              <select
                className="select"
                value={step.action_required}
                onChange={(event) => setStep({ ...step, action_required: event.target.value })}
              >
                <option value="decision">{t("formal_actionDecision")}</option>
                <option value="study">{t("formal_actionStudy")}</option>
                <option value="execution">{t("formal_actionExecution")}</option>
                <option value="reply">{t("formal_actionExternalReply")}</option>
                <option value="route">{t("formal_actionRoute")}</option>
                <option value="hold">{t("formal_actionHold")}</option>
              </select>
            </label>
            <label className="field mb-0">
              <span className="label">{t("formal_dateLabel")}</span>
              <input className="input" type="date" value={step.date} onChange={(event) => setStep({ ...step, date: event.target.value })} />
            </label>
            <label className="field mb-0">
              <span className="label">{t("formal_statusLabel")}</span>
              <select className="select" value={step.status} onChange={(event) => setStep({ ...step, status: event.target.value })}>
                <option value="routed">{t("formal_statusRouted")}</option>
                <option value="received">{t("formal_statusReceived")}</option>
                <option value="archived">{t("formal_statusArchived")}</option>
              </select>
            </label>
            <label className="field mb-0">
              <span className="label">{t("formal_registryNumberLabel")}</span>
              <input
                className="input"
                value={step.registry_number}
                onChange={(event) => setStep({ ...step, registry_number: event.target.value })}
              />
            </label>
            <label className="field mb-0">
              <span className="label">{t("formal_letterTitleLabel")} *</span>
              <input
                className="input"
                required
                value={step.document_title}
                onChange={(event) => setStep({ ...step, document_title: event.target.value })}
              />
            </label>
            <label className="field mb-0 sm:col-span-2">
              <span className="label">{t("formal_letterBodyLabel")} *</span>
              <RichTextField
                value={step.document_body}
                onChange={(value) => setStep({ ...step, document_body: value })}
                placeholder={t("formal_letterBodyPlaceholder")}
              />
            </label>
            <label className="field mb-0 sm:col-span-2">
              <span className="label">{t("formal_attachmentLabel")}</span>
              <span className="flex cursor-pointer items-center gap-3 rounded border border-dashed border-line bg-surface px-3 py-3 hover:border-line-strong">
                <UploadCloud size={18} className="shrink-0 text-brand-500" />
                <span className="min-w-0 flex-1 truncate text-[13px] text-ink">
                  {step.attachments[0]?.name || t("formal_optionalSupportingFile")}
                </span>
                <input
                  className="hidden"
                  type="file"
                  accept=".pdf,.doc,.docx,.png,.jpg,.jpeg"
                  onChange={(event) => setStep({ ...step, attachments: event.target.files?.[0] ? [event.target.files[0]] : [] })}
                />
              </span>
            </label>
            <div className="flex items-center justify-end gap-2 sm:col-span-2">
              <button type="button" className="btn btn-secondary" onClick={close}>{t("cancel")}</button>
              <button className="btn btn-primary" type="submit" disabled={!ready || saving}>
                {t(editing ? "formal_saveProcessingEditBtn" : "formal_saveProcessingBtn")}
              </button>
            </div>
          </form>
        </article>
      </div>
    </div>
  );
}
