import {
  BookOpenCheck,
  ChartColumn,
  ChevronLeft,
  ClipboardList,
  Compass,
  DatabaseBackup,
  FileText,
  FileInput,
  HelpCircle,
  LayoutDashboard,
  ListChecks,
  Mail,
  Megaphone,
  Network,
  RotateCcw,
  Search,
  ShieldCheck,
  UserRound,
} from "lucide-react";
import { useMemo, useState } from "react";
import { GUIDE_RESTART_EVENT } from "../../Components/InterfaceGuide";
import { useLanguage } from "../../Provider/LanguageContext";
const sections = [
  {
    id: "dashboard",
    icon: LayoutDashboard,
    title: "guide_secDashboardTitle",
    summary: "guide_secDashboardSummary",
    details: [
      "guide_secDashboardD1",
      "guide_secDashboardD2",
      "guide_secDashboardD3",
    ],
  },
  {
    id: "tasks",
    icon: ListChecks,
    title: "guide_secTasksTitle",
    summary: "guide_secTasksSummary",
    details: ["guide_secTasksD1", "guide_secTasksD2", "guide_secTasksD3"],
  },
  {
    id: "task-stats",
    icon: ChartColumn,
    title: "guide_secTaskStatsTitle",
    summary: "guide_secTaskStatsSummary",
    details: [
      "guide_secTaskStatsD1",
      "guide_secTaskStatsD2",
      "guide_secTaskStatsD3",
    ],
  },
  {
    id: "drive",
    icon: BookOpenCheck,
    title: "guide_secDriveTitle",
    summary: "guide_secDriveSummary",
    details: ["guide_secDriveD1", "guide_secDriveD2", "guide_secDriveD3"],
  },
  {
    id: "reports",
    icon: ClipboardList,
    title: "guide_secReportsTitle",
    summary: "guide_secReportsSummary",
    details: [
      "guide_secReportsD1",
      "guide_secReportsD2",
      "guide_secReportsD3",
      "guide_secReportsD4",
      "guide_secReportsD5",
    ],
  },
  {
    id: "organization",
    icon: Network,
    title: "guide_secOrgTitle",
    summary: "guide_secOrgSummary",
    details: ["guide_secOrgD1", "guide_secOrgD2", "guide_secOrgD3"],
  },
  {
    id: "communications",
    icon: Mail,
    title: "guide_secCommsTitle",
    summary: "guide_secCommsSummary",
    details: ["guide_secCommsD1", "guide_secCommsD2", "guide_secCommsD3"],
  },
  {
    id: "formal-correspondences",
    icon: FileText,
    title: "guide_secFormalTitle",
    summary: "guide_secFormalSummary",
    details: [
      "guide_secFormalD1",
      "guide_secFormalD2",
      "guide_secFormalD3",
      "guide_secFormalD4",
    ],
  },
  {
    id: "circulars",
    icon: Megaphone,
    title: "guide_secCircularsTitle",
    summary: "guide_secCircularsSummary",
    details: [
      "guide_secCircularsD1",
      "guide_secCircularsD2",
      "guide_secCircularsD3",
    ],
  },
  {
    id: "forms",
    icon: FileInput,
    title: "guide_secFormsTitle",
    summary: "guide_secFormsSummary",
    details: ["guide_secFormsD1", "guide_secFormsD2", "guide_secFormsD3"],
  },
  {
    id: "profile",
    icon: UserRound,
    title: "guide_secProfileTitle",
    summary: "guide_secProfileSummary",
    details: ["guide_secProfileD1", "guide_secProfileD2", "guide_secProfileD3"],
  },
  {
    id: "permissions",
    icon: ShieldCheck,
    title: "guide_secPermsTitle",
    summary: "guide_secPermsSummary",
    details: ["guide_secPermsD1", "guide_secPermsD2", "guide_secPermsD3"],
  },
];
const forms = [
  ["guide_formReportTitle", "guide_formReportText"],
  ["guide_formTaskTitle", "guide_formTaskText"],
  ["guide_formUploadTitle", "guide_formUploadText"],
  ["guide_formMessageTitle", "guide_formMessageText"],
  ["guide_formFormalTitle", "guide_formFormalText"],
  ["guide_formStationTitle", "guide_formStationText"],
  ["guide_formCircularTitle", "guide_formCircularText"],
  ["guide_formEmployeeTitle", "guide_formEmployeeText"],
  ["guide_formPersonalTitle", "guide_formPersonalText"],
  ["guide_formDesignerTitle", "guide_formDesignerText"],
];
const backupSteps = [1, 2, 3, 4, 5, 6].map((step) => [
  `guide_backupStep${step}Title`,
  `guide_backupStep${step}Text`,
]);
export function UserGuidePage() {
  const { t } = useLanguage();
  const [query, setQuery] = useState("");
  const translatedSections = useMemo(
    () =>
      sections.map((section) => ({
        ...section,
        title: t(section.title),
        summary: t(section.summary),
        details: section.details.map((detail) => t(detail)),
      })),
    [t],
  );
  const visible = useMemo(
    () =>
      translatedSections.filter((section) =>
        `${section.title} ${section.summary} ${section.details.join(" ")}`.includes(
          query,
        ),
      ),
    [query, translatedSections],
  );
  return (
    <div className="page">
      <section className="page-header">
        <div className="min-w-0">
          <p className="eyebrow flex items-center gap-1.5">
            <HelpCircle size={14} /> {t("guide_helpCenter")}
          </p>
          <h1 className="page-title mt-1">{t("guide_pageTitle")}</h1>
          <span className="page-subtitle mt-1 block">
            {t("guide_pageSubtitle")}
          </span>
        </div>
        <button
          className="btn btn-secondary"
          onClick={() => window.dispatchEvent(new Event(GUIDE_RESTART_EVENT))}
        >
          <RotateCcw size={16} /> {t("guide_restartTour")}
        </button>
      </section>
      <div className="flex h-9 items-center gap-2 rounded border border-line bg-white px-3">
        <Search size={16} className="shrink-0 text-muted" />
        <input
          className="w-full border-0 bg-transparent text-[13px] text-ink outline-none"
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          placeholder={t("guide_searchPlaceholder")}
        />
      </div>
      <section className="card">
        <header className="card-head">
          <div className="flex min-w-0 items-center gap-2.5">
            <Compass className="h-5 w-5 shrink-0 text-brand-500" />
            <span className="flex min-w-0 flex-col">
              <b className="section-title">{t("guide_systemInterfaces")}</b>
              <small className="text-xs text-muted">
                {t("guide_systemInterfacesHint")}
              </small>
            </span>
          </div>
        </header>
        <div className="card-body grid grid-cols-1 gap-3 lg:grid-cols-2">
          {visible.map(({ id, icon: Icon, title, summary, details }) => (
            <article key={id} className="rounded border border-line bg-subtle p-4">
              <div className="mb-3 flex items-center gap-3">
                <span className="inline-grid h-9 w-9 shrink-0 place-items-center rounded border border-line bg-surface text-brand-500">
                  <Icon className="h-[18px] w-[18px]" />
                </span>
                <div className="min-w-0">
                  <h2 className="section-title">{title}</h2>
                  <p className="mt-0.5 text-xs text-muted">{summary}</p>
                </div>
              </div>
              <ul className="m-0 flex list-none flex-col gap-1.5 p-0">
                {details.map((detail) => (
                  <li
                    key={detail}
                    className="flex items-start gap-1.5 text-xs leading-relaxed text-muted"
                  >
                    <ChevronLeft
                      size={14}
                      className="mt-0.5 shrink-0 text-line-strong rtl:rotate-180"
                    />
                    {detail}
                  </li>
                ))}
              </ul>
            </article>
          ))}
        </div>
      </section>
      <section className="card">
        <header className="card-head">
          <div className="flex min-w-0 items-center gap-2.5">
            <DatabaseBackup className="h-5 w-5 shrink-0 text-brand-500" />
            <span className="flex min-w-0 flex-col">
              <b className="section-title">{t("guide_backupTitle")}</b>
              <small className="text-xs text-muted">{t("guide_backupHint")}</small>
            </span>
          </div>
        </header>
        <ol className="card-body m-0 grid list-none grid-cols-1 gap-3 lg:grid-cols-2">
          {backupSteps.map(([titleKey, textKey], index) => (
            <li
              key={titleKey}
              className="flex gap-3 rounded border border-line bg-subtle p-3"
            >
              <span className="inline-grid h-8 w-8 shrink-0 place-items-center rounded bg-brand-500 text-[11px] font-semibold text-white">
                {index + 1}
              </span>
              <div className="min-w-0">
                <h3 className="text-[13px] font-semibold text-ink">{t(titleKey)}</h3>
                <p className="mt-1 text-xs leading-relaxed text-muted">{t(textKey)}</p>
              </div>
            </li>
          ))}
        </ol>
      </section>
      <section className="card">
        <header className="card-head">
          <div className="flex min-w-0 items-center gap-2.5">
            <FileInput className="h-5 w-5 shrink-0 text-brand-500" />
            <span className="flex min-w-0 flex-col">
              <b className="section-title">{t("guide_formsReference")}</b>
              <small className="text-xs text-muted">
                {t("guide_formsReferenceHint")}
              </small>
            </span>
          </div>
        </header>
        <div className="card-body grid grid-cols-1 gap-3 lg:grid-cols-2">
          {forms.map(([titleKey, textKey], index) => (
            <article
              key={titleKey}
              className="flex gap-3 rounded border border-line p-3"
            >
              <span className="inline-grid h-8 w-8 shrink-0 place-items-center rounded bg-brand-800 text-[11px] font-semibold text-white">
                {String(index + 1).padStart(2, "0")}
              </span>
              <div className="min-w-0">
                <h3 className="text-[13px] font-semibold text-ink">
                  {t(titleKey)}
                </h3>
                <p className="mt-1 text-xs leading-relaxed text-muted">
                  {t(textKey)}
                </p>
              </div>
            </article>
          ))}
        </div>
      </section>
    </div>
  );
}
