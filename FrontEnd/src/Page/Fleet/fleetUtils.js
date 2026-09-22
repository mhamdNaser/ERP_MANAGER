export const fleetTypeLabelKeys = {
  mission: "fleet_typeMission",
};

export const fleetStatusLabelKeys = {
  pending_fleet: "fleet_statusPendingFleet",
  pending_gm: "fleet_statusPendingGm",
  approved: "fleet_statusApproved",
  rejected: "fleet_statusRejected",
  cancelled: "fleet_statusCancelled",
};

export const fleetStageLabelKeys = {
  fleet: "fleet_stageFleet",
  gm: "fleet_stageGm",
  done: "fleet_stageDone",
};

export function fleetStatusTone(status) {
  if (status === "approved") return "badge-ok";
  if (status === "rejected" || status === "cancelled") return "badge-danger";
  return "badge-warn";
}

/** مدة المهمة كنص مختصر. */
export function fleetDuration(t, item) {
  if (!item.start_date) return "—";
  const days = item.days ? ` · ${t("fleet_daysCount", { count: item.days })}` : "";
  return `${item.start_date} → ${item.end_date || item.start_date}${days}`;
}
