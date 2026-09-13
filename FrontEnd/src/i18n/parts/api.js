// Translations for API-layer messages (src/Api/http.js) — user-facing errors
// thrown outside React and surfaced via toasts.
export default {
  ar: {
    api_requestFailed: "تعذر إكمال الطلب، حاول مرة أخرى.",
    api_timeout:
      "انتهت مهلة الطلب قبل اكتماله. حاول رفع ملفات أقل أو تحقق من سرعة الاتصال.",
    api_payloadTooLarge:
      "حجم الملفات أكبر من الحد المسموح على السيرفر. يجب رفع حد Nginx/PHP أو تقليل حجم الدفعة.",
    api_requestTooLarge: "حجم الطلب أكبر من الحد المسموح على السيرفر.",
    api_blobFailed:
      "فشل تحميل محتوى الملف من السيرفر رغم قبول الطلب. تحقق من إعدادات Nginx أو حجم الملف أو اكتمال الملف على التخزين.",
    api_archiveConnFailed: "تعذر الاتصال بالسيرفر أثناء تجهيز الملف المضغوط.",
    api_archiveTimeout: "انتهت مهلة تجهيز الملف المضغوط قبل اكتماله.",
    api_uploadConnFailed: "تعذر الاتصال بالسيرفر أثناء رفع الملفات.",
    api_uploadTimeout:
      "انتهت مهلة الرفع قبل اكتماله. حاول رفع ملفات أقل أو تحقق من سرعة الاتصال.",
  },
  en: {
    api_requestFailed: "The request could not be completed. Please try again.",
    api_timeout:
      "The request timed out before completing. Try fewer files or check your connection speed.",
    api_payloadTooLarge:
      "The files exceed the server limit. Raise the Nginx/PHP limit or reduce the batch size.",
    api_requestTooLarge: "The request exceeds the server size limit.",
    api_blobFailed:
      "Failed to load the file content from the server even though the request was accepted. Check the Nginx settings, the file size, or whether the file is complete in storage.",
    api_archiveConnFailed:
      "Could not reach the server while preparing the archive.",
    api_archiveTimeout: "Preparing the archive timed out before completing.",
    api_uploadConnFailed: "Could not reach the server while uploading files.",
    api_uploadTimeout:
      "The upload timed out before completing. Try fewer files or check your connection speed.",
  },
};
