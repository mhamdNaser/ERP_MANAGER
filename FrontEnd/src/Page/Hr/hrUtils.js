export const hrTypeLabelKeys = {
  leave: "hr_typeLeave",
  departure: "hr_typeDeparture",
  document: "hr_typeDocument",
};

export const hrSubtypeLabelKeys = {
  annual: "hr_leaveAnnual",
  sick: "hr_leaveSick",
  unpaid: "hr_leaveUnpaid",
  employment: "hr_docEmployment",
  salary: "hr_docSalary",
};

export const hrStatusLabelKeys = {
  pending_hr: "hr_statusPendingHr",
  pending_gm: "hr_statusPendingGm",
  approved: "hr_statusApproved",
  rejected: "hr_statusRejected",
  cancelled: "hr_statusCancelled",
};

export const hrStageLabelKeys = {
  hr: "hr_stageHr",
  gm: "hr_stageGm",
  done: "hr_stageDone",
};

export function hrStatusTone(status) {
  if (status === "approved") return "badge-ok";
  if (status === "rejected" || status === "cancelled") return "badge-danger";
  return "badge-warn";
}

/** الحقول المطلوبة تختلف باختلاف نوع الطلب. */
export function hrFieldsFor(type) {
  return {
    dates: type === "leave",
    times: type === "departure",
    subtype: type === "leave" || type === "document",
  };
}

export function hrSubtypeOptions(type) {
  if (type === "leave") return ["annual", "sick", "unpaid"];
  if (type === "document") return ["employment", "salary"];
  return [];
}

/** مدة الطلب كنص مختصر حسب نوعه. */
export function hrDuration(t, item) {
  if (item.type === "departure") {
    return `${item.start_time?.slice(0, 5) || "--"} → ${item.end_time?.slice(0, 5) || "--"}`;
  }
  if (item.start_date) {
    const days = item.days ? ` · ${t("hr_daysCount", { count: item.days })}` : "";
    return `${item.start_date} → ${item.end_date || item.start_date}${days}`;
  }
  return "—";
}
