import { Plus, Users } from "lucide-react";
import { useCallback, useEffect, useState } from "react";
import { useLanguage } from "../../Provider/LanguageContext";
import { api } from "../../lib";
import { HrBalancesPanel } from "./components/HrBalancesPanel";
import { HrDocumentModal } from "./components/HrDocumentModal";
import { HrRequestCard } from "./components/HrRequestCard";
import { HrRequestModal } from "./components/HrRequestModal";
import { hrStatusLabelKeys, hrTypeLabelKeys } from "./hrUtils";

const SUMMARY = [
  ["total", "hr_summaryTotal"],
  ["pending", "hr_summaryPending"],
  ["awaiting_hr", "hr_summaryAwaitingHr"],
  ["approved", "hr_summaryApproved"],
  ["rejected", "hr_summaryRejected"],
];

/** لوحة قسم الموارد البشرية: كل الطلبات وقرارات مرحلة الموارد البشرية والأرصدة. */
export function HrPage({ notify }) {
  const { t } = useLanguage();
  const [data, setData] = useState({ requests: [], summary: {}, balances: [], employees: [], is_hr_staff: false });
  const [filters, setFilters] = useState({ type: "", status: "" });
  const [creating, setCreating] = useState(false);
  const [tab, setTab] = useState("requests");
  const [document, setDocument] = useState(null);

  const load = useCallback(
    () => api.hrRequests(filters).then(setData).catch((error) => notify?.(error.message, "error")),
    [filters, notify],
  );
  useEffect(() => { load(); }, [load]);
  const isHrStaff = data.is_hr_staff === true;

  const decide = async (item, action, note) => {
    try {
      await api.decideHrRequest(item.id, { action, note });
      notify?.(t(action === "approve" ? "hr_approvedToast" : "hr_rejectedToast"), "success");
      load();
    } catch (error) {
      notify?.(error.message, "error");
    }
  };

  return (
    <div className="page">
      <div className="page-header">
        <div className="min-w-0">
          <p className="eyebrow">{t("hr_eyebrow")}</p>
          <h1 className="page-title mt-1">{t("navHr")}</h1>
          <span className="page-subtitle mt-1 block">{t(isHrStaff ? "hr_deskIntro" : "hr_gmDeskIntro")}</span>
        </div>
        {isHrStaff && (
          <button className="btn btn-primary" onClick={() => setCreating(true)}>
            <Plus size={16} />
            {t("hr_recordForEmployee")}
          </button>
        )}
      </div>

      <section className="grid grid-cols-2 gap-3 lg:grid-cols-5">
        {SUMMARY.map(([key, label]) => (
          <p key={key} className="flex flex-col gap-1 rounded border border-line bg-surface p-4">
            <small className="text-xs font-semibold text-muted">{t(label)}</small>
            <b className="text-lg font-semibold text-ink">{data.summary?.[key] ?? 0}</b>
          </p>
        ))}
      </section>

      <div className="flex items-center gap-2 border-b border-line">
        {(isHrStaff ? ["requests", "balances"] : ["requests"]).map((key) => (
          <button
            key={key}
            type="button"
            className={`px-3 py-2 text-[13px] font-semibold ${tab === key ? "border-b-2 border-brand-500 text-ink" : "text-muted"}`}
            onClick={() => setTab(key)}
          >
            {t(key === "requests" ? "hr_tabRequests" : "hr_tabBalances")}
          </button>
        ))}
      </div>

      {tab === "requests" ? (
        <section className="flex flex-col gap-3">
          <div className="flex flex-wrap items-center gap-2">
            <select className="select w-auto" value={filters.type} onChange={(event) => setFilters({ ...filters, type: event.target.value })}>
              <option value="">{t("hr_allTypes")}</option>
              {Object.entries(hrTypeLabelKeys).map(([value, key]) => (
                <option key={value} value={value}>{t(key)}</option>
              ))}
            </select>
            <select className="select w-auto" value={filters.status} onChange={(event) => setFilters({ ...filters, status: event.target.value })}>
              <option value="">{t("hr_allStatuses")}</option>
              {Object.entries(hrStatusLabelKeys).map(([value, key]) => (
                <option key={value} value={value}>{t(key)}</option>
              ))}
            </select>
            <span className="ms-auto text-xs text-muted">{data.requests?.length || 0}</span>
          </div>

          {data.requests?.length ? (
            <div className="grid grid-cols-1 gap-3 xl:grid-cols-2">
              {data.requests.map((item) => (
                <HrRequestCard
                  key={item.id}
                  item={item}
                  canDecide={item.status === (isHrStaff ? "pending_hr" : "pending_gm")}
                  onDecide={decide}
                  onOpenDocument={setDocument}
                />
              ))}
            </div>
          ) : (
            <div className="empty-state min-h-[220px]">
              <Users size={28} className="text-muted" />
              <b className="text-sm font-semibold text-ink">{t("hr_noRequests")}</b>
              <p>{t("hr_noRequestsHint")}</p>
            </div>
          )}
        </section>
      ) : (
        <HrBalancesPanel balances={data.balances} notify={notify} reload={load} />
      )}

      {document && <HrDocumentModal item={document} close={() => setDocument(null)} />}

      {creating && (
        <HrRequestModal
          employees={data.employees}
          close={() => setCreating(false)}
          done={() => { setCreating(false); load(); }}
          notify={notify}
        />
      )}
    </div>
  );
}
