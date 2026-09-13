export function formatBytes(size) {
  if (size < 1024) return `${size} B`;
  if (size < 1048576) return `${(size / 1024).toFixed(1)} KB`;
  return `${(size / 1048576).toFixed(1)} MB`;
}
// t is passed in so the "not set" fallback can be translated (runs outside React).
export function formatDateTime(value, t) {
  if (!value) return t ? t("task_notSet") : "غير محدد";
  return new Date(value).toLocaleString("ar-SY", {
    dateStyle: "medium",
    timeStyle: "short",
  });
}
export function formatDueDate(value, t) {
  if (!value) return t ? t("task_notSet") : "غير محدد";
  return new Date(value).toLocaleDateString("ar-SY", {
    dateStyle: "medium",
  });
}

// Arabic-aware normalization for search/compare: drops diacritics and tatweel
// and unifies the letter shapes people type interchangeably, so a search for
// "احمد" still matches an assignee stored as "أحمد".
export function normalizeText(value) {
  return String(value ?? "")
    .toLowerCase()
    .replace(/[\u064B-\u0652\u0640]/g, "")
    .replace(/[\u0623\u0625\u0622\u0671]/g, "\u0627")
    .replace(/\u0649/g, "\u064A")
    .replace(/\u0629/g, "\u0647")
    .replace(/\s+/g, " ")
    .trim();
}
// Local "YYYY-MM-DD" key. due_date already arrives in that shape from the API;
// parsing it through Date() would shift it by the UTC offset, so keep it as text.
export function toDateKey(value) {
  if (typeof value === "string") {
    const match = value.match(/^\d{4}-\d{2}-\d{2}/);
    if (match) return match[0];
  }
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  const pad = (part) => String(part).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}
export function shiftDateKey(key, days) {
  const date = new Date(`${key}T00:00:00`);
  date.setDate(date.getDate() + days);
  return toDateKey(date);
}
// A task counts as late only while it is still open — approved and cancelled
// work is history, not a pending deadline.
export function isOverdue(task) {
  if (!task?.due_date || ["completed", "cancelled"].includes(task.status))
    return false;
  return toDateKey(task.due_date) < toDateKey(new Date());
}

// مدة مقروءة بأكبر وحدة تستوعبها: دقائق ثم ساعات ثم أيام. تُستعمل لزمن بقاء
// المهمة في مرحلتها ولمتوسطات لوحة الإحصائيات معًا.
export function formatDuration(seconds, t) {
  if (seconds == null || Number.isNaN(seconds)) return t("task_notSet");
  if (seconds < 60) return t("task_durationNow");
  if (seconds < 3600) return t("task_durationMinutes", { count: Math.round(seconds / 60) });
  if (seconds < 86400) return t("task_durationHours", { count: Math.round(seconds / 3600) });
  return t("task_durationDays", { count: Math.round(seconds / 8640) / 10 });
}

// The statistics API reports averages in hours; the board reports raw seconds.
export function formatHours(hours, t) {
  return hours == null ? t("task_noData") : formatDuration(hours * 3600, t);
}

export function elapsedSince(value) {
  if (!value) return null;
  const start = new Date(value).getTime();
  return Number.isNaN(start) ? null : Math.max(0, (Date.now() - start) / 1000);
}
