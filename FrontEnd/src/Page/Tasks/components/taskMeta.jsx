import {
  Archive,
  ArrowLeftRight,
  BadgeCheck,
  Ban,
  Clock3,
  FilePenLine,
  MessagesSquare,
  Paperclip,
  Plus,
  ScanSearch,
  Sparkles,
  Trash2,
} from "lucide-react";

// مسار المهمة: مؤرشفة ← مخطط لها ← قيد التنفيذ ⇄ التواصل ← التدقيق ← الاعتماد،
// والإلغاء خارج المسار. الترتيب هنا هو ترتيب أعمدة اللوحة نفسه.
// title/hint hold translation KEYS; translate at the call site with t().
export const columns = [
  {
    id: "archived",
    title: "task_colArchived",
    hint: "task_colArchivedHint",
    icon: Archive,
  },
  {
    id: "planned",
    title: "task_colPlanned",
    hint: "task_colPlannedHint",
    icon: Sparkles,
  },
  {
    id: "in_progress",
    title: "task_colInProgress",
    hint: "task_colInProgressHint",
    icon: Clock3,
  },
  {
    id: "communication",
    title: "task_colCommunication",
    hint: "task_colCommunicationHint",
    icon: MessagesSquare,
  },
  {
    id: "review",
    title: "task_colReview",
    hint: "task_colReviewHint",
    icon: ScanSearch,
  },
  {
    // المرحلة النهائية مخزَّنة في القاعدة باسمها القديم `completed` — المراحل
    // الجديدة أُضيفت إلى المسار ولم تُلغِ القديمة — وتُعرض «الاعتماد».
    id: "completed",
    title: "task_colApproved",
    hint: "task_colApprovedHint",
    icon: BadgeCheck,
  },
  {
    id: "cancelled",
    title: "task_colCancelled",
    hint: "task_colCancelledHint",
    icon: Ban,
  },
];

export function columnOf(status) {
  return columns.find((column) => column.id === status);
}

// المراحل التي تحتاج مدخلًا إضافيًا قبل النقل. الخادم هو من يفرضها، والواجهة
// تسأل عنها مسبقًا حتى لا تصل الحركة إليه ناقصة فترتد برسالة خطأ.
export function moveNeedsCommunicationUser(target) {
  return target === "communication";
}

export function moveNeedsNote(from, target) {
  return from === "communication" && target === "in_progress";
}

// values are translation KEYS; translate at the call site with t().
export const priorityLabels = {
  low: "task_prioLow",
  medium: "task_prioMedium",
  high: "task_prioHigh",
  urgent: "task_prioUrgent",
};

export const priorityBadges = {
  low: "badge badge-neutral",
  medium: "badge badge-neutral",
  high: "badge badge-warn",
  urgent: "badge badge-danger",
};

export const statusBadges = {
  archived: "badge badge-neutral",
  planned: "badge badge-neutral",
  in_progress: "badge badge-info",
  communication: "badge badge-warn",
  review: "badge badge-info",
  completed: "badge badge-ok",
  cancelled: "badge badge-danger",
};

// label holds a translation KEY; translate at the call site with t().
export const activityTypes = {
  task_created: {
    label: "task_actCreated",
    icon: Plus,
    badge: "badge badge-ok",
    accent: "text-ok-500",
  },
  task_updated: {
    label: "task_actUpdated",
    icon: FilePenLine,
    badge: "badge badge-info",
    accent: "text-info-500",
  },
  task_moved: {
    label: "task_actMoved",
    icon: ArrowLeftRight,
    badge: "badge badge-warn",
    accent: "text-warn-500",
  },
  task_deleted: {
    label: "task_actDeleted",
    icon: Trash2,
    badge: "badge badge-danger",
    accent: "text-danger-500",
  },
  file_attached: {
    label: "task_actFileAttached",
    icon: Paperclip,
    badge: "badge badge-ok",
    accent: "text-ok-500",
  },
  file_deleted: {
    label: "task_actFileDeleted",
    icon: Trash2,
    badge: "badge badge-danger",
    accent: "text-danger-500",
  },
};

// values are translation KEYS; translate at the call site with t().
export const fieldLabels = {
  title: "task_fieldTitle",
  description: "task_description",
  assignee_id: "task_assignee",
  priority: "task_priority",
  label: "task_label",
  due_date: "task_fieldDueDate",
  status: "task_fieldStatus",
};
