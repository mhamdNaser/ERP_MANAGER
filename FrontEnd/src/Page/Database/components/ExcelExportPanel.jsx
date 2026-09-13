import { FileSpreadsheet } from "lucide-react";
import { useState } from "react";
import { useLanguage } from "../../../Provider/LanguageContext";

export function ExcelExportPanel({ tables, onExport }) {
  const { t } = useLanguage();
  const [table, setTable] = useState("");

  return (
    <section className="card">
      <div className="card-head">
        <div>
          <h2 className="section-title">{t("excelExportTitle")}</h2>
          <p className="text-xs text-muted">{t("excelExportIntro")}</p>
        </div>
      </div>
      <div className="flex flex-wrap items-end gap-2 p-4 pt-0">
        <label className="min-w-56 flex-1 text-xs font-medium text-muted">
          {t("selectTableToExport")}
          <select
            className="select mt-1"
            value={table}
            onChange={(event) => setTable(event.target.value)}
          >
            <option value="">—</option>
            {tables.map((name) => (
              <option key={name} value={name}>
                {name}
              </option>
            ))}
          </select>
        </label>
        <button
          className="btn btn-primary"
          disabled={!table}
          onClick={() => onExport(table)}
        >
          <FileSpreadsheet size={16} />
          {t("downloadExcel")}
        </button>
      </div>
    </section>
  );
}
