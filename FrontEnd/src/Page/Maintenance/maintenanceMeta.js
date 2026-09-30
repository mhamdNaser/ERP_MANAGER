import { BadgeCheck, CircleCheck, CircleX, Package, Wrench } from "lucide-react";

// ترتيب الحالات على مسار القطعة. الألوان فُحصت بأداة التحقق (تمييز عمى
// الألوان والتباين)، وكل لون يرافقه أيقونة واسم فلا تُقرأ الحالة من اللون وحده.
export const STATUSES = [
  { key: "in_stock", color: "#2a78d6", icon: Package },
  { key: "under_maintenance", color: "#eb6834", icon: Wrench },
  { key: "repaired", color: "#4a3aa7", icon: BadgeCheck },
  { key: "ready", color: "#1baf7a", icon: CircleCheck },
  { key: "damaged", color: "#e34948", icon: CircleX },
];

export const statusOf = (key) => STATUSES.find((status) => status.key === key);

export const statusLabel = (key, t) => (key ? t(`mt_status_${key}`) : "—");

export const UNIT_SUGGESTIONS = ["قطعة", "بكرة", "علبة", "طقم", "متر", "لتر"];

export function formatMoney(value) {
  return `$${Number(value || 0).toLocaleString("en-US", { maximumFractionDigits: 2 })}`;
}

export function formatNumber(value) {
  return Number(value || 0).toLocaleString("en-US");
}

export function formatDateTime(value) {
  if (!value) return "—";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString("en-GB", { year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit" });
}

export const emptyFilters = {
  search: "",
  category_id: "",
  type_id: "",
  brand_id: "",
  status: "",
  low_stock: false,
};
