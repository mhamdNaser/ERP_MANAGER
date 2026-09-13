export const typeLabelKeys = {
  external_to_internal: "formal_typeExtToInt",
  internal_to_internal: "formal_typeIntToInt",
  internal_to_external: "formal_typeIntToExt",
};

// أنواع الجهات المسموح بها لكل اتجاه — مطابقة لـ FormalPartyResolver::DIRECTION_RULES.
export const directionPartyRules = {
  external_to_internal: { source: ["external_entity"], target: ["our_org"] },
  internal_to_internal: {
    source: ["office", "department", "branch", "general_manager", "diwan"],
    target: ["office", "department", "branch", "general_manager", "diwan"],
  },
  internal_to_external: {
    source: ["office", "department", "branch", "general_manager", "diwan"],
    target: ["external_entity"],
  },
};

export const partyTypeLabelKeys = {
  our_org: "formal_partyOurOrg",
  external_entity: "formal_partyExternal",
  branch: "formal_optBranch",
  department: "formal_optDepartment",
  office: "formal_optOffice",
  general_manager: "formal_optGeneralManager",
  diwan: "formal_partyDiwan",
};

// الجهة المصدرة/المخاطبة مع الرجوع إلى الأعمدة القديمة للسجلات السابقة.
export function correspondenceParty(item, side, fallback = "") {
  if (!item) return fallback;
  const label = side === "source" ? item.source_label : item.target_label;
  if (label) return label;
  if (side === "source") return item.sender_external_entity?.name || fallback;
  return (
    item.recipient_external_entity?.name ||
    item.recipient_department?.name ||
    item.recipient_branch?.name ||
    fallback
  );
}

export const statusLabelKeys = {
  open: "formal_statusOpen",
  received: "formal_statusReceived",
  routed: "formal_statusRouted",
  archived: "formal_statusArchived",
};

export function canDeleteFormal(user) {
  return isDiwanUser(user);
}

export function isDiwanUser(user) {
  if (!user) return false;
  if (user.office?.code === "REGISTRY") return true;
  const officeName = user.office?.name || "";
  return ["ديوان", "السجل", "registry"].some((keyword) =>
    officeName.toLocaleLowerCase().includes(keyword));
}

export function canCreateFormal(user) {
  return user?.role === "database_manager" || isDiwanUser(user);
}

export function canProcessCorrespondence(user, item) {
  if (!user || !item?.events?.length) return false;
  const latest = [...item.events].sort(
    (a, b) => new Date(a.created_at) - new Date(b.created_at) || Number(a.id) - Number(b.id),
  ).at(-1);

  return String(latest?.assigned_user_id || "") === String(user.id)
    || partyMatchesUser(user, latest?.target_type, latest?.target_id);
}

export function canEditCorrespondence(user, item) {
  if (typeof item?.can_edit === "boolean") return item.can_edit;
  if (!user || !item?.events?.length) return false;
  const ordered = [...item.events].sort((a, b) => Number(a.id) - Number(b.id));
  if (ordered.length !== 1) return false;
  const first = ordered[0];

  if (isInternalSource(first.source_type)) {
    return partyMatchesUser(user, first.source_type, first.source_id);
  }

  return String(item.creator_id || "") === String(user.id)
    || String(first.actor_id || "") === String(user.id);
}

export function canEditProcessing(user, event, events = []) {
  if (typeof event?.can_edit === "boolean") return event.can_edit;
  if (!user || !event) return false;
  const hasLaterProcessing = events.some((candidate) => Number(candidate.id) > Number(event.id));
  if (hasLaterProcessing) return false;

  if (isInternalSource(event.source_type)) {
    return partyMatchesUser(user, event.source_type, event.source_id);
  }

  return String(event.actor_id || "") === String(user.id);
}

function isInternalSource(type) {
  return ["diwan", "general_manager", "branch", "department", "office", "user"].includes(type);
}

export function targetOptions(directory, targetType) {
  if (!directory) return [];
  if (targetType === "general_manager") {
    return directory.general_managers?.length
      ? directory.general_managers.map((user) => ({ id: user.id, name: user.name }))
      : [{ id: "", name: "المدير العام" }];
  }
  if (targetType === "branch") return directory.branches || [];
  if (targetType === "department") {
    return (directory.branches || []).flatMap((branch) =>
      (branch.departments || []).map((department) => ({
        id: department.id,
        name: `${branch.name} / ${department.name}`,
      })),
    );
  }
  if (targetType === "office") return directory.offices || [];
  if (targetType === "external_entity") return directory.external_entities || [];
  return [];
}

export function selectedTargetLabel(directory, step) {
  if (step.target_type === "diwan") return "الديوان";
  if (step.target_type === "general_manager" && !step.target_id) return "المدير العام";
  if (step.target_type === "free_text") return step.place || "";
  return targetOptions(directory, step.target_type)
    .find((choice) => String(choice.id) === String(step.target_id))?.name || step.place || "";
}

export function defaultActionForTarget(targetType) {
  return {
    general_manager: "decision",
    branch: "study",
    department: "study",
    office: "study",
    external_entity: "reply",
    free_text: "route",
  }[targetType] || "route";
}

export function actionLabelKey(action) {
  return {
    decision: "formal_actionDecision",
    study: "formal_actionStudy",
    execution: "formal_actionExecution",
    reply: "formal_actionReply",
    route: "formal_actionRoute",
    hold: "formal_actionHold",
  }[action] || "formal_actionUnset";
}

export const decisionTypeLabelKeys = {
  reply: "formal_decTypeReply",
  route_internal: "formal_decTypeRouteInternal",
  hold: "formal_decTypeHold",
};

export const decisionStatusLabelKeys = {
  pending: "formal_decStatusPending",
  completed: "formal_decStatusCompleted",
  responded: "formal_decStatusResponded",
};

// Composes the decision label. `t` is injected because this runs outside React.
export function decisionLabel(t, type, status) {
  if (!type) return t("formal_decisionNone");
  const typeLabel = decisionTypeLabelKeys[type] ? t(decisionTypeLabelKeys[type]) : type;
  const statusLabel = decisionStatusLabelKeys[status] ? t(decisionStatusLabelKeys[status]) : status;
  return statusLabel ? `${typeLabel} · ${statusLabel}` : typeLabel;
}

export function stageTone(stage) {
  if (stage.decision_type === "hold" || stage.action_required === "hold") return "hold";
  if (stage.decision_type === "reply" || stage.action_required === "reply" || stage.target_type === "external_entity") return "reply";
  if (stage.action_required === "decision" || stage.target_type === "general_manager") return "decision";
  if (stage.action_required === "execution") return "execution";
  if (stage.action_required === "study") return "study";
  return "route";
}

export function findDocumentById(item, documentId) {
  return [
    ...(item?.documents || []),
    ...(item?.events || []).flatMap((event) => event.documents || []),
  ]
    .find((document) => String(document.id) === String(documentId));
}

// `t` is injected because this runs outside React.
export function recipientLine(t, value) {
  const text = String(value || "").trim();
  if (!text) return t("formal_recipientPrefix");
  return /^(إلى|الى)\s/.test(text) ? text : t("formal_recipientLine", { name: text });
}

export function userOwnsStage(user, stage) {
  if (!user || !stage) return false;
  if (stage.target_type === "branch") return user.role === "branch_manager" && String(stage.target_id) === String(user.branch_id);
  if (stage.target_type === "department") return user.role === "department_head" && String(stage.target_id) === String(user.department_id);
  if (stage.target_type === "office") return String(stage.target_id) === String(user.office_id);
  return false;
}

// هل ينتمي المستخدم إلى الجهة المحددة — يعكس FormalVisibilityService::partyMatchesUser.
export function partyMatchesUser(user, type, id) {
  if (!user) return false;
  const officeName = user.office?.name || "";
  switch (type) {
    case "diwan":
      return ["ديوان", "السجل", "registry"].some((keyword) => officeName.includes(keyword));
    case "general_manager":
      return user.role === "general_manager";
    case "branch":
      return String(id) === String(user.branch_id);
    case "department":
      return String(id) === String(user.department_id);
    case "office":
      return String(id) === String(user.office_id);
    case "user":
      return String(id) === String(user.id);
    default:
      return false;
  }
}

const ASSIGNER_ROLES = ["department_head", "branch_manager", "office_manager", "general_manager", "database_manager"];

export function canAssignStage(user, stage) {
  if (!user || !stage) return false;
  return ASSIGNER_ROLES.includes(user.role) && partyMatchesUser(user, stage.target_type, stage.target_id);
}

// موظفو الجهة التي وصلتها المعالجة — المرشحون للتخويل.
export function stageAssignableUsers(directory, stage) {
  const staff = directory?.staff || [];
  switch (stage?.target_type) {
    case "branch":
      return staff.filter((person) => String(person.branch_id) === String(stage.target_id));
    case "department":
      return staff.filter((person) => String(person.department_id) === String(stage.target_id));
    case "office":
      return staff.filter((person) => String(person.office_id) === String(stage.target_id));
    case "diwan": {
      const registryIds = (directory?.offices || [])
        .filter((office) => office.code === "REGISTRY" || ["ديوان", "السجل"].some((keyword) => office.name?.includes(keyword)))
        .map((office) => String(office.id));
      return staff.filter((person) => registryIds.includes(String(person.office_id)));
    }
    case "general_manager":
      return staff.filter((person) => person.role === "general_manager");
    default:
      return [];
  }
}

// المعالجات السابقة التي وصلت إلى الجهة نفسها قبل هذه المعالجة.
export function previousProcessings(item, stage) {
  return [...(item?.events || [])]
    .sort((a, b) => new Date(a.created_at) - new Date(b.created_at))
    .filter((event) => new Date(event.created_at) < new Date(stage.created_at) || (event.id !== stage.id && event.created_at === stage.created_at));
}

export function formatGregorianDate(value = new Date()) {
  return new Intl.DateTimeFormat("ar-SY-u-nu-latn", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).format(value);
}

export function formatHijriDate(value = new Date()) {
  return new Intl.DateTimeFormat("ar-SY-u-ca-islamic-umalqura-nu-latn", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).format(value);
}
