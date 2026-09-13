import { Bell, CheckCheck } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { useLanguage } from "../Provider/LanguageContext";
export function NotificationCenter({ notifications, open, readAll }) {
  const { t } = useLanguage();
  const [visible, setVisible] = useState(false);
  const ref = useRef(null);
  useEffect(() => {
    const close = (event) => {
      if (!ref.current?.contains(event.target)) setVisible(false);
    };
    document.addEventListener("mousedown", close);
    return () => document.removeEventListener("mousedown", close);
  }, []);
  const unread = notifications.filter((item) => !item.read_at).length;
  return (
    <div className="relative" ref={ref}>
      <button
        className="btn-icon relative"
        onClick={() => setVisible(!visible)}
        aria-label={t("notifications")}
      >
        <Bell size={17} />
        {unread > 0 && (
          <i className="absolute end-1.5 top-1.5 h-1.5 w-1.5 rounded-full bg-warn-500 not-italic" />
        )}
      </button>
      {visible && (
        <div className="absolute end-0 top-[calc(100%+6px)] z-50 w-[min(360px,calc(100vw-2rem))] rounded border border-line bg-surface shadow-sm">
          <header className="flex items-center justify-between gap-2 border-b border-line px-3 py-2.5">
            <div className="flex flex-col">
              <b className="text-[13px] font-semibold text-ink">
                {t("notifications")}
              </b>
              <small className="text-[11px] text-muted">
                {unread} {t("unread")}
              </small>
            </div>
            <button
              className="flex items-center gap-1.5 text-[11px] font-semibold text-brand-500 hover:text-brand-600"
              onClick={readAll}
            >
              <CheckCheck size={14} />
              {t("markAllRead")}
            </button>
          </header>
          <div className="max-h-96 overflow-y-auto">
            {notifications.length ? (
              notifications.slice(0, 8).map((item) => (
                <button
                  key={item.id}
                  className={`flex w-full items-start gap-2.5 border-b border-line px-3 py-2.5 text-start last:border-b-0 hover:bg-canvas ${
                    item.read_at ? "bg-surface" : "bg-brand-50"
                  }`}
                  onClick={() => {
                    open(item);
                    setVisible(false);
                  }}
                >
                  <span className="mt-0.5 inline-grid h-7 w-7 shrink-0 place-items-center rounded border border-line bg-canvas text-muted">
                    <Bell size={13} />
                  </span>
                  <div className="flex min-w-0 flex-col">
                    <b className="truncate text-xs font-semibold text-ink">
                      {item.title}
                    </b>
                    <p className="truncate text-[11px] text-muted">
                      {item.message}
                    </p>
                    <small className="text-[11px] text-muted">
                      {new Date(item.created_at).toLocaleString()}
                    </small>
                  </div>
                </button>
              ))
            ) : (
              <p className="px-3 py-8 text-center text-xs text-muted">
                {t("noNotifications")}
              </p>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
