import { createContext, useContext, useState } from "react";
import { useLanguage } from "./LanguageContext";
const ConfirmContext = createContext(() => Promise.resolve(false));
export function ConfirmProvider({ children }) {
  const { t } = useLanguage();
  const [pending, setPending] = useState(null);
  const confirm = (options) =>
    new Promise((resolve) => setPending({ ...options, resolve }));
  const finish = (value) => {
    pending?.resolve(value);
    setPending(null);
  };
  return (
    <ConfirmContext.Provider value={confirm}>
      {children}
      {pending && (
        <div
          className="overlay z-[100] grid place-items-center"
          onClick={() => finish(false)}
        >
          <section
            role="alertdialog"
            aria-modal="true"
            className="modal max-w-md shadow-sm"
            onClick={(event) => event.stopPropagation()}
          >
            <div className="modal-body">
              <h2 className="text-sm font-semibold text-ink">
                {pending.title || t("ui_confirmTitle")}
              </h2>
              <p className="mt-2 text-[13px] leading-relaxed break-words text-muted">
                {pending.message}
              </p>
            </div>
            <footer className="modal-foot justify-start">
              <button
                autoFocus
                className="btn btn-secondary"
                onClick={() => finish(false)}
              >
                {pending.cancelLabel || t("cancel")}
              </button>
              <button
                className={
                  pending.danger !== false
                    ? "btn btn-danger"
                    : "btn btn-primary"
                }
                onClick={() => finish(true)}
              >
                {pending.confirmLabel || t("ui_deletePermanently")}
              </button>
            </footer>
          </section>
        </div>
      )}
    </ConfirmContext.Provider>
  );
}
// eslint-disable-next-line react-refresh/only-export-components
export const useConfirm = () => useContext(ConfirmContext);
