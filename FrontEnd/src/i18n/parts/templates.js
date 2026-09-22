export const templates = {
  ar: {
    navDocumentTemplates: "قوالب الوثائق",

    tpl_eyebrow: "قوالب Word",
    tpl_intro:
      "كل وثيقة يصدرها النظام تُبنى من قالب Word. استبدل أي قالب بملف جديد يحلّ مكانه بالاسم نفسه، وتبقى النسخة السابقة محفوظة للتراجع.",
    tpl_refresh: "تحديث",
    tpl_empty: "لا قوالب معرَّفة.",

    tpl_present: "موجود",
    tpl_absent: "غير موجود",

    tpl_fileName: "اسم الملف",
    tpl_size: "الحجم",
    tpl_updatedAt: "آخر تحديث",

    tpl_fields: "الحقول التي تملؤها الوثيقة",
    tpl_fieldPresent: "موجود في القالب",
    tpl_fieldMissing: "ناقص من القالب",
    tpl_missingWarning: "ينقص القالب {count} حقل — ستخرج الوثيقة ناقصةً في مواضعها.",

    tpl_replace: "استبدال",
    tpl_download: "تنزيل الحالي",
    tpl_blank: "النسخة الفارغة",
    tpl_blankHint: "النموذج الورقي الفارغ للطباعة والتعبئة باليد.",
    tpl_replacedToast: "استُبدل القالب، والنسخة السابقة محفوظة.",

    tpl_missingTitle: "القالب ينقصه حقول",
    tpl_missingConfirm:
      "الحقول التالية غير موجودة في الملف المرفوع، وستبقى مواضعها فارغة في الوثيقة. أتريد المتابعة؟",
    tpl_replaceAnyway: "استبدل على أي حال",

    tpl_previousVersions: "النسخ السابقة",
    tpl_restore: "استعادة",
    tpl_restoreConfirm: "ستحلّ هذه النسخة محل القالب الحالي، والحالي يُحفظ قبلها.",
    tpl_restoredToast: "أُعيدت النسخة السابقة.",
  },
  en: {
    navDocumentTemplates: "Document templates",

    tpl_eyebrow: "Word templates",
    tpl_intro:
      "Every document the system issues is built from a Word template. Replace any template with a new file that takes its place under the same name; the previous version is kept for rollback.",
    tpl_refresh: "Refresh",
    tpl_empty: "No templates defined.",

    tpl_present: "Present",
    tpl_absent: "Missing",

    tpl_fileName: "File name",
    tpl_size: "Size",
    tpl_updatedAt: "Last updated",

    tpl_fields: "Fields the document fills",
    tpl_fieldPresent: "Present in template",
    tpl_fieldMissing: "Missing from template",
    tpl_missingWarning: "The template is missing {count} field(s) — those spots will come out blank.",

    tpl_replace: "Replace",
    tpl_download: "Download current",
    tpl_blank: "Blank form",
    tpl_blankHint: "The blank paper form, for printing and filling by hand.",
    tpl_replacedToast: "Template replaced; the previous version is kept.",

    tpl_missingTitle: "Template is missing fields",
    tpl_missingConfirm:
      "These fields are absent from the uploaded file and will come out blank in the document. Continue?",
    tpl_replaceAnyway: "Replace anyway",

    tpl_previousVersions: "Previous versions",
    tpl_restore: "Restore",
    tpl_restoreConfirm: "This version will replace the current template; the current one is saved first.",
    tpl_restoredToast: "Previous version restored.",
  },
};
