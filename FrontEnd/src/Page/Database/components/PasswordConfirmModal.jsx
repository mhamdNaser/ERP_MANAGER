import { useState } from "react";
import { useLanguage } from "../../../Provider/LanguageContext";

export function PasswordConfirmModal({ state, busy, onConfirm, onClose }) {
  const { t } = useLanguage();
  const [password, setPassword] = useState("");

  if (!state) return null;

  const close = () => {
    setPassword("");
    onClose();
  };

  const submit = async (event) => {
    event.preventDefault();
    if (!password || busy) return;
    const ok = await onConfirm(password);
    if (ok) setPassword("");
  };

  return (
    <div className="overlay z-[100] grid place-items-center" onClick={close}>
      <section
        role="alertdialog"
        aria-modal="true"
        className="modal max-w-md shadow-sm"
        onClick={(event) => event.stopPropagation()}
      >
        <form onSubmit={submit}>
          <div className="modal-body">
            <h2 className="text-sm font-semibold text-ink">{state.title}</h2>
            <p className="mt-2 text-[13px] leading-relaxed break-words text-muted">
              {state.message}
            </p>
            <label className="mt-4 block text-xs font-medium text-muted">
              {t("password")}
              <input
                type="password"
                autoFocus
                className="input mt-1"
                placeholder={t("passwordPlaceholder")}
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                autoComplete="current-password"
              />
            </label>
          </div>
          <footer className="modal-foot justify-start">
            <button
              type="button"
              className="btn btn-secondary"
              onClick={close}
              disabled={busy}
            >
              {t("cancel")}
            </button>
            <button
              type="submit"
              className="btn btn-danger"
              disabled={busy || !password}
            >
              {busy ? t("processing") : t("confirmAndExecute")}
            </button>
          </footer>
        </form>
      </section>
    </div>
  );
}
