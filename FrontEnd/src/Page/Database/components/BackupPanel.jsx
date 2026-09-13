import { Download, ShieldCheck } from "lucide-react";
import { useMemo, useState } from "react";
import { useLanguage } from "../../../Provider/LanguageContext";
import { TableMultiSelect } from "./TableMultiSelect";

export function BackupPanel({ tables, onCreate }) {
  const { t } = useLanguage();
  const [format, setFormat] = useState("json");
  const [scope, setScope] = useState("all");
  const [selected, setSelected] = useState([]);
  const [bundleFiles, setBundleFiles] = useState(false);

  const scopedTables = useMemo(
    () => (scope === "specific" ? selected : undefined),
    [scope, selected],
  );
  const disabled = scope === "specific" && selected.length === 0;

  return (
    <div className="flex flex-col gap-3 rounded border border-line bg-surface p-3">
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <label className="text-xs font-medium text-muted">
          {t("exportFormat")}
          <select
            className="select mt-1"
            value={format}
            onChange={(event) => setFormat(event.target.value)}
          >
            <option value="json">{t("formatJson")}</option>
            <option value="sql">{t("formatSql")}</option>
            <option value="backup">{t("formatBackup")}</option>
          </select>
        </label>
        <label className="text-xs font-medium text-muted">
          {t("backupScope")}
          <select
            className="select mt-1"
            value={scope}
            onChange={(event) => setScope(event.target.value)}
          >
            <option value="all">{t("scopeAllTables")}</option>
            <option value="specific">{t("scopeSpecificTables")}</option>
          </select>
        </label>
      </div>
      {scope === "specific" && (
        <TableMultiSelect tables={tables} selected={selected} onChange={setSelected} />
      )}
      <label className="flex items-center gap-2 text-xs font-medium text-muted">
        <input
          type="checkbox"
          className="h-4 w-4 accent-brand-500"
          checked={bundleFiles}
          onChange={(event) => setBundleFiles(event.target.checked)}
        />
        {t("bundleAttachedFiles")}
      </label>
      <div className="flex flex-wrap items-center gap-2">
        <button
          className="btn btn-primary"
          disabled={disabled}
          onClick={() => onCreate("internal", { format, tables: scopedTables, bundleFiles })}
        >
          <ShieldCheck size={16} />
          {t("createInternalBackup")}
        </button>
        <button
          className="btn btn-secondary"
          disabled={disabled}
          onClick={() => onCreate("external", { format, tables: scopedTables, bundleFiles })}
        >
          <Download size={16} />
          {t("createExternalBackup")}
        </button>
      </div>
    </div>
  );
}
