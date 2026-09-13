import { RotateCcw, Search, SlidersHorizontal } from "lucide-react";
import { useLanguage } from "../../../Provider/LanguageContext";
import { priorityLabels } from "./taskMeta";
import {
  countActiveFilters,
  dueOptions,
  emptyFilters,
  fileOptions,
  NO_LABEL,
  UNASSIGNED,
} from "./taskFiltering";

export function TaskFilters({
  filters,
  onChange,
  search,
  onSearchChange,
  options,
  shown,
  total,
}) {
  const { t } = useLanguage();
  const active = countActiveFilters(filters) + (search.trim() ? 1 : 0);
  const set = (key) => (event) =>
    onChange({ ...filters, [key]: event.target.value });
  return (
    <section className="flex flex-wrap items-center gap-2 rounded border border-line bg-surface p-3">
      <span className="flex items-center gap-1.5 text-xs font-semibold text-muted">
        <SlidersHorizontal size={15} className="shrink-0" />
        {t("task_filtersLabel")}
        {active > 0 && (
          <b className="inline-grid h-5 min-w-5 place-items-center rounded border border-line bg-subtle px-1 text-[10px] font-bold text-ink">
            {active}
          </b>
        )}
      </span>
      <label className="relative flex min-w-56 flex-1 items-center sm:max-w-xs">
        <Search
          size={16}
          className="pointer-events-none absolute start-3 text-muted"
        />
        <input
          className="input ps-9"
          value={search}
          onChange={(e) => onSearchChange(e.target.value)}
          placeholder={t("task_searchPlaceholder")}
        />
      </label>
      <select
        className="select w-auto min-w-40"
        aria-label={t("task_filterAssignee")}
        value={filters.assignee}
        onChange={set("assignee")}
      >
        <option value="">{t("task_allAssignees")}</option>
        {options.hasUnassigned && (
          <option value={UNASSIGNED}>{t("task_unassignedOption")}</option>
        )}
        {options.assignees.map((assignee) => (
          <option key={assignee.id} value={String(assignee.id)}>
            {assignee.name}
          </option>
        ))}
      </select>
      <select
        className="select w-auto min-w-36"
        aria-label={t("task_filterPriority")}
        value={filters.priority}
        onChange={set("priority")}
      >
        <option value="">{t("task_allPriorities")}</option>
        {Object.entries(priorityLabels).map(([value, label]) => (
          <option key={value} value={value}>
            {t(label)}
          </option>
        ))}
      </select>
      <select
        className="select w-auto min-w-36"
        aria-label={t("task_filterDue")}
        value={filters.due}
        onChange={set("due")}
      >
        <option value="">{t("task_allDueDates")}</option>
        {dueOptions.map((option) => (
          <option key={option.value} value={option.value}>
            {t(option.label)}
          </option>
        ))}
      </select>
      {(options.labels.length > 0 || options.hasUnlabelled) && (
        <select
          className="select w-auto min-w-36"
          aria-label={t("task_filterLabel")}
          value={filters.label}
          onChange={set("label")}
        >
          <option value="">{t("task_allLabels")}</option>
          {options.labels.map((label) => (
            <option key={label} value={label}>
              {label}
            </option>
          ))}
          {options.hasUnlabelled && (
            <option value={NO_LABEL}>{t("task_noLabelOption")}</option>
          )}
        </select>
      )}
      {options.creators.length > 1 && (
        <select
          className="select w-auto min-w-36"
          aria-label={t("task_filterCreator")}
          value={filters.creator}
          onChange={set("creator")}
        >
          <option value="">{t("task_allCreators")}</option>
          {options.creators.map((creator) => (
            <option key={creator.id} value={String(creator.id)}>
              {creator.name}
            </option>
          ))}
        </select>
      )}
      <select
        className="select w-auto min-w-36"
        aria-label={t("task_filterFiles")}
        value={filters.files}
        onChange={set("files")}
      >
        <option value="">{t("task_allAttachments")}</option>
        {fileOptions.map((option) => (
          <option key={option.value} value={option.value}>
            {t(option.label)}
          </option>
        ))}
      </select>
      {active > 0 && (
        <button
          className="btn btn-secondary btn-sm"
          onClick={() => {
            onChange(emptyFilters);
            onSearchChange("");
          }}
        >
          <RotateCcw size={14} />
          {t("task_clearFilters")}
        </button>
      )}
      <span className="ms-auto text-xs whitespace-nowrap text-muted">
        {active > 0
          ? t("task_filteredCount", { count: shown, total })
          : `${total} ${t("results")}`}
      </span>
    </section>
  );
}
