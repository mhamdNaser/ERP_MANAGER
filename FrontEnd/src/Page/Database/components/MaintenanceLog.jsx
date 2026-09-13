import { useLanguage } from "../../../Provider/LanguageContext";

function tablesLabel(tables) {
  if (Array.isArray(tables)) return tables.join(", ");
  if (tables && typeof tables === "object") return Object.keys(tables).join(", ");
  return "—";
}

export function MaintenanceLog({ logs }) {
  const { t } = useLanguage();

  if (!logs.length) {
    return (
      <section className="card">
        <div className="card-head">
          <div>
            <h2 className="section-title">{t("maintenanceLog")}</h2>
            <p className="text-xs text-muted">{t("maintenanceLogIntro")}</p>
          </div>
        </div>
        <div className="p-4">
          <div className="empty-state">
            <p>{t("noMaintenanceLogs")}</p>
          </div>
        </div>
      </section>
    );
  }

  return (
    <section className="card">
      <div className="card-head">
        <div>
          <h2 className="section-title">{t("maintenanceLog")}</h2>
          <p className="text-xs text-muted">{t("maintenanceLogIntro")}</p>
        </div>
      </div>
      <div className="overflow-x-auto p-4">
        <table className="w-full text-xs">
          <thead>
            <tr className="text-start text-muted">
              <th className="p-2 text-start">{t("actionType")}</th>
              <th className="p-2 text-start">{t("affectedTables")}</th>
              <th className="p-2 text-start">{t("performedBy")}</th>
              <th className="p-2 text-start">{t("backupDate")}</th>
            </tr>
          </thead>
          <tbody>
            {logs.map((log) => (
              <tr key={log.id} className="border-t border-line">
                <td className="p-2">
                  <span
                    className={`badge ${log.action === "truncate" ? "badge-danger" : "badge-brand"}`}
                  >
                    {log.action === "truncate" ? t("actionTruncate") : t("actionRestore")}
                  </span>
                </td>
                <td className="p-2 text-ink">{tablesLabel(log.tables)}</td>
                <td className="p-2 text-ink">{log.user?.name || "—"}</td>
                <td className="p-2 text-muted">{new Date(log.created_at).toLocaleString()}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  );
}
