import { Check, Plus, Save, X } from "lucide-react";
import { useState } from "react";
import { useLanguage } from "../../../Provider/LanguageContext";
import { api } from "../../../lib";
import { STATUSES, UNIT_SUGGESTIONS } from "../maintenanceMeta";

const uniqueById = (list) => [...new Map(list.map((entry) => [entry.id, entry])).values()];

const draftFrom = (item) => ({
  name: item?.name || "",
  part_number: item?.part_number || "",
  category_id: item?.category_id ? String(item.category_id) : "",
  type_id: item?.type_id ? String(item.type_id) : "",
  brand_id: item?.brand_id ? String(item.brand_id) : "",
  device: item?.device || "",
  unit: item?.unit || "قطعة",
  unit_price: item?.unit_price ?? "",
  min_quantity: item?.min_quantity ?? 0,
  location: item?.location || "",
  notes: item?.notes || "",
  initial_quantity: "",
  initial_status: "in_stock",
});

/**
 * قائمة منسدلة بزر «إضافة جديد» بجانبها: من لا يجد الفئة أو النوع أو الماركة
 * يضيفها من مكانه دون مغادرة النموذج.
 */
function PickOrAdd({ label, value, options, onChange, onCreate, disabled, disabledHint, prompt }) {
  const { t } = useLanguage();
  const [adding, setAdding] = useState(false);
  const [name, setName] = useState("");
  const [saving, setSaving] = useState(false);

  const create = async () => {
    if (!name.trim() || saving) return;
    setSaving(true);
    const created = await onCreate(name.trim());
    setSaving(false);
    if (created) {
      setAdding(false);
      setName("");
    }
  };

  return (
    <label className="field mb-0">
      <span className="label">{label}</span>
      {adding ? (
        <span className="flex gap-1.5">
          <input
            className="input"
            autoFocus
            placeholder={prompt}
            value={name}
            onChange={(event) => setName(event.target.value)}
            onKeyDown={(event) => {
              if (event.key === "Enter") {
                event.preventDefault();
                create();
              }
            }}
          />
          <button type="button" className="btn btn-primary btn-sm h-9" disabled={!name.trim() || saving} onClick={create}><Check size={14} /></button>
          <button type="button" className="btn btn-secondary btn-sm h-9" onClick={() => setAdding(false)}><X size={14} /></button>
        </span>
      ) : (
        <span className="flex gap-1.5">
          <select className="select" value={value} disabled={disabled} onChange={(event) => onChange(event.target.value)}>
            <option value="">{disabled ? disabledHint : t("mt_choose")}</option>
            {options.map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}
          </select>
          <button type="button" className="btn btn-secondary btn-sm h-9 shrink-0" disabled={disabled} title={t("mt_addNew")} onClick={() => setAdding(true)}>
            <Plus size={14} />
          </button>
        </span>
      )}
    </label>
  );
}

export function ItemFormModal({ item, catalog, notify, close, saved, catalogChanged }) {
  const { t } = useLanguage();
  const [draft, setDraft] = useState(() => draftFrom(item));
  const [image, setImage] = useState(null);
  const [removeImage, setRemoveImage] = useState(false);
  const [saving, setSaving] = useState(false);
  // ما يُضاف من داخل النموذج يظهر فوراً قبل أن يعود السجل من الخادم.
  const [extra, setExtra] = useState({ categories: [], types: [], brands: [] });

  const categories = uniqueById([...catalog.categories, ...extra.categories]);
  const types = uniqueById([
    ...(categories.find((category) => String(category.id) === draft.category_id)?.types || []),
    ...extra.types.filter((type) => String(type.category_id) === draft.category_id),
  ]);
  const brands = uniqueById([...catalog.brands, ...extra.brands]);
  const set = (patch) => setDraft((current) => ({ ...current, ...patch }));

  const createEntry = (entity, key, payload) => async (name) => {
    try {
      const created = await api.saveMaintenanceCatalog(entity, { ...payload, name });
      setExtra((current) => ({ ...current, [key]: [...current[key], { ...created, types: [] }] }));
      set({ [`${key === "categories" ? "category" : key === "types" ? "type" : "brand"}_id`]: String(created.id), ...(key === "categories" ? { type_id: "" } : {}) });
      catalogChanged?.();
      return created;
    } catch (error) {
      notify?.(error.message, "error");
      return null;
    }
  };

  const submit = async (event) => {
    event.preventDefault();
    if (!draft.name.trim() || saving) return;
    setSaving(true);
    try {
      const payload = { ...draft, image, remove_image: removeImage ? 1 : "" };
      if (item) {
        delete payload.initial_quantity;
        delete payload.initial_status;
      }
      await api.saveMaintenanceItem(payload, item?.id);
      notify?.(t("mt_saved"), "success");
      saved();
    } catch (error) {
      notify?.(error.message, "error");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="overlay grid place-items-center overflow-y-auto">
      <section className="modal max-w-2xl">
        <header className="modal-head">
          <div className="min-w-0">
            <small className="eyebrow">{t("mt_eyebrow")}</small>
            <h2 className="mt-1 text-base font-semibold text-ink">{t(item ? "mt_editItem" : "mt_newItem")}</h2>
            <p className="text-xs text-muted">{t("mt_itemFormHint")}</p>
          </div>
          <button className="btn-icon shrink-0" onClick={close} aria-label={t("close")}><X size={16} /></button>
        </header>
        <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
          <div className="modal-body grid grid-cols-1 gap-4 sm:grid-cols-2">
            <label className="field mb-0">
              <span className="label">{t("mt_name")} *</span>
              <input className="input" required autoFocus placeholder={t("mt_namePlaceholder")} value={draft.name} onChange={(event) => set({ name: event.target.value })} />
            </label>
            <label className="field mb-0">
              <span className="label">{t("mt_partNumber")}</span>
              <input className="input [direction:ltr]" placeholder={t("mt_partNumberPlaceholder")} value={draft.part_number} onChange={(event) => set({ part_number: event.target.value })} />
            </label>

            <PickOrAdd
              label={t("mt_category")}
              value={draft.category_id}
              options={categories}
              prompt={t("mt_newCategoryPrompt")}
              onChange={(category_id) => set({ category_id, type_id: "" })}
              onCreate={createEntry("categories", "categories", {})}
            />
            <PickOrAdd
              label={t("mt_type")}
              value={draft.type_id}
              options={types}
              prompt={t("mt_newTypePrompt")}
              disabled={!draft.category_id}
              disabledHint={t("mt_pickCategoryFirst")}
              onChange={(type_id) => set({ type_id })}
              onCreate={createEntry("types", "types", { category_id: draft.category_id })}
            />
            <PickOrAdd
              label={t("mt_brand")}
              value={draft.brand_id}
              options={brands}
              prompt={t("mt_newBrandPrompt")}
              onChange={(brand_id) => set({ brand_id })}
              onCreate={createEntry("brands", "brands", {})}
            />
            <label className="field mb-0">
              <span className="label">{t("mt_device")}</span>
              <input className="input" list="mt-devices" placeholder={t("mt_devicePlaceholder")} value={draft.device} onChange={(event) => set({ device: event.target.value })} />
              <datalist id="mt-devices">{catalog.devices.map((device) => <option key={device} value={device} />)}</datalist>
            </label>

            <label className="field mb-0">
              <span className="label">{t("mt_unit")}</span>
              <input className="input" list="mt-units" value={draft.unit} onChange={(event) => set({ unit: event.target.value })} />
              <datalist id="mt-units">
                {[...new Set([...UNIT_SUGGESTIONS, ...catalog.units])].map((unit) => <option key={unit} value={unit} />)}
              </datalist>
            </label>
            <label className="field mb-0">
              <span className="label">{t("mt_unitPrice")}</span>
              <input className="input" type="number" min="0" step="0.01" value={draft.unit_price} onChange={(event) => set({ unit_price: event.target.value })} />
            </label>
            <label className="field mb-0">
              <span className="label">{t("mt_location")}</span>
              <input className="input" placeholder={t("mt_locationPlaceholder")} value={draft.location} onChange={(event) => set({ location: event.target.value })} />
            </label>
            <label className="field mb-0">
              <span className="label">{t("mt_minQuantity")}</span>
              <input className="input" type="number" min="0" value={draft.min_quantity} onChange={(event) => set({ min_quantity: event.target.value })} />
              <span className="hint">{t("mt_minQuantityHint")}</span>
            </label>

            {!item && (
              <>
                <label className="field mb-0">
                  <span className="label">{t("mt_initialQuantity")}</span>
                  <input className="input" type="number" min="0" value={draft.initial_quantity} onChange={(event) => set({ initial_quantity: event.target.value })} />
                </label>
                <label className="field mb-0">
                  <span className="label">{t("mt_initialStatus")}</span>
                  <select className="select" value={draft.initial_status} onChange={(event) => set({ initial_status: event.target.value })}>
                    {STATUSES.map((status) => <option key={status.key} value={status.key}>{t(`mt_status_${status.key}`)}</option>)}
                  </select>
                </label>
              </>
            )}

            <label className="field mb-0 sm:col-span-2">
              <span className="label">{t("mt_notes")}</span>
              <textarea className="textarea min-h-16" value={draft.notes} onChange={(event) => set({ notes: event.target.value })} />
            </label>
            <div className="field mb-0 sm:col-span-2">
              <span className="label">{t("mt_image")}</span>
              <div className="flex flex-wrap items-center gap-3">
                {item?.image_url && !removeImage && !image && (
                  <img src={item.image_url} alt="" className="h-14 w-14 rounded border border-line object-cover" />
                )}
                <input
                  className="input h-auto flex-1 py-2 file:me-3 file:rounded file:border file:border-line file:bg-canvas file:px-2 file:py-1 file:text-xs file:font-semibold file:text-ink"
                  type="file"
                  accept="image/*"
                  onChange={(event) => setImage(event.target.files?.[0] ?? null)}
                />
                {item?.image_url && (
                  <label className="flex items-center gap-1.5 text-xs text-muted">
                    <input type="checkbox" checked={removeImage} onChange={(event) => setRemoveImage(event.target.checked)} />
                    {t("mt_removeImage")}
                  </label>
                )}
              </div>
            </div>
          </div>
          <footer className="modal-foot">
            <button type="button" className="btn btn-secondary" onClick={close}>{t("cancel")}</button>
            <button className="btn btn-primary" disabled={!draft.name.trim() || saving}>
              <Save size={16} />
              {saving ? t("saving") : t("save")}
            </button>
          </footer>
        </form>
      </section>
    </div>
  );
}
