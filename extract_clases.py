# -*- coding: utf-8 -*-
import zipfile, re

docx_path = u"C:/Users/OsirisTK/Downloads/Parque Espa\xf1a Final.docx"
with zipfile.ZipFile(docx_path) as z:
    with z.open("word/document.xml") as f:
        content = f.read().decode("utf-8")

paragraphs = re.findall(r"<w:p[ >].*?</w:p>", content, re.DOTALL)

texts = []
for p in paragraphs:
    p_clean = re.sub(r"<w:instrText[^>]*>.*?</w:instrText>", "", p, flags=re.DOTALL)
    runs = re.findall(r"<w:t[^>]*>(.*?)</w:t>", p_clean, re.DOTALL)
    text = "".join(runs).strip()
    if text:
        texts.append(text)

occurrences = []
for i, t in enumerate(texts):
    if t.strip() == "Clases":
        occurrences.append(i)

# Use the last occurrence (actual content, not TOC)
if occurrences:
    i = occurrences[-1]
    end = min(len(texts), i+80)
    for j in range(i, end):
        print(texts[j].encode("utf-8", "replace").decode("utf-8"))
