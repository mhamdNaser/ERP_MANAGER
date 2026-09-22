import { FileStack, RefreshCw } from "lucide-react";
import { useCallback, useEffect, useMemo, useState } from "react";
import { useLanguage } from "../../Provider/LanguageContext";
import { useConfirm } from "../../Provider/ConfirmContext";
import { api, downloadBlob } from "../../lib";
import { TemplateCard } from "./components/TemplateCard";
import { groupTemplates } from "./templateUtils";

/**
 * تبويب قوالب الوثائق: كل قالب Word يبني منه النظام وثيقةً رسمية.
 * الاستبدال يكتب الملف في مسار القالب نفسه وباسمه — لأن المسار مكتوب في
 * إعدادات الخادم وتقرؤه خدمات التوليد — ويحفظ السابق كي يبقى التراجع ممكناً.
 */
export function DocumentTemplatesPage({ notify }) {
  const { t } = useLanguage();
  const confirm = useConfirm();
  const [templates, setTemplates] = useState([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(
    () =>
      api
        .documentTemplates()
        .then((data) => setTemplates(data.templates ?? []))
        .catch((error) => notify?.(error.message, "error"))
        .finally(() => setLoading(false)),
    [notify],
  );

  useEffect(() => { load(); }, [load]);

  const groups = useMemo(() => groupTemplates(templates), [templates]);

  const apply = (updated) =>
    setTemplates((items) => items.map((item) => (item.key === updated.key ? updated : item)));

  const upload = async (template, file, force = false) => {
    try {
      const result = await api.uploadDocumentTemplate(template.key, file, force);
      apply(result.template);
      notify?.(t("tpl_replacedToast"), "success");
      return true;
    } catch (error) {
      // قالب ناقص الحقول يُرفض أولاً؛ الرفع يمضي فقط إن أصرّ من يرفعه.
      const missing = error.data?.missing ?? [];
      if (error.data?.requires_force && !force) {
        const accepted = await confirm({
          title: t("tpl_missingTitle"),
          message: `${t("tpl_missingConfirm")}\n\n${missing.map((field) => `{{${field}}}`).join("  ")}`,
          confirmLabel: t("tpl_replaceAnyway"),
          danger: true,
        });

        if (accepted) return upload(template, file, true);
        return false;
      }

      notify?.(error.message, "error");
      return false;
    }
  };

  const download = async (template) => {
    try {
      downloadBlob(await api.downloadDocumentTemplate(template.key), template.file_name);
    } catch (error) {
      notify?.(error.message, "error");
    }
  };

  const blank = async (template) => {
    try {
      downloadBlob(await api.downloadBlankTemplate(template.key), `blank-${template.file_name}`);
    } catch (error) {
      notify?.(error.message, "error");
    }
  };

  const restore = async (template, backup) => {
    const accepted = await confirm({
      title: t("tpl_restore"),
      message: `${t("tpl_restoreConfirm")}\n${backup.name}`,
      confirmLabel: t("tpl_restore"),
      danger: false,
    });
    if (!accepted) return;

    try {
      const result = await api.restoreDocumentTemplate(template.key, backup.name);
      apply(result.template);
      notify?.(t("tpl_restoredToast"), "success");
    } catch (error) {
      notify?.(error.message, "error");
    }
  };

  return (
    <div className="page">
      <div className="page-header">
        <div>
          <p className="eyebrow">{t("tpl_eyebrow")}</p>
          <h1 className="page-title">{t("navDocumentTemplates")}</h1>
          <p className="page-subtitle">{t("tpl_intro")}</p>
        </div>
        <button className="btn btn-secondary btn-sm" onClick={load}>
          <RefreshCw size={15} />
          {t("tpl_refresh")}
        </button>
      </div>

      {!loading && templates.length === 0 && (
        <div className="empty-state min-h-[220px]">
          <FileStack size={28} className="text-muted" />
          <b className="text-sm font-semibold text-ink">{t("tpl_empty")}</b>
        </div>
      )}

      {groups.map((group) => (
        <section key={group.name} className="flex flex-col gap-3">
          <h2 className="text-sm font-semibold text-ink">{group.name}</h2>
          {group.items.map((template) => (
            <TemplateCard
              key={template.key}
              template={template}
              onUpload={(item, file) => upload(item, file)}
              onDownload={download}
              onBlank={blank}
              onRestore={restore}
            />
          ))}
        </section>
      ))}
    </div>
  );
}
