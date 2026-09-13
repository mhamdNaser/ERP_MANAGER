import { ChevronLeft, ChevronRight } from "lucide-react";
import { columns } from "./taskMeta";

// سهم تقليب على أحد طرفي اللوحة. لا يُرسم إلا حين تكون هناك مرحلة مخبّأة خلف
// ذلك الطرف، ويحمل اسمها في التلميح فيعرف المستخدم إلى أين ينقله قبل الضغط.
//
// السهم منطقي لا فيزيائي: `start` تعني بداية المسار (يمين الشاشة في العربية،
// يسارها في الإنجليزية)، ولذلك تُقلب الأيقونة مع اتجاه الصفحة.
export function BoardArrow({ side, stage, hide, onClick, t }) {
  const column = stage === null ? null : columns[stage];
  if (!column || hide) return null;

  const toStart = side === "start";
  const Chevron = toStart ? ChevronLeft : ChevronRight;
  const label = t(toStart ? "task_previousStage" : "task_nextStage", {
    stage: t(column.title),
  });

  // الزر يحاذي صف عناوين المراحل لا منتصف اللوحة: الأعمدة طويلة فيبعد منتصفها
  // كثيرًا عن أعلى الشاشة. top-2.5 يضع مركز الزر (36 بكسل) على 28 بكسل من أعلى
  // اللوحة = حشوة العمود 8 + حشوة رأسه 4 + نصف ارتفاع أيقونة المرحلة 16.
  return (
    <button
      type="button"
      className={`absolute top-2.5 z-10 inline-grid h-9 w-9 place-items-center rounded-full border border-line bg-surface text-muted hover:bg-brand-50 hover:text-brand-600 ${
        toStart ? "start-0" : "end-0"
      }`}
      onClick={onClick}
      title={label}
      aria-label={label}
    >
      <Chevron size={18} className="rtl:rotate-180" />
    </button>
  );
}
