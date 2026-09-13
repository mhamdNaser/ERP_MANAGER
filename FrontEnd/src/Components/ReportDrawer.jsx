import { AlertCircle, FileDown, Pencil, RefreshCw, Send, X } from "lucide-react";
import { useState } from "react";
import { useLanguage } from "../Provider/LanguageContext";
import { api, exportReports } from "../lib";
import { reportType, StatusBadge } from "./Reports";
export function ReportDrawer({ report, user, close, changed, notify, edit }) {
  const { t } = useLanguage();
  const [returnDialogOpen, setReturnDialogOpen] = useState(false);
  const transition = async (action, note = "") => {
    try {
      await api.transition(report.id, action, note);
      changed();
      close();
    } catch (e) {
      notify(e.message, "error");
    }
  };
  const isEmployee = ["employee", "technician"].includes(user.role);
  const isDatabaseManager = user.role === "database_manager";
  const canTransition = !!user.permissions?.includes("reports.transition");
  const isApproved = report.status === "approved";
  const canEdit =
    (isEmployee &&
      report.status === "returned" &&
      report.employee?.id === user.id) ||
    isDatabaseManager;
  const canSubmit =
    isEmployee &&
    report.employee?.id === user.id &&
    ["draft", "returned"].includes(report.status);
  const actions = reportActions(user, report, canTransition);
  return (
    <div className="fixed inset-0 z-50 bg-brand-900/40" onClick={close}>
      <aside className="drawer" onClick={(e) => e.stopPropagation()}>
        <header className="flex items-start justify-between gap-3 border-b border-line px-4 py-3">
          <div className="min-w-0">
            <span className="eyebrow">{reportType(report.type, t)}</span>
            <h2 className="my-1 text-base font-semibold text-ink">
              {report.title}
            </h2>
            <StatusBadge status={report.status} />
          </div>
          <button
            type="button"
            className="btn-icon shrink-0"
            onClick={close}
            aria-label={t("cancel")}
          >
            <X size={16} />
          </button>
        </header>
        <div className="grid grid-cols-1 gap-3 border-b border-line bg-subtle px-4 py-3 sm:grid-cols-3">
          <p className="flex flex-col">
            <small className="text-[11px] text-muted">{t("employee")}</small>
            <b className="text-[13px] font-semibold text-ink">
              {report.employee?.name}
            </b>
          </p>
          <p className="flex flex-col">
            <small className="text-[11px] text-muted">{t("entity")}</small>
            <b className="text-[13px] font-semibold text-ink">
              {report.department?.name}
            </b>
          </p>
          <p className="flex flex-col">
            <small className="text-[11px] text-muted">{t("period")}</small>
            <b className="text-[13px] font-semibold text-ink">
              {report.period_start}
            </b>
          </p>
        </div>
        <article className="flex flex-1 flex-col gap-4 overflow-y-auto p-4">
          <h3 className="section-title">{t("executiveSummary")}</h3>
          <p className="border-b border-line pb-3 text-[13px] leading-relaxed text-muted">
            {report.summary}
          </p>
          <h3 className="section-title">{t("achievements")}</h3>
          <p className="border-b border-line pb-3 text-[13px] leading-relaxed text-muted">
            {report.achievements || "-"}
          </p>
          <h3 className="section-title">{t("challenges")}</h3>
          <p className="border-b border-line pb-3 text-[13px] leading-relaxed text-muted">
            {report.challenges || "-"}
          </p>
          <h3 className="section-title">{t("nextSteps")}</h3>
          <p className="border-b border-line pb-3 text-[13px] leading-relaxed text-muted">
            {report.next_steps || "-"}
          </p>
          {report.return_note && (
            <section className="flex gap-3 rounded border border-danger-500/25 bg-danger-50 p-3">
              <span className="inline-grid h-8 w-8 shrink-0 place-items-center rounded text-danger-500">
                <AlertCircle size={18} />
              </span>
              <div className="min-w-0">
                <h3 className="text-sm font-semibold text-danger-500">
                  {t("returnReason")}
                </h3>
                <p className="mt-1 text-[13px] leading-relaxed text-ink">
                  {report.return_note}
                </p>
              </div>
            </section>
          )}
        </article>
        <footer className="flex flex-wrap items-center justify-between gap-2 border-t border-line px-4 py-3">
          <div className="flex flex-wrap items-center gap-2">
            <button
              className="btn btn-secondary btn-sm"
              onClick={() => exportReports([report], "word")}
            >
              <FileDown size={15} />
              Word
            </button>
            <button
              className="btn btn-secondary btn-sm"
              onClick={() => exportReports([report], "pdf")}
            >
              <FileDown size={15} />
              PDF
            </button>
          </div>
          {(isEmployee || canTransition || isDatabaseManager) && (
            <div className="flex flex-wrap items-center gap-2">
              {canEdit && (
                <button
                  className="btn btn-secondary btn-sm"
                  onClick={() => edit?.(report)}
                >
                  <Pencil size={15} />
                  {t("editReturnedReport")}
                </button>
              )}
              {canSubmit ? (
                <>
                  <button
                    className="btn btn-primary btn-sm"
                    onClick={() => transition("submit")}
                  >
                    <Send size={15} />
                    {t("forwardHead")}
                  </button>
                </>
              ) : (
                <>
                  {(canTransition || isDatabaseManager) && (
                    <>
                      <button
                        className="btn btn-danger btn-sm"
                        disabled={isApproved || !actions.includes("return")}
                        onClick={() => setReturnDialogOpen(true)}
                      >
                        <RefreshCw size={15} />
                        {t("returnEmployee")}
                      </button>
                      {actions.includes("forward_branch") && (
                        <button
                          className="btn btn-primary btn-sm"
                          onClick={() => transition("forward_branch")}
                        >
                          <Send size={15} />
                          {t("forwardBranch")}
                        </button>
                      )}
                      {actions.includes("forward_general") && (
                        <button
                          className="btn btn-primary btn-sm"
                          onClick={() => transition("forward_general")}
                        >
                          <Send size={15} />
                          {t("forwardGeneral")}
                        </button>
                      )}
                      {actions.includes("approve") && (
                        <button
                          className="btn btn-primary btn-sm"
                          onClick={() => transition("approve")}
                        >
                          <Send size={15} />
                          {t("approveFinal")}
                        </button>
                      )}
                    </>
                  )}
                </>
              )}
            </div>
          )}
        </footer>
        {returnDialogOpen && (
          <ReturnReasonDialog
            close={() => setReturnDialogOpen(false)}
            submit={(note) => transition("return", note)}
          />
        )}
      </aside>
    </div>
  );
}

function ReturnReasonDialog({ close, submit }) {
  const { t } = useLanguage();
  const [note, setNote] = useState("");
  const canSubmit = note.trim().length > 0;

  return (
    <div className="overlay grid place-items-center" onClick={close}>
      <section
        className="modal max-w-lg"
        onClick={(e) => e.stopPropagation()}
      >
        <header className="modal-head items-start">
          <span className="inline-grid h-8 w-8 shrink-0 place-items-center rounded border border-danger-500/25 bg-danger-50 text-danger-500">
            <RefreshCw size={16} />
          </span>
          <div className="min-w-0 flex-1">
            <h3 className="text-sm font-semibold text-ink">
              {t("returnEmployee")}
            </h3>
            <p className="mt-1 text-xs leading-relaxed text-muted">
              {t("returnReasonPrompt")}
            </p>
          </div>
          <button
            className="btn-icon shrink-0"
            type="button"
            onClick={close}
            aria-label={t("cancel")}
          >
            <X size={16} />
          </button>
        </header>
        <div className="modal-body">
          <textarea
            className="textarea min-h-36"
            value={note}
            onChange={(event) => setNote(event.target.value)}
            placeholder={t("returnReasonPlaceholder")}
            autoFocus
          />
        </div>
        <footer className="modal-foot">
          <button type="button" className="btn btn-secondary" onClick={close}>
            {t("cancel")}
          </button>
          <button
            type="button"
            className="btn btn-danger"
            disabled={!canSubmit}
            onClick={() => submit(note.trim())}
          >
            <RefreshCw size={16} />
            {t("returnEmployee")}
          </button>
        </footer>
      </section>
    </div>
  );
}

function reportActions(user, report, canTransition) {
  const role = user.role;
  if (!canTransition && role !== "database_manager") return [];
  if (role === "database_manager") {
    return {
      draft: ["submit"],
      returned: ["submit"],
      department_review: ["return", "forward_branch", "forward_general"],
      branch_review: ["return", "forward_general"],
      general_review: ["return", "approve"],
      approved: [],
    }[report.status] || [];
  }
  if (role === "department_head" && report.status === "department_review") {
    return ["return", "forward_branch", "forward_general"];
  }
  if (role === "branch_manager" && report.status === "branch_review") {
    return ["return", "forward_general"];
  }
  if (role === "general_manager" && report.status === "general_review") {
    return ["return", "approve"];
  }
  return [];
}
