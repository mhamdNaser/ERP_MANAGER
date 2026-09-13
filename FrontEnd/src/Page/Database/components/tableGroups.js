import {
  Bell,
  Building2,
  ClipboardList,
  FileInput,
  FileText,
  HardDrive,
  KanbanSquare,
  Layers,
  Mail,
  ShieldCheck,
  Users,
} from "lucide-react";

export const TABLE_GROUPS = [
  {
    key: "organization",
    labelKey: "tableGroupOrganization",
    icon: Building2,
    tables: ["branches", "departments", "offices", "external_entities"],
  },
  {
    key: "usersPermissions",
    labelKey: "tableGroupUsersPermissions",
    icon: ShieldCheck,
    tables: [
      "users",
      "roles",
      "permissions",
      "model_has_roles",
      "model_has_permissions",
      "role_has_permissions",
      "user_addresses",
      "user_family_details",
      "user_personal_details",
    ],
  },
  {
    key: "reports",
    labelKey: "tableGroupReports",
    icon: ClipboardList,
    tables: ["reports", "report_actions"],
  },
  {
    key: "tasks",
    labelKey: "tableGroupTasks",
    icon: KanbanSquare,
    tables: ["tasks", "task_activities", "task_stage_transitions"],
  },
  {
    key: "formal",
    labelKey: "tableGroupFormal",
    icon: FileText,
    tables: [
      "formal_correspondences",
      "formal_correspondence_documents",
      "formal_correspondence_events",
      "formal_correspondence_stage_responses",
    ],
  },
  {
    key: "messages",
    labelKey: "tableGroupMessages",
    icon: Mail,
    tables: ["messages", "message_replies", "circulars", "circular_recipients"],
  },
  {
    key: "forms",
    labelKey: "tableGroupForms",
    icon: FileInput,
    tables: [
      "custom_forms",
      "custom_form_fields",
      "custom_form_publications",
      "custom_form_submissions",
    ],
  },
  {
    key: "drive",
    labelKey: "tableGroupDrive",
    icon: HardDrive,
    tables: [
      "drive_files",
      "drive_folders",
      "drive_file_shares",
      "drive_folder_shares",
      "role_drive_quotas",
    ],
  },
  {
    key: "hr",
    labelKey: "tableGroupHr",
    icon: Users,
    tables: ["hr_requests", "hr_request_actions", "hr_leave_balances"],
  },
  {
    key: "notifications",
    labelKey: "tableGroupNotifications",
    icon: Bell,
    tables: ["cnd_notifications"],
  },
];

/** يبني قائمة المجموعات المرئية من جدول الجداول المتاحة فعليًا، مع مجموعة "أخرى" لأي جدول غير مصنَّف. */
export function buildVisibleGroups(tables) {
  const available = new Set(tables);
  const used = new Set();
  const groups = TABLE_GROUPS.map((group) => {
    const present = group.tables.filter((table) => available.has(table));
    present.forEach((table) => used.add(table));
    return { ...group, tables: present };
  }).filter((group) => group.tables.length > 0);

  const leftover = tables.filter((table) => !used.has(table));
  if (leftover.length) {
    groups.push({
      key: "other",
      labelKey: "tableGroupOther",
      icon: Layers,
      tables: leftover,
    });
  }

  return groups;
}
