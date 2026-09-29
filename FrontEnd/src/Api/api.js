import { filesFormData, toFormData } from "../utils/formData";
import { request, requestBlob, requestBlobProgress, requestUpload } from "./axios-client";

const systemApi = {
  health: () => request("/status"),
  login: (email, password) =>
    request("/auth/login", {
      method: "POST",
      body: JSON.stringify({ email, password }),
    }),
  me: () => request("/auth/me"),
  dashboard: () => request("/dashboard"),
  translations: (lang) => request(`/locale/${lang}`),
};
const taskApi = {
  taskBoard: (departmentId, all = false) =>
    request(
      all
        ? "/tasks?all=1"
        : `/tasks${departmentId ? `?department_id=${departmentId}` : ""}`,
    ),
  createTask: (data) =>
    request("/tasks", { method: "POST", body: JSON.stringify(data) }),
  updateTask: (id, data) =>
    request(`/tasks/${id}`, { method: "PUT", body: JSON.stringify(data) }),
  // extra يحمل مدخلات المراحل التي تحتاجها: موظف التواصل عند التحويل إليه،
  // والملاحظة المرافقة للانتقال.
  moveTask: (id, status, extra = {}) =>
    request(`/tasks/${id}/move`, {
      method: "PATCH",
      body: JSON.stringify({ status, position: 0, ...extra }),
    }),
  deleteTask: (id) => request(`/tasks/${id}`, { method: "DELETE" }),
  taskActivities: (params = {}) => {
    const query = new URLSearchParams(
      Object.entries(params).filter(([, value]) => value !== "" && value != null),
    ).toString();
    return request(`/task-activities${query ? `?${query}` : ""}`);
  },
  taskStatistics: (params = {}) => {
    const query = new URLSearchParams(
      Object.entries(params).filter(([, value]) => value !== "" && value != null),
    ).toString();
    return request(`/task-statistics${query ? `?${query}` : ""}`);
  },
};
const driveApi = {
  driveFiles: () => request("/drive-files"),
  uploadDriveFiles: (files, data, onProgress) =>
    requestUpload("/drive-files", {
      method: "POST",
      body: filesFormData(files, data),
      onProgress,
    }),
  createDriveFolder: (data) =>
    request("/drive-folders", { method: "POST", body: JSON.stringify(data) }),
  downloadDriveArchive: (data) =>
    requestBlob("/drive-archive", {
      method: "POST",
      body: JSON.stringify(data),
    }),
  downloadDriveArchiveWithProgress: (data, onProgress) =>
    requestBlobProgress("/drive-archive", {
      method: "POST",
      body: JSON.stringify(data),
      onProgress,
    }),
  driveRoleQuotas: () => request("/drive-role-quotas"),
  updateDriveRoleQuotas: (quotas) =>
    request("/drive-role-quotas", {
      method: "PUT",
      body: JSON.stringify({ quotas }),
    }),
  shareDriveFile: (id, data) =>
    request(`/drive-files/${id}/share`, {
      method: "POST",
      body: JSON.stringify(data),
    }),
  shareDriveFolder: (id, data) =>
    request(`/drive-folders/${id}/share`, {
      method: "POST",
      body: JSON.stringify(data),
    }),
  revokeDriveFileShare: (id, data) =>
    request(`/drive-files/${id}/share`, {
      method: "DELETE",
      body: JSON.stringify(data),
    }),
  revokeDriveFolderShare: (id, data) =>
    request(`/drive-folders/${id}/share`, {
      method: "DELETE",
      body: JSON.stringify(data),
    }),
  createPublicFileLink: (id) =>
    request(`/drive-files/${id}/public-link`, { method: "POST" }),
  revokePublicFileLink: (id) =>
    request(`/drive-files/${id}/public-link`, { method: "DELETE" }),
  createPublicFolderLink: (id) =>
    request(`/drive-folders/${id}/public-link`, { method: "POST" }),
  revokePublicFolderLink: (id) =>
    request(`/drive-folders/${id}/public-link`, { method: "DELETE" }),
  downloadDriveFile: (id) => requestBlob(`/drive-files/${id}/download`),
  previewDriveFile: (id) => requestBlob(`/drive-files/${id}/preview`),
  deleteDriveFile: (id) => request(`/drive-files/${id}`, { method: "DELETE" }),
  deleteDriveFolder: (id) =>
    request(`/drive-folders/${id}`, { method: "DELETE" }),
};
const reportApi = {
  reports: () => request("/reports"),
  createReport: (data) =>
    request("/reports", { method: "POST", body: JSON.stringify(data) }),
  updateReturnedReport: (id, data) =>
    request(`/reports/${id}`, { method: "PUT", body: JSON.stringify(data) }),
  transition: (id, action, note = "") =>
    request(`/reports/${id}/transition`, {
      method: "POST",
      body: JSON.stringify({ action, note }),
    }),
  notifications: () => request("/notifications"),
  notification: (id) => request(`/notifications/${id}`),
  readAllNotifications: () =>
    request("/notifications/read-all", { method: "POST" }),
};
const formsApi = {
  forms: () => request("/forms"),
  saveForm: (data, id) =>
    request(id ? `/forms/${id}` : "/forms", {
      method: id ? "PUT" : "POST",
      body: JSON.stringify(data),
    }),
  deleteForm: (id) => request(`/forms/${id}`, { method: "DELETE" }),
  publishForm: (id, data) =>
    request(`/forms/${id}/publish`, {
      method: "POST",
      body: JSON.stringify(data),
    }),
  submitForm: (publicationId, payload) =>
    request(`/forms/publications/${publicationId}/submit`, {
      method: "POST",
      body: JSON.stringify({ payload }),
    }),
  formSubmissions: (formId) => request(`/forms/${formId}/submissions`),
  userFormHistory: (userId) => request(`/forms/users/${userId}`),
};
const communicationApi = {
  communicationDirectory: () => request("/communication-directory"),
  messages: () => request("/messages"),
  messageUnreadCount: () => request("/messages/unread-count"),
  createMessage: (data) =>
    request("/messages", { method: "POST", body: toFormData(data) }),
  markMessageRead: (id) =>
    request(`/messages/${id}/read`, { method: "PATCH" }),
  replyMessage: (id, data) =>
    request(`/messages/${id}/replies`, {
      method: "POST",
      body: toFormData(data),
    }),
  deleteMessage: (id) => request(`/messages/${id}`, { method: "DELETE" }),
  formalCorrespondences: () => request("/formal-correspondences"),
  formalCorrespondenceDirectory: () => request("/formal-correspondences-directory"),
  createFormalCorrespondence: (data) =>
    request("/formal-correspondences", { method: "POST", body: toFormData(data) }),
  updateFormalCorrespondence: (id, data) =>
    request(`/formal-correspondences/${id}`, { method: "POST", body: toFormData(data) }),
  deleteFormalCorrespondence: (id) =>
    request(`/formal-correspondences/${id}`, { method: "DELETE" }),
  updateFormalCorrespondenceDocument: (id, data) =>
    request(`/formal-correspondences/documents/${id}`, {
      method: "POST",
      body: data instanceof FormData ? data : toFormData(data),
    }),
  // POST + _method حتى تصل المرفقات: PHP لا يفكّ multipart في طلبات PUT.
  updateFormalCorrespondenceEvent: (id, data) =>
    request(`/formal-correspondences/events/${id}`, {
      method: "POST",
      body: toFormData({ ...data, _method: "PUT" }),
    }),
  decideFormalCorrespondenceEvent: (id, data) =>
    request(`/formal-correspondences/events/${id}/decision`, {
      method: "POST",
      body: toFormData(data),
    }),
  respondFormalCorrespondenceEvent: (id, data) =>
    request(`/formal-correspondences/events/${id}/response`, {
      method: "POST",
      body: toFormData(data),
    }),
  addFormalCorrespondenceEventDocument: (id, data) =>
    request(`/formal-correspondences/events/${id}/documents`, {
      method: "POST",
      body: toFormData(data),
    }),
  assignFormalCorrespondenceEvent: (id, data) =>
    request(`/formal-correspondences/events/${id}/assign`, {
      method: "POST",
      body: toFormData(data),
    }),
  deleteFormalCorrespondenceEvent: (id) =>
    request(`/formal-correspondences/events/${id}`, { method: "DELETE" }),
  addFormalCorrespondenceEvent: (id, data) =>
    request(`/formal-correspondences/${id}/events`, {
      method: "POST",
      body: toFormData(data),
    }),
  circulars: () => request("/circulars"),
  circular: (id) => request(`/circulars/${id}`),
  createCircular: (data) =>
    request("/circulars", { method: "POST", body: toFormData(data) }),
};
const listQuery = (params = {}) => {
  const query = new URLSearchParams(
    Object.entries(params).filter(([, value]) => value !== "" && value != null),
  ).toString();
  return query ? `?${query}` : "";
};

const organizationApi = {
  offices: () => request("/offices"),
  saveOffice: (data, id) =>
    request(id ? `/offices/${id}` : "/offices", {
      method: id ? "PUT" : "POST",
      body: JSON.stringify(data),
    }),
  deleteOffice: (id) => request(`/offices/${id}`, { method: "DELETE" }),
  myHrRequests: () => request("/hr/my-requests"),
  hrRequests: (params = {}) => {
    const query = new URLSearchParams(
      Object.entries(params).filter(([, value]) => value !== "" && value != null),
    ).toString();
    return request(`/hr/requests${query ? `?${query}` : ""}`);
  },
  createHrRequest: (data) =>
    request("/hr/requests", { method: "POST", body: toFormData(data) }),
  decideHrRequest: (id, data) =>
    request(`/hr/requests/${id}/decision`, { method: "POST", body: JSON.stringify(data) }),
  cancelHrRequest: (id) =>
    request(`/hr/requests/${id}/cancel`, { method: "POST" }),
  updateHrBalance: (employeeId, data) =>
    request(`/hr/balances/${employeeId}`, { method: "PUT", body: JSON.stringify(data) }),
  myFleetMissions: () => request("/fleet/my-missions"),
  fleetMissions: (params) => request(`/fleet/missions${listQuery(params)}`),
  createFleetMission: (data) =>
    request("/fleet/missions", { method: "POST", body: toFormData(data) }),
  decideFleetMission: (id, data) =>
    request(`/fleet/missions/${id}/decision`, { method: "POST", body: JSON.stringify(data) }),
  cancelFleetMission: (id) =>
    request(`/fleet/missions/${id}/cancel`, { method: "POST" }),
  employees: (params) => request(`/employees${listQuery(params)}`),
  saveEmployee: (data, id) =>
    request(id ? `/employees/${id}` : "/employees", {
      method: id ? "PUT" : "POST",
      body: JSON.stringify(data),
    }),
  employeeDetails: () => request("/profile/details"),
  updateMyDetails: (data) =>
    request("/profile/details", { method: "PUT", body: JSON.stringify(data) }),
  updateMySignature: (signatureData) =>
    request("/profile/signature", {
      method: "POST",
      body: JSON.stringify({ signature_data: signatureData }),
    }),
  updateEmployeeDetails: (id, data) =>
    request(`/employees/${id}`, { method: "PUT", body: JSON.stringify(data) }),
  updateEmployeePassword: (id, data) =>
    request(`/employees/${id}/password`, { method: "PUT", body: JSON.stringify(data) }),
  updateEmployeePermissions: (id, permissions) =>
    request(`/employees/${id}/permissions`, {
      method: "PUT",
      body: JSON.stringify({ permissions }),
    }),
  deleteEmployee: (id) => request(`/employees/${id}`, { method: "DELETE" }),
  organization: () => request("/organization"),
  organizationBranches: (params) =>
    request(`/organization/branches${listQuery(params)}`),
  organizationDepartments: (params) =>
    request(`/organization/departments${listQuery(params)}`),
  saveBranch: (data, id) =>
    request(id ? `/branches/${id}` : "/branches", {
      method: id ? "PUT" : "POST",
      body: JSON.stringify(data),
    }),
  deleteBranch: (id) => request(`/branches/${id}`, { method: "DELETE" }),
  saveDepartment: (data, id) =>
    request(id ? `/departments/${id}` : "/departments", {
      method: id ? "PUT" : "POST",
      body: JSON.stringify(data),
    }),
  deleteDepartment: (id) => request(`/departments/${id}`, { method: "DELETE" }),
};
const administrationApi = {
  rolesPermissions: () => request("/roles-permissions"),
  syncRolePermissions: (id, permissions) =>
    request(`/roles-permissions/${id}`, {
      method: "PUT",
      body: JSON.stringify({ permissions }),
    }),
  databaseBackups: () => request("/database-backups"),
  databaseTables: () => request("/database-backups/tables"),
  backupTables: (fileName) =>
    request(`/database-backups/${encodeURIComponent(fileName)}/tables`),
  createInternalBackup: ({ format = "json", tables, bundleFiles } = {}) =>
    request("/database-backups/internal", {
      method: "POST",
      body: JSON.stringify({ format, tables, bundle_files: bundleFiles }),
    }),
  createExternalBackup: ({ format = "json", tables, bundleFiles } = {}) =>
    requestBlob("/database-backups/external", {
      method: "POST",
      body: JSON.stringify({ format, tables, bundle_files: bundleFiles }),
    }),
  downloadDatabaseBackup: (fileName) =>
    requestBlob(`/database-backups/${fileName}/download`),
  backupPresets: () => request("/database-backups/presets"),
  createPresetBackup: (preset) =>
    request("/database-backups/internal", {
      method: "POST",
      body: JSON.stringify({ preset }),
    }),
  downloadPresetBackup: (preset) =>
    requestBlob("/database-backups/external", {
      method: "POST",
      body: JSON.stringify({ preset }),
    }),
  buildMigrationPackage: () =>
    request("/database-backups/migration-package", { method: "POST" }),
  documentTemplates: () => request("/document-templates"),
  downloadDocumentTemplate: (key) =>
    requestBlob(`/document-templates/${key}/download`),
  downloadBlankTemplate: (key) => requestBlob(`/document-templates/${key}/blank`),
  uploadDocumentTemplate: (key, file, force = false) =>
    request(`/document-templates/${key}`, {
      method: "POST",
      body: toFormData({ template: file, force: force ? 1 : 0 }),
    }),
  restoreDocumentTemplate: (key, backup) =>
    request(`/document-templates/${key}/restore`, {
      method: "POST",
      body: JSON.stringify({ backup }),
    }),
  deleteDatabaseBackup: (fileName) =>
    request(`/database-backups/${encodeURIComponent(fileName)}`, {
      method: "DELETE",
    }),
  truncateDatabase: ({ password, tables }) =>
    request("/database-maintenance/truncate", {
      method: "POST",
      body: JSON.stringify({ password, tables }),
    }),
  restoreDatabaseBackup: ({ password, fileName, tables }) =>
    request("/database-maintenance/restore", {
      method: "POST",
      body: JSON.stringify({ password, file_name: fileName, tables }),
    }),
  databaseMaintenanceLogs: () => request("/database-maintenance/logs"),
  exportTableExcel: (table) =>
    requestBlob(`/database-backups/export/${encodeURIComponent(table)}`),
  importableTables: () => request("/database-import/tables"),
  previewTableImport: (table, file) =>
    requestUpload(`/database-import/${encodeURIComponent(table)}/preview`, {
      method: "POST",
      body: toFormData({ file }),
    }),
  commitTableImport: (table, file) =>
    requestUpload(`/database-import/${encodeURIComponent(table)}/commit`, {
      method: "POST",
      body: toFormData({ file }),
    }),
};
export const api = {
  ...systemApi,
  ...taskApi,
  ...driveApi,
  ...reportApi,
  ...formsApi,
  ...communicationApi,
  ...organizationApi,
  ...administrationApi,
};
