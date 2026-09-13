import permissionGroups from "../../../../json/permissions.json";
export function buildPermissionGroups(availablePermissions) {
  const available = new Set(availablePermissions);
  return Object.entries(permissionGroups)
    .map(([key, group]) => ({
      key,
      labelKey: group.labelKey,
      permissions: group.permissions.filter((p) => available.has(p)),
    }))
    .filter((group) => group.permissions.length > 0);
}
