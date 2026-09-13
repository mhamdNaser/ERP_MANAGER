import { isOverdue, normalizeText, shiftDateKey, toDateKey } from "./taskUtils";

// Sentinel values for the "no value at all" buckets, kept distinct from "" —
// which every select uses for "no filter applied".
export const UNASSIGNED = "unassigned";
export const NO_LABEL = "__none__";

export const emptyFilters = {
  assignee: "",
  priority: "",
  label: "",
  creator: "",
  due: "",
  files: "",
};

// values are translation KEYS; translate at the call site with t().
export const dueOptions = [
  { value: "overdue", label: "task_dueOverdue" },
  { value: "today", label: "task_dueToday" },
  { value: "week", label: "task_dueWeek" },
  { value: "none", label: "task_dueNone" },
];

export const fileOptions = [
  { value: "with", label: "task_filesWith" },
  { value: "without", label: "task_filesWithout" },
];

const byName = (a, b) => String(a.name).localeCompare(String(b.name), "ar");

// Options come from the loaded board, not from a fixed list: the assignee
// dropdown must also offer people who hold a task but are no longer an active
// member of the department, otherwise their tasks become unreachable.
export function buildFilterOptions(tasks, members) {
  const assignees = new Map();
  const creators = new Map();
  const labels = new Set();
  members.forEach((member) =>
    assignees.set(String(member.id), {
      id: member.id,
      name: member.name,
      job_title: member.job_title,
    }),
  );
  let unassigned = 0;
  let unlabelled = 0;
  tasks.forEach((task) => {
    if (task.assignee?.id) assignees.set(String(task.assignee.id), task.assignee);
    else unassigned += 1;
    if (task.creator?.id) creators.set(String(task.creator.id), task.creator);
    if (task.label) labels.add(task.label);
    else unlabelled += 1;
  });
  return {
    assignees: [...assignees.values()].sort(byName),
    creators: [...creators.values()].sort(byName),
    labels: [...labels].sort((a, b) => a.localeCompare(b, "ar")),
    hasUnassigned: unassigned > 0,
    hasUnlabelled: unlabelled > 0,
  };
}

// Every term must hit somewhere in the task — that is what lets "احمد صيانة"
// narrow down to the maintenance tasks assigned to Ahmad.
function matchesSearch(task, terms) {
  if (!terms.length) return true;
  const haystack = normalizeText(
    [
      task.title,
      task.description,
      task.label,
      task.assignee?.name,
      task.assignee?.job_title,
      task.creator?.name,
      task.department?.name,
    ]
      .filter(Boolean)
      .join(" "),
  );
  return terms.every((term) => haystack.includes(term));
}

function matchesDue(task, due) {
  if (!due) return true;
  if (due === "none") return !task.due_date;
  if (!task.due_date) return false;
  if (due === "overdue") return isOverdue(task);
  const today = toDateKey(new Date());
  const dueKey = toDateKey(task.due_date);
  if (due === "today") return dueKey === today;
  return dueKey >= today && dueKey <= shiftDateKey(today, 7);
}

export function filterTasks(tasks, filters, search) {
  const terms = normalizeText(search).split(" ").filter(Boolean);
  return tasks.filter((task) => {
    if (!matchesSearch(task, terms)) return false;
    if (filters.assignee) {
      const assignee = task.assignee?.id ? String(task.assignee.id) : UNASSIGNED;
      if (assignee !== filters.assignee) return false;
    }
    if (filters.priority && task.priority !== filters.priority) return false;
    if (filters.label) {
      const label = task.label || NO_LABEL;
      if (label !== filters.label) return false;
    }
    if (filters.creator && String(task.creator?.id || "") !== filters.creator)
      return false;
    if (!matchesDue(task, filters.due)) return false;
    if (filters.files) {
      const attached = (task.files?.length || 0) > 0;
      if (attached !== (filters.files === "with")) return false;
    }
    return true;
  });
}

export function countActiveFilters(filters) {
  return Object.values(filters).filter(Boolean).length;
}
