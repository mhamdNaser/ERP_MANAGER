import { Download, Eye, FileSpreadsheet, Image, Trash2, Upload } from "lucide-react";
import { useCallback, useEffect, useState } from "react";
import { useLanguage } from "../../../Provider/LanguageContext";
import { api, downloadBlob } from "../../../lib";
import { formatDateTime, formatMoney, formatNumber } from "../maintenanceMeta";

const ACTION_BADGES = { create: "badge-ok", merge: "badge-info", skip: "badge-neutral" };

function Step({ number, title, children }) {
  return (
    <div className="flex gap-3">
      <span className="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-500 text-xs font-bold text-white">{number}</span>
      <div className="flex min-w-0 flex-1 flex-col gap-2">
        <b className="text-sm font-semibold text-ink">{title}</b>
        {children}
      </div>
    </div>
  );
}

/**
 * رفع ملفات الفرع كما هي: معاينة لا تكتب شيئاً، ثم اعتماد. المعاينة تُظهر
 * لكل سطر ما سيحدث له، فلا يُفاجأ المستخدم بتكرار أو سطر مُهمَل.
 */
export function ImportPanel({ catalog, notify, imported }) {
  const { t } = useLanguage();
  const [file, setFile] = useState(null);
  const [categoryId, setCategoryId] = useState("");
  const [existing, setExisting] = useState("skip");
  const [preview, setPreview] = useState(null);
  const [busy, setBusy] = useState("");
  const [history, setHistory] = useState([]);

  const loadHistory = useCallback(
    () => api.maintenanceImports().then(setHistory).catch((error) => notify?.(error.message, "error")),
    [notify],
  );
  useEffect(() => { loadHistory(); }, [loadHistory]);

  const payload = () => ({ file, category_id: categoryId, existing });

  const runPreview = async () => {
    setBusy("preview");
    try {
      setPreview(await api.previewMaintenanceImport(payload()));
    } catch (error) {
      setPreview(null);
      notify?.(error.message, "error");
    } finally {
      setBusy("");
    }
  };

  const commit = async () => {
    setBusy("commit");
    try {
      const result = await api.commitMaintenanceImport(payload());
      notify?.(t("mt_imported", { created: result.created_count, merged: result.merged_count, skipped: result.skipped_count }), "success");
      setFile(null);
      setPreview(null);
      loadHistory();
      imported();
    } catch (error) {
      notify?.(error.message, "error");
    } finally {
      setBusy("");
    }
  };

  // أي تغيير في المدخلات يُبطل المعاينة السابقة كي لا يُعتمد غير ما عُرض.
  const change = (setter) => (value) => {
    setter(value);
    setPreview(null);
  };

  const summary = preview?.summary;

  return (
    <div className="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
      <section className="card flex min-w-0 flex-col">
        <header className="card-head">
          <div className="min-w-0">
            <h2 className="section-title">{t("mt_importTitle")}</h2>
            <p className="page-subtitle mt-0.5 max-w-3xl text-xs leading-relaxed">{t("mt_importHint")}</p>
          </div>
        </header>
        <div className="card-body flex flex-col gap-5">
          <Step number="1" title={t("mt_importStep1")}>
            <label className="flex cursor-pointer items-center gap-3 rounded border border-dashed border-line-strong bg-subtle px-4 py-5 hover:border-brand-500">
              <FileSpreadsheet size={24} className="shrink-0 text-brand-500" />
              <span className="min-w-0 text-sm">
                <b className="block truncate font-semibold text-ink">{file ? file.name : "xlsx · xls · csv"}</b>
                {file && <small className="text-xs text-muted">{(file.size / 1024).toFixed(0)} KB</small>}
              </span>
              <input type="file" className="sr-only" accept=".xlsx,.xls,.csv" onChange={(event) => change(setFile)(event.target.files?.[0] ?? null)} />
            </label>
          </Step>

          <Step number="2" title={t("mt_importStep2")}>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <label className="field mb-0">
                <span className="label">{t("mt_category")}</span>
                <select className="select" value={categoryId} onChange={(event) => change(setCategoryId)(event.target.value)}>
                  <option value="">{t("mt_none")}</option>
                  {catalog.categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
                </select>
                <span className="hint">{t("mt_importCategoryHint")}</span>
              </label>
              <label className="field mb-0">
                <span className="label">{t("mt_importExisting")}</span>
                <select className="select" value={existing} onChange={(event) => change(setExisting)(event.target.value)}>
                  <option value="skip">{t("mt_importExistingSkip")}</option>
                  <option value="add">{t("mt_importExistingAdd")}</option>
                </select>
              </label>
            </div>
          </Step>

          <Step number="3" title={t("mt_importStep3")}>
            <div className="flex flex-wrap items-center gap-2">
              <button className="btn btn-secondary" disabled={!file || !!busy} onClick={runPreview}>
                <Eye size={16} />
                {busy === "preview" ? t("mt_previewing") : t("mt_preview")}
              </button>
              <button className="btn btn-primary" disabled={!preview || !!busy || !(summary.create + summary.merge)} onClick={commit}>
                <Upload size={16} />
                {busy === "commit" ? t("mt_committing") : t("mt_commit")}
              </button>
            </div>

            {preview && (
              <>
                <p className="rounded border border-brand-200 bg-brand-50 px-3 py-2 text-xs font-semibold text-brand-600">
                  {t("mt_previewSummary", {
                    total: summary.total,
                    create: summary.create,
                    merge: summary.merge,
                    skip: summary.skip,
                    units: formatNumber(summary.units),
                    value: formatNumber(summary.value),
                  })}
                </p>
                <div className="table-wrap max-h-[460px] overflow-y-auto">
                  <table className="table min-w-[760px] text-xs">
                    <thead className="sticky top-0 bg-subtle">
                      <tr className="text-muted">
                        <th className="p-2 text-start font-semibold">{t("mt_colRow")}</th>
                        <th className="p-2 text-start font-semibold">{t("mt_name")}</th>
                        <th className="p-2 text-start font-semibold">{t("mt_partNumber")}</th>
                        <th className="p-2 text-start font-semibold">{t("mt_type")} / {t("mt_brand")}</th>
                        <th className="p-2 text-start font-semibold">{t("mt_device")}</th>
                        <th className="p-2 text-end font-semibold">{t("mt_quantity")}</th>
                        <th className="p-2 text-end font-semibold">{t("mt_unitPrice")}</th>
                        <th className="p-2 text-start font-semibold" />
                      </tr>
                    </thead>
                    <tbody>
                      {preview.rows.map((row) => (
                        <tr key={`${row.sheet}-${row.row}`} className={`border-t border-line ${row.action === "skip" ? "opacity-55" : ""}`}>
                          <td className="p-2 text-muted">{row.row}</td>
                          <td className="p-2 font-semibold text-ink">
                            <span className="flex items-center gap-1">
                              {row.name}
                              {row.has_image && <Image size={12} className="text-muted" aria-label={t("mt_hasImage")} />}
                            </span>
                          </td>
                          <td className="p-2 [direction:ltr] text-start">{row.part_number || "—"}</td>
                          <td className="p-2">{[row.type, row.brand].filter(Boolean).join(" · ") || "—"}</td>
                          <td className="p-2">{row.device || "—"}</td>
                          <td className="p-2 text-end">{formatNumber(Object.values(row.quantities).reduce((a, b) => a + b, 0))} <span className="text-muted">{row.unit || ""}</span></td>
                          <td className="p-2 text-end">{row.unit_price != null ? formatMoney(row.unit_price) : "—"}</td>
                          <td className="p-2">
                            <span className={`badge ${ACTION_BADGES[row.action]}`}>
                              {t(`mt_action_${row.action}`)}{row.existing_code ? ` ${row.existing_code}` : ""}
                            </span>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </>
            )}
          </Step>
        </div>
      </section>

      <section className="card flex min-w-0 flex-col">
        <header className="card-head">
          <div className="min-w-0">
            <h2 className="section-title">{t("mt_importHistory")}</h2>
            <p className="page-subtitle mt-0.5 text-xs">{t("mt_importHistoryHint")}</p>
          </div>
        </header>
        {history.length ? (
          <ul className="flex flex-col divide-y divide-line">
            {history.map((entry) => (
              <li key={entry.id} className="flex items-start gap-2 px-4 py-3">
                <FileSpreadsheet size={18} className="mt-0.5 shrink-0 text-brand-500" />
                <div className="min-w-0 flex-1">
                  <b className="block truncate text-sm font-semibold text-ink" title={entry.file_name}>{entry.file_name}</b>
                  <small className="block text-xs text-muted">
                    {t("mt_importCounts", { created: entry.created_count, merged: entry.merged_count, skipped: entry.skipped_count })}
                  </small>
                  <small className="block text-[11px] text-muted">
                    {[entry.category?.name, entry.actor?.name, formatDateTime(entry.created_at)].filter(Boolean).join(" · ")}
                  </small>
                </div>
                <button
                  className="btn-icon shrink-0"
                  title={t("mt_download")}
                  onClick={async () => {
                    try {
                      downloadBlob(await api.downloadMaintenanceImport(entry.id), entry.file_name);
                    } catch (error) {
                      notify?.(error.message, "error");
                    }
                  }}
                >
                  <Download size={15} />
                </button>
                <button
                  className="btn-icon shrink-0 text-danger-500"
                  title={t("mt_deleteImport")}
                  onClick={async () => {
                    if (!window.confirm(t("mt_confirmDeleteImport", { name: entry.file_name }))) return;
                    try {
                      await api.deleteMaintenanceImport(entry.id);
                      notify?.(t("mt_importDeleted"), "success");
                      loadHistory();
                    } catch (error) {
                      notify?.(error.message, "error");
                    }
                  }}
                >
                  <Trash2 size={15} />
                </button>
              </li>
            ))}
          </ul>
        ) : (
          <p className="empty-state m-4">{t("mt_noImports")}</p>
        )}
      </section>
    </div>
  );
}
