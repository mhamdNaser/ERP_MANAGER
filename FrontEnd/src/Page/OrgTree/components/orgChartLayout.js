/**
 * تخطيط شجرة تنظيمية من ثلاث طبقات: الإدارة ← الأفرع ← الأقسام.
 *
 * الاتجاه من اليمين إلى اليسار موافقةً لاتجاه الواجهة: الإدارة في أقصى
 * اليمين، وكل مستوى تالٍ إلى يسار سابقه، والإخوة يتراصّون عمودياً.
 *
 * الخوارزمية تمنع التداخل بالبناء لا بالتصحيح بعده: ارتفاع كل عقدة يساوي
 * مجموع ارتفاعات أبنائها مع الفواصل بينهم، فلا يتقاطع فرعان مهما كثرت
 * أقسامهما. ثم يُوضع الأب في منتصف مدى أبنائه.
 */

export const NODE_WIDTH = 168;
export const NODE_HEIGHT = 56;
/** المسافة الأفقية بين مستوى ومستوى — تتسع لناقل خطوط الوصل في منتصفها. */
export const GAP_LEVEL = 56;
/** المسافة العمودية بين الإخوة. */
export const GAP_SIBLING = 12;
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

/** ارتفاع الشجرة الفرعية: أطول من عقدةٍ واحدة فقط إن كان لها أبناء. */
function measure(node) {
  if (node.children.length === 0) {
    node.height = NODE_HEIGHT;
    return node.height;
  }

  const childrenHeight =
    node.children.reduce((total, child) => total + measure(child), 0) +
    GAP_SIBLING * (node.children.length - 1);

  node.height = Math.max(NODE_HEIGHT, childrenHeight);
  return node.height;
}

/** عدد المستويات، لحساب عرض اللوحة قبل وضع العقد. */
function levelCount(node) {
  if (node.children.length === 0) return 1;
  return 1 + Math.max(...node.children.map(levelCount));
}

/** يضع كل عقدة: الأب في منتصف مدى أبنائه، والأبناء متتابعين بلا تداخل. */
function place(node, top, depth, xFor, out) {
  node.x = xFor(depth);

  if (node.children.length === 0) {
    node.y = top + (node.height - NODE_HEIGHT) / 2;
    out.push(node);
    return;
  }

  let cursor = top;
  node.children.forEach((child) => {
    place(child, cursor, depth + 1, xFor, out);
    cursor += child.height + GAP_SIBLING;
  });

  const first = node.children[0];
  const last = node.children[node.children.length - 1];
  node.y = (first.y + last.y + NODE_HEIGHT) / 2 - NODE_HEIGHT / 2;
  out.push(node);
}

/** خطوط الوصل: خروج من يسار الأب، ثم ناقلٌ عمودي، ثم دخول إلى يمين كل ابن. */
function connectors(node, lines) {
  if (node.children.length === 0) return;

  const parentLeft = node.x;
  const busX = parentLeft - GAP_LEVEL / 2;
  const parentMiddle = node.y + NODE_HEIGHT / 2;

  lines.push({ x1: parentLeft, y1: parentMiddle, x2: busX, y2: parentMiddle });

  const middles = node.children.map((child) => child.y + NODE_HEIGHT / 2);
  if (middles.length > 1) {
    lines.push({
      x1: busX,
      y1: Math.min(...middles),
      x2: busX,
      y2: Math.max(...middles),
    });
  }

  node.children.forEach((child) => {
    const middle = child.y + NODE_HEIGHT / 2;
    lines.push({ x1: busX, y1: middle, x2: child.x + NODE_WIDTH, y2: middle });
    connectors(child, lines);
  });
}

/** يعيد العقد بمواضعها، وخطوط الوصل، وأبعاد اللوحة. */
export function layoutTree(root) {
  measure(root);

  const levels = levelCount(root);
  const width = PADDING * 2 + levels * NODE_WIDTH + (levels - 1) * GAP_LEVEL;
  // العمق يمضي يساراً: المستوى صفر في أقصى اليمين.
  const xFor = (depth) =>
    width - PADDING - NODE_WIDTH - depth * (NODE_WIDTH + GAP_LEVEL);

  const nodes = [];
  place(root, PADDING, 0, xFor, nodes);

  const lines = [];
  connectors(root, lines);

  return { nodes, lines, width, height: root.height + PADDING * 2 };
}
