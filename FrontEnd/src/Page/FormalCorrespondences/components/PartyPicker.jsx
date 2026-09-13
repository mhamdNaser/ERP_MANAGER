import { useMemo } from "react";
import { AutocompleteInput } from "../../../Components/AutocompleteInput";
import { useLanguage } from "../../../Provider/LanguageContext";
import { partyTypeLabelKeys, targetOptions } from "./formalUtils";

// الجهات الثابتة التي لا تحتاج معرّفاً.
const FIXED_TYPES = { our_org: "formal_partyOurOrg", diwan: "formal_partyDiwan" };

/**
 * منتقي جهة موحّد: يُستعمل للجهة المصدرة والجهة المخاطبة معاً.
 * value: { type, id, name }
 */
export function PartyPicker({ label, inputLabel, hint, allowedTypes, value, onChange, directory, disabled = false }) {
  const { t } = useLanguage();
  const type = allowedTypes.includes(value.type) ? value.type : allowedTypes[0];
  const choices = useMemo(() => targetOptions(directory, type), [directory, type]);
  const externalNames = useMemo(
    () => (directory?.external_entities || []).map((entity) => entity.name),
    [directory],
  );

  const setType = (nextType) => onChange({ type: nextType, id: "", name: "" });

  return (
    <div className="flex flex-col gap-2">
      <span className="label">{label}</span>
      {allowedTypes.length > 1 && (
        <select
          className="select"
          value={type}
          disabled={disabled}
          onChange={(event) => setType(event.target.value)}
        >
          {allowedTypes.map((option) => (
            <option key={option} value={option}>{t(partyTypeLabelKeys[option] || option)}</option>
          ))}
        </select>
      )}

      {!FIXED_TYPES[type] && inputLabel && <span className="label mt-1">{inputLabel}</span>}
      {FIXED_TYPES[type] ? (
        <input className="input" value={t(FIXED_TYPES[type])} readOnly />
      ) : type === "external_entity" ? (
        <AutocompleteInput
          value={value.name || ""}
          onChange={(name) => onChange({ type, id: "", name })}
          options={externalNames}
          placeholder={t("formal_startTypingSearch")}
          disabled={disabled}
        />
      ) : (
        <select
          className="select"
          value={value.id || ""}
          disabled={disabled}
          onChange={(event) => {
            const id = event.target.value;
            const name = choices.find((choice) => String(choice.id) === String(id))?.name || "";
            onChange({ type, id, name });
          }}
        >
          <option value="">{t("formal_pickTarget")}</option>
          {choices.map((choice) => (
            <option key={`${type}-${choice.id}`} value={choice.id}>{choice.name}</option>
          ))}
        </select>
      )}

      {hint && <small className="text-xs text-muted">{hint}</small>}
    </div>
  );
}
