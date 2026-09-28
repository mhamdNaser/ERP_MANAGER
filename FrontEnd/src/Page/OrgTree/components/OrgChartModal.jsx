import { Image, Printer, X } from "lucide-react";
import { useMemo, useState } from "react";
import { useLanguage } from "../../../Provider/LanguageContext";
import { OrgChart } from "./OrgChart";
import { downloadChartPng, printChart } from "./orgChartExport";
import { buildTree, layoutTree } from "./orgChartLayout";

/** معاينة المخطط التنظيمي كاملاً في نافذة، مع تصديره للطباعة PDF أو صورة. */
export function OrgChartModal({ branches, departments, close, notify }) {
  const { t } = useLanguage();
  const [saving, setSaving] = useState(false);

  const chart = useMemo(
    () =>
      layoutTree(
        buildTree(branches, departments, t("orgChartRoot"), t("orgChartLooseHint")),
      ),
    [branches, departments, t],
  );

  const isEmpty = branches.length === 0 && departments.length === 0;
  const title = t("orgChartTitle");

  const savePng = async () => {
    setSaving(true);
    try {
      await downloadChartPng(chart, title);
      notify?.(t("orgChartImageSaved"), "success");
    } catch {
      notify?.(t("orgChartExportFailed"), "error");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="overlay grid place-items-center overflow-y-auto">
      <section className="modal max-w-[96vw]">
        <header className="modal-head">
          <div className="min-w-0">
            <small className="eyebrow">{t("orgTreeEyebrow")}</small>
            <h2 className="mt-1 text-base font-semibold text-ink">{title}</h2>
            <p className="text-xs text-muted">{t("orgChartHint")}</p>
          </div>
          <div className="flex shrink-0 items-center gap-1.5">
            {!isEmpty && (
              <>
                <button
                  type="button"
                  className="btn btn-secondary btn-sm"
                  onClick={() => printChart(chart, title)}
                >
                  <Printer size={14} />
                  {t("orgChartExportPdf")}
                </button>
                <button
                  type="button"
                  className="btn btn-secondary btn-sm"
                  disabled={saving}
                  onClick={savePng}
                >
                  <Image size={14} />
                  {saving ? t("saving") : t("orgChartExportImage")}
                </button>
              </>
            )}
            <button className="btn-icon" onClick={close} aria-label={t("close")}>
              <X size={16} />
            </button>
          </div>
        </header>

        <div className="modal-body">
          {isEmpty ? (
            <p className="py-8 text-center text-xs text-muted">
              {t("orgChartEmpty")}
            </p>
          ) : (
            // الشجرة قد تتجاوز النافذة في الاتجاهين، فتُمرَّر بدل أن تُضغط وتتشابك.
            <div className="overflow-auto">
              <OrgChart chart={chart} />
            </div>
          )}
        </div>
      </section>
    </div>
  );
}
