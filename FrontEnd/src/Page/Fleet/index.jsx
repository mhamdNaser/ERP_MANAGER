import { Plus, Truck } from "lucide-react";
import { useCallback, useEffect, useMemo, useState } from "react";
import { useLanguage } from "../../Provider/LanguageContext";
import { api } from "../../lib";
import { FleetDocumentModal } from "./components/FleetDocumentModal";
import { FleetMissionCard } from "./components/FleetMissionCard";
import { FleetMissionModal } from "./components/FleetMissionModal";
import { fleetStatusLabelKeys } from "./fleetUtils";

const SUMMARY = [
  ["total", "fleet_summaryTotal"],
  ["pending", "fleet_summaryPending"],
  ["awaiting_fleet", "fleet_summaryAwaitingFleet"],
  ["approved", "fleet_summaryApproved"],
  ["rejected", "fleet_summaryRejected"],
];

function EmptyMissions() {
  const { t } = useLanguage();

  return (
    <div className="empty-state min-h-[220px]">
      <Truck size={28} className="text-muted" />
      <b className="text-sm font-semibold text-ink">{t("fleet_noMissions")}</b>
      <p>{t("fleet_noMissionsHint")}</p>
    </div>
  );
}

/**
 * تبويب الآليات: لوحة الفرع لمن يملك الاطلاع، ومهام الموظف الشخصية للجميع.
 * مهمة العمل أول ما استلمه الفرع من الموارد البشرية.
 */
export function FleetPage({ user, notify }) {
  const { t } = useLanguage();
  const canViewDesk = user?.permissions?.includes("fleet.view") === true;
  const [desk, setDesk] = useState({ missions: [], summary: {}, employees: [], is_fleet_staff: false });
  const [mine, setMine] = useState({ missions: [] });
  const [status, setStatus] = useState("");
  const [creating, setCreating] = useState(null);
  const [tab, setTab] = useState(canViewDesk ? "missions" : "mine");
  const [document, setDocument] = useState(null);

  const loadDesk = useCallback(() => {
    if (!canViewDesk) return Promise.resolve();
    return api.fleetMissions({ status }).then(setDesk).catch((error) => notify?.(error.message, "error"));
  }, [canViewDesk, notify, status]);
  const loadMine = useCallback(
    () => api.myFleetMissions().then(setMine).catch((error) => notify?.(error.message, "error")),
    [notify],
  );

  useEffect(() => { loadDesk(); }, [loadDesk]);
  useEffect(() => { loadMine(); }, [loadMine]);

  const isFleetStaff = desk.is_fleet_staff === true;
  const tabs = useMemo(() => (canViewDesk ? ["missions", "mine"] : ["mine"]), [canViewDesk]);

  const decide = async (item, action, note) => {
    try {
      await api.decideFleetMission(item.id, { action, note });
      notify?.(t(action === "approve" ? "fleet_approvedToast" : "fleet_rejectedToast"), "success");
      loadDesk();
      loadMine();
    } catch (error) {
      notify?.(error.message, "error");
    }
  };

  const cancel = async (item) => {
    try {
      await api.cancelFleetMission(item.id);
      notify?.(t("fleet_cancelledToast"), "success");
      loadDesk();
      loadMine();
    } catch (error) {
      notify?.(error.message, "error");
    }
  };

  const closeCreating = () => setCreating(null);
  const createdMission = () => {
    closeCreating();
    loadDesk();
    loadMine();
  };

  return (
    <div className="page">
      <div className="page-header">
        <div className="min-w-0">
          <p className="eyebrow">{t("fleet_eyebrow")}</p>
          <h1 className="page-title mt-1">{t("navFleet")}</h1>
          <span className="page-subtitle mt-1 block">
            {t(canViewDesk ? (isFleetStaff ? "fleet_deskIntro" : "fleet_gmDeskIntro") : "fleet_myMissionsIntro")}
          </span>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {isFleetStaff && (
            <button className="btn btn-secondary" onClick={() => setCreating("employee")}>
              <Plus size={16} />
              {t("fleet_recordForEmployee")}
            </button>
          )}
          <button className="btn btn-primary" onClick={() => setCreating("self")}>
            <Plus size={16} />
            {t("fleet_newMission")}
          </button>
        </div>
      </div>

      {canViewDesk && (
        <section className="grid grid-cols-2 gap-3 lg:grid-cols-5">
          {SUMMARY.map(([key, label]) => (
            <p key={key} className="flex flex-col gap-1 rounded border border-line bg-surface p-4">
              <small className="text-xs font-semibold text-muted">{t(label)}</small>
              <b className="text-lg font-semibold text-ink">{desk.summary?.[key] ?? 0}</b>
            </p>
          ))}
        </section>
      )}

      {tabs.length > 1 && (
        <div className="flex items-center gap-2 border-b border-line">
          {tabs.map((key) => (
            <button
              key={key}
              type="button"
              className={`px-3 py-2 text-[13px] font-semibold ${tab === key ? "border-b-2 border-brand-500 text-ink" : "text-muted"}`}
              onClick={() => setTab(key)}
            >
              {t(key === "missions" ? "fleet_tabMissions" : "fleet_tabMine")}
            </button>
          ))}
        </div>
      )}

      {tab === "missions" ? (
        <section className="flex flex-col gap-3">
          <div className="flex flex-wrap items-center gap-2">
            <select className="select w-auto" value={status} onChange={(event) => setStatus(event.target.value)}>
              <option value="">{t("fleet_allStatuses")}</option>
              {Object.entries(fleetStatusLabelKeys).map(([value, key]) => (
                <option key={value} value={value}>{t(key)}</option>
              ))}
            </select>
            <span className="ms-auto text-xs text-muted">{desk.missions?.length || 0}</span>
          </div>

          {desk.missions?.length ? (
            <div className="grid grid-cols-1 gap-3 xl:grid-cols-2">
              {desk.missions.map((item) => (
                <FleetMissionCard
                  key={item.id}
                  item={item}
                  canDecide={item.status === (isFleetStaff ? "pending_fleet" : "pending_gm")}
                  onDecide={decide}
                  onOpenDocument={setDocument}
                />
              ))}
            </div>
          ) : (
            <EmptyMissions />
          )}
        </section>
      ) : (
        <section className="flex flex-col gap-3">
          {mine.missions?.length ? (
            <div className="grid grid-cols-1 gap-3 xl:grid-cols-2">
              {mine.missions.map((item) => (
                <FleetMissionCard
                  key={item.id}
                  item={item}
                  showOwner={false}
                  onCancel={cancel}
                  onOpenDocument={setDocument}
                />
              ))}
            </div>
          ) : (
            <EmptyMissions />
          )}
        </section>
      )}

      {document && <FleetDocumentModal item={document} close={() => setDocument(null)} />}

      {creating === "employee" && (
        <FleetMissionModal
          employees={desk.employees}
          close={closeCreating}
          done={createdMission}
          notify={notify}
        />
      )}

      {creating === "self" && (
        <FleetMissionModal
          employees={user ? [user] : null}
          defaultUserId={user?.id || ""}
          lockEmployee
          close={closeCreating}
          done={createdMission}
          notify={notify}
        />
      )}
    </div>
  );
}
