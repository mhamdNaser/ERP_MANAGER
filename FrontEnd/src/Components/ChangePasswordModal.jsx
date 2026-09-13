import { Eye, EyeOff, KeyRound, X } from "lucide-react";
import { useState } from "react";
import { useLanguage } from "../Provider/LanguageContext";
import { api } from "../lib";

const MIN_LENGTH = 8;

function PasswordField({ label, value, onChange, autoComplete, invalid }) {
  const { t } = useLanguage();
  const [visible, setVisible] = useState(false);

  return (
    <label className="field mb-0">
      <span className="label">{label}</span>
      <span className="relative flex items-center">
        <input
          className={`input pe-10 ${invalid ? "border-danger-500" : ""}`}
          type={visible ? "text" : "password"}
          value={value}
          onChange={(event) => onChange(event.target.value)}
          autoComplete={autoComplete}
          required
          minLength={MIN_LENGTH}
        />
        <button
          type="button"
          className="absolute end-1 inline-grid h-7 w-7 place-items-center rounded text-muted hover:bg-canvas hover:text-ink"
          onClick={() => setVisible((current) => !current)}
          aria-label={t(visible ? "hidePassword" : "showPassword")}
          title={t(visible ? "hidePassword" : "showPassword")}
        >
          {visible ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
        </button>
      </span>
    </label>
  );
}

/** تغيير كلمة مرور موظف دون الحاجة إلى كلمته القديمة. */
export function ChangePasswordModal({ employee, close, notify }) {
  const { t } = useLanguage();
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [saving, setSaving] = useState(false);

  const tooShort = password.length > 0 && password.length < MIN_LENGTH;
  const mismatch = confirmation.length > 0 && password !== confirmation;
  const ready = password.length >= MIN_LENGTH && password === confirmation;

  const submit = async (event) => {
    event.preventDefault();
    if (!ready || saving) return;
    setSaving(true);
    try {
      const response = await api.updateEmployeePassword(employee.id, {
        password,
        password_confirmation: confirmation,
      });
      notify?.(response?.message || t("passwordUpdated"), "success");
      close();
    } catch (error) {
      notify?.(error.message, "error");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="overlay grid place-items-center overflow-y-auto">
      <section className="modal max-w-lg">
        <header className="modal-head">
          <div className="min-w-0">
            <small className="eyebrow">{t("changePassword")}</small>
            <h2 className="mt-1 text-base font-semibold break-words text-ink">{employee.name}</h2>
            <p className="text-xs text-muted">{t("changePasswordHint")}</p>
          </div>
          <button className="btn-icon shrink-0" onClick={close} aria-label={t("close")}><X className="h-4 w-4" /></button>
        </header>
        <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
          <div className="modal-body flex flex-col gap-4">
            <PasswordField
              label={t("newPassword")}
              value={password}
              onChange={setPassword}
              autoComplete="new-password"
              invalid={tooShort}
            />
            <PasswordField
              label={t("confirmPassword")}
              value={confirmation}
              onChange={setConfirmation}
              autoComplete="new-password"
              invalid={mismatch}
            />
            {tooShort && <p className="text-xs font-semibold text-danger-500">{t("passwordTooShort", { min: MIN_LENGTH })}</p>}
            {mismatch && <p className="text-xs font-semibold text-danger-500">{t("passwordMismatch")}</p>}
          </div>
          <footer className="modal-foot">
            <button type="button" className="btn btn-secondary" onClick={close}>{t("cancel")}</button>
            <button className="btn btn-primary" disabled={!ready || saving}>
              <KeyRound className="h-4 w-4" />
              {saving ? t("saving") : t("savePassword")}
            </button>
          </footer>
        </form>
      </section>
    </div>
  );
}
