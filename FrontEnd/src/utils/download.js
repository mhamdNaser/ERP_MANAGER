/**
 * يحفظ blob في ملف على جهاز المستخدم.
 *
 * الرابط يُضاف إلى الصفحة قبل النقر لأن فايرفوكس يتجاهل نقرة رابط خارجها،
 * ويُحرَّر بعد مهلة قصيرة لا فوراً، لأن التحرير الفوري قد يقطع التنزيل
 * قبل أن يبدأ المتصفح بقراءته.
 */
export function downloadBlob(blob, name) {
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = name;
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1200);
}
