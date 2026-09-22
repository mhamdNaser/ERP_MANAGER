import { Send, X } from "lucide-react";
import { useMemo, useState } from "react";
import { useLanguage } from "../../../Provider/LanguageContext";
import { api } from "../../../lib";
import { hrFieldsFor, hrSubtypeOptions, hrTypeLabelKeys, hrSubtypeLabelKeys } from "../hrUtils";

const emptyDraft = () => ({
  type: "leave",
  subtype: "annual",
  start_date: "",
  end_date: "",
  start_time: "",
  end_time: "",
  reason: "",
});

/** تقديم طلب موارد بشرية — للموظف عن نفسه أو للموارد البشرية نيابةً عن غيره. */
export function HrRequestModal({ close, done, notify, employees = null, defaultUserId = "", lockEmployee = false }) {
  const { t } = useLanguage();
  const [draft, setDraft] = useState(emptyDraft);
  const [userId, setUserId] = useState(defaultUserId);
  const [attachment, setAttachment] = useState(null);
  const [saving, setSaving] = useState(false);
  const fields = useMemo(() => hrFieldsFor(draft.type), [draft.type]);
  const subtypes = useMemo(() => hrSubtypeOptions(draft.type), [draft.type]);

  const ready =
    draft.reason.trim() &&
    (!fields.dates || (draft.start_date && draft.end_date)) &&
    (!fields.times || (draft.start_time && draft.end_time)) &&
    (!employees || userId);

  const submit = async (event) => {
    event.preventDefault();
    if (!ready || saving) return;
    setSaving(true);
    try {
      const created = await api.createHrRequest({
        ...draft,
        subtype: fields.subtype ? draft.subtype : "",
        user_id: employees ? userId : "",
        attachment,
      });
      notify?.(t("hr_requestSubmitted"), "success");
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
            <small className="eyebrow">{t("hr_newRequestEyebrow")}</small>
            <h2 className="mt-1 text-base font-semibold text-ink">{t("hr_newRequest")}</h2>
            <p className="text-xs text-muted">{t("hr_newRequestHint")}</p>
          </div>
          <button className="btn-icon shrink-0" onClick={close} aria-label={t("close")}><X size={16} /></button>
        </header>
        <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
          <div className="modal-body grid grid-cols-1 gap-4 sm:grid-cols-2">
            {employees && (
              <label className="field mb-0 sm:col-span-2">
                <span className="label">{t("hr_employeeLabel")}</span>
                <select
                  className="select"
                  value={userId}
                  disabled={lockEmployee}
                  onChange={(event) => setUserId(event.target.value)}
                >
                  {!lockEmployee && <option value="">{t("hr_pickEmployee")}</option>}
                  {employees.map((person) => (
                    <option key={person.id} value={person.id}>
                      {person.name}{person.job_title ? ` — ${person.job_title}` : ""}
                    </option>
                  ))}
                </select>
              </label>
            )}
            <label className="field mb-0">
              <span className="label">{t("hr_typeLabel")}</span>
              <select
                className="select"
                value={draft.type}
                onChange={(event) => {
                  const type = event.target.value;
                  setDraft({ ...emptyDraft(), type, subtype: hrSubtypeOptions(type)[0] || "" });
                }}
              >
                {Object.entries(hrTypeLabelKeys).map(([value, key]) => (
                  <option key={value} value={value}>{t(key)}</option>
                ))}
              </select>
            </label>
            {fields.subtype && (
              <label className="field mb-0">
                <span className="label">{t("hr_subtypeLabel")}</span>
                <select className="select" value={draft.subtype} onChange={(event) => setDraft({ ...draft, subtype: event.target.value })}>
                  {subtypes.map((value) => (
                    <option key={value} value={value}>{t(hrSubtypeLabelKeys[value])}</option>
                  ))}
                </select>
              </label>
            )}
            {fields.dates && (
              <>
                <label className="field mb-0">
                  <span className="label">{t("hr_startDate")}</span>
                  <input className="input" type="date" value={draft.start_date} onChange={(event) => setDraft({ ...draft, start_date: event.target.value })} />
                </label>
                <label className="field mb-0">
                  <span className="label">{t("hr_endDate")}</span>
                  <input className="input" type="date" value={draft.end_date} min={draft.start_date} onChange={(event) => setDraft({ ...draft, end_date: event.target.value })} />
                </label>
              </>
            )}
            {fields.times && (
              <>
                <label className="field mb-0">
                  <span className="label">{t("hr_startTime")}</span>
                  <input className="input" type="time" value={draft.start_time} onChange={(event) => setDraft({ ...draft, start_time: event.target.value })} />
                </label>
                <label className="field mb-0">
                  <span className="label">{t("hr_endTime")}</span>
                  <input className="input" type="time" value={draft.end_time} onChange={(event) => setDraft({ ...draft, end_time: event.target.value })} />
                </label>
              </>
            )}
            <label className="field mb-0 sm:col-span-2">
              <span className="label">{t("hr_reason")}</span>
              <textarea className="textarea" required value={draft.reason} onChange={(event) => setDraft({ ...draft, reason: event.target.value })} />
            </label>
            <label className="field mb-0 sm:col-span-2">
              <span className="label">{t("hr_attachment")}</span>
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
              {saving ? t("saving") : t("hr_submitRequest")}
            </button>
          </footer>
        </form>
      </section>
    </div>
  );
}
