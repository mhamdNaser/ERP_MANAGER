import { RotateCcw } from "lucide-react";
import { useEffect, useState } from "react";
import { useLanguage } from "../../../Provider/LanguageContext";

const FORMAT_LABELS = { json: "JSON", sql: "SQL", backup: "Backup" };

export function RestorePanel({ backups, loadBackupContents, onRestore }) {
  const { t } = useLanguage();
  const [fileName, setFileName] = useState("");
  const [contents, setContents] = useState([]);
  const [selected, setSelected] = useState([]);

  const backup = backups.find((item) => item.file_name === fileName);
  const wholeFileOnly = backup?.format === "sql";

  useEffect(() => {
    if (!fileName) {
      setContents([]);
      setSelected([]);
      return;
    }
    let active = true;
    void loadBackupContents(fileName).then((rows) => {
      if (!active) return;
      setContents(rows);
      setSelected(rows.map((row) => row.table));
    });
    return () => {
      active = false;
    };
  }, [fileName, loadBackupContents]);

  const toggle = (table) => {
    if (wholeFileOnly) return;
    setSelected((prev) =>
      prev.includes(table) ? prev.filter((item) => item !== table) : [...prev, table],
    );
  };

  return (
    <section className="card">
      <div className="card-head">
        <div>
          <h2 className="section-title">{t("restoreData")}</h2>
          <p className="text-xs text-muted">{t("restoreDataIntro")}</p>
        </div>
      </div>
      <div className="flex flex-col gap-3 p-4">
        <label className="text-xs font-medium text-muted">
          {t("selectBackupFile")}
          <select
            className="select mt-1"
            value={fileName}
            onChange={(event) => setFileName(event.target.value)}
          >
            <option value="">—</option>
            {backups.map((item) => (
              <option key={item.file_name} value={item.file_name}>
                [{FORMAT_LABELS[item.format] || item.format}] {item.file_name}
              </option>
            ))}
          </select>
        </label>

        {!backups.length && <p className="text-xs text-muted">{t("noBackups")}</p>}

        {fileName && (
          <div className="flex flex-col gap-2">
            <h3 className="text-xs font-semibold text-ink">{t("backupContents")}</h3>
            {wholeFileOnly && (
              <p className="text-xs text-muted">{t("sqlRestoreWholeFileNote")}</p>
            )}
            <div className="grid max-h-48 grid-cols-1 gap-1 overflow-y-auto rounded border border-line p-2 sm:grid-cols-2">
              {contents.map((row) => (
                <label
                  key={row.table}
                  className="flex items-center justify-between gap-2 text-xs text-ink"
                >
                  <span className="flex items-center gap-2">
                    <input
                      type="checkbox"
                      checked={selected.includes(row.table)}
                      disabled={wholeFileOnly}
                      onChange={() => toggle(row.table)}
                    />
                    {row.table}
                  </span>
                  <span className="text-muted">{row.rows ?? "—"}</span>
                </label>
              ))}
            </div>
            <button
              className="btn btn-danger self-start"
              disabled={selected.length === 0}
              onClick={() => onRestore(fileName, selected)}
            >
              <RotateCcw size={16} />
              {t("restoreSelectedButton")}
            </button>
          </div>
        )}
      </div>
    </section>
  );
}
