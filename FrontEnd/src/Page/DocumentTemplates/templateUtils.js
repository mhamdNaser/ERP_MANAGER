export function formatBytes(size) {
  if (size == null) return "—";
  if (size < 1024) return `${size} B`;
  if (size < 1048576) return `${(size / 1024).toFixed(1)} KB`;
  return `${(size / 1048576).toFixed(1)} MB`;
}

export function formatMoment(value) {
  if (!value) return "—";
  return String(value).replace("T", " ").slice(0, 16);
}

/** يجمع القوالب تحت مجموعاتها بترتيب ورودها من الخادم. */
export function groupTemplates(templates) {
  const groups = [];

  templates.forEach((template) => {
    const found = groups.find((group) => group.name === template.group);
    if (found) {
      found.items.push(template);
      return;
    }

    groups.push({ name: template.group, items: [template] });
  });

  return groups;
}
