import { UploadCloud, X } from "lucide-react";
import { useState } from "react";
import { useLanguage } from "../../../Provider/LanguageContext";
import { api } from "../../../lib";
import { PartyPicker } from "./PartyPicker";
import { RelatedCorrespondencePicker } from "./RelatedCorrespondencePicker";
import { directionPartyRules, typeLabelKeys } from "./formalUtils";

const emptyParty = (type) => ({ type, id: "", name: "" });

export function CreateFormalModal({ directory, close, done, notify }) {
  const { t } = useLanguage();
  const [attachment, setAttachment] = useState(null);
  const [saving, setSaving] = useState(false);
  const [direction, setDirection] = useState("external_to_internal");
  const [source, setSource] = useState(emptyParty("external_entity"));
  const [target, setTarget] = useState(emptyParty("our_org"));
  const [data, setData] = useState({
    parent_id: "",
    subject: "",
    summary: "",
    issued_at: "",
    first_note: "",
  });

  const rules = directionPartyRules[direction];

  const changeDirection = (next) => {
    setDirection(next);
    setSource(emptyParty(directionPartyRules[next].source[0]));
    setTarget(emptyParty(directionPartyRules[next].target[0]));
  };

  const partyReady = (party, allowed) => {
    const type = allowed.includes(party.type) ? party.type : allowed[0];
    if (["our_org", "diwan", "general_manager"].includes(type)) return true;
    if (type === "external_entity") return Boolean(party.name?.trim());
    return Boolean(party.id);
  };

  const ready = data.subject.trim() && partyReady(source, rules.source) && partyReady(target, rules.target);

  const submit = async (event) => {
    event.preventDefault();
    if (!ready || saving) return;
    setSaving(true);
    try {
      const item = await api.createFormalCorrespondence({
        ...data,
        direction,
        source_type: source.type,
        source_id: source.id,
        source_name: source.name,
        target_type: target.type,
        target_id: target.id,
        target_name: target.name,
        attachment,
      });
      notify?.(t("formal_createdToast"), "success");
      done(item);
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
            <small className="eyebrow">{t("formal_registerEyebrow")}</small>
            <h2 className="mt-1 text-base font-semibold text-ink">{t("formal_newCorrespondence")}</h2>
            <p className="text-xs text-muted">{t("formal_createHint")}</p>
          </div>
          <button className="btn-icon shrink-0" onClick={close} aria-label={t("formal_close")}><X size={16} /></button>
        </header>
        <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
          <div className="modal-body grid grid-cols-1 gap-4 sm:grid-cols-2">
            <label className="field mb-0">
              <span className="label">{t("formal_correspondenceTypeLabel")}</span>
              <select className="select" value={direction} onChange={(event) => changeDirection(event.target.value)}>
                {Object.entries(typeLabelKeys).map(([value, key]) => (
                  <option key={value} value={value}>{t(key)}</option>
                ))}
              </select>
            </label>
            <label className="field mb-0">
              <span className="label">{t("formal_subjectRequired")}</span>
              <input className="input" required value={data.subject} onChange={(event) => setData({ ...data, subject: event.target.value })} />
            </label>

            <div className="sm:col-span-2">
              <RelatedCorrespondencePicker
                options={directory.correspondences || []}
                value={data.parent_id}
                onChange={(parentId) => setData({ ...data, parent_id: parentId })}
              />
            </div>

            <PartyPicker
              label={t("formal_sourcePartyLabel")}
              hint={direction === "external_to_internal" ? t("formal_sourceExternalHint") : t("formal_sourceInternalHint")}
              allowedTypes={rules.source}
              value={source}
              onChange={setSource}
              directory={directory}
            />
            <PartyPicker
              label={t("formal_addressedPartyLabel")}
              hint={direction === "external_to_internal" ? t("formal_addressedOurOrgHint") : t("formal_addressedHint")}
              allowedTypes={rules.target}
              value={target}
              onChange={setTarget}
              directory={directory}
              disabled={direction === "external_to_internal"}
            />

            <label className="field mb-0">
              <span className="label">{t("formal_startDateLabel")}</span>
              <input className="input" type="date" value={data.issued_at} onChange={(event) => setData({ ...data, issued_at: event.target.value })} />
            </label>
            <label className="field mb-0">
              <span className="label">{t("formal_correspondenceFileLabel")}</span>
              <input
                className="input h-auto py-2 file:me-3 file:rounded file:border file:border-line file:bg-canvas file:px-2 file:py-1 file:text-xs file:font-semibold file:text-ink"
                type="file"
                accept=".pdf,.doc,.docx,.png,.jpg,.jpeg"
                onChange={(event) => setAttachment(event.target.files?.[0] ?? null)}
              />
            </label>
            <label className="field mb-0 sm:col-span-2">
              <span className="label">{t("formal_summaryLabel")}</span>
              <textarea className="textarea" value={data.summary} onChange={(event) => setData({ ...data, summary: event.target.value })} />
            </label>
            <label className="field mb-0 sm:col-span-2">
              <span className="label">{t("formal_firstStopNoteLabel")}</span>
              <textarea className="textarea" value={data.first_note} onChange={(event) => setData({ ...data, first_note: event.target.value })} />
            </label>
            {direction === "external_to_internal" && (
              <p className="rounded border border-line bg-subtle px-3 py-2 text-xs text-muted sm:col-span-2">
                {t("formal_diwanFirstNotice")}
              </p>
            )}
          </div>
          <footer className="modal-foot">
            <button type="button" className="btn btn-secondary" onClick={close}>{t("cancel")}</button>
            <button className="btn btn-primary" disabled={!ready || saving}>
              <UploadCloud size={16} /> {t("formal_saveCorrespondence")}
            </button>
          </footer>
        </form>
      </section>
    </div>
  );
}
