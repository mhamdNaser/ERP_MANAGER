import { ArrowLeft, ArrowRight, Check, Compass, X } from "lucide-react";
import { useEffect, useLayoutEffect, useMemo, useState } from "react";
import { useLanguage } from "../Provider/LanguageContext";
export const GUIDE_STORAGE_KEY = "cnd-interface-guide-v2-completed";
export const GUIDE_RESTART_EVENT = "cnd-interface-guide-restart";
export function InterfaceGuide({ items, navigate }) {
  const { t } = useLanguage();
  const [open, setOpen] = useState(
    () => !localStorage.getItem(GUIDE_STORAGE_KEY),
  );
  const [index, setIndex] = useState(0);
  const [rect, setRect] = useState(null);
  const steps = useMemo(
    () => [
      {
        target: '[data-guide="sidebar"]',
        title: t("guide_tourSidebarTitle"),
        text: t("guide_tourSidebarText"),
      },
      {
        target: '[data-guide="notifications"]',
        title: t("guide_tourNotificationsTitle"),
        text: t("guide_tourNotificationsText"),
      },
      ...items
        .filter((item) => item.id !== "guide")
        .map((item) => ({
          target: '[data-guide="content"]',
          view: item.id,
          title: item.label,
          text: item.description,
        })),
      {
        target: '[data-guide="guide"]',
        view: "guide",
        title: t("guide_tourGuideTitle"),
        text: t("guide_tourGuideText"),
      },
    ],
    [items, t],
  );
  useEffect(() => {
    const restart = () => {
      localStorage.removeItem(GUIDE_STORAGE_KEY);
      setIndex(0);
      setOpen(true);
    };
    window.addEventListener(GUIDE_RESTART_EVENT, restart);
    return () => window.removeEventListener(GUIDE_RESTART_EVENT, restart);
  }, []);
  useEffect(() => {
    if (open && steps[index]?.view) navigate(steps[index].view);
  }, [index, navigate, open, steps]);
  useLayoutEffect(() => {
    if (!open) return;
    const update = () => {
      const target = document.querySelector(steps[index]?.target);
      setRect(target?.getBoundingClientRect() || null);
    };
    const timer = window.setTimeout(update, 120);
    window.addEventListener("resize", update);
    return () => {
      window.clearTimeout(timer);
      window.removeEventListener("resize", update);
    };
  }, [open, index, steps]);
  useEffect(() => {
    if (open) document.body.classList.add("overflow-hidden");
    return () => document.body.classList.remove("overflow-hidden");
  }, [open]);
  if (!open || !rect || !steps[index]) return null;
  const finish = () => {
    localStorage.setItem(GUIDE_STORAGE_KEY, "done");
    setOpen(false);
  };
  const cardStyle = {
    top: Math.min(
      window.innerHeight - 300,
      Math.max(20, rect.top + Math.min(rect.height + 18, 150)),
    ),
    right: Math.min(
      window.innerWidth - 390,
      Math.max(20, window.innerWidth - rect.right),
    ),
  };
  return (
    <div className="pointer-events-none fixed inset-0 z-[110]">
      <div
        className="pointer-events-none fixed rounded border-2 border-brand-500 shadow-[0_0_0_9999px_rgba(4,35,32,0.72)]"
        style={{
          top: rect.top - 7,
          left: rect.left - 7,
          width: rect.width + 14,
          height: rect.height + 14,
        }}
      />
      <section
        className="pointer-events-auto fixed z-[111] w-[min(350px,calc(100vw-40px))] rounded border border-line bg-surface p-4"
        style={cardStyle}
      >
        <header className="flex items-center justify-between gap-2">
          <span className="text-xs font-semibold text-brand-500">
            {index + 1} / {steps.length}
          </span>
          <button
            className="inline-grid h-7 w-7 place-items-center rounded text-muted hover:bg-canvas hover:text-ink"
            onClick={finish}
          >
            <X size={16} />
          </button>
        </header>
        <h3 className="mt-3 mb-1.5 text-base font-semibold text-ink">
          {steps[index].title}
        </h3>
        <p className="mb-4 text-[13px] leading-relaxed text-muted">
          {steps[index].text}
        </p>
        <footer className="flex flex-wrap items-center justify-between gap-2">
          <button className="btn btn-ghost btn-sm" onClick={finish}>
            {t("guide_tourSkip")}
          </button>
          <div className="flex gap-2">
            {index > 0 && (
              <button
                className="btn btn-secondary btn-sm"
                onClick={() => setIndex(index - 1)}
              >
                <ArrowRight size={15} /> {t("guide_tourPrev")}
              </button>
            )}
            <button
              className="btn btn-primary btn-sm"
              onClick={() =>
                index === steps.length - 1 ? finish() : setIndex(index + 1)
              }
            >
              {index === steps.length - 1 ? (
                <Check size={16} />
              ) : (
                <ArrowLeft size={16} />
              )}{" "}
              {index === steps.length - 1
                ? t("guide_tourFinish")
                : t("guide_tourNext")}
            </button>
          </div>
        </footer>
        <small className="mt-3 flex items-start gap-1.5 border-t border-line pt-3 text-xs leading-relaxed text-muted">
          <Compass size={13} className="mt-0.5 shrink-0" />{" "}
          {t("guide_tourAutoNote")}
        </small>
      </section>
    </div>
  );
}
