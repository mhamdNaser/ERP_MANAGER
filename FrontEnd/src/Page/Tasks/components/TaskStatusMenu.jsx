import { useEffect, useRef, useState } from "react";
import { ArrowLeftRight, Check } from "lucide-react";
import { useLanguage } from "../../../Provider/LanguageContext";
import { columns } from "./taskMeta";

// Alternative to drag-and-drop for changing a task's status — some tasks
// (e.g. sitting in a column that's awkward to drag out of) are easier to
// move via a menu than by dragging. Reuses the same `move()` handler as DnD.
//
// القائمة تعرض فقط المراحل التي يسمح بها مسار العمل لهذا المستخدم من مرحلة
// المهمة الحالية (allowed_transitions القادمة من الخادم)، إضافة إلى المرحلة
// الحالية كمؤشر لا أكثر.
export function TaskStatusMenu({ task, move }) {
  const { t } = useLanguage();
  const [open, setOpen] = useState(false);
  const menu = useRef(null);
  const allowed = task.allowed_transitions || [];
  const options = columns.filter(
    (column) => column.id === task.status || allowed.includes(column.id),
  );

  useEffect(() => {
    if (!open) return;
    const close = (event) => {
      if (!menu.current?.contains(event.target)) setOpen(false);
    };
    const escape = (event) => {
      if (event.key === "Escape") setOpen(false);
    };
    document.addEventListener("mousedown", close);
    document.addEventListener("keydown", escape);
    return () => {
      document.removeEventListener("mousedown", close);
      document.removeEventListener("keydown", escape);
    };
  }, [open]);

  const choose = (status) => {
    setOpen(false);
    if (status !== task.status) move(task.id, status);
  };

  return (
    <div
      className="relative shrink-0"
      ref={menu}
      draggable={false}
      onClick={(e) => e.stopPropagation()}
      onMouseDown={(e) => e.stopPropagation()}
      onDragStart={(e) => e.preventDefault()}
    >
      <button
        type="button"
        className="inline-grid h-7 w-7 place-items-center rounded text-muted hover:bg-brand-50 hover:text-brand-600"
        onClick={() => setOpen((current) => !current)}
        aria-expanded={open}
        title={t("task_changeStatus")}
      >
        <ArrowLeftRight size={14} />
      </button>
      {open && (
        <div className="absolute end-0 top-[calc(100%+6px)] z-50 w-52 rounded border border-line bg-surface p-1">
          {options.map((column) => (
            <button
              type="button"
              key={column.id}
              className={`flex w-full items-center gap-2 rounded px-2 py-1.5 text-start text-xs ${
                column.id === task.status
                  ? "bg-brand-50 text-brand-600"
                  : "hover:bg-canvas"
              }`}
              onClick={() => choose(column.id)}
            >
              <column.icon size={14} className="shrink-0" />
              <span className="min-w-0 flex-1 truncate">{t(column.title)}</span>
              {column.id === task.status && (
                <Check size={13} className="shrink-0 text-brand-500" />
              )}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
