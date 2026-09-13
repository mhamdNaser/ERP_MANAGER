import { useEffect, useMemo, useState } from "react";
import { Activity, LayoutGrid, Plus } from "lucide-react";
import { api } from "../../lib";
import { useConfirm } from "../../Provider/ConfirmContext";
import { useLanguage } from "../../Provider/LanguageContext";
import {
  DepartmentPicker,
  TaskActivityDrawer,
  TaskDetails,
  TaskEditor,
} from "./components/TaskComponents";
import { TaskBoardStats } from "./components/TaskBoardStats";
import { TaskCard } from "./components/TaskCard";
import { TaskFilters } from "./components/TaskFilters";
import { BoardArrow } from "./components/BoardArrow";
import { TaskMoveDialog } from "./components/TaskMoveDialog";
import {
  buildFilterOptions,
  emptyFilters,
  filterTasks,
} from "./components/taskFiltering";
import {
  columns,
  moveNeedsCommunicationUser,
  moveNeedsNote,
} from "./components/taskMeta";
import { useBoardScroll } from "./useBoardScroll";
import { useFocusTask } from "./useFocusTask";
export function TasksBoardPage({ user, notify, focusTaskId, onTaskFocused }) {
  const { t } = useLanguage();
  const [board, setBoard] = useState(null);
  const [department, setDepartment] = useState("");
  const [search, setSearch] = useState("");
  const [filters, setFilters] = useState(emptyFilters);
  const [editor, setEditor] = useState(undefined);
  const [selected, setSelected] = useState(null);
  const [activityOpen, setActivityOpen] = useState(false);
  const [dragging, setDragging] = useState(null);
  const [pendingMove, setPendingMove] = useState(null);
  const {
    board: boardRef,
    hidden,
    onScroll,
    toPrevious,
    toNext,
  } = useBoardScroll(columns.length, Boolean(board));
  const isGlobal = ["general_manager", "database_manager"].includes(user.role);
  const canCreateTasks = user.permissions?.includes("tasks.create");
  const canUpdateTasks = user.permissions?.includes("tasks.update");
  const canDeleteTasks = user.permissions?.includes(
    "tasks.delete_with_activities",
  );
  const confirm = useConfirm();
  const load = (selection = department) => {
    const all = selection === "all";
    api
      .taskBoard(all ? undefined : Number(selection) || undefined, all)
      .then((data) => {
        setBoard(data);
        if (!selection)
          setDepartment(String(data.active_department_id || "all"));
      })
      .catch((error) => notify(error.message, "error"));
  };
  useEffect(() => {
    load("");
  }, []); // eslint-disable-line react-hooks/exhaustive-deps
  useFocusTask(board, focusTaskId, onTaskFocused, setSelected);
  const tasks = useMemo(
    () => filterTasks(board?.tasks || [], filters, search),
    [board, filters, search],
  );
  const filterOptions = useMemo(
    () => buildFilterOptions(board?.tasks || [], board?.members || []),
    [board],
  );
  const activeDepartment = board?.departments.find(
    (item) => item.id === Number(department),
  );
  const completion = tasks.length
    ? Math.round(
        (tasks.filter((task) => task.status === "completed").length /
          tasks.length) *
          100,
      )
    : 0;
  // Assignees, labels and creators are department-scoped, so a filter carried
  // across a board switch would silently hide every task.
  const changeDepartment = (value) => {
    setDepartment(value);
    setFilters(emptyFilters);
    load(value);
  };
  // مسار العمل يقرره الخادم: البطاقة تحمل allowed_transitions لهذا المستخدم،
  // فلا تُعرض حركة سيرفضها، ولا تُنفَّذ نقلة ينقصها مدخل مرحلتها.
  const requestMove = (taskId, status) => {
    const task = board?.tasks.find((item) => item.id === taskId);
    setDragging(null);
    if (!task || task.status === status) return;
    if (!(task.allowed_transitions || []).includes(status)) {
      notify(t("task_moveNotAllowed"), "error");
      return;
    }
    if (moveNeedsCommunicationUser(status) || moveNeedsNote(task.status, status))
      setPendingMove({ task, status });
    else move(taskId, status);
  };
  const move = async (taskId, status, extra = {}) => {
    if (!board) return;
    const previous = board.tasks;
    setBoard({
      ...board,
      tasks: board.tasks.map((task) =>
        task.id === taskId ? { ...task, status } : task,
      ),
    });
    try {
      const updated = await api.moveTask(taskId, status, extra);
      setBoard((current) =>
        current
          ? {
              ...current,
              tasks: current.tasks.map((task) =>
                task.id === taskId ? updated : task,
              ),
            }
          : current,
      );
      setSelected((current) => (current?.id === taskId ? updated : current));
      setPendingMove(null);
    } catch (error) {
      setBoard({ ...board, tasks: previous });
      notify(error.message, "error");
    }
    setDragging(null);
  };
  const remove = async (task) => {
    if (
      !(await confirm({
        message: t("task_confirmDeleteMsg", { title: task.title }),
        confirmLabel: t("task_confirmDeleteLabel"),
      }))
    )
      return;
    try {
      await api.deleteTask(task.id);
      setBoard((current) =>
        current
          ? {
              ...current,
              tasks: current.tasks.filter((item) => item.id !== task.id),
            }
          : current,
      );
      notify(t("task_deletedToast"), "delete");
    } catch (error) {
      notify(error.message, "error");
    }
  };
  if (!board)
    return (
      <div className="page">
        <div className="empty-state">{t("task_preparingBoard")}</div>
      </div>
    );
  return (
    <div className="page">
      <header className="page-header">
        <div className="flex min-w-0 items-start gap-3">
          <span className="inline-grid h-10 w-10 shrink-0 place-items-center rounded border border-line bg-subtle text-muted">
            <LayoutGrid size={18} />
          </span>
          <div className="min-w-0">
            <p className="eyebrow">{t("task_workspaceEyebrow")}</p>
            <h1 className="page-title">
              {department === "all"
                ? t("task_allBoardsTitle")
                : activeDepartment?.name || user.department?.name}
            </h1>
            <small className="page-subtitle">
              {department === "all"
                ? t("task_allScopeSubtitle")
                : `${activeDepartment?.branch?.name || user.branch?.name || ""} · ${t("task_deptScopeSubtitle")}`}
            </small>
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {(board.departments.length > 1 || isGlobal) && (
            <DepartmentPicker
              departments={board.departments}
              value={department}
              global={isGlobal}
              change={changeDepartment}
            />
          )}
          <button
            className="btn btn-secondary"
            onClick={() => setActivityOpen(true)}
          >
            <Activity size={16} />
            {t("task_activityLog")}
          </button>
          {canCreateTasks && (
            <button
              className="btn btn-primary"
              disabled={department === "all"}
              onClick={() => setEditor(null)}
            >
              <Plus size={16} />
              {t("task_newTask")}
            </button>
          )}
        </div>
      </header>
      <TaskBoardStats tasks={tasks} completion={completion} />
      <TaskFilters
        filters={filters}
        onChange={setFilters}
        search={search}
        onSearchChange={setSearch}
        options={filterOptions}
        shown={tasks.length}
        total={board.tasks.length}
      />
      {/* سبع مراحل لا تتسع لها الشاشة، فتُقلَّب بسهمين — سهم لكل جهة تخبّئ مرحلة،
          يحمل اسمها ويختفي عند بلوغ الطرف. يختفيان أثناء السحب حتى لا يعترضا
          إفلات البطاقة في العمود الذي تحتهما. */}
      <div className="relative">
        <BoardArrow
          side="start"
          stage={hidden.start}
          hide={Boolean(dragging)}
          onClick={toPrevious}
          t={t}
        />
        <main
          className="scroll-hidden -mx-1 flex items-start gap-3 overflow-x-auto px-1"
          ref={boardRef}
          onScroll={onScroll}
        >
          {columns.map((column) => (
            <section
              className="flex min-h-[420px] w-[270px] shrink-0 flex-col rounded border border-line bg-subtle p-2"
              key={column.id}
              onDragOver={(e) => e.preventDefault()}
              onDrop={() => dragging && requestMove(dragging, column.id)}
            >
              <header className="flex items-center gap-2 px-1 pb-3 pt-1">
                <span className="inline-grid h-8 w-8 shrink-0 place-items-center rounded border border-line bg-surface text-muted">
                  <column.icon size={16} />
                </span>
                <div className="min-w-0 flex-1">
                  <h2 className="truncate text-sm font-semibold text-ink">
                    {t(column.title)}
                  </h2>
                  <p className="truncate text-xs text-muted">{t(column.hint)}</p>
                </div>
                <b className="inline-grid h-6 min-w-6 place-items-center rounded border border-line bg-surface px-1 text-xs font-semibold text-muted">
                  {tasks.filter((task) => task.status === column.id).length}
                </b>
              </header>
              <div className="flex flex-1 flex-col gap-2">
                {tasks
                  .filter((task) => task.status === column.id)
                  .map((task) => (
                    <TaskCard
                      task={task}
                      key={task.id}
                      t={t}
                      dragging={dragging}
                      setDragging={setDragging}
                      move={requestMove}
                      remove={remove}
                      canDeleteTasks={canDeleteTasks}
                      onSelect={setSelected}
                    />
                  ))}
                {!tasks.some((task) => task.status === column.id) && (
                  <div className="empty-state">
                    <column.icon size={20} />
                    <p>{t("task_emptyColumn")}</p>
                    <small className="text-xs">{t("task_emptyColumnHint")}</small>
                  </div>
                )}
                {column.id === "archived" &&
                  department !== "all" &&
                  canCreateTasks && (
                    <button
                      className="flex h-9 items-center justify-center gap-2 rounded border border-dashed border-line bg-surface text-xs font-semibold text-muted hover:text-ink"
                      onClick={() => setEditor(null)}
                    >
                      <Plus size={16} />
                      {t("task_addTask")}
                    </button>
                  )}
              </div>
              </section>
            ))}
        </main>
        <BoardArrow
          side="end"
          stage={hidden.end}
          hide={Boolean(dragging)}
          onClick={toNext}
          t={t}
        />
      </div>
      {editor !== undefined && (
        <TaskEditor
          task={editor || undefined}
          departmentId={Number(department)}
          members={board.members}
          close={() => setEditor(undefined)}
          saved={() => {
            setEditor(undefined);
            load();
          }}
          notify={notify}
        />
      )}
      {pendingMove && (
        <TaskMoveDialog
          task={pendingMove.task}
          target={pendingMove.status}
          members={board.members}
          close={() => setPendingMove(null)}
          confirm={(extra) =>
            move(pendingMove.task.id, pendingMove.status, extra)
          }
        />
      )}
      {selected && (
        <TaskDetails
          task={selected}
          close={() => setSelected(null)}
          edit={() => {
            setSelected(null);
            setEditor(selected);
          }}
          move={requestMove}
          canDelete={selected.can_delete ?? canDeleteTasks}
          canEdit={canUpdateTasks}
          changed={load}
          notify={notify}
        />
      )}
      {activityOpen && (
        <TaskActivityDrawer
          department={department}
          all={department === "all"}
          close={() => setActivityOpen(false)}
        />
      )}
    </div>
  );
}
