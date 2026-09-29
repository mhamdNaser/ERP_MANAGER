import { useState } from "react";
import { MessagesSquare, X } from "lucide-react";
import { useLanguage } from "../../../Provider/LanguageContext";
import { columnOf, moveNeedsCommunicationUser, moveNeedsNote } from "./taskMeta";

// نافذة الانتقالات التي تحتاج مدخلًا قبل تنفيذها: تحويل المهمة إلى مرحلة
// التواصل يستلزم اختيار الموظف الذي سينفذ التواصل، وإعادتها منه إلى قيد
// التنفيذ تستلزم ملاحظة تشرح المطلوب. ما عدا ذلك ينتقل مباشرة دون نافذة.
export function TaskMoveDialog({ task, target, members, close, confirm }) {
  const { t } = useLanguage();
  const [communicationUserId, setCommunicationUserId] = useState(
    task.communication_user?.id ? String(task.communication_user.id) : "",
  );
  const [note, setNote] = useState("");
  const [saving, setSaving] = useState(false);
  const needsUser = moveNeedsCommunicationUser(target);
  const noteRequired = moveNeedsNote(task.status, target);
  const column = columnOf(target);
  const inDepartment = members.filter((member) => member.id !== task.assignee?.id);
  // الاختيار من موظفي التواصل المعيَّنين؛ وإن لم يُعيَّن أحد بعد بقيت القائمة
  // كل أعضاء القسم، مطابقةً لما يسمح به الخادم — فلا تُقفل لوحة بلا تعيين.
  const officers = inDepartment.filter((member) => member.is_communication_officer);
  const candidates = officers.length ? officers : inDepartment;

  const submit = async (event) => {
    event.preventDefault();
    setSaving(true);
    try {
      await confirm({
        communication_user_id: needsUser ? Number(communicationUserId) : undefined,
        note: note.trim() || undefined,
      });
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="overlay overflow-y-auto" onClick={close}>
      <section
        className="modal max-w-lg"
        onClick={(event) => event.stopPropagation()}
      >
        <header className="modal-head items-start">
          <div className="flex min-w-0 flex-col gap-1">
            <small className="eyebrow">{t("task_moveEyebrow")}</small>
            <h2 className="text-base font-semibold text-ink">
              {t("task_moveTo", { stage: t(column?.title) })}
            </h2>
            <p className="text-[13px] text-muted">{task.title}</p>
          </div>
          <button className="btn-icon shrink-0" onClick={close}>
            <X size={16} />
          </button>
        </header>
        <form onSubmit={submit} className="flex flex-col gap-4 p-4">
          {needsUser && (
            <label className="field mb-0">
              <span className="label">{t("task_communicationUser")} *</span>
              <select
                className="select"
                required
                autoFocus
                value={communicationUserId}
                onChange={(event) => setCommunicationUserId(event.target.value)}
              >
                <option value="">{t("task_pickCommunicationUser")}</option>
                {candidates.map((member) => (
                  <option key={member.id} value={member.id}>
                    {member.name} · {member.job_title}
                  </option>
                ))}
              </select>
              <small className="mt-1 flex items-center gap-1.5 text-[11px] text-muted">
                <MessagesSquare size={12} className="shrink-0" />
                {officers.length
                  ? t("task_communicationUserHint")
                  : t("task_noCommunicationOfficers")}
              </small>
            </label>
          )}
          <label className="field mb-0">
            <span className="label">
              {t("task_moveNote")} {noteRequired ? "*" : ""}
            </span>
            <textarea
              className="textarea"
              required={noteRequired}
              autoFocus={noteRequired}
              value={note}
              onChange={(event) => setNote(event.target.value)}
              placeholder={
                noteRequired
                  ? t("task_moveNoteRequiredPlaceholder")
                  : t("task_moveNotePlaceholder")
              }
            />
          </label>
          <footer className="flex items-center justify-end gap-2 border-t border-line pt-3">
            <button type="button" className="btn btn-secondary" onClick={close}>
              {t("cancel")}
            </button>
            <button disabled={saving} className="btn btn-primary">
              {saving ? t("task_saving") : t("task_confirmMove")}
            </button>
          </footer>
        </form>
      </section>
    </div>
  );
}
