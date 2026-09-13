import { AlertTriangle, Trash2 } from "lucide-react";
import { useState } from "react";
import { useLanguage } from "../../../Provider/LanguageContext";
import { TableMultiSelect } from "./TableMultiSelect";

export function MaintenancePanel({ tables, onTruncateAll, onTruncateTables }) {
  const { t } = useLanguage();
  const [selected, setSelected] = useState([]);

  return (
    <section className="card">
      <div className="card-head">
        <div>
          <h2 className="section-title">{t("databaseMaintenance")}</h2>
          <p className="text-xs text-muted">{t("databaseMaintenanceIntro")}</p>
        </div>
      </div>
      <div className="flex flex-col gap-4 p-4">
        <div className="flex items-start gap-2 rounded border border-danger-500/25 bg-danger-50 p-3 text-xs text-danger-500">
          <AlertTriangle size={16} className="mt-0.5 shrink-0" />
          <div>
            <p>{t("truncateWarning")}</p>
            <p className="mt-1">{t("protectedTablesNote")}</p>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <button className="btn btn-danger" onClick={onTruncateAll}>
            <Trash2 size={16} />
            {t("truncateWholeDatabase")}
          </button>
        </div>

        <div className="flex flex-col gap-3 rounded border border-line bg-surface p-3">
          <h3 className="text-xs font-semibold text-ink">{t("truncateSpecificTables")}</h3>
          <TableMultiSelect tables={tables} selected={selected} onChange={setSelected} />
          <button
            className="btn btn-danger self-start"
            disabled={selected.length === 0}
            onClick={() => onTruncateTables(selected)}
          >
            <Trash2 size={16} />
            {t("truncateSelectedButton")}
          </button>
        </div>
      </div>
    </section>
  );
}
