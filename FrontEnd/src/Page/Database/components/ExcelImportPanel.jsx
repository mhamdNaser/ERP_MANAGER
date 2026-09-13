import { Eye, Upload } from "lucide-react";
import { useState } from "react";
import { useLanguage } from "../../../Provider/LanguageContext";

export function ExcelImportPanel({ tables, onPreview, onCommit }) {
  const { t } = useLanguage();
  const [table, setTable] = useState("");
  const [file, setFile] = useState(null);
  const [preview, setPreview] = useState(null);
  const [busy, setBusy] = useState(false);

  const reset = () => setPreview(null);

  const runPreview = async () => {
    if (!table || !file) return;
    setBusy(true);
    try {
      setPreview(await onPreview(table, file));
    } finally {
      setBusy(false);
    }
  };

  const runCommit = async () => {
    setBusy(true);
    try {
      const result = await onCommit(table, file);
      if (result) {
        setPreview(null);
        setFile(null);
      }
    } finally {
      setBusy(false);
    }
  };

  return (
    <section className="card">
      <div className="card-head">
        <div>
          <h2 className="section-title">{t("excelImportTitle")}</h2>
          <p className="text-xs text-muted">{t("excelImportIntro")}</p>
        </div>
      </div>
      <div className="flex flex-col gap-3 p-4 pt-0">
        {tables.length === 0 && (
          <p className="text-xs text-muted">{t("noImportableTables")}</p>
        )}
        <div className="flex flex-wrap items-end gap-2">
          <label className="min-w-56 flex-1 text-xs font-medium text-muted">
            {t("selectTableToImport")}
            <select
              className="select mt-1"
              value={table}
              onChange={(event) => {
                setTable(event.target.value);
                reset();
              }}
            >
              <option value="">—</option>
              {tables.map((name) => (
                <option key={name} value={name}>
                  {name}
                </option>
              ))}
            </select>
          </label>
          <label className="min-w-56 flex-1 text-xs font-medium text-muted">
            {t("chooseExcelFile")}
            <input
              type="file"
              accept=".xlsx,.xls,.csv"
              className="input mt-1"
              onChange={(event) => {
                setFile(event.target.files?.[0] || null);
                reset();
              }}
            />
          </label>
          <button
            className="btn btn-secondary"
            disabled={!table || !file || busy}
            onClick={runPreview}
          >
            <Eye size={16} />
            {t("previewImportBtn")}
          </button>
        </div>

        {preview && (
          <div className="flex flex-col gap-2 rounded border border-line bg-canvas p-3">
            <p className="text-xs font-medium text-muted">
              {t("importPreviewSummary", {
                valid: preview.valid_count,
                errors: preview.error_count,
              })}
            </p>
            <div className="max-h-64 overflow-y-auto">
              {preview.rows.map((row) => (
                <div
                  key={row.row}
                  className={`border-b border-line px-1 py-1.5 text-xs ${row.errors.length ? "text-danger-500" : "text-ink"}`}
                >
                  <b>{t("importRowLabel", { row: row.row })}</b>{" "}
                  {row.errors.length
                    ? row.errors.join(" — ")
                    : Object.values(row.values).join(" · ")}
                </div>
              ))}
            </div>
            {preview.error_count > 0 && (
              <p className="text-xs text-danger-500">{t("importHasErrorsNote")}</p>
            )}
            <button
              className="btn btn-primary self-start"
              disabled={preview.error_count > 0 || busy}
              onClick={runCommit}
            >
              <Upload size={16} />
              {t("commitImportBtn")}
            </button>
          </div>
        )}
      </div>
    </section>
  );
}
