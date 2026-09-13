import { CalendarCheck, Plus } from "lucide-react";
import { useCallback, useEffect, useState } from "react";
import { useLanguage } from "../../Provider/LanguageContext";
import { api } from "../../lib";
import { HrRequestCard } from "../Hr/components/HrRequestCard";
import { HrRequestModal } from "../Hr/components/HrRequestModal";

/** تبويب «طلباتي»: يقدّم الموظف طلبه ويتابعه، ويبتّ الرؤساء فيما ينتظر قرارهم. */
export function HrMyRequestsPage({ user, notify }) {
  const { t } = useLanguage();
  const [data, setData] = useState({ requests: [], balance: null });
  const [creating, setCreating] = useState(false);

  const load = useCallback(
    () => api.myHrRequests().then(setData).catch((error) => notify?.(error.message, "error")),
    [notify],
  );
  useEffect(() => { load(); }, [load]);

  const cancel = async (item) => {
    try {
      await api.cancelHrRequest(item.id);
      notify?.(t("hr_cancelledToast"), "success");
      load();
    } catch (error) {
      notify?.(error.message, "error");
    }
  };

  const balance = data.balance;

  return (
    <div className="page">
      <div className="page-header">
        <div className="min-w-0">
          <p className="eyebrow">{t("hr_eyebrow")}</p>
          <h1 className="page-title mt-1">{t("navMyHrRequests")}</h1>
          <span className="page-subtitle mt-1 block">{t("hr_myRequestsIntro")}</span>
        </div>
        <button className="btn btn-primary" onClick={() => setCreating(true)}>
          <Plus size={16} />
          {t("hr_newRequest")}
        </button>
      </div>

      {balance && (
        <section className="grid grid-cols-1 gap-3 sm:grid-cols-3">
          {[
            ["hr_entitlement", balance.annual_entitlement],
            ["hr_usedDays", balance.used_days],
            ["hr_remainingDays", balance.remaining_days],
          ].map(([key, value]) => (
            <p key={key} className="flex flex-col gap-1 rounded border border-line bg-surface p-4">
              <small className="text-xs font-semibold text-muted">{t(key)}</small>
              <b className="text-lg font-semibold text-ink">{value}</b>
            </p>
          ))}
        </section>
      )}

      <section className="flex flex-col gap-3">
        <h3 className="section-title">{t("hr_myRequests")}</h3>
        {data.requests?.length ? (
          <div className="grid grid-cols-1 gap-3 xl:grid-cols-2">
            {data.requests.map((item) => (
              <HrRequestCard key={item.id} item={item} showOwner={false} onCancel={cancel} />
            ))}
          </div>
        ) : (
          <div className="empty-state min-h-[220px]">
            <CalendarCheck size={28} className="text-muted" />
            <b className="text-sm font-semibold text-ink">{t("hr_noRequests")}</b>
            <p>{t("hr_noRequestsHint")}</p>
          </div>
        )}
      </section>

      {creating && (
        <HrRequestModal
          employees={user ? [user] : null}
          defaultUserId={user?.id || ""}
          lockEmployee
          close={() => setCreating(false)}
          done={() => { setCreating(false); load(); }}
          notify={notify}
        />
      )}
    </div>
  );
}
