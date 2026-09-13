import { FileText, X } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { useLanguage } from "../Provider/LanguageContext";
import { api } from "../lib";
export function UserFormHistoryDrawer({ user, close, title }) {
  const { t } = useLanguage();
  const [history, setHistory] = useState([]);
  const [loading, setLoading] = useState(true);
  useEffect(() => {
    let alive = true;
    const load = async () => {
      try {
        setLoading(true);
        const items = await api.userFormHistory(user.id);
        if (alive) setHistory(items);
      } catch {
        if (alive) setLoading(false);
      } finally {
        if (alive) setLoading(false);
      }
    };
    void load();
    return () => {
      alive = false;
    };
  }, [user.id]);
  const groups = useMemo(
    () =>
      history.reduce((accumulator, item) => {
        const list = accumulator[item.form.id] || [];
        list.push(item);
        accumulator[item.form.id] = list;
        return accumulator;
      }, {}),
    [history],
  );
  return (
    <div className="fixed inset-0 z-50 bg-brand-900/40" onClick={close}>
      <aside
        className="drawer"
        onClick={(event) => event.stopPropagation()}
      >
        <header className="flex items-start justify-between gap-3 border-b border-line px-4 py-3">
          <div className="min-w-0">
            <span className="eyebrow">{t("formHistory")}</span>
            <h2 className="mt-1 text-base font-semibold text-ink">
              {title || `${t("formHistoryFor")} · ${user.name}`}
            </h2>
            <small className="mt-1 block text-xs text-muted">
              {user.job_title}
            </small>
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

        <section className="grid grid-cols-2 gap-3 border-b border-line px-4 py-3">
          <div className="flex items-center justify-between gap-2 rounded border border-line bg-subtle px-3 py-2.5">
            <small className="text-xs text-muted">{t("submissions")}</small>
            <b className="text-sm font-semibold text-ink">{history.length}</b>
          </div>
          <div className="flex items-center justify-between gap-2 rounded border border-line bg-subtle px-3 py-2.5">
            <small className="text-xs text-muted">{t("formsCount")}</small>
            <b className="text-sm font-semibold text-ink">
              {Object.keys(groups).length}
            </b>
          </div>
        </section>

        {loading && (
          <div className="p-4">
            <div className="empty-state">
              <FileText size={22} className="text-line-strong" />
              <p>{t("loading")}</p>
            </div>
          </div>
        )}
        {!loading && !history.length && (
          <div className="p-4">
            <div className="empty-state">
              <FileText size={22} className="text-line-strong" />
              <p>{t("noSubmissions")}</p>
            </div>
          </div>
        )}

        <div className="flex flex-1 flex-col gap-3 overflow-y-auto p-4">
          {Object.entries(groups).map(([formId, submissions]) => (
            <article
              key={formId}
              className="rounded border border-line bg-surface"
            >
              <header className="flex flex-wrap items-center justify-between gap-3 border-b border-line px-3 py-2.5">
                <div className="min-w-0">
                  <h3 className="text-[13px] font-semibold text-ink">
                    {submissions[0].form.title}
                  </h3>
                  <p className="text-xs text-muted">
                    {submissions[0].form.description || t("noDescription")}
                  </p>
                </div>
                <span className="badge badge-neutral">
                  {submissions.length} {t("versions")}
                </span>
              </header>
              <div className="flex flex-col gap-3 p-3">
                {submissions
                  .sort((left, right) => left.version - right.version)
                  .map((submission) => (
                    <div
                      key={submission.id}
                      className="flex flex-col gap-2 rounded border border-line bg-subtle p-3"
                    >
                      <div className="flex items-start justify-between gap-2">
                        <b className="text-xs font-semibold text-ink">
                          {t("version")} #{submission.version}
                        </b>
                        <small className="text-[11px] text-muted">
                          {new Date(submission.submitted_at).toLocaleString()}
                        </small>
                      </div>
                      <div className="flex flex-col">
                        {submission.form.fields.map((field) => (
                          <p
                            key={field.id}
                            className="flex justify-between gap-3 border-b border-line py-1.5 text-xs last:border-b-0"
                          >
                            <span className="text-muted">{field.label}</span>
                            <b className="text-end font-semibold text-ink">
                              {formatFormValue(submission.payload[field.field_key], t)}
                            </b>
                          </p>
                        ))}
                      </div>
                    </div>
                  ))}
              </div>
            </article>
          ))}
        </div>
      </aside>
    </div>
  );
}

function formatFormValue(value, t) {
  if (Array.isArray(value)) return value.length ? value.join("، ") : "—";
  if (typeof value === "boolean") return value ? t("ui_yes") : t("ui_no");
  return String(value ?? "—");
}
