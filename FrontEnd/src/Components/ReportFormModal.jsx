import { useLanguage } from "../Provider/LanguageContext";
import { api } from "../lib";
import { ReusableFormModal } from "./ReusableFormModal";
export function ReportFormModal({ report, notify, done, close }) {
  const { t } = useLanguage();
  const fields = [
    {
      name: "type",
      label: t("documentType"),
      type: "select",
      required: true,
      options: [
        { value: "daily_report", label: t("dailyReport") },
        { value: "daily_plan", label: t("dailyPlan") },
        { value: "weekly_report", label: t("weeklyReport") },
        { value: "weekly_plan", label: t("weeklyPlan") },
      ],
    },
    {
      name: "period_start",
      label: t("startDate"),
      type: "date",
      required: true,
    },
    {
      name: "title",
      label: t("clearTitle"),
      required: true,
      wide: true,
      placeholder: t("titlePlaceholder"),
    },
    {
      name: "summary",
      label: t("executiveSummary"),
      type: "textarea",
      required: true,
      wide: true,
      placeholder: t("summaryPlaceholder"),
    },
    { name: "achievements", label: t("achievements"), type: "textarea" },
    { name: "challenges", label: t("challenges"), type: "textarea" },
    { name: "next_steps", label: t("nextSteps"), type: "textarea", wide: true },
  ];
  const initial = {
    type: report?.type || "daily_report",
    period_start: report?.period_start || new Date().toISOString().slice(0, 10),
    title: report?.title || "",
    summary: report?.summary || "",
    achievements: report?.achievements || "",
    challenges: report?.challenges || "",
    next_steps: report?.next_steps || "",
  };
  const submit = async (values) => {
    try {
      const payload = Object.fromEntries(
        Object.entries(values).map(([key, value]) => [key, String(value)]),
      );
      if (report) await api.updateReturnedReport(report.id, payload);
      else await api.createReport(payload);
      notify(report ? t("reportUpdated") : t("reportCreated"), "success");
      done();
    } catch (error) {
      notify(error.message, "error");
      throw error;
    }
  };
  return (
    <ReusableFormModal
      title={
        report
          ? report.status === "returned"
            ? t("editReturnedReport")
            : `${t("edit")} ${t("report")}`
          : t("createReport")
      }
      subtitle={
        report
          ? report.status === "returned"
            ? t("editReturnedIntro")
            : t("reportsIntro")
          : t("createReportIntro")
      }
      fields={fields}
      initial={initial}
      submitLabel={report ? t("updateReport") : t("saveDraft")}
      onSubmit={submit}
      onClose={close}
    />
  );
}
