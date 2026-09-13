import { BellRing, FileText } from "lucide-react";
import { StatusBadge } from "../../Components/Reports";
import { useLanguage } from "../../Provider/LanguageContext";
export function NotificationPage({
  notice,
  openReport,
  openCircular,
  openForm,
}) {
  const { t } = useLanguage();
  const isCircular = notice.source_type === "circular" && notice.circular;
  const isForm =
    notice.source_type === "form" && notice.custom_form_publication;
  return (
    <div className="page max-w-[1100px]">
      <div className="page-header">
        <div className="min-w-0">
          <p className="eyebrow">{t("notificationDetails")}</p>
          <h1 className="page-title mt-1">{notice.title}</h1>
          <p className="page-subtitle mt-1">{notice.message}</p>
        </div>
      </div>
      <section className="card flex items-start gap-4 p-4">
        <BellRing className="h-9 w-9 shrink-0 rounded border border-line bg-subtle p-2 text-brand-500" />
        <div className="min-w-0">
          <small className="block text-xs text-muted">
            {new Date(notice.created_at).toLocaleString()}
          </small>
          <h2 className="section-title mt-1">{notice.title}</h2>
          <p className="mt-1 text-[13px] leading-relaxed text-muted">
            {notice.message}
          </p>
        </div>
      </section>
      {notice.report && (
        <section className="card flex flex-wrap items-center gap-3 p-4">
          <div className="flex min-w-0 flex-1 items-center gap-3">
            <FileText className="h-5 w-5 shrink-0 text-brand-500" />
            <span className="flex min-w-0 flex-col gap-0.5">
              <small className="text-xs text-muted">{t("linkedReport")}</small>
              <b className="truncate text-[13px] font-semibold text-ink">
                {notice.report.title}
              </b>
            </span>
          </div>
          <StatusBadge status={notice.report.status} />
          <button
            className="btn btn-primary"
            onClick={() => openReport(notice.report)}
          >
            {t("openReport")}
          </button>
        </section>
      )}
      {isCircular && (
        <section className="card flex flex-wrap items-center gap-3 border-s-2 border-s-brand-500 bg-subtle p-4">
          <div className="flex min-w-0 flex-1 items-center gap-3">
            <BellRing className="h-5 w-5 shrink-0 text-brand-500" />
            <span className="flex min-w-0 flex-col gap-0.5">
              <small className="text-xs text-muted">
                {t("linkedCircular")}
              </small>
              <b className="truncate text-[13px] font-semibold text-ink">
                {notice.circular.title}
              </b>
            </span>
          </div>
          <button
            className="btn btn-primary"
            onClick={() => openCircular(notice.circular)}
          >
            {t("openCircular")}
          </button>
        </section>
      )}
      {isForm && (
        <section className="card flex flex-wrap items-center gap-3 border-s-2 border-s-brand-500 bg-subtle p-4">
          <div className="flex min-w-0 flex-1 items-center gap-3">
            <FileText className="h-5 w-5 shrink-0 text-brand-500" />
            <span className="flex min-w-0 flex-col gap-0.5">
              <small className="text-xs text-muted">{t("linkedForm")}</small>
              <b className="truncate text-[13px] font-semibold text-ink">
                {notice.custom_form_publication.form.title}
              </b>
            </span>
          </div>
          <button
            className="btn btn-primary"
            onClick={() => openForm(notice.custom_form_publication)}
          >
            {t("openForm")}
          </button>
        </section>
      )}
    </div>
  );
}
