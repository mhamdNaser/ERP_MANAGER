"""
يحوّل نموذجَي الإجازة الورقيين إلى قالبَي Word قابلين للتعبئة الآلية.

    python scripts/build-hr-templates.py [مجلد المصدر]

النموذجان الأصليان ورقيان: خطوط منقّطة تُملأ بالقلم. هذا السكربت يستبدل
كل خط منقّط بحقل {{...}} يفهمه DocxTemplateRenderer، ويحتفظ بالأصل الفارغ
كما هو كي يبقى قابلاً للطباعة والتعبئة اليدوية.

المخرجات:
    resources/templates/documents/hr/leave-request.docx          للتعبئة الآلية
    resources/templates/documents/hr/hourly-leave-request.docx   للتعبئة الآلية
    resources/templates/documents/hr/blank/*.docx                الأصل الفارغ

المصدر: مجلد يحوي «اجازة.docx» و«ساعية اجازة.docx». إن لم يُمرَّر مجلد،
يُبحث عنهما في جذر المشروع ثم في مجلد blank (لإعادة البناء بعد تعديل الأصل).
"""

import os
import re
import shutil
import sys
import zipfile

sys.stdout.reconfigure(encoding="utf-8")

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TEMPLATES = os.path.join(ROOT, "BackEnd", "resources", "templates", "documents", "hr")
BLANK = os.path.join(TEMPLATES, "blank")

TEXT_NODE = re.compile(r"(<w:t(?: [^>]*)?>)(.*?)(</w:t>)", re.S)
DOTS_ONLY = re.compile(r"^[\s.،]*\.{2,}[\s.،]*$")
# خانة تاريخ كاملة داخل عقدة واحدة: «......../ ......../2026 م».
# الشرطة شرطٌ لازم، وإلا التبس خطٌّ منقّط عادي بخانة تاريخ.
DATE_BLANK = re.compile(r"^[\s.]*\.{2,}\s*/[\s./]*\.{2,}[\s./]*(?:2026)?[\s.م/]*$")


def nodes_of(xml):
    """كل عُقد النص في المستند بترتيب ورودها."""
    return [(m.start(), m.end(), m.group(1), m.group(2), m.group(3)) for m in TEXT_NODE.finditer(xml)]


def rebuild(xml, nodes, texts):
    """يعيد بناء الـXML بنصوص جديدة، من آخر عقدة إلى أولها كي تبقى المواضع صالحة."""
    for (start, end, open_tag, _old, close_tag), text in sorted(zip(nodes, texts), key=lambda p: -p[0][0]):
        tag = open_tag
        # المسافات في بداية النص أو نهايته تضيع بلا xml:space="preserve".
        if text != text.strip() and "xml:space" not in tag:
            tag = tag[:-1] + ' xml:space="preserve">'
        xml = xml[:start] + tag + text + close_tag + xml[end:]
    return xml


def fill_after_label(texts, index, placeholder):
    """يضع الحقل في أول خط منقّط بعد العنوان، ويمحو بقية الخطوط المتّصلة به."""
    cursor = index + 1
    placed = False

    while cursor < len(texts):
        current = texts[cursor]

        if DATE_BLANK.match(current) and current.strip():
            texts[cursor] = (" " + placeholder + " ") if not placed else ""
            placed = True
            break

        if DOTS_ONLY.match(current):
            texts[cursor] = (" " + placeholder + " ") if not placed else ""
            placed = True
            cursor += 1
            continue

        # عقدة تبدأ بنقاط ثم تُكمل نصاً: تُقتطع النقاط وحدها.
        stripped = re.sub(r"^[\s.]*\.{2,}", "", current)
        if stripped != current:
            texts[cursor] = (" " + placeholder + " " if not placed else "") + stripped
            placed = True

        break

    return placed


def clear_separators(texts, index, span=6):
    """شرطتا «/ /» قبل السنة تذهبان لأن الحقل يحمل التاريخ كاملاً."""
    for back in range(max(0, index - span), index):
        if texts[back].strip() == "/":
            texts[back] = ""


def looks_back(texts, index, needle, span=4):
    """هل ورد هذا النص في العقد القليلة السابقة؟ — لتمييز «بدء» من «انتهاء»."""
    return any(needle in texts[j] for j in range(max(0, index - span), index))


def transform(xml, kind):
    nodes = nodes_of(xml)
    texts = [node[3] for node in nodes]

    for index, text in enumerate(texts):
        stripped = text.strip()

        # ---- ترويسة الكتاب: الرقم والتاريخان -------------------------------
        # «رقم : (  .  ص )» — النقطة المفردة بين القوسين هي موضع الرقم.
        if stripped == "." and looks_back(texts, index, "قم :", 10):
            texts[index] = "{{registry_number}}"
            continue

        # السنة الميلادية والهجرية تردان كعقدتين مستقلتين في الترويسة وحدها.
        if stripped == "2026":
            texts[index] = "{{gregorian_date}}"
            clear_separators(texts, index)
            continue

        if stripped == "1448":
            texts[index] = "{{hijri_date}}"
            clear_separators(texts, index)
            continue

        # ---- بيانات الموظف --------------------------------------------------
        if "الثلاثي :" in text:
            trimmed = re.sub(r"\.{2,}[\s.]*$", "", text)
            if trimmed != text:                       # النقاط داخل العقدة نفسها
                texts[index] = trimmed + " {{employee_name}} "
                for ahead in range(index + 1, min(index + 4, len(texts))):
                    if DOTS_ONLY.match(texts[ahead]):
                        texts[ahead] = ""
                    else:
                        break
            else:
                fill_after_label(texts, index, "{{employee_name}}")
            continue

        if "الوظيفي :" in text:
            fill_after_label(texts, index, "{{job_title}}")
            continue

        if "الذاتي :" in text:
            fill_after_label(texts, index, "{{employee_number}}")
            continue

        # ---- حقول تعتمد على ما قبلها ---------------------------------------
        if "الاجازة :" in text or "الإجازة :" in text:
            if looks_back(texts, index, "بدء"):
                field = "{{start_date}}" if kind == "leave" else "{{start_time}}"
            elif looks_back(texts, index, "انتهاء"):
                field = "{{end_date}}" if kind == "leave" else "{{end_time}}"
            elif looks_back(texts, index, "تاريخ يوم"):
                field = "{{date}}"
            else:
                continue

            # صيغة التاريخ «......../ ......../2026 م» قد تكون داخل العقدة نفسها.
            inline = re.sub(r"[\s.]*\.{2,}[/\s.]*\.{2,}[\s./]*2026", " " + field, text)
            if inline != text:
                texts[index] = inline
            else:
                fill_after_label(texts, index, field)
                # «2026 م» التالية تبقى «م» وحدها لأن السنة صارت ضمن الحقل.
                for ahead in range(index + 1, min(index + 4, len(texts))):
                    if "2026" in texts[ahead]:
                        texts[ahead] = texts[ahead].replace("2026", "").replace("  ", " ")
                        break
            continue

        # ---- حقول داخل نص الطلب ---------------------------------------------
        if "لمدة" in text and re.search(r"/\.{2,}/", text):
            texts[index] = re.sub(r"/\.{2,}/", "/ {{days}} /", text)

        if "بسبب" in text:
            texts[index] = re.sub(r"(بسبب\s*:)[\s.]*\.{2,}[\s.]*", r"\1 {{reason}}", texts[index])

        if "ساعية" in text and re.search(r"\.{2,}", text) and kind == "hourly":
            texts[index] = re.sub(r"[\s.]*\.{2,}[\s.]*$", " ", texts[index])

    return rebuild(xml, nodes, texts)


def build(source, target, kind):
    shutil.copyfile(source, target)

    with zipfile.ZipFile(source) as archive:
        entries = {name: archive.read(name) for name in archive.namelist()}

    entries["word/document.xml"] = transform(
        entries["word/document.xml"].decode("utf-8"), kind
    ).encode("utf-8")

    with zipfile.ZipFile(target, "w", zipfile.ZIP_DEFLATED) as archive:
        for name, payload in entries.items():
            archive.writestr(name, payload)

    return placeholders_in(target)


def placeholders_in(path):
    with zipfile.ZipFile(path) as archive:
        xml = archive.read("word/document.xml").decode("utf-8", "ignore")

    text = re.sub(r"<[^>]+>", "", xml)
    return sorted(set(re.findall(r"\{\{[a-z_]+\}\}", text)))


def plain_text(path):
    with zipfile.ZipFile(path) as archive:
        xml = archive.read("word/document.xml").decode("utf-8", "ignore")

    flat = re.sub(r"<[^>]+>", "", xml.replace("</w:p>", "\n"))

    paragraphs = []
    for raw in flat.split("\n"):
        line = " ".join(raw.split())
        if line and (not paragraphs or paragraphs[-1] != line):
            paragraphs.append(line)

    return paragraphs


FORMS = [
    ("اجازة.docx", "leave-request.docx", "leave"),
    ("ساعية اجازة.docx", "hourly-leave-request.docx", "hourly"),
]


def locate(source_dir, original, blank_name):
    for candidate in (os.path.join(source_dir, original), os.path.join(BLANK, blank_name)):
        if os.path.isfile(candidate):
            return candidate

    return None


def main():
    source_dir = sys.argv[1] if len(sys.argv) > 1 else ROOT
    os.makedirs(BLANK, exist_ok=True)

    for original, built_name, kind in FORMS:
        source = locate(source_dir, original, built_name)
        if not source:
            sys.exit(f"لم يُعثر على النموذج: {original} في {source_dir}")

        blank = os.path.join(BLANK, built_name)
        if os.path.abspath(source) != os.path.abspath(blank):
            shutil.copyfile(source, blank)

        target = os.path.join(TEMPLATES, built_name)
        found = build(blank, target, kind)

        print(f"\n=== {built_name} ===")
        print("  الأصل الفارغ :", os.path.relpath(blank, ROOT))
        print("  القالب       :", os.path.relpath(target, ROOT))
        print("  الحقول       :", " ".join(found) if found else "لا شيء!")
        print("  ---- النص بعد الحقن ----")
        for line in plain_text(target):
            print("   ", line)


if __name__ == "__main__":
    main()
