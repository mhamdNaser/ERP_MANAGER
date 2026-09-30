import { useLanguage } from "../../../Provider/LanguageContext";
import { STATUSES, formatNumber, statusOf } from "../maintenanceMeta";

/** شارة الحالة: أيقونة واسم، واللون علامة إضافية لا الوحيدة. */
export function StatusBadge({ status, count }) {
  const { t } = useLanguage();
  const meta = statusOf(status);
  if (!meta) return null;
  const Icon = meta.icon;

  return (
    <span className="inline-flex items-center gap-1 rounded border border-line bg-surface px-1.5 py-0.5 text-[11px] font-semibold text-ink">
      <Icon size={12} style={{ color: meta.color }} aria-hidden />
      {t(`mt_status_${status}`)}
      {count != null && <b className="font-semibold text-muted">{formatNumber(count)}</b>}
    </span>
  );
}

/**
 * شريط مكدّس يوزّع كمية الصنف على حالاته، بفاصل 2px بين المقاطع.
 * الرقم مكتوب بجانبه ويظهر تفصيله عند المرور، فلا يُقرأ الطول وحده.
 */
export function StatusBar({ quantities, height = "h-2" }) {
  const { t } = useLanguage();
  const total = STATUSES.reduce((sum, status) => sum + (quantities?.[status.key] || 0), 0);
  const detail = STATUSES.filter((status) => quantities?.[status.key])
    .map((status) => `${t(`mt_status_${status.key}`)}: ${formatNumber(quantities[status.key])}`)
    .join(" · ");

  if (!total) {
    return <span className={`block ${height} w-full rounded bg-canvas`} title="0" />;
  }

  return (
    <span className={`flex ${height} w-full gap-[2px] overflow-hidden rounded`} title={detail} role="img" aria-label={detail}>
      {STATUSES.filter((status) => quantities?.[status.key]).map((status) => (
        <i
          key={status.key}
          className="block h-full first:rounded-s last:rounded-e"
          style={{ width: `${(quantities[status.key] / total) * 100}%`, minWidth: 3, background: status.color }}
        />
      ))}
    </span>
  );
}

/** دليل الألوان: يظهر دائماً مع المخططات المتعددة السلاسل. */
export function StatusLegend({ values }) {
  const { t } = useLanguage();

  return (
    <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
      {STATUSES.map((status) => {
        const Icon = status.icon;
        return (
          <span key={status.key} className="flex items-center gap-1.5 text-xs text-muted">
            <i className="h-2.5 w-2.5 rounded-[2px]" style={{ background: status.color }} />
            <Icon size={12} aria-hidden />
            {t(`mt_status_${status.key}`)}
            {values && <b className="font-semibold text-ink">{formatNumber(values[status.key])}</b>}
          </span>
        );
      })}
    </div>
  );
}
