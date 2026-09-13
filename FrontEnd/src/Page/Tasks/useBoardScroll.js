import { useEffect, useRef, useState } from "react";

// تقليب أعمدة اللوحة بسهمين بدل شريط التمرير: المراحل السبع أوسع من الشاشة،
// فيظهر سهم على كل جهة تخبّئ مرحلة، وضغطه ينقل عمودًا واحدًا نحوها — كتقليب
// المنتجات في المتاجر. السهم يختفي عند بلوغ الطرف فلا يبقى زر لا يفعل شيئًا.
//
// كل الحسابات تجري بالمسافة المطلقة عن البداية (Math.abs) لأن scrollLeft في
// الاتجاه من اليمين لليسار يبدأ صفرًا ويصير سالبًا، بينما يبدأ صفرًا ويصير
// موجبًا في الاتجاه المعاكس — واللوحة تعمل باللغتين.
// `ready` تخبر الخطّاف متى ركّبت اللوحة فعلًا: الصفحة تعرض شاشة تحميل قبل وصول
// البيانات، فلا يوجد عنصر لقياسه عند أول تركيب للمكوّن.
export function useBoardScroll(columnCount, ready) {
  const board = useRef(null);
  // فهرس المرحلة المخبّأة خلف كل طرف، أو null إذا لم يبقَ شيء في تلك الجهة.
  const [hidden, setHidden] = useState({ start: null, end: null });

  const measure = () => {
    const element = board.current;
    if (!element) return;
    const step = columnStep(element);
    const progress = Math.abs(element.scrollLeft);
    const remaining = element.scrollWidth - element.clientWidth;
    const first = Math.round(progress / step);
    const last = Math.ceil((progress + element.clientWidth) / step) - 1;

    setHidden({
      start: progress > 1 ? Math.max(0, first - 1) : null,
      end: progress < remaining - 1 ? Math.min(columnCount - 1, last + 1) : null,
    });
  };

  useEffect(() => {
    const element = board.current;
    if (!element) return;
    measure();
    // تغيّر عرض الإطار (حجم النافذة، طي الشريط الجانبي) يغيّر عدد الأعمدة الظاهرة.
    const observer = new ResizeObserver(measure);
    observer.observe(element);
    return () => observer.disconnect();
  }, [columnCount, ready]); // eslint-disable-line react-hooks/exhaustive-deps

  // direction: ‎-1 نحو بداية المسار، ‎+1 نحو نهايته. الإشارة الفيزيائية تُقلب في
  // الواجهة العربية لأن البداية فيها على اليمين.
  const move = (direction) => {
    const element = board.current;
    if (!element) return;
    const rtl = getComputedStyle(element).direction === "rtl";
    element.scrollBy({
      left: columnStep(element) * direction * (rtl ? -1 : 1),
      behavior: "smooth",
    });
  };

  return {
    board,
    hidden,
    onScroll: measure,
    toPrevious: () => move(-1),
    toNext: () => move(1),
  };
}

// عرض خطوة واحدة = عرض العمود + الفجوة، مقيسًا من العمودين الأولين بدل افتراض
// قيمة ثابتة تفترق عن التنسيق لو تغيّر لاحقًا.
function columnStep(element) {
  const [first, second] = element.children;
  if (first && second)
    return Math.abs(second.offsetLeft - first.offsetLeft) || first.offsetWidth;
  return first?.offsetWidth || element.clientWidth;
}
