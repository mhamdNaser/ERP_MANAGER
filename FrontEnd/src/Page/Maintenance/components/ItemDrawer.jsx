import { ArrowLeftRight, PackageMinus, PackagePlus, Pencil, Trash2, TriangleAlert, X } from "lucide-react";
import { useEffect, useState } from "react";
import { useLanguage } from "../../../Provider/LanguageContext";
import { api } from "../../../lib";
import { STATUSES, formatDateTime, formatMoney, formatNumber } from "../maintenanceMeta";
import { MovementLine } from "./MovementLine";
import { StatusBar } from "./StatusParts";

function Fact({ label, value, ltr = false }) {
  return (
    <div className="min-w-0">
      <dt className="text-[11px] font-semibold text-muted">{label}</dt>
      <dd className={`truncate text-sm text-ink ${ltr ? "[direction:ltr] text-end rtl:text-right" : ""}`}>{value || "—"}</dd>
    </div>
  );
}

/** درج الصنف: كل ما يُعرف عنه، وكمياته في كل حالة، وسجل حركاته. */
export function ItemDrawer({ id, revision, canManage, notify, close, onEdit, onMove, onDelete }) {
  const { t } = useLanguage();
  const [item, setItem] = useState(null);

  useEffect(() => {
    let alive = true;
    api.maintenanceItem(id).then((data) => alive && setItem(data)).catch((error) => notify?.(error.message, "error"));
    return () => { alive = false; };
  }, [id, revision, notify]);

  useEffect(() => {
    const onKey = (event) => event.key === "Escape" && close();
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [close]);

  return (
    <div className="fixed inset-0 z-50 bg-brand-900/40" onClick={close}>
      <aside className="drawer" onClick={(event) => event.stopPropagation()}>
        <header className="flex items-start justify-between gap-3 border-b border-line px-4 py-3">
          <div className="min-w-0">
            <span className="eyebrow [direction:ltr]">{item?.code}</span>
            <h2 className="my-1 text-base font-semibold text-ink">{item?.name || "…"}</h2>
            {item?.part_number && <p className="text-xs text-muted [direction:ltr]">{item.part_number}</p>}
          </div>
          <button className="btn-icon shrink-0" onClick={close} aria-label={t("close")}><X size={16} /></button>
        </header>

        {item && (
          <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-4">
            {canManage && (
              <div className="flex flex-wrap gap-1.5">
                <button className="btn btn-primary btn-sm" onClick={() => onMove(item, "move")}><ArrowLeftRight size={14} />{t("mt_move")}</button>
                <button className="btn btn-secondary btn-sm" onClick={() => onMove(item, "receive")}><PackagePlus size={14} />{t("mt_receive")}</button>
                <button className="btn btn-secondary btn-sm" onClick={() => onMove(item, "issue")}><PackageMinus size={14} />{t("mt_issue")}</button>
                <button className="btn btn-ghost btn-sm" onClick={() => onEdit(item)}><Pencil size={14} />{t("mt_editItem")}</button>
                <button className="btn btn-ghost btn-sm text-danger-500" onClick={() => onDelete(item)}><Trash2 size={14} />{t("mt_delete")}</button>
              </div>
            )}

            <div className="flex gap-4">
              {item.image_url && (
                <a href={item.image_url} target="_blank" rel="noreferrer" className="shrink-0">
                  <img src={item.image_url} alt={item.name} className="h-28 w-28 rounded border border-line object-contain bg-canvas" />
                </a>
              )}
              <dl className="grid min-w-0 flex-1 grid-cols-2 gap-x-4 gap-y-2.5">
                <Fact label={t("mt_category")} value={item.category?.name} />
                <Fact label={t("mt_type")} value={item.type?.name} />
                <Fact label={t("mt_brand")} value={item.brand?.name} />
                <Fact label={t("mt_device")} value={item.device} />
                <Fact label={t("mt_unitPrice")} value={item.unit_price != null ? formatMoney(item.unit_price) : null} />
                <Fact label={t("mt_location")} value={item.location} />
              </dl>
            </div>

            <section className="flex flex-col gap-2 rounded border border-line p-3">
              <header className="flex items-center justify-between gap-2">
                <b className="text-sm font-semibold text-ink">
                  {formatNumber(item.total_quantity)} {item.unit}
                  {item.unit_price != null && <small className="ms-2 text-xs font-normal text-muted">{formatMoney(item.total_quantity * item.unit_price)}</small>}
                </b>
                {item.is_low_stock && (
                  <span className="badge badge-danger"><TriangleAlert size={11} />{t("mt_lowStock")} · {t("mt_minQuantity")} {item.min_quantity}</span>
                )}
              </header>
              <StatusBar quantities={item.quantities} height="h-3" />
              <ul className="grid grid-cols-2 gap-1.5 sm:grid-cols-3">
                {STATUSES.map((status) => {
                  const Icon = status.icon;
                  return (
                    <li key={status.key} className="flex items-center gap-1.5 text-xs" title={t(`mt_statusHint_${status.key}`)}>
                      <Icon size={13} style={{ color: status.color }} aria-hidden />
                      <span className="text-muted">{t(`mt_status_${status.key}`)}</span>
                      <b className="ms-auto font-semibold text-ink">{formatNumber(item.quantities?.[status.key])}</b>
                    </li>
                  );
                })}
              </ul>
            </section>

            {item.notes && <p className="whitespace-pre-line rounded bg-canvas p-3 text-sm text-ink">{item.notes}</p>}
            {item.created_by && (
              <p className="text-xs text-muted">{t("mt_createdBy", { name: item.created_by.name })} · {formatDateTime(item.created_at)}</p>
            )}

            <section className="flex flex-col gap-2">
              <h3 className="section-title">{t("mt_history")}</h3>
              {item.movements?.length ? (
                <ol className="flex flex-col divide-y divide-line rounded border border-line">
                  {item.movements.map((movement) => <MovementLine key={movement.id} movement={movement} unit={item.unit} />)}
                </ol>
              ) : (
                <p className="empty-state py-6">{t("mt_noHistory")}</p>
              )}
            </section>
          </div>
        )}
      </aside>
    </div>
  );
}
