import { Save } from "lucide-react";
import { useState } from "react";
import { useLanguage } from "../../../Provider/LanguageContext";
import { api } from "../../../lib";

/** أرصدة الإجازات السنوية — قابلة للضبط من الموارد البشرية. */
export function HrBalancesPanel({ balances = [], notify, reload }) {
  const { t } = useLanguage();
  const [editing, setEditing] = useState(null);
  const [draft, setDraft] = useState({ annual_entitlement: "", used_days: "" });
  const [saving, setSaving] = useState(false);

  const start = (balance) => {
    setEditing(balance.id);
    setDraft({ annual_entitlement: balance.annual_entitlement, used_days: balance.used_days });
  };

  const save = async (balance) => {
    setSaving(true);
    try {
      await api.updateHrBalance(balance.user_id, draft);
      notify?.(t("hr_balanceSaved"), "success");
      setEditing(null);
      reload();
    } catch (error) {
      notify?.(error.message, "error");
    } finally {
      setSaving(false);
    }
  };

  return (
    <section className="card overflow-hidden">
      <div className="w-full overflow-x-auto">
        <table className="table">
          <thead>
            <tr>
              <th>{t("employee")}</th>
              <th>{t("hr_entitlement")}</th>
              <th>{t("hr_usedDays")}</th>
              <th>{t("hr_remainingDays")}</th>
              <th>{t("actions")}</th>
            </tr>
          </thead>
          <tbody>
            {balances.map((balance) => (
              <tr key={balance.id}>
                <td>
                  <b className="font-semibold text-ink">{balance.user?.name}</b>
                  {balance.user?.job_title && <small className="block text-xs text-muted">{balance.user.job_title}</small>}
                </td>
                <td>
                  {editing === balance.id ? (
                    <input
                      className="input w-24"
                      type="number"
                      min="0"
                      step="0.5"
                      value={draft.annual_entitlement}
                      onChange={(event) => setDraft({ ...draft, annual_entitlement: event.target.value })}
                    />
                  ) : (
                    <span className="text-muted">{balance.annual_entitlement}</span>
                  )}
                </td>
                <td>
                  {editing === balance.id ? (
                    <input
                      className="input w-24"
                      type="number"
                      min="0"
                      step="0.5"
                      value={draft.used_days}
                      onChange={(event) => setDraft({ ...draft, used_days: event.target.value })}
                    />
                  ) : (
                    <span className="text-muted">{balance.used_days}</span>
                  )}
                </td>
                <td><b className="text-ink">{balance.remaining_days}</b></td>
                <td>
                  {editing === balance.id ? (
                    <div className="flex items-center gap-2">
                      <button type="button" className="btn btn-secondary btn-sm" onClick={() => setEditing(null)}>{t("cancel")}</button>
                      <button type="button" className="btn btn-primary btn-sm" disabled={saving} onClick={() => save(balance)}>
                        <Save className="h-3.5 w-3.5" />
                        {t("hr_save")}
                      </button>
                    </div>
                  ) : (
                    <button type="button" className="btn btn-secondary btn-sm" onClick={() => start(balance)}>
                      {t("hr_adjustBalance")}
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {!balances.length && <p className="px-3 py-10 text-center text-[13px] text-muted">{t("hr_noBalances")}</p>}
    </section>
  );
}
