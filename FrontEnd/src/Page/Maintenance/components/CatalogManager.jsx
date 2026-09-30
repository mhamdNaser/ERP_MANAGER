import { Check, Pencil, Plus, Trash2, X } from "lucide-react";
import { useState } from "react";
import { useLanguage } from "../../../Provider/LanguageContext";
import { api } from "../../../lib";

/** سطر في قائمة: اسم قابل لإعادة التسمية والحذف في مكانه. */
function EntryRow({ entry, meta, selected, onSelect, canManage, onRename, onDelete }) {
  const { t } = useLanguage();
  const [editing, setEditing] = useState(false);
  const [name, setName] = useState(entry.name);

  const save = async () => {
    if (!name.trim() || name.trim() === entry.name) return setEditing(false);
    if (await onRename(entry, name.trim())) setEditing(false);
  };

  return (
    <li className={`flex items-center gap-2 px-3 py-2 ${selected ? "bg-brand-50" : ""}`}>
      {editing ? (
        <>
          <input className="input h-8 flex-1" autoFocus value={name} onChange={(event) => setName(event.target.value)} onKeyDown={(event) => event.key === "Enter" && save()} />
          <button className="btn-icon" onClick={save} title={t("save")}><Check size={14} /></button>
          <button className="btn-icon" onClick={() => { setEditing(false); setName(entry.name); }} title={t("cancel")}><X size={14} /></button>
        </>
      ) : (
        <>
          <button type="button" className="min-w-0 flex-1 truncate text-start text-sm text-ink" onClick={onSelect} disabled={!onSelect}>
            {entry.name}
          </button>
          {meta && <small className="shrink-0 text-xs text-muted">{meta}</small>}
          {canManage && (
            <>
              <button className="btn-icon" onClick={() => setEditing(true)} title={t("mt_rename")}><Pencil size={13} /></button>
              <button className="btn-icon text-danger-500" onClick={() => onDelete(entry)} title={t("mt_delete")}><Trash2 size={13} /></button>
            </>
          )}
        </>
      )}
    </li>
  );
}

function AddRow({ placeholder, onAdd, disabled }) {
  const { t } = useLanguage();
  const [name, setName] = useState("");
  const add = async () => {
    if (!name.trim()) return;
    if (await onAdd(name.trim())) setName("");
  };

  return (
    <div className="flex gap-1.5 border-t border-line p-2">
      <input className="input h-8 flex-1" disabled={disabled} placeholder={placeholder} value={name} onChange={(event) => setName(event.target.value)} onKeyDown={(event) => event.key === "Enter" && add()} />
      <button className="btn btn-primary btn-sm h-8" disabled={disabled || !name.trim()} onClick={add}><Plus size={14} />{t("mt_add")}</button>
    </div>
  );
}

function Column({ title, hint, children }) {
  return (
    <section className="card flex min-w-0 flex-col">
      <header className="card-head">
        <div className="min-w-0">
          <h2 className="section-title">{title}</h2>
          <p className="page-subtitle mt-0.5 text-xs">{hint}</p>
        </div>
      </header>
      {children}
    </section>
  );
}

/** الفئات وأنواعها والماركات: ثلاثة أعمدة، والنوع يتبع الفئة المختارة. */
export function CatalogManager({ catalog, canManage, notify, changed }) {
  const { t } = useLanguage();
  const [pickedId, setCategoryId] = useState(null);
  // السجل يصل بعد أول رسم، فالفئة الأولى تُختار ضمناً حتى يختار المستخدم.
  const categoryId = catalog.categories.some((entry) => entry.id === pickedId) ? pickedId : catalog.categories[0]?.id ?? null;
  const category = catalog.categories.find((entry) => entry.id === categoryId);

  const run = async (action) => {
    try {
      await action();
      notify?.(t("mt_catalogSaved"), "success");
      changed();
      return true;
    } catch (error) {
      notify?.(error.message, "error");
      return false;
    }
  };
  const rename = (entity) => (entry, name) => run(() => api.saveMaintenanceCatalog(entity, { name, description: entry.description }, entry.id));
  const remove = (entity) => (entry) =>
    window.confirm(t("mt_confirmDeleteEntry", { name: entry.name })) && run(() => api.deleteMaintenanceCatalog(entity, entry.id));
  const list = (entries, entity, extra = {}) => (
    <ul className="flex max-h-[420px] flex-1 flex-col divide-y divide-line overflow-y-auto">
      {entries.map((entry) => (
        <EntryRow key={entry.id} entry={entry} canManage={canManage} onRename={rename(entity)} onDelete={remove(entity)} {...extra(entry)} />
      ))}
    </ul>
  );

  return (
    <div className="flex flex-col gap-3">
      {!canManage && <p className="text-xs text-muted">{t("mt_viewOnly")}</p>}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <Column title={t("mt_categories")} hint={t("mt_categoriesHint")}>
          {list(catalog.categories, "categories", (entry) => ({
            selected: entry.id === categoryId,
            onSelect: () => setCategoryId(entry.id),
            meta: t("mt_itemsIn", { count: entry.items_count ?? 0 }),
          }))}
          {canManage && <AddRow placeholder={t("mt_newCategoryPrompt")} onAdd={(name) => run(() => api.saveMaintenanceCatalog("categories", { name }))} />}
        </Column>

        <Column title={`${t("mt_types")}${category ? ` — ${category.name}` : ""}`} hint={t("mt_typesHint")}>
          {category ? list(category.types || [], "types", () => ({})) : <p className="empty-state m-3">{t("mt_pickCategory")}</p>}
          {canManage && (
            <AddRow
              placeholder={t("mt_newTypePrompt")}
              disabled={!category}
              onAdd={(name) => run(() => api.saveMaintenanceCatalog("types", { name, category_id: categoryId }))}
            />
          )}
        </Column>

        <Column title={t("mt_brands")} hint={t("mt_brandsHint")}>
          {list(catalog.brands, "brands", () => ({}))}
          {canManage && <AddRow placeholder={t("mt_newBrandPrompt")} onAdd={(name) => run(() => api.saveMaintenanceCatalog("brands", { name }))} />}
        </Column>
      </div>
    </div>
  );
}
