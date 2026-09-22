import { Download, FileCheck2, FileWarning, History, Printer, Upload } from "lucide-react";
import { useRef, useState } from "react";
import { useLanguage } from "../../../Provider/LanguageContext";
import { formatBytes, formatMoment } from "../templateUtils";

/**
 * بطاقة قالب واحد: حالته على القرص، وحقوله، واستبداله، ونسخه السابقة.
 * الاستبدال يضع الملف في مسار القالب نفسه وباسمه، فلا يحتاج تعديل إعدادات.
 */
export function TemplateCard({ template, onUpload, onDownload, onBlank, onRestore }) {
  const { t } = useLanguage();
  const input = useRef(null);
  const [file, setFile] = useState(null);
  const [busy, setBusy] = useState(false);
  const [showBackups, setShowBackups] = useState(false);

  const missing = template.missing ?? [];
  const backups = template.backups ?? [];

  const run = async (action) => {
    setBusy(true);
    try {
      await action();
    } finally {
      setBusy(false);
    }
  };

  const submit = async () => {
    if (!file) return;
    await run(async () => {
      const done = await onUpload(template, file);
      if (done) {
        setFile(null);
        if (input.current) input.current.value = "";
      }
    });
  };

  return (
    <article className="card">
      <div className="card-head items-start">
        <div>
          <h3 className="section-title">{template.label}</h3>
          <p className="text-xs text-muted">{template.description}</p>
        </div>
        <span className={`badge ${template.exists ? "badge-ok" : "badge-warn"}`}>
          {template.exists ? (
            <FileCheck2 size={13} />
          ) : (
            <FileWarning size={13} />
          )}
          {t(template.exists ? "tpl_present" : "tpl_absent")}
        </span>
      </div>

      <div className="flex flex-col gap-3 p-4 pt-0">
        <dl className="grid grid-cols-2 gap-x-4 gap-y-1 text-xs sm:grid-cols-3">
          <div>
            <dt className="text-muted">{t("tpl_fileName")}</dt>
            <dd className="font-mono text-[11px] break-all text-ink">{template.file_name || "—"}</dd>
          </div>
          <div>
            <dt className="text-muted">{t("tpl_size")}</dt>
            <dd className="text-ink">{formatBytes(template.size)}</dd>
          </div>
          <div>
            <dt className="text-muted">{t("tpl_updatedAt")}</dt>
            <dd className="text-ink">{formatMoment(template.updated_at)}</dd>
          </div>
        </dl>

        <div>
          <p className="mb-1 text-xs text-muted">{t("tpl_fields")}</p>
          <div className="flex flex-wrap gap-1">
            {template.fields.map((field) => {
              const absent = missing.includes(field);
              return (
                <span
                  key={field}
                  className={`badge ${absent ? "badge-danger" : "badge-neutral"} font-mono text-[10px]`}
                  title={t(absent ? "tpl_fieldMissing" : "tpl_fieldPresent")}
                >
                  {`{{${field}}}`}
                </span>
              );
            })}
          </div>
          {missing.length > 0 && (
            <p className="mt-1 text-[11px] text-danger-500">
              {t("tpl_missingWarning").replace("{count}", String(missing.length))}
            </p>
          )}
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <input
            ref={input}
            type="file"
            accept=".docx"
            className="input flex-1 min-w-56 text-xs"
            onChange={(event) => setFile(event.target.files?.[0] ?? null)}
          />
          <button className="btn btn-primary btn-sm" disabled={!file || busy} onClick={submit}>
            <Upload size={15} />
            {t("tpl_replace")}
          </button>
          <button
            className="btn btn-secondary btn-sm"
            disabled={!template.exists || busy}
            onClick={() => run(() => onDownload(template))}
          >
            <Download size={15} />
            {t("tpl_download")}
          </button>
          {template.has_blank && (
            <button
              className="btn btn-secondary btn-sm"
              disabled={busy}
              onClick={() => run(() => onBlank(template))}
              title={t("tpl_blankHint")}
            >
              <Printer size={15} />
              {t("tpl_blank")}
            </button>
          )}
          {backups.length > 0 && (
            <button
              className="btn btn-ghost btn-sm"
              onClick={() => setShowBackups((shown) => !shown)}
            >
              <History size={15} />
              {t("tpl_previousVersions")} ({backups.length})
            </button>
          )}
        </div>

        {showBackups && backups.length > 0 && (
          <ul className="divide-y divide-[var(--color-line)] rounded-lg border border-line">
            {backups.map((backup) => (
              <li key={backup.name} className="flex items-center justify-between gap-2 px-3 py-2">
                <div className="min-w-0">
                  <p className="truncate font-mono text-[11px] text-ink">{backup.name}</p>
                  <p className="text-[11px] text-muted">
                    {formatMoment(backup.created_at)} — {formatBytes(backup.size)}
                  </p>
                </div>
                <button
                  className="btn btn-secondary btn-sm"
                  disabled={busy}
                  onClick={() => run(() => onRestore(template, backup))}
                >
                  {t("tpl_restore")}
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>
    </article>
  );
}
