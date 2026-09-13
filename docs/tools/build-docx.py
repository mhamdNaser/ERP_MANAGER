"""
يولّد نسخة Word (.docx) منسّقة من مصادر Markdown في docs/_markdown.

    python docs/tools/build-docx.py

الآلية: Markdown -> HTML منسّق RTL -> Microsoft Word (COM) -> .docx
الناتج يوضع في نفس بنية المجلدات داخل docs/ بجانب المصادر.

المتطلبات: Windows + Microsoft Word مثبّت + حزمة python: pip install markdown
"""

import os
import sys
import shutil
import tempfile

try:
    import markdown
except ImportError:
    sys.exit("ينقص: pip install markdown")

try:
    import win32com.client as win32
except ImportError:
    win32 = None

DOCS = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SOURCE = os.path.join(DOCS, "_markdown")

WD_FORMAT_DOCX = 16

CSS = """
@page { size: A4; margin: 2cm 2.2cm; }
body {
  font-family: 'Segoe UI', 'Noto Sans Arabic', Tahoma, Arial, sans-serif;
  font-size: 11pt; line-height: 1.75; color: #17201d;
  direction: rtl; text-align: right;
}
h1 {
  font-size: 20pt; color: #06312d; border-bottom: 3px solid #0d655b;
  padding-bottom: 6px; margin: 0 0 16px;
}
h2 {
  font-size: 15pt; color: #06312d; border-bottom: 1px solid #cfded9;
  padding-bottom: 4px; margin: 22px 0 10px;
}
h3 { font-size: 13pt; color: #0a544c; margin: 18px 0 8px; }
h4 { font-size: 11.5pt; color: #0a544c; margin: 14px 0 6px; }
p  { margin: 0 0 9px; }
ul, ol { margin: 0 0 10px; padding-right: 22px; }
li { margin-bottom: 4px; }
strong { color: #06312d; }
table {
  width: 100%; border-collapse: collapse; margin: 10px 0 16px;
  font-size: 9.5pt; direction: rtl;
}
th, td {
  border: 1px solid #cfded9; padding: 6px 8px;
  text-align: right; vertical-align: top;
}
th { background: #06312d; color: #ffffff; font-weight: 700; }
tbody tr:nth-child(even) td { background: #f4f9f7; }
pre {
  background: #f5f8f7; border: 1px solid #dfe6e4; border-right: 4px solid #0d655b;
  padding: 9px 11px; margin: 0 0 12px;
  direction: ltr; text-align: left;
  font-family: Consolas, 'Courier New', monospace; font-size: 8.5pt;
  line-height: 1.45; white-space: pre-wrap; word-wrap: break-word;
}
code {
  font-family: Consolas, 'Courier New', monospace; font-size: 9pt;
  background: #eef5f3; padding: 1px 4px; direction: ltr; unicode-bidi: embed;
}
pre code { background: none; padding: 0; font-size: 8.5pt; }
blockquote {
  border-right: 4px solid #a9691a; background: #fdf5e9;
  margin: 0 0 12px; padding: 8px 12px; color: #5a4520;
}
blockquote p { margin: 0 0 4px; }
hr { border: none; border-top: 1px solid #cfded9; margin: 18px 0; }
a { color: #0d655b; }
"""


def html_for(md_text, title):
    body = markdown.markdown(
        md_text,
        extensions=["tables", "fenced_code", "sane_lists", "nl2br"],
    )
    return (
        '<!doctype html><html dir="rtl" lang="ar"><head><meta charset="utf-8">'
        f"<title>{title}</title><style>{CSS}</style></head>"
        f"<body>{body}</body></html>"
    )


def collect():
    """يعيد قائمة (مسار md، مسار docx الهدف)."""
    jobs = []
    for root, _dirs, files in os.walk(SOURCE):
        for name in sorted(files):
            if not name.endswith(".md"):
                continue
            md_path = os.path.join(root, name)
            rel = os.path.relpath(md_path, SOURCE)
            docx_path = os.path.join(DOCS, os.path.splitext(rel)[0] + ".docx")
            jobs.append((md_path, docx_path))
    return jobs


def main():
    if not os.path.isdir(SOURCE):
        sys.exit(f"لا يوجد مجلد المصادر: {SOURCE}")

    jobs = collect()
    if not jobs:
        sys.exit("لا توجد ملفات Markdown في مجلد المصادر.")

    if win32 is None:
        sys.exit("ينقص: pip install pywin32  (مطلوب لتشغيل Microsoft Word)")

    temp_dir = tempfile.mkdtemp(prefix="cnd-docs-")
    word = win32.Dispatch("Word.Application")
    word.Visible = False
    word.DisplayAlerts = 0

    done = 0
    try:
        for md_path, docx_path in jobs:
            with open(md_path, encoding="utf-8") as handle:
                md_text = handle.read()

            title = os.path.splitext(os.path.basename(md_path))[0]
            html_path = os.path.join(temp_dir, f"page-{done}.html")
            with open(html_path, "w", encoding="utf-8") as handle:
                handle.write(html_for(md_text, title))

            os.makedirs(os.path.dirname(docx_path), exist_ok=True)
            if os.path.exists(docx_path):
                os.remove(docx_path)

            document = word.Documents.Open(html_path, False, True)
            try:
                document.SaveAs2(docx_path, WD_FORMAT_DOCX)
            finally:
                document.Close(False)

            done += 1
            print(f"[{done}/{len(jobs)}] {os.path.relpath(docx_path, DOCS)}")
    finally:
        word.Quit()
        shutil.rmtree(temp_dir, ignore_errors=True)

    print(f"\nتم توليد {done} ملف Word في {DOCS}")


if __name__ == "__main__":
    main()
