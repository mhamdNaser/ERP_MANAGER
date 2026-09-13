import { Route } from "lucide-react";
import { useLanguage } from "../../../Provider/LanguageContext";
import {
  actionLabelKey,
  correspondenceParty,
  decisionLabel,
  statusLabelKeys,
  typeLabelKeys,
} from "./formalUtils";

function InfoRow({ label, children }) {
  return (
    <p className="flex flex-col gap-1 rounded border border-line bg-surface p-3">
      <small className="text-xs font-semibold text-muted">{label}</small>
      <b className="text-[13px] font-semibold break-words text-ink">{children}</b>
    </p>
  );
}

/**
 * الشريط الجانبي لمعاينة المعالجة: بيانات المراسلة عموماً ثم بيانات هذه المعالجة.
 */
export function StageSidebar({ stage, correspondence, currentHolder }) {
  const { t } = useLanguage();

  return (
    <aside className="flex flex-col gap-3 rounded border border-line bg-subtle p-3">
      <div className="flex items-center gap-3 rounded bg-brand-800 p-3 text-white">
        <Route size={18} className="shrink-0 text-white/70" />
        <div className="min-w-0">
          <span className="block text-[11px] text-white/60">{t("formal_correspondenceLabel")}</span>
          <b className="block text-[13px] font-semibold break-words">{correspondence.reference_code}</b>
        </div>
      </div>

      <h4 className="text-xs font-semibold text-muted">{t("formal_correspondenceInfoTitle")}</h4>
      <div className="flex flex-col gap-2">
        <InfoRow label={t("formal_correspondenceTypeLabel")}>
          {typeLabelKeys[correspondence.direction] ? t(typeLabelKeys[correspondence.direction]) : correspondence.direction}
        </InfoRow>
        <InfoRow label={t("formal_sourcePartyLabel")}>
          {correspondenceParty(correspondence, "source", t("formal_ourOrg"))}
        </InfoRow>
        <InfoRow label={t("formal_addressedPartyLabel")}>
          {correspondenceParty(correspondence, "target", t("formal_unspecifiedF"))}
        </InfoRow>
        <InfoRow label={t("formal_currentHolderLabel")}>
          {currentHolder || t("formal_unspecifiedF")}
        </InfoRow>
        <InfoRow label={t("formal_relatedLabel")}>
          {correspondence.parent
            ? `${correspondence.parent.reference_code} — ${correspondence.parent.subject}`
            : t("formal_none")}
        </InfoRow>
        {correspondence.summary && (
          <p className="flex flex-col gap-1 rounded border border-line bg-surface p-3">
            <small className="text-xs font-semibold text-muted">{t("formal_summaryLabel")}</small>
            <span className="text-[13px] leading-relaxed whitespace-pre-wrap break-words text-ink">{correspondence.summary}</span>
          </p>
        )}
        <InfoRow label={t("formal_fileLabel")}>
          {correspondence.attachment_url ? (
            <a
              className="text-brand-500 hover:underline"
              href={correspondence.attachment_url}
              target="_blank"
              rel="noreferrer"
              download={correspondence.attachment_name}
            >
              {correspondence.attachment_name || t("formal_openAttachment")}
            </a>
          ) : (
            t("formal_none")
          )}
        </InfoRow>
      </div>

      <h4 className="mt-1 text-xs font-semibold text-muted">{t("formal_processingInfoTitle")}</h4>
      <div className="flex flex-col gap-2">
        <InfoRow label={t("formal_sourcePartyLabel")}>
          {stage.source_label || t("formal_sourceUnspecified")}
        </InfoRow>
        <InfoRow label={t("formal_addressedPartyLabel")}>
          {stage.target_label || stage.meta?.place || t("formal_targetUnspecified")}
        </InfoRow>
        <InfoRow label={t("formal_requiredLabel")}>{t(actionLabelKey(stage.action_required))}</InfoRow>
        <InfoRow label={t("formal_decisionFieldLabel")}>
          {decisionLabel(t, stage.decision_type, stage.decision_status)}
        </InfoRow>
        <InfoRow label={t("formal_statusLabel")}>
          {statusLabelKeys[stage.to_status] ? t(statusLabelKeys[stage.to_status]) : stage.to_status || t("formal_unspecifiedF")}
        </InfoRow>
        {stage.assigned_user && (
          <InfoRow label={t("formal_assignEmployeeTitle")}>{stage.assigned_user.name}</InfoRow>
        )}
      </div>
    </aside>
  );
}
