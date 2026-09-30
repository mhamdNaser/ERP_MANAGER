import { ArrowLeft } from "lucide-react";
import { useLanguage } from "../../../Provider/LanguageContext";
import { formatDateTime, formatNumber } from "../maintenanceMeta";
import { StatusBadge } from "./StatusParts";

/** سطر حركة: من ← إلى، العدد، من قام بها ومتى، وملاحظتها. */
export function MovementLine({ movement, unit, showItem = false }) {
  const { t } = useLanguage();
  const outside = (label) => <span className="rounded border border-dashed border-line px-1.5 py-0.5 text-[11px] text-muted">{t(label)}</span>;

  return (
    <li className="flex flex-col gap-1 px-3 py-2">
      <div className="flex flex-wrap items-center gap-1.5 text-xs">
        {showItem && movement.item && (
          <b className="me-1 font-semibold text-ink">
            {movement.item.name}
            {movement.item.part_number && <span className="ms-1 font-normal text-muted [direction:ltr]">{movement.item.part_number}</span>}
          </b>
        )}
        {movement.from_status ? <StatusBadge status={movement.from_status} /> : outside("mt_outside")}
        <ArrowLeft size={12} className="text-muted ltr:rotate-180" aria-hidden />
        {movement.to_status ? <StatusBadge status={movement.to_status} /> : outside("mt_outsideDept")}
        <b className="ms-auto font-semibold text-ink">{formatNumber(movement.quantity)} {unit || movement.item?.unit || ""}</b>
      </div>
      <small className="text-[11px] text-muted">
        {t(`mt_kind_${movement.kind}`)}
        {movement.actor && ` · ${t("mt_by", { name: movement.actor.name })}`}
        {` · ${formatDateTime(movement.created_at)}`}
      </small>
      {movement.note && <p className="text-xs text-ink">{movement.note}</p>}
    </li>
  );
}
