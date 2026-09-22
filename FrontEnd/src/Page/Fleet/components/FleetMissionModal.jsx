import { Send, X } from "lucide-react";
import { useState } from "react";
import { useLanguage } from "../../../Provider/LanguageContext";
import { api } from "../../../lib";

const emptyDraft = () => ({
  start_date: "",
  end_date: "",
  destination: "",
  reason: "",
});

/** تقديم مهمة عمل — للموظف عن نفسه أو لفرع الآليات نيابةً عن غيره. */
export function FleetMissionModal({ close, done, notify, employees = null, defaultUserId = "", lockEmployee = false }) {
  const { t } = useLanguage();
  const [draft, setDraft] = useState(emptyDraft);
  const [userId, setUserId] = useState(defaultUserId);
  const [attachment, setAttachment] = useState(null);
  const [saving, setSaving] = useState(false);

  const ready =
    draft.reason.trim() &&
    draft.destination.trim() &&
    draft.start_date &&
    draft.end_date &&
    (!employees || userId);

  const submit = async (event) => {
    event.preventDefault();
    if (!ready || saving) return;
    setSaving(true);
    try {
      const created = await api.createFleetMission({
        ...draft,
        user_id: employees ? userId : "",
        attachment,
      });
      notify?.(t("fleet_missionSubmitted"), "success");
      done(created);
    } catch (error) {
      notify?.(error.message, "error");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="overlay grid place-items-center overflow-y-auto">
      <section className="modal">
        <header className="modal-head">
          <div className="min-w-0">
            <small className="eyebrow">{t("fleet_newMissionEyebrow")}</small>
            <h2 className="mt-1 text-base font-semibold text-ink">{t("fleet_newMission")}</h2>
            <p className="text-xs text-muted">{t("fleet_newMissionHint")}</p>
          </div>
          <button className="btn-icon shrink-0" onClick={close} aria-label={t("close")}><X size={16} /></button>
        </header>
        <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
          <div className="modal-body grid grid-cols-1 gap-4 sm:grid-cols-2">
            {employees && (
              <label className="field mb-0 sm:col-span-2">
                <span className="label">{t("fleet_employeeLabel")}</span>
                <select
                  className="select"
                  value={userId}
                  disabled={lockEmployee}
                  onChange={(event) => setUserId(event.target.value)}
                >
                  {!lockEmployee && <option value="">{t("fleet_pickEmployee")}</option>}
                  {employees.map((person) => (
                    <option key={person.id} value={person.id}>
                      {person.name}{person.job_title ? ` — ${person.job_title}` : ""}
                    </option>
                  ))}
                </select>
              </label>
            )}
            <label className="field mb-0">
              <span className="label">{t("fleet_startDate")}</span>
              <input className="input" type="date" value={draft.start_date} onChange={(event) => setDraft({ ...draft, start_date: event.target.value })} />
            </label>
            <label className="field mb-0">
              <span className="label">{t("fleet_endDate")}</span>
              <input className="input" type="date" value={draft.end_date} min={draft.start_date} onChange={(event) => setDraft({ ...draft, end_date: event.target.value })} />
            </label>
            <label className="field mb-0 sm:col-span-2">
              <span className="label">{t("fleet_destination")}</span>
              <input className="input" value={draft.destination} onChange={(event) => setDraft({ ...draft, destination: event.target.value })} />
            </label>
            <label className="field mb-0 sm:col-span-2">
              <span className="label">{t("fleet_reason")}</span>
              <textarea className="textarea" required value={draft.reason} onChange={(event) => setDraft({ ...draft, reason: event.target.value })} />
            </label>
            <label className="field mb-0 sm:col-span-2">
              <span className="label">{t("fleet_attachment")}</span>
              <input
                className="input h-auto py-2 file:me-3 file:rounded file:border file:border-line file:bg-canvas file:px-2 file:py-1 file:text-xs file:font-semibold file:text-ink"
                type="file"
                accept=".pdf,.doc,.docx,.png,.jpg,.jpeg"
                onChange={(event) => setAttachment(event.target.files?.[0] ?? null)}
              />
            </label>
          </div>
          <footer className="modal-foot">
            <button type="button" className="btn btn-secondary" onClick={close}>{t("cancel")}</button>
            <button className="btn btn-primary" disabled={!ready || saving}>
              <Send size={16} />
              {saving ? t("saving") : t("fleet_submitMission")}
            </button>
          </footer>
        </form>
      </section>
    </div>
  );
}
