import { Database, Download, Trash2 } from "lucide-react";
import { useLanguage } from "../../../Provider/LanguageContext";

const FORMAT_LABELS = { json: "JSON", sql: "SQL", backup: "Backup" };
const KIND_BADGE = { internal: "badge-brand", external: "badge-neutral", auto: "badge-warn" };

export function BackupList({ backups, onDownload, onDelete }) {
  const { t } = useLanguage();

  if (!backups.length) {
    return (
      <div className="p-4">
        <div className="empty-state">
          <Database size={22} className="text-line-strong" />
          <p>{t("noBackups")}</p>
        </div>
      </div>
    );
  }

  return (
    <div className="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
      {backups.map((backup) => (
        <article
          key={backup.file_name}
          className="flex flex-col gap-2 rounded border border-line bg-surface p-3"
        >
          <div className="flex items-center justify-between gap-2">
            <span className={`badge ${KIND_BADGE[backup.kind] || "badge-neutral"}`}>
              {backup.kind === "auto"
                ? t("autoBackup")
                : backup.kind === "internal"
                  ? t("internalBackup")
                  : t("externalBackup")}
            </span>
            <span className="badge badge-neutral">
              {FORMAT_LABELS[backup.format] || backup.format}
            </span>
          </div>
          <h3 className="text-[13px] font-semibold break-words text-ink">
            {backup.file_name}
          </h3>
          <p className="text-xs text-muted">
            {t("backupDate")}: {new Date(backup.created_at).toLocaleString()}
          </p>
          <p className="text-xs text-muted">
            {t("backupSize")}: {Math.round(backup.size / 1024)} KB
          </p>
          <div className="mt-1 flex items-center gap-2">
            <button
              className="btn btn-secondary btn-sm flex-1"
              onClick={() => onDownload(backup.file_name)}
            >
              <Download size={15} />
              {t("downloadBackup")}
            </button>
            <button
              className="btn btn-danger btn-sm"
              onClick={() => onDelete(backup.file_name)}
              title={t("deleteBackup")}
              aria-label={t("deleteBackup")}
            >
              <Trash2 size={15} />
            </button>
          </div>
        </article>
      ))}
    </div>
  );
}
