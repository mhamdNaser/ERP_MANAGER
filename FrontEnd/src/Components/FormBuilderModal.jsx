import { Plus, Trash2, X } from "lucide-react";
import { useMemo, useState } from "react";
import { useLanguage } from "../Provider/LanguageContext";
const createLocalId = () =>
  `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
const emptyField = () => ({
  local_id: createLocalId(),
  label: "",
  input_type: "text",
  is_required: true,
  options_text: "",
});
const optionFieldTypes = ["select", "radio", "checkbox_group"];
const fieldTypeOptions = [
  ["text", "typeText"],
  ["email", "typeEmail"],
  ["password", "typePassword"],
  ["date", "typeDate"],
  ["number", "typeNumber"],
  ["textarea", "typeTextarea"],
  ["select", "typeSelect"],
  ["checkbox", "typeCheckbox"],
  ["checkbox_group", "typeCheckboxGroup"],
  ["radio", "typeRadio"],
];
const toDraft = (form) => ({
  title: form?.title || "",
  description: form?.description || "",
  target_group: form?.target_group || "branch_managers_heads_employees",
  default_scope: form?.default_scope || "organization",
  default_duration_days: String(form?.default_duration_days ?? ""),
  is_active: form?.is_active ?? true,
  fields: form?.fields?.length
    ? form.fields.map((field) => ({
        local_id: createLocalId(),
        field_key: field.field_key,
        label: field.label,
        input_type: field.input_type,
        is_required: field.is_required,
        placeholder: field.placeholder || "",
        help_text: field.help_text || "",
        options_text: Array.isArray(field.options)
          ? field.options
              .map((option) =>
                typeof option === "string" ? option : option.label,
              )
              .join(", ")
          : "",
      }))
    : [emptyField()],
});
const hasOptions = (field) =>
  !optionFieldTypes.includes(field.input_type) ||
  field.options_text
    .split(/[\n,]/)
    .map((option) => option.trim())
    .filter(Boolean).length > 0;

export function FormBuilderModal({ form, onClose, onSubmit }) {
  const { t } = useLanguage();
  const [draft, setDraft] = useState(toDraft(form || undefined));
  const [saving, setSaving] = useState(false);
  const title = form ? t("editForm") : t("createForm");
  const canSave = useMemo(
    () =>
      draft.title.trim().length > 1 &&
      draft.fields.some((field) => field.label.trim().length > 0) &&
      draft.fields
        .filter((field) => field.label.trim().length > 0)
        .every(hasOptions),
    [draft],
  );
  const updateField = (index, patch) => {
    setDraft((current) => ({
      ...current,
      fields: current.fields.map((field, fieldIndex) =>
        fieldIndex === index ? { ...field, ...patch } : field,
      ),
    }));
  };
  const addField = () =>
    setDraft((current) => ({
      ...current,
      fields: [...current.fields, emptyField()],
    }));
  const removeField = (index) =>
    setDraft((current) => ({
      ...current,
      fields: current.fields.filter((_, fieldIndex) => fieldIndex !== index),
    }));
  const submit = async (event) => {
    event.preventDefault();
    setSaving(true);
    try {
      await onSubmit({
        title: draft.title.trim(),
        description: draft.description.trim() || null,
        target_group: draft.target_group,
        default_scope: draft.default_scope,
        default_duration_days: draft.default_duration_days
          ? Number(draft.default_duration_days)
          : null,
        is_active: draft.is_active,
        fields: draft.fields
          .filter((field) => field.label.trim().length > 0)
          .map((field, index) => ({
            field_key: field.field_key?.trim() || `field_${index + 1}`,
            label: field.label.trim(),
            input_type: field.input_type,
            is_required: field.is_required,
            placeholder: field.placeholder?.trim() || null,
            help_text: field.help_text?.trim() || null,
            options:
              optionFieldTypes.includes(field.input_type)
                ? field.options_text
                    .split(/[\n,]/)
                    .map((option) => option.trim())
                    .filter(Boolean)
                    .map((option) => ({ value: option, label: option }))
                : null,
          })),
      });
      onClose();
    } finally {
      setSaving(false);
    }
  };
  return (
    <div className="overlay overflow-y-auto" onMouseDown={onClose}>
      <section
        className="modal my-4 max-w-4xl"
        onMouseDown={(event) => event.stopPropagation()}
      >
        <header className="modal-head items-start">
          <div className="min-w-0">
            <small className="eyebrow">{t("formBuilder")}</small>
            <h2 className="mt-1 text-base font-semibold text-ink">{title}</h2>
            <p className="mt-1 text-xs text-muted">{t("formBuilderHint")}</p>
          </div>
          <button
            type="button"
            className="btn-icon shrink-0"
            onClick={onClose}
            aria-label={t("cancel")}
          >
            <X size={16} />
          </button>
        </header>

        <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col overflow-y-auto">
          <div className="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
            <label className="field mb-0 min-w-0 md:col-span-2">
              <span className="label">{t("formTitle")} *</span>
              <input
                className="input"
                required
                value={draft.title}
                onChange={(event) =>
                  setDraft((current) => ({
                    ...current,
                    title: event.target.value,
                  }))
                }
              />
            </label>
            <label className="field mb-0 min-w-0 md:col-span-2">
              <span className="label">{t("formDescription")}</span>
              <textarea
                className="textarea"
                value={draft.description}
                onChange={(event) =>
                  setDraft((current) => ({
                    ...current,
                    description: event.target.value,
                  }))
                }
              />
            </label>
            <label className="field mb-0 min-w-0">
              <span className="label">{t("targetGroup")} *</span>
              <select
                className="select"
                value={draft.target_group}
                onChange={(event) =>
                  setDraft((current) => ({
                    ...current,
                    target_group: event.target.value,
                  }))
                }
              >
                <option value="branch_managers">{t("branch_managers")}</option>
                <option value="branch_managers_heads">
                  {t("branch_managers_heads")}
                </option>
                <option value="branch_managers_heads_employees">
                  {t("branch_managers_heads_employees")}
                </option>
                <option value="fixed_employees">{t("fixed_employees")}</option>
                <option value="contract_employees">
                  {t("contract_employees")}
                </option>
                <option value="all_staff">{t("targetAllStaff")}</option>
              </select>
            </label>
            <label className="field mb-0 min-w-0">
              <span className="label">{t("defaultScope")} *</span>
              <select
                className="select"
                value={draft.default_scope}
                onChange={(event) =>
                  setDraft((current) => ({
                    ...current,
                    default_scope: event.target.value,
                  }))
                }
              >
                <option value="organization">{t("scopeOrganization")}</option>
                <option value="branch">{t("scopeBranch")}</option>
                <option value="department">{t("scopeDepartment")}</option>
              </select>
            </label>
            <label className="field mb-0 min-w-0">
              <span className="label">{t("visibilityDays")}</span>
              <input
                className="input"
                type="number"
                min="1"
                max="365"
                value={draft.default_duration_days}
                onChange={(event) =>
                  setDraft((current) => ({
                    ...current,
                    default_duration_days: event.target.value,
                  }))
                }
              />
            </label>
            <label className="flex min-w-0 items-center gap-2 self-end rounded border border-line bg-subtle px-3 py-2.5">
              <input
                className="h-4 w-4 shrink-0 accent-brand-500"
                type="checkbox"
                checked={draft.is_active}
                onChange={(event) =>
                  setDraft((current) => ({
                    ...current,
                    is_active: event.target.checked,
                  }))
                }
              />
              <span className="label">{t("formActive")}</span>
            </label>
          </div>

          <section className="px-4 pb-4">
            <div className="flex flex-wrap items-end justify-between gap-3 pb-3">
              <div>
                <h3 className="section-title">{t("formFields")}</h3>
                <p className="text-xs text-muted">{t("formFieldsHint")}</p>
              </div>
              <button
                type="button"
                className="btn btn-secondary btn-sm"
                onClick={addField}
              >
                <Plus size={15} />
                {t("addField")}
              </button>
            </div>

            <div className="flex flex-col gap-3">
              {draft.fields.map((field, index) => (
                <article
                  key={field.local_id}
                  className="rounded border border-line bg-subtle p-3"
                >
                  <div className="mb-3 flex items-center justify-between gap-2">
                    <b className="text-xs font-semibold text-ink">
                      {t("field")} {index + 1}
                    </b>
                    <button
                      type="button"
                      className="btn-icon"
                      onClick={() => removeField(index)}
                      aria-label={t("delete")}
                    >
                      <Trash2 size={15} />
                    </button>
                  </div>
                  <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <label className="field mb-0 min-w-0">
                      <span className="label">{t("fieldLabel")} *</span>
                      <input
                        className="input"
                        required
                        value={field.label}
                        onChange={(event) =>
                          updateField(index, { label: event.target.value })
                        }
                      />
                    </label>
                    <label className="field mb-0 min-w-0">
                      <span className="label">{t("fieldType")} *</span>
                      <select
                        className="select"
                        value={field.input_type}
                        onChange={(event) =>
                          updateField(index, { input_type: event.target.value })
                        }
                      >
                        {fieldTypeOptions.map(([value, labelKey]) => (
                          <option key={value} value={value}>{t(labelKey)}</option>
                        ))}
                      </select>
                    </label>
                    <label className="field mb-0 min-w-0">
                      <span className="label">{t("fieldPlaceholder")}</span>
                      <input
                        className="input"
                        value={field.placeholder}
                        onChange={(event) =>
                          updateField(index, {
                            placeholder: event.target.value,
                          })
                        }
                      />
                    </label>
                    <label className="field mb-0 min-w-0">
                      <span className="label">{t("fieldHint")}</span>
                      <input
                        className="input"
                        value={field.help_text}
                        onChange={(event) =>
                          updateField(index, { help_text: event.target.value })
                        }
                      />
                    </label>
                    {optionFieldTypes.includes(field.input_type) && (
                      <label className="field mb-0 min-w-0 md:col-span-2">
                        <span className="label">{t("fieldOptions")}</span>
                        <textarea
                          className="textarea"
                          value={field.options_text}
                          onChange={(event) =>
                            updateField(index, {
                              options_text: event.target.value,
                            })
                          }
                          placeholder={t("fieldOptionsHint")}
                        />
                      </label>
                    )}
                    <label className="flex min-w-0 items-center gap-2 self-end rounded border border-line bg-white px-3 py-2.5">
                      <input
                        className="h-4 w-4 shrink-0 accent-brand-500"
                        type="checkbox"
                        checked={field.is_required}
                        onChange={(event) =>
                          updateField(index, {
                            is_required: event.target.checked,
                          })
                        }
                      />
                      <span className="label">{t("requiredField")}</span>
                    </label>
                  </div>
                </article>
              ))}
            </div>
          </section>

          <footer className="modal-foot sticky bottom-0 bg-surface">
            <button type="button" className="btn btn-secondary" onClick={onClose}>
              {t("cancel")}
            </button>
            <button className="btn btn-primary" disabled={!canSave || saving}>
              <Plus size={16} />
              {t("saveForm")}
            </button>
          </footer>
        </form>
      </section>
    </div>
  );
}
