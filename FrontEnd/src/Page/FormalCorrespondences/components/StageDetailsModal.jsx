import { FileText, X } from "lucide-react";
import { useMemo, useState } from "react";
import { useLanguage } from "../../../Provider/LanguageContext";
import { api } from "../../../lib";
import { StageAttachmentPreview } from "./StageAttachmentPreview";
import { StageSidebar } from "./StageSidebar";
import {
  canAssignStage,
  correspondenceParty,
  previousProcessings,
  selectedTargetLabel,
  stageAssignableUsers,
  targetOptions,
  userOwnsStage,
} from "./formalUtils";

export function StageDetailsModal({ stage, correspondence, close, done, notify, user, directory }) {
  const { t } = useLanguage();
  const [decisionDraft, setDecisionDraft] = useState({
    decision_type: "route_internal",
    target_type: "department",
    target_id: "",
    place: "",
    note: "",
    document_title: "",
    document_body: "",
    attachments: [],
  });
  const [responseDraft, setResponseDraft] = useState({
    response_type: "study",
    body: "",
    recommendation: "",
    attachment: null,
  });
  const [saving, setSaving] = useState(false);
  const [assignedUserId, setAssignedUserId] = useState(stage.assigned_user_id || "");
  const documents = stage.documents || [];
  const place = stage.meta?.place || stage.event;
  const decisionTargets = useMemo(
    () => targetOptions(directory, decisionDraft.target_type),
    [directory, decisionDraft.target_type],
  );
  const canDecide = stage.target_type === "general_manager" && user?.role === "general_manager";
  const canRespond = ["branch", "department", "office"].includes(stage.target_type) && userOwnsStage(user, stage);
  const canAssign = canAssignStage(user, stage);
  const assignableUsers = useMemo(
    () => stageAssignableUsers(directory, stage),
    [directory, stage],
  );
  const currentHolder = useMemo(() => {
    const latest = [...(correspondence.events || [])]
      .sort((a, b) => new Date(a.created_at) - new Date(b.created_at))
      .at(-1);
    return latest?.target_label || latest?.meta?.place || correspondenceParty(correspondence, "target");
  }, [correspondence]);
  const previousStages = useMemo(
    () => previousProcessings(correspondence, stage),
    [correspondence, stage],
  );

  const assignEmployee = async (event) => {
    event.preventDefault();
    if (!assignedUserId || saving) return;
    setSaving(true);
    try {
      const updated = await api.assignFormalCorrespondenceEvent(stage.id, {
        assigned_user_id: assignedUserId,
      });
      notify?.(t("formal_assignmentSavedToast"), "success");
      done(updated);
    } catch (error) {
      notify?.(error.message, "error");
    } finally {
      setSaving(false);
    }
  };

  const saveDecision = async (event) => {
    event.preventDefault();
    setSaving(true);
    try {
      const updated = await api.decideFormalCorrespondenceEvent(stage.id, {
        ...decisionDraft,
        place: selectedTargetLabel(directory, decisionDraft) || decisionDraft.place,
      });
      notify?.(t("formal_gmDecisionSavedToast"), "success");
      done(updated);
    } catch (error) {
      notify?.(error.message, "error");
    } finally {
      setSaving(false);
    }
  };

  const saveResponse = async (event) => {
    event.preventDefault();
    setSaving(true);
    try {
      const updated = await api.respondFormalCorrespondenceEvent(stage.id, responseDraft);
      notify?.(t("formal_stageResultSavedToast"), "success");
      done(updated);
    } catch (error) {
      notify?.(error.message, "error");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="overlay grid place-items-center overflow-y-auto">
      <section className="modal max-w-6xl">
        <header className="modal-head">
          <div className="min-w-0">
            <small className="eyebrow">{t("formal_stageDetailsTitle")}</small>
            <h2 className="mt-1 text-base font-semibold break-words text-ink">{place}</h2>
            <p className="text-xs text-muted">{new Date(stage.created_at).toLocaleDateString()}</p>
          </div>
          <button className="btn-icon shrink-0" onClick={close} aria-label={t("formal_close")}><X size={16} /></button>
        </header>
        <div className="modal-body">
          <div className="grid grid-cols-1 items-start gap-4 lg:grid-cols-[minmax(230px,300px)_minmax(0,1fr)]">
            <StageSidebar stage={stage} correspondence={correspondence} currentHolder={currentHolder} />
            <main className="flex min-w-0 flex-col gap-4">
              <StageAttachmentPreview correspondence={correspondence} />

              {!!previousStages.length && (
                <section className="flex flex-col gap-3 rounded border border-line bg-subtle p-3">
                  <div>
                    <h4 className="text-sm font-semibold text-ink">{t("formal_previousProcessingsTitle")}</h4>
                    <p className="text-xs text-muted">{t("formal_previousProcessingsHint")}</p>
                  </div>
                  <div className="flex flex-col gap-2">
                    {previousStages.map((previous) => (
                      <article key={previous.id} className="rounded border border-line bg-surface p-3">
                        <b className="block text-[13px] font-semibold text-ink">
                          {previous.source_label || t("formal_sourceUnspecified")} ← {previous.target_label || t("formal_targetUnspecified")}
                        </b>
                      </article>
                    ))}
                  </div>
                </section>
              )}

              {canAssign && (
                <form className="flex flex-col gap-3 rounded border border-line p-3" onSubmit={assignEmployee}>
                  <div>
                    <h4 className="text-sm font-semibold text-ink">{t("formal_assignEmployeeTitle")}</h4>
                    <p className="text-xs text-muted">{t("formal_assignEmployeeHint")}</p>
                  </div>
                  <div className="flex flex-col gap-2 sm:flex-row">
                    <select className="select" value={assignedUserId} onChange={(event) => setAssignedUserId(event.target.value)}>
                      <option value="">{t("formal_pickEmployee")}</option>
                      {assignableUsers.map((person) => (
                        <option key={person.id} value={person.id}>
                          {person.name}{person.job_title ? ` — ${person.job_title}` : ""}
                        </option>
                      ))}
                    </select>
                    <button className="btn btn-primary shrink-0" disabled={!assignedUserId || saving}>
                      {t("formal_assignEmployeeBtn")}
                    </button>
                  </div>
                  {stage.assigned_user && (
                    <small className="text-xs font-semibold text-brand-500">
                      {t("formal_assignedTo", { name: stage.assigned_user.name })}
                    </small>
                  )}
                </form>
              )}

              {!!stage.responses?.length && (
                <section className="flex flex-col gap-3">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                      <h4 className="text-sm font-semibold text-ink">{t("formal_studyExecTitle")}</h4>
                      <p className="text-xs text-muted">{t("formal_responsesHint")}</p>
                    </div>
                  </div>
                  <div className="flex flex-col gap-2">
                    {stage.responses.map((response) => (
                      <article key={response.id} className="flex flex-col gap-1.5 rounded border border-line bg-subtle p-3">
                        <b className="text-[13px] font-semibold text-ink">{response.response_type === "execution" ? t("formal_actionExecution") : t("formal_actionStudy")} · {response.actor?.name || t("formal_userFallback")}</b>
                        <p className="text-[13px] leading-relaxed whitespace-pre-wrap text-ink">{response.body}</p>
                        {response.recommendation && <small className="text-xs font-semibold text-muted">{response.recommendation}</small>}
                        {response.attachment_url && (
                          <a className="text-xs font-semibold text-brand-500 hover:underline" href={response.attachment_url} target="_blank" rel="noreferrer">
                            {t("formal_openAttachment")}
                          </a>
                        )}
                      </article>
                    ))}
                  </div>
                </section>
              )}

              {canDecide && (
                <form className="flex flex-col gap-3" onSubmit={saveDecision}>
                  <section className="flex flex-col gap-3">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div className="min-w-0">
                        <h4 className="text-sm font-semibold text-ink">{t("formal_gmDecisionTitle")}</h4>
                        <p className="text-xs text-muted">{t("formal_gmDecisionHint")}</p>
                      </div>
                    </div>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                      <label className="field mb-0">
                        <span className="label">{t("formal_decisionTypeLabel")}</span>
                        <select
                          className="select"
                          value={decisionDraft.decision_type}
                          onChange={(event) =>
                            setDecisionDraft({
                              ...decisionDraft,
                              decision_type: event.target.value,
                              target_type: event.target.value === "reply" ? "external_entity" : "department",
                              target_id: "",
                              place: "",
                            })
                          }
                        >
                          <option value="reply">{t("formal_optReplyClose")}</option>
                          <option value="route_internal">{t("formal_optRouteInternal")}</option>
                          <option value="hold">{t("formal_optHold")}</option>
                        </select>
                      </label>
                      {decisionDraft.decision_type === "route_internal" && (
                        <>
                          <label className="field mb-0">
                            <span className="label">{t("formal_targetTypeLabel")}</span>
                            <select
                              className="select"
                              value={decisionDraft.target_type}
                              onChange={(event) =>
                                setDecisionDraft({
                                  ...decisionDraft,
                                  target_type: event.target.value,
                                  target_id: "",
                                  place: "",
                                })
                              }
                            >
                              <option value="department">{t("formal_optDepartment")}</option>
                              <option value="branch">{t("formal_optBranch")}</option>
                              <option value="office">{t("formal_optOffice")}</option>
                              <option value="external_entity">{t("formal_optExternalOrg")}</option>
                            </select>
                          </label>
                          <label className="field mb-0">
                            <span className="label">{t("formal_partyLabel")}</span>
                            <select
                              className="select"
                              value={decisionDraft.target_id}
                              onChange={(event) => {
                                const targetId = event.target.value;
                                const label = decisionTargets.find((choice) => String(choice.id) === String(targetId))?.name || "";
                                setDecisionDraft({ ...decisionDraft, target_id: targetId, place: label });
                              }}
                            >
                              <option value="">{t("formal_pickTarget")}</option>
                              {decisionTargets.map((choice) => (
                                <option key={`${decisionDraft.target_type}-${choice.id}`} value={choice.id}>
                                  {choice.name}
                                </option>
                              ))}
                            </select>
                          </label>
                        </>
                      )}
                      <label className="field mb-0 sm:col-span-2">
                        <span className="label">{t("formal_decisionNoteLabel")}</span>
                        <textarea
                          className="textarea"
                          value={decisionDraft.note}
                          onChange={(event) => setDecisionDraft({ ...decisionDraft, note: event.target.value })}
                        />
                      </label>
                      {decisionDraft.decision_type === "reply" && (
                        <>
                          <label className="field mb-0">
                            <span className="label">{t("formal_replyLetterTitleLabel")}</span>
                            <input
                              className="input"
                              value={decisionDraft.document_title}
                              onChange={(event) => setDecisionDraft({ ...decisionDraft, document_title: event.target.value })}
                            />
                          </label>
                          <label className="field mb-0 sm:col-span-2">
                            <span className="label">{t("formal_replyLetterBodyLabel")}</span>
                            <textarea
                              className="textarea"
                              value={decisionDraft.document_body}
                              onChange={(event) => setDecisionDraft({ ...decisionDraft, document_body: event.target.value })}
                            />
                          </label>
                        </>
                      )}
                    </div>
                  </section>
                  <footer className="flex items-center justify-end gap-2">
                    <button className="btn btn-primary" disabled={saving || (decisionDraft.decision_type === "route_internal" && !decisionDraft.target_id)}>
                      {t("formal_saveDecisionBtn")}
                    </button>
                  </footer>
                </form>
              )}

              {canRespond && (
                <form className="flex flex-col gap-3" onSubmit={saveResponse}>
                  <section className="flex flex-col gap-3">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div className="min-w-0">
                        <h4 className="text-sm font-semibold text-ink">{t("formal_studyExecFormTitle")}</h4>
                        <p className="text-xs text-muted">{t("formal_studyExecFormHint")}</p>
                      </div>
                    </div>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                      <label className="field mb-0">
                        <span className="label">{t("formal_workTypeLabel")}</span>
                        <select
                          className="select"
                          value={responseDraft.response_type}
                          onChange={(event) => setResponseDraft({ ...responseDraft, response_type: event.target.value })}
                        >
                          <option value="study">{t("formal_actionStudy")}</option>
                          <option value="execution">{t("formal_actionExecution")}</option>
                        </select>
                      </label>
                      <label className="field mb-0 sm:col-span-2">
                        <span className="label">{t("formal_textLabel")}</span>
                        <textarea
                          className="textarea"
                          required
                          value={responseDraft.body}
                          onChange={(event) => setResponseDraft({ ...responseDraft, body: event.target.value })}
                        />
                      </label>
                      <label className="field mb-0 sm:col-span-2">
                        <span className="label">{t("formal_recommendationLabel")}</span>
                        <textarea
                          className="textarea"
                          value={responseDraft.recommendation}
                          onChange={(event) => setResponseDraft({ ...responseDraft, recommendation: event.target.value })}
                        />
                      </label>
                      <label className="field mb-0">
                        <span className="label">{t("formal_attachmentLabel")}</span>
                        <input
                          className="input h-auto py-2 file:me-3 file:rounded file:border file:border-line file:bg-canvas file:px-2 file:py-1 file:text-xs file:font-semibold file:text-ink"
                          type="file"
                          accept=".pdf,.doc,.docx,.png,.jpg,.jpeg"
                          onChange={(event) => setResponseDraft({ ...responseDraft, attachment: event.target.files?.[0] ?? null })}
                        />
                      </label>
                    </div>
                  </section>
                  <footer className="flex items-center justify-end gap-2">
                    <button className="btn btn-primary" disabled={saving || !responseDraft.body.trim()}>
                      {t("formal_saveStudyExecBtn")}
                    </button>
                  </footer>
                </form>
              )}

            </main>
          </div>
        </div>
      </section>
    </div>
  );
}
