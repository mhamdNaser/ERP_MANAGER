/**
 * تخطيط شجرة تنظيمية من ثلاث طبقات: الإدارة ← الأفرع ← الأقسام.
 *
 * الخوارزمية تمنع التداخل بالبناء لا بالتصحيح بعده: عرض كل عقدة يساوي مجموع
 * أعراض أبنائها مع الفواصل بينهم، فلا يتقاطع فرعان مهما كثرت أقسامهما. ثم
 * يُوضع الأب في منتصف مدى أبنائه.
 */

export const NODE_WIDTH = 168;
export const NODE_HEIGHT = 56;
export const GAP_X = 18;
export const GAP_Y = 46;
export const PADDING = 16;

/** يبني الشجرة من قوائم الأفرع والأقسام كما يعيدها الخادم. */
export function buildTree(branches, departments, rootLabel, looseLabel) {
  const inBranch = (branchId) =>
    departments
      .filter((department) => department.branch_id === branchId)
      .map((department) => ({
        id: `department-${department.id}`,
        label: department.name,
        kind: "department",
        inactive: department.is_active === false,
        children: [],
      }));

  const branchNodes = branches.map((branch) => ({
    id: `branch-${branch.id}`,
    label: branch.name,
    meta: branch.code,
    kind: "branch",
    inactive: branch.is_active === false,
    children: inBranch(branch.id),
  }));

  // الأقسام بلا فرع تتبع الإدارة مباشرةً، فتقف صفّاً مع الأفرع لا تحتها.
  const loose = inBranch(null);

  return {
    id: "root",
    label: rootLabel,
    meta: loose.length ? looseLabel : undefined,
    kind: "root",
    children: [...branchNodes, ...loose],
  };
}

/** عرض الشجرة الفرعية: أوسع من عقدةٍ واحدة فقط إن كان لها أبناء. */
function measure(node) {
  if (node.children.length === 0) {
    node.width = NODE_WIDTH;
    return node.width;
  }

  const childrenWidth =
    node.children.reduce((total, child) => total + measure(child), 0) +
    GAP_X * (node.children.length - 1);

  node.width = Math.max(NODE_WIDTH, childrenWidth);
  return node.width;
}

/** يضع كل عقدة: الأب في منتصف مدى أبنائه، والأبناء متتابعين بلا تداخل. */
function place(node, left, depth, out) {
  const top = PADDING + depth * (NODE_HEIGHT + GAP_Y);

  if (node.children.length === 0) {
    node.x = left + (node.width - NODE_WIDTH) / 2;
    node.y = top;
    out.push(node);
    return;
  }

  let cursor = left;
  node.children.forEach((child) => {
    place(child, cursor, depth + 1, out);
    cursor += child.width + GAP_X;
  });

  const first = node.children[0];
  const last = node.children[node.children.length - 1];
  node.x = (first.x + last.x + NODE_WIDTH) / 2 - NODE_WIDTH / 2;
  node.y = top;
  out.push(node);
}

/** خطوط الوصل: نزول من الأب، ثم ناقلٌ أفقي، ثم نزول إلى كل ابن. */
function connectors(node, lines) {
  if (node.children.length === 0) return;

  const parentBottom = node.y + NODE_HEIGHT;
  const busY = parentBottom + GAP_Y / 2;
  const parentCenter = node.x + NODE_WIDTH / 2;

  lines.push({ x1: parentCenter, y1: parentBottom, x2: parentCenter, y2: busY });

  const centers = node.children.map((child) => child.x + NODE_WIDTH / 2);
  if (centers.length > 1) {
    lines.push({
      x1: Math.min(...centers),
      y1: busY,
      x2: Math.max(...centers),
      y2: busY,
    });
  }

  node.children.forEach((child) => {
    const center = child.x + NODE_WIDTH / 2;
    lines.push({ x1: center, y1: busY, x2: center, y2: child.y });
    connectors(child, lines);
  });
}

/** يعيد العقد بمواضعها، وخطوط الوصل، وأبعاد اللوحة. */
export function layoutTree(root) {
  measure(root);

  const nodes = [];
  place(root, PADDING, 0, nodes);

  const lines = [];
  connectors(root, lines);

  const depth = root.children.some((child) => child.children.length) ? 3 : 2;

  return {
    nodes,
    lines,
    width: root.width + PADDING * 2,
    height: PADDING * 2 + depth * NODE_HEIGHT + (depth - 1) * GAP_Y,
  };
}
