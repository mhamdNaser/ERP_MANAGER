import { Download, FileText, Printer, X } from "lucide-react";
import { useRef } from "react";
import { useLanguage } from "../../../Provider/LanguageContext";
import { fleetDuration, fleetTypeLabelKeys } from "../fleetUtils";

function InfoRow({ label, children }) {
  return (
    <p className="flex flex-col gap-1 rounded border border-line bg-surface p-3">
      <small className="text-xs font-semibold text-muted">{label}</small>
      <b className="text-[13px] font-semibold break-words text-ink">{children}</b>
    </p>
  );
}

/** معاينة وثيقة المهمة المعتمدة وطباعتها. */
export function FleetDocumentModal({ item, close }) {
  const { t } = useLanguage();
  const frameRef = useRef(null);

  const print = () => {
    const frame = frameRef.current;
    if (!frame) return;
    // الطباعة من داخل الإطار تحفظ ترويسة الوثيقة كما هي.
    try {
      frame.contentWindow.focus();
      frame.contentWindow.print();
    } catch {
      window.open(item.pdf_url, "_blank", "noreferrer");
    }
  };

  return (
    <div className="overlay grid place-items-center overflow-y-auto">
      <section className="modal max-w-6xl">
        <header className="modal-head">
          <div className="min-w-0">
            <small className="eyebrow">{t("fleet_documentEyebrow")}</small>
            <h2 className="mt-1 text-base font-semibold break-words text-ink">{item.reference_code}</h2>
            <p className="text-xs text-muted">{item.user?.name}</p>
          </div>
          <button className="btn-icon shrink-0" onClick={close} aria-label={t("close")}><X size={16} /></button>
        </header>
        <div className="modal-body">
          <div className="grid grid-cols-1 items-start gap-4 lg:grid-cols-[minmax(230px,290px)_minmax(0,1fr)]">
            <aside className="flex flex-col gap-2 rounded border border-line bg-subtle p-3">
              <InfoRow label={t("fleet_typeMission")}>{t(fleetTypeLabelKeys[item.type] || item.type)}</InfoRow>
              <InfoRow label={t("fleet_employeeLabel")}>{item.user?.name || "—"}</InfoRow>
              <InfoRow label={t("fleet_documentNumber")}>{item.document_number || "—"}</InfoRow>
              <InfoRow label={t("fleet_startDate")}>{fleetDuration(t, item)}</InfoRow>
              <InfoRow label={t("fleet_destination")}>{item.destination || "—"}</InfoRow>
              <InfoRow label={t("fleet_reason")}>{item.reason}</InfoRow>

              <div className="mt-1 flex flex-wrap gap-1.5">
                {item.pdf_url && (
                  <>
                    <button type="button" className="btn btn-primary btn-sm" onClick={print}>
                      <Printer className="h-3.5 w-3.5" />
                      {t("fleet_print")}
                    </button>
                    <a className="btn btn-secondary btn-sm no-underline" href={item.pdf_url} target="_blank" rel="noreferrer" download>
                      <Download className="h-3.5 w-3.5" />
                      PDF
                    </a>
                  </>
                )}
                {item.word_url && (
                  <a className="btn btn-secondary btn-sm no-underline" href={item.word_url} target="_blank" rel="noreferrer" download>
                    <Download className="h-3.5 w-3.5" />
                    Word
                  </a>
                )}
              </div>
            </aside>

            <main className="min-w-0">
              {item.pdf_url ? (
                <iframe
                  ref={frameRef}
                  className="min-h-[620px] w-full rounded border border-line bg-surface"
                  src={item.pdf_url}
                  title={item.reference_code}
                />
              ) : (
                <div className="empty-state">
                  <FileText size={28} className="text-muted" />
                  <b className="text-sm font-semibold text-ink">{t("fleet_documentNotReady")}</b>
                  <p>{t("fleet_documentNotReadyHint")}</p>
                </div>
              )}
            </main>
          </div>
        </div>
      </section>
    </div>
  );
}
