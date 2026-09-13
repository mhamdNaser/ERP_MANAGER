import { FileDown, RefreshCw, Table2, X } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { useLanguage } from "../Provider/LanguageContext";
import { api, exportFormSubmissions } from "../lib";
export function FormSubmissionsDrawer({ form, close, notify }) {
  const { t } = useLanguage();
  const [submissions, setSubmissions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [reloadIndex, setReloadIndex] = useState(0);
  useEffect(() => {
    let mounted = true;
    const load = async () => {
      try {
        setLoading(true);
        const response = await api.formSubmissions(form.id);
        if (!mounted) return;
        setSubmissions(response.submissions);
      } catch (error) {
        if (!mounted) return;
        notify(error.message, "error");
      } finally {
        if (mounted) setLoading(false);
      }
    };
    void load();
    return () => {
      mounted = false;
    };
  }, [form.id, notify, reloadIndex]);
  const fields = useMemo(
    () =>
      form.fields
        .slice()
        .sort((left, right) => left.sort_order - right.sort_order),
    [form.fields],
  );
  return (
    <div className="fixed inset-0 z-50 bg-brand-900/40" onClick={close}>
      <aside
        className="drawer max-w-5xl"
        onClick={(event) => event.stopPropagation()}
      >
        <header className="flex items-start justify-between gap-3 border-b border-line px-4 py-3">
          <div className="min-w-0">
            <span className="eyebrow">{t("formSubmissions")}</span>
            <h2 className="mt-1 text-base font-semibold text-ink">
              {form.title}
            </h2>
            <p className="mt-1 text-xs text-muted">
              {form.description || t("noDescription")}
            </p>
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
            <small className="text-[11px] text-muted">{t("submissions")}</small>
            <b className="text-[13px] font-semibold text-ink">
              {submissions.length}
            </b>
          </p>
          <p className="flex flex-col">
            <small className="text-[11px] text-muted">{t("targetGroup")}</small>
            <b className="text-[13px] font-semibold text-ink">
              {t(form.target_group)}
            </b>
          </p>
          <p className="flex flex-col">
            <small className="text-[11px] text-muted">
              {t("defaultScope")}
            </small>
            <b className="text-[13px] font-semibold text-ink">
              {t(
                `scope${form.default_scope.charAt(0).toUpperCase()}${form.default_scope.slice(1)}`,
              )}
            </b>
          </p>
        </div>

        <div className="flex flex-wrap gap-2 border-b border-line px-4 py-3">
          <button
            className="btn btn-secondary btn-sm"
            onClick={() => exportFormSubmissions(form, submissions, "excel")}
            disabled={!submissions.length}
          >
            <FileDown size={15} />
            {t("exportExcel")}
          </button>
          <button
            className="btn btn-secondary btn-sm"
            onClick={() => exportFormSubmissions(form, submissions, "pdf")}
            disabled={!submissions.length}
          >
            <FileDown size={15} />
            {t("exportPdf")}
          </button>
          <button
            className="btn btn-secondary btn-sm"
            onClick={() => setReloadIndex((index) => index + 1)}
          >
            <RefreshCw size={15} />
            {t("refresh")}
          </button>
        </div>

        {loading ? (
          <div className="p-4 text-[13px] text-muted">{t("loading")}</div>
        ) : submissions.length ? (
          <div className="flex-1 overflow-auto p-4">
            <table className="table min-w-[900px]">
              <thead>
                <tr>
                  <th>{t("version")}</th>
                  <th>{t("employee")}</th>
                  <th>{t("jobTitle")}</th>
                  <th>{t("branch")}</th>
                  <th>{t("department")}</th>
                  <th>{t("submittedAt")}</th>
                  {fields.map((field) => (
                    <th key={field.id}>{field.label}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {submissions.map((submission) => (
                  <tr key={submission.id}>
                    <td>
                      <span className="badge badge-neutral">
                        {submission.version}
                      </span>
                    </td>
                    <td className="whitespace-nowrap">
                      <b className="font-semibold">{submission.user.name}</b>
                    </td>
                    <td className="whitespace-nowrap">
                      {submission.user.job_title || submission.user.role}
                    </td>
                    <td className="whitespace-nowrap">
                      {submission.user.branch?.name || "—"}
                    </td>
                    <td className="whitespace-nowrap">
                      {submission.user.department?.name || "—"}
                    </td>
                    <td className="whitespace-nowrap">
                      {new Date(submission.submitted_at).toLocaleString()}
                    </td>
                    {fields.map((field) => (
                      <td key={`${submission.id}-${field.id}`}>
                        {formatFormValue(submission.payload[field.field_key], t)}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="p-4">
            <div className="empty-state">
              <Table2 size={22} className="text-line-strong" />
              <p>{t("noFormSubmissions")}</p>
            </div>
          </div>
        )}
      </aside>
    </div>
  );
}

function formatFormValue(value, t) {
  if (Array.isArray(value)) return value.length ? value.join("، ") : "—";
  if (typeof value === "boolean") return value ? t("ui_yes") : t("ui_no");
  return String(value ?? "—");
}
