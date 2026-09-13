import { Download, FileText } from "lucide-react";
import { useLanguage } from "../../../Provider/LanguageContext";

/**
 * معاينة ملف المراسلة الأصلي داخل تفاصيل المعالجة (PDF أو صورة).
 */
export function StageAttachmentPreview({ correspondence }) {
  const { t } = useLanguage();
  const url = correspondence.attachment_url;
  const name = correspondence.attachment_name || "";
  const isPdf = /\.pdf$/i.test(name) || (!!url && !/\.(png|jpe?g|gif|webp)$/i.test(name));
  const isImage = /\.(png|jpe?g|gif|webp)$/i.test(name);

  return (
    <section className="flex flex-col gap-3">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <h4 className="text-sm font-semibold text-ink">{t("formal_attachmentPreviewTitle")}</h4>
          <p className="text-xs text-muted">{url ? (name || t("formal_attachmentPreviewHint")) : t("formal_attachmentPreviewHint")}</p>
        </div>
        {url && (
          <a className="btn btn-secondary btn-sm no-underline" href={url} target="_blank" rel="noreferrer" download={name}>
            <Download size={14} />
            {t("formal_openAttachment")}
          </a>
        )}
      </div>

      {!url ? (
        <div className="empty-state">
          <FileText size={28} className="text-muted" />
          <b className="text-sm font-semibold text-ink">{t("formal_noAttachment")}</b>
          <p>{t("formal_noAttachmentHint")}</p>
        </div>
      ) : isImage ? (
        <img className="max-h-[560px] w-full rounded border border-line bg-canvas object-contain" src={url} alt={name} />
      ) : isPdf ? (
        <iframe className="min-h-[540px] w-full rounded border border-line bg-surface" src={url} title={name || "correspondence file"} />
      ) : (
        <div className="empty-state">
          <FileText size={28} className="text-muted" />
          <b className="text-sm font-semibold text-ink">{name}</b>
          <p>{t("formal_previewUnavailable")}</p>
        </div>
      )}
    </section>
  );
}
