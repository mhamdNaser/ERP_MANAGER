import { File, FileArchive, FileImage, FileText } from "lucide-react";

export function matches(name, owner, search) {
  return (
    !search || `${name} ${owner}`.toLowerCase().includes(search.toLowerCase())
  );
}
export function matchesFilter(ownerId, scope, filter, userId) {
  return (
    filter === "all" ||
    (filter === "mine" && ownerId === userId) ||
    (filter === "shared" && ownerId !== userId) ||
    (filter === "task" && scope === "task")
  );
}
export { downloadBlob } from "../../../utils/download";
export function formatSize(size) {
  if (size == null) return "غير محدودة";
  if (size < 1024) return `${size} B`;
  if (size < 1048576) return `${(size / 1024).toFixed(1)} KB`;
  if (size < 1073741824) return `${(size / 1048576).toFixed(1)} MB`;
  return `${(size / 1073741824).toFixed(1)} GB`;
}
export const roleLabelKeys = {
  employee: "drive_roleEmployee",
  technician: "drive_roleTechnician",
  department_head: "drive_roleDepartmentHead",
  branch_manager: "drive_roleBranchManager",
  general_manager: "drive_roleGeneralManager",
  database_manager: "drive_roleDatabaseManager",
};
export function fileIcon(file) {
  if (file.mime_type?.startsWith("image/")) return <FileImage />;
  if (file.mime_type?.includes("zip") || file.mime_type?.includes("rar"))
    return <FileArchive />;
  if (
    file.mime_type?.includes("pdf") ||
    file.mime_type?.includes("document") ||
    file.mime_type?.includes("text")
  )
    return <FileText />;
  return <File />;
}

