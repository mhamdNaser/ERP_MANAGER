import { ArrowLeftRight, FileSpreadsheet, FileText, ImageOff, PackagePlus, PackageMinus, Pencil, Trash2, TriangleAlert, Wrench } from "lucide-react";
import { useMemo, useState } from "react";
import { ListSearch } from "../../../Components/ListSearch";
import { useLanguage } from "../../../Provider/LanguageContext";
import { api, downloadBlob } from "../../../lib";
import { STATUSES, emptyFilters, formatMoney, formatNumber } from "../maintenanceMeta";
import { printMaintenancePdf } from "../maintenanceExport";
import { StatusBar, StatusLegend } from "./StatusParts";

/** سجل القطع: المرشحات في سطر واحد فوق الجدول، والتصدير يتبع المرشحات نفسها. */
export function ItemsTable({ board, loading, filters, setFilters, canManage, notify, onOpen, onEdit, onMove, onDelete }) {
  const { t } = useLanguage();
  const [exporting, setExporting] = useState(false);
  const { items, catalog } = board;
  const types = useMemo(
    () => catalog.categories.find((category) => String(category.id) === String(filters.category_id))?.types || [],
    [catalog.categories, filters.category_id],
  );
  const filtered = Object.entries(filters).some(([key, value]) => value !== emptyFilters[key]);
  const set = (patch) => setFilters({ ...filters, ...patch });

  const exportExcel = async () => {
    setExporting(true);
    try {
      downloadBlob(await api.exportMaintenanceExcel(filters), `maintenance-${new Date().toISOString().slice(0, 10)}.xlsx`);
    } catch (error) {
      notify?.(error.message, "error");
    } finally {
      setExporting(false);
    }
  };

  return (
    <section className="flex flex-col gap-3">
      <div className="flex flex-wrap items-center gap-2">
        <ListSearch value={filters.search} onChange={(search) => set({ search })} placeholder={t("mt_search")} />
        <select className="select w-auto text-xs" value={filters.category_id} onChange={(event) => set({ category_id: event.target.value, type_id: "" })}>
          <option value="">{t("mt_allCategories")}</option>
          {catalog.categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
        </select>
        {types.length > 0 && (
          <select className="select w-auto text-xs" value={filters.type_id} onChange={(event) => set({ type_id: event.target.value })}>
            <option value="">{t("mt_allTypes")}</option>
            {types.map((type) => <option key={type.id} value={type.id}>{type.name}</option>)}
          </select>
        )}
        <select className="select w-auto text-xs" value={filters.brand_id} onChange={(event) => set({ brand_id: event.target.value })}>
          <option value="">{t("mt_allBrands")}</option>
          {catalog.brands.map((brand) => <option key={brand.id} value={brand.id}>{brand.name}</option>)}
        </select>
        <select className="select w-auto text-xs" value={filters.status} onChange={(event) => set({ status: event.target.value })}>
          <option value="">{t("mt_allStatuses")}</option>
          {STATUSES.map((status) => <option key={status.key} value={status.key}>{t(`mt_status_${status.key}`)}</option>)}
        </select>
        <label className="flex h-9 items-center gap-1.5 rounded border border-line bg-surface px-2.5 text-xs font-semibold text-ink">
          <input type="checkbox" checked={filters.low_stock} onChange={(event) => set({ low_stock: event.target.checked })} />
          {t("mt_lowStockOnly")}
        </label>
        {filtered && (
          <button className="btn btn-ghost btn-sm" onClick={() => setFilters(emptyFilters)}>{t("mt_clearFilters")}</button>
        )}
        <span className="ms-auto flex items-center gap-2">
          <span className="text-xs text-muted">{t("mt_results", { count: items.length })}</span>
          <button className="btn btn-secondary btn-sm" disabled={exporting} onClick={exportExcel}>
            <FileSpreadsheet size={14} />
            {exporting ? t("mt_exporting") : t("mt_exportExcel")}
          </button>
          <button className="btn btn-secondary btn-sm" disabled={!items.length} onClick={() => printMaintenancePdf(items, filters, catalog, t)}>
            <FileText size={14} />
            {t("mt_exportPdf")}
          </button>
        </span>
      </div>

      <StatusLegend />

      {items.length ? (
        <div className="table-wrap">
          <table className="table min-w-[900px]">
            <thead>
              <tr className="bg-subtle text-start text-xs text-muted">
                <th className="p-2.5 text-start font-semibold">{t("mt_colItem")}</th>
                <th className="p-2.5 text-start font-semibold">{t("mt_colCategory")}</th>
                <th className="p-2.5 text-start font-semibold">{t("mt_colDevice")}</th>
                <th className="w-56 p-2.5 text-start font-semibold">{t("mt_colDistribution")}</th>
                <th className="p-2.5 text-end font-semibold">{t("mt_colTotal")}</th>
                <th className="p-2.5 text-end font-semibold">{t("mt_colValue")}</th>
                {canManage && <th className="p-2.5" />}
              </tr>
            </thead>
            <tbody>
              {items.map((item) => (
                <tr key={item.id} className="cursor-pointer border-t border-line align-middle hover:bg-subtle" onClick={() => onOpen(item)}>
                  <td className="p-2.5">
                    <div className="flex items-center gap-2.5">
                      {item.image_url ? (
                        <img src={item.image_url} alt="" className="h-9 w-9 shrink-0 rounded border border-line object-cover" />
                      ) : (
                        <span className="grid h-9 w-9 shrink-0 place-items-center rounded border border-line bg-canvas text-muted"><ImageOff size={14} /></span>
                      )}
                      <div className="min-w-0">
                        <b className="block truncate font-semibold text-ink">{item.name}</b>
                        <small className="flex flex-wrap items-center gap-x-2 text-xs text-muted">
                          <span className="[direction:ltr]">{item.code}</span>
                          {item.part_number && <span className="[direction:ltr]">{item.part_number}</span>}
                          {item.brand && <span>· {item.brand.name}</span>}
                        </small>
                      </div>
                    </div>
                  </td>
                  <td className="p-2.5 text-xs">
                    <span className="block text-ink">{item.category?.name || "—"}</span>
                    {item.type && <span className="text-muted">{item.type.name}</span>}
                  </td>
                  <td className="p-2.5 text-xs text-ink">{item.device || "—"}</td>
                  <td className="p-2.5"><StatusBar quantities={item.quantities} /></td>
                  <td className="p-2.5 text-end">
                    <b className="font-semibold text-ink">{formatNumber(item.total_quantity)}</b>
                    <small className="ms-1 text-xs text-muted">{item.unit}</small>
                    {item.is_low_stock && (
                      <span className="badge badge-danger ms-1.5"><TriangleAlert size={11} />{t("mt_lowStock")}</span>
                    )}
                  </td>
                  <td className="p-2.5 text-end text-xs text-ink">
                    {item.unit_price != null ? formatMoney(item.total_quantity * item.unit_price) : "—"}
                  </td>
                  {canManage && (
                    <td className="p-2.5" onClick={(event) => event.stopPropagation()}>
                      <div className="flex items-center justify-end gap-0.5">
                        <button className="btn-icon" title={t("mt_move")} onClick={() => onMove(item, "move")}><ArrowLeftRight size={15} /></button>
                        <button className="btn-icon" title={t("mt_receive")} onClick={() => onMove(item, "receive")}><PackagePlus size={15} /></button>
                        <button className="btn-icon" title={t("mt_issue")} onClick={() => onMove(item, "issue")}><PackageMinus size={15} /></button>
                        <button className="btn-icon" title={t("mt_editItem")} onClick={() => onEdit(item)}><Pencil size={15} /></button>
                        <button className="btn-icon text-danger-500" title={t("mt_delete")} onClick={() => onDelete(item)}><Trash2 size={15} /></button>
                      </div>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        !loading && (
          <div className="empty-state min-h-[220px]">
            <Wrench size={28} className="text-muted" />
            <b className="text-sm font-semibold text-ink">{filtered ? t("mt_noMatch") : t("mt_noItems")}</b>
            {!filtered && <p>{t("mt_noItemsHint")}</p>}
          </div>
        )
      )}
    </section>
  );
}
