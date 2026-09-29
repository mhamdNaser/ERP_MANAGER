import { Check, X } from "lucide-react";
import { useState } from "react";
import { useLanguage } from "../Provider/LanguageContext";
export function ReusableFormModal({
  title,
  subtitle,
  fields,
  initial = {},
  submitLabel,
  onSubmit,
  onClose,
}) {
  const { t } = useLanguage();
  const [values, setValues] = useState(initial);
  const [saving, setSaving] = useState(false);
  const [validationError, setValidationError] = useState("");
  // تُقبل fields مصفوفةً كما كانت، أو دالّةً على القيم الحالية حين يتوقّف
  // حقلٌ على آخر — كقائمة الأقسام التي تضيق بحسب الفرع المختار.
  const resolvedFields = typeof fields === "function" ? fields(values) : fields;
  const change = (name, value) => {
    setValidationError("");
    setValues((current) => {
      const next = { ...current, [name]: value };
      // تغيير الحقل الأب يُفرغ ما يتبعه، وإلا بقي اختيارٌ لا يطابق الأب.
      const changed = resolvedFields.find((field) => field.name === name);
      (changed?.resets || []).forEach((dependent) => {
        next[dependent] = "";
      });
      return next;
    });
  };
  const toggleGroupValue = (name, optionValue, checked) =>
    setValues((current) => {
      setValidationError("");
      const selected = Array.isArray(current[name]) ? current[name] : [];
      return {
        ...current,
        [name]: checked
          ? [...new Set([...selected, optionValue])]
          : selected.filter((value) => value !== optionValue),
      };
    });
  const submit = async (event) => {
    event.preventDefault();
    const invalidField = resolvedFields.find((field) => {
      if (!field.required) return false;
      if (field.type === "checkbox_group") return !Array.isArray(values[field.name]) || values[field.name].length === 0;
      if (field.type === "radio") return !values[field.name];
      return false;
    });
    if (invalidField) {
      setValidationError(`${invalidField.label}: ${t("requiredField")}`);
      return;
    }
    setSaving(true);
    try {
      await onSubmit(values);
      onClose();
    } finally {
      setSaving(false);
    }
  };
  return (
    <div className="overlay overflow-y-auto" onMouseDown={onClose}>
      <section
        className="modal my-4"
        onMouseDown={(event) => event.stopPropagation()}
      >
        <header className="modal-head items-start">
          <div className="min-w-0">
            <small className="eyebrow">{t("dataEntry")}</small>
            <h2 className="mt-1 text-base font-semibold text-ink">{title}</h2>
            {subtitle && <p className="mt-1 text-xs text-muted">{subtitle}</p>}
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
        <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
          <div className="grid flex-1 grid-cols-1 gap-4 overflow-y-auto p-4 md:grid-cols-2">
            {resolvedFields.map((field) => (
              <label
                key={field.name}
                className={`field mb-0 min-w-0 ${field.wide ? "md:col-span-2" : ""}`}
              >
                {field.type === "checkbox" ? (
                  <>
                    <span className="label">
                      {field.label}
                      {field.required && (
                        <em className="ms-1 not-italic text-danger-500">*</em>
                      )}
                    </span>
                    <div className="flex items-center gap-2 rounded border border-line bg-subtle p-3">
                      <input
                        className="h-4 w-4 shrink-0 accent-brand-500"
                        required={field.required}
                        type="checkbox"
                        checked={Boolean(values[field.name])}
                        onChange={(event) =>
                          change(field.name, event.target.checked)
                        }
                      />
                      <small className="hint">
                        {field.hint || t("formCheckboxHint")}
                      </small>
                    </div>
                  </>
                ) : field.type === "checkbox_group" ? (
                  <>
                    <span className="label">
                      {field.label}
                      {field.required && (
                        <em className="ms-1 not-italic text-danger-500">*</em>
                      )}
                    </span>
                    <div className="flex flex-col gap-2 rounded border border-line bg-subtle p-2">
                      {field.options?.map((option) => (
                        <label
                          key={option.value}
                          className="flex items-center gap-2 rounded border border-line bg-white px-2.5 py-2"
                        >
                          <input
                            className="h-4 w-4 shrink-0 accent-brand-500"
                            type="checkbox"
                            checked={(Array.isArray(values[field.name]) ? values[field.name] : []).includes(option.value)}
                            onChange={(event) =>
                              toggleGroupValue(field.name, option.value, event.target.checked)
                            }
                          />
                          <span className="text-[13px] text-ink">
                            {option.label}
                          </span>
                        </label>
                      ))}
                    </div>
                    {field.hint && <small className="hint">{field.hint}</small>}
                  </>
                ) : field.type === "radio" ? (
                  <>
                    <span className="label">
                      {field.label}
                      {field.required && (
                        <em className="ms-1 not-italic text-danger-500">*</em>
                      )}
                    </span>
                    <div className="flex flex-col gap-2 rounded border border-line bg-subtle p-2">
                      {field.options?.map((option) => (
                        <label
                          key={option.value}
                          className="flex items-center gap-2 rounded border border-line bg-white px-2.5 py-2"
                        >
                          <input
                            className="h-4 w-4 shrink-0 accent-brand-500"
                            required={field.required}
                            type="radio"
                            name={field.name}
                            value={option.value}
                            checked={String(values[field.name] ?? "") === String(option.value)}
                            onChange={(event) => change(field.name, event.target.value)}
                          />
                          <span className="text-[13px] text-ink">
                            {option.label}
                          </span>
                        </label>
                      ))}
                    </div>
                    {field.hint && <small className="hint">{field.hint}</small>}
                  </>
                ) : (
                  <>
                    <span className="label">
                      {field.label}
                      {field.required && (
                        <em className="ms-1 not-italic text-danger-500">*</em>
                      )}
                    </span>
                    {field.type === "textarea" ? (
                      <textarea
                        className="textarea"
                        required={field.required}
                        placeholder={field.placeholder}
                        value={String(values[field.name] ?? "")}
                        onChange={(event) =>
                          change(field.name, event.target.value)
                        }
                      />
                    ) : field.type === "select" ? (
                      <select
                        className="select"
                        required={field.required}
                        value={String(values[field.name] ?? "")}
                        onChange={(event) =>
                          change(field.name, event.target.value)
                        }
                      >
                        <option value="">{t("chooseValue")}</option>
                        {field.options?.map((option) => (
                          <option key={option.value} value={option.value}>
                            {option.label}
                          </option>
                        ))}
                      </select>
                    ) : field.type === "file" ? (
                      <input
                        className="input h-auto py-2 file:me-3 file:rounded file:border file:border-line file:bg-canvas file:px-2.5 file:py-1 file:text-xs file:font-semibold file:text-ink"
                        required={field.required}
                        type="file"
                        accept={field.accept}
                        onChange={(event) =>
                          change(field.name, event.target.files?.[0] ?? null)
                        }
                      />
                    ) : (
                      <input
                        className="input"
                        required={field.required}
                        type={field.type || "text"}
                        placeholder={field.placeholder}
                        value={String(values[field.name] ?? "")}
                        onChange={(event) =>
                          change(field.name, event.target.value)
                        }
                      />
                    )}{" "}
                    {field.hint && <small className="hint">{field.hint}</small>}
                  </>
                )}
              </label>
            ))}
          </div>
          {validationError && (
            <p className="error-text px-4 pb-2">{validationError}</p>
          )}
          <footer className="modal-foot">
            <button type="button" className="btn btn-secondary" onClick={onClose}>
              {t("cancel")}
            </button>
            <button className="btn btn-primary" disabled={saving}>
              <Check size={16} />
              {submitLabel || t("save")}
            </button>
          </footer>
        </form>
      </section>
    </div>
  );
}
