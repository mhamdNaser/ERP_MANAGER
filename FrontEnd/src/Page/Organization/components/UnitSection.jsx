import { Pencil, Power, PowerOff, Trash2 } from "lucide-react";
import { useLanguage } from "../../../Provider/LanguageContext";

export function UnitSection({ title, items, action, actionLabel }) {
  const { t } = useLanguage();
  return (
    <section className="card">
      <div className="card-head">
        <h2 className="section-title">{title}</h2>
        {action && (
          <button className="btn btn-secondary btn-sm" onClick={action}>
            {actionLabel}
          </button>
        )}
      </div>
      <div className="flex flex-col">
        {items.map(({ id, name, meta, Icon, isActive, edit, remove, toggleActive }) => (
          <div
            className={`flex items-center gap-3 border-b border-line px-4 py-3 last:border-b-0 ${
              isActive === false ? "opacity-60" : ""
            }`}
            key={id}
          >
            <span className="inline-grid h-8 w-8 shrink-0 place-items-center rounded border border-line bg-subtle text-brand-500">
              <Icon className="h-4 w-4" />
            </span>
            <div className="min-w-0 flex-1">
              <div className="flex items-center gap-2">
                <b className="block truncate text-[13px] font-semibold text-ink">
                  {name}
                </b>
                {isActive === false && (
                  <span className="badge badge-neutral shrink-0">
                    {t("statusInactive")}
                  </span>
                )}
              </div>
              <small className="block truncate text-xs text-muted">{meta}</small>
            </div>
            <div className="flex items-center gap-1">
              {toggleActive && (
                <button
                  className="btn-icon h-8 w-8"
                  onClick={toggleActive}
                  title={isActive ? t("deactivateAction") : t("activateAction")}
                  aria-label={isActive ? t("deactivateAction") : t("activateAction")}
                >
                  {isActive ? <PowerOff size={15} /> : <Power size={15} />}
                </button>
              )}
              {edit && (
                <button className="btn-icon h-8 w-8" onClick={edit}>
                  <Pencil size={15} />
                </button>
              )}
              {remove && (
                <button
                  className="btn-icon h-8 w-8 hover:text-danger-500"
                  onClick={remove}
                >
                  <Trash2 size={15} />
                </button>
              )}
            </div>
          </div>
        ))}
      </div>
    </section>
  );
}
