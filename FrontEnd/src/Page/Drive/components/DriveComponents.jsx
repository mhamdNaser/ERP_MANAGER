import { useEffect, useMemo, useState } from "react";
import {
  CheckSquare,
  Download,
  ExternalLink,
  Eye,
  FileText,
  Folder,
  Globe2,
  HardDrive,
  Link2,
  Share2,
  Square,
  Trash2,
  UploadCloud,
  Users,
  X,
} from "lucide-react";
import { api } from "../../../lib";
import { useConfirm } from "../../../Provider/ConfirmContext";
import { useLanguage } from "../../../Provider/LanguageContext";
import { fileIcon, formatSize, roleLabelKeys } from "./driveUtils";

export function StorageQuotaPanel({ data, user, notify, load }) {
  const { t } = useLanguage();
  const quota = data.storage_quota;
  const roleQuotas = useMemo(() => data.role_quotas || [], [data.role_quotas]);
  const [editing, setEditing] = useState(false);
  const [values, setValues] = useState(() =>
    Object.fromEntries(
      roleQuotas.map((item) => [
        item.role,
        item.quota_bytes ? Math.round(item.quota_bytes / 1073741824) : "",
      ]),
    ),
  );
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setValues(
      Object.fromEntries(
        roleQuotas.map((item) => [
          item.role,
          item.quota_bytes ? Math.round(item.quota_bytes / 1073741824) : "",
        ]),
      ),
    );
  }, [roleQuotas]);
  const save = async () => {
    try {
      await api.updateDriveRoleQuotas(
        roleQuotas
          .filter((item) => item.role !== "database_manager")
          .map((item) => ({
            role: item.role,
            quota_bytes: Math.max(0, Number(values[item.role]) || 0) * 1073741824,
          })),
      );
      notify(t("drive_quotasUpdated"), "success");
      setEditing(false);
      load();
    } catch (error) {
      notify(error.message, "error");
    }
  };
  return (
    <section className="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(320px,1fr)_minmax(0,2fr)]">
      <div className="flex items-start gap-3 rounded border border-line bg-surface p-3">
        <HardDrive
          size={16}
          className="inline-grid h-9 w-9 shrink-0 rounded border border-line bg-subtle p-2 text-muted"
        />
        <div className="flex min-w-0 flex-1 flex-col gap-1">
          <b className="text-[13px] font-semibold text-ink">
            {t("drive_storageQuota")}
          </b>
          <small className="text-xs text-muted">
            {quota.unlimited
              ? t("drive_storageUsedUnlimited", {
                  used: formatSize(quota.used_bytes),
                })
              : t("drive_storageUsedOf", {
                  used: formatSize(quota.used_bytes),
                  total: formatSize(quota.quota_bytes),
                })}
          </small>
          <div className="mt-1 h-1 w-full rounded bg-canvas">
            <i
              className="block h-1 rounded bg-brand-500 not-italic"
              style={{ width: `${quota.unlimited ? 100 : quota.percent}%` }}
            />
          </div>
          {!quota.unlimited && (
            <em className="text-xs text-muted not-italic">
              {t("drive_storageRemaining", {
                remaining: formatSize(quota.remaining_bytes),
              })}
            </em>
          )}
        </div>
      </div>
      {user.role === "database_manager" && (
        <div className="flex flex-col items-start gap-3 rounded border border-line bg-surface p-3">
          <button
            className="btn btn-secondary btn-sm"
            onClick={() => setEditing((open) => !open)}
          >
            {editing ? t("drive_hideQuotas") : t("drive_editQuotas")}
          </button>
          {editing && (
            <div className="grid w-full grid-cols-1 items-end gap-2 sm:grid-cols-2 xl:grid-cols-3">
              {roleQuotas.map((item) => (
                <label
                  key={item.role}
                  className="flex items-center gap-2 rounded border border-line bg-subtle p-2"
                >
                  <span className="min-w-0 flex-1 truncate text-xs text-muted">
                    {t(roleLabelKeys[item.role] || item.role)}
                  </span>
                  {item.role === "database_manager" ? (
                    <b className="text-xs font-semibold text-brand-600">
                      {t("drive_unlimited")}
                    </b>
                  ) : (
                    <input
                      className="input h-8 w-16 px-2 text-center"
                      type="number"
                      min="0"
                      step="1"
                      value={values[item.role] ?? ""}
                      onChange={(event) =>
                        setValues((current) => ({
                          ...current,
                          [item.role]: event.target.value,
                        }))
                      }
                    />
                  )}
                  {item.role !== "database_manager" && (
                    <small className="text-xs text-muted">GB</small>
                  )}
                </label>
              ))}
              <button className="btn btn-primary" onClick={save}>
                {t("drive_saveQuotas")}
              </button>
            </div>
          )}
        </div>
      )}
    </section>
  );
}
export function FolderCard({
  folder,
  open,
  controls,
  share,
  link,
  revoke,
  remove,
  selected = false,
  toggleSelect,
}) {
  const { t } = useLanguage();
  return (
    <article
      className={`relative flex flex-col gap-2 rounded border bg-surface p-3 ${
        selected ? "border-brand-500" : "border-line"
      }`}
    >
      {toggleSelect && (
        <button
          type="button"
          className="absolute top-2 end-2 z-10 inline-grid h-7 w-7 place-items-center rounded border border-line bg-white text-brand-500"
          title={selected ? t("drive_deselectFolder") : t("drive_selectFolder")}
          onClick={toggleSelect}
        >
          {selected ? <CheckSquare size={14} /> : <Square size={14} />}
        </button>
      )}
      <button className="flex items-start gap-2 pe-9 text-start" onClick={open}>
        <div className="inline-grid h-10 w-10 shrink-0 place-items-center rounded border border-line bg-subtle text-muted">
          <Folder size={17} />
        </div>
        <div className="flex min-w-0 flex-1 flex-col leading-tight">
          <span className="text-[11px] font-semibold text-brand-500">
            {folder.scope === "organization"
              ? t("drive_folderScopeOrg")
              : folder.scope === "department"
                ? t("drive_folderScopeDept")
                : t("drive_folderScopePersonal")}
          </span>
          <h3 className="truncate text-[13px] font-semibold text-ink">
            {folder.name}
          </h3>
          <p className="truncate text-xs text-muted">
            {t("drive_folderCounts", {
              folders: folder.children_count,
              files: folder.files_count,
            })}
          </p>
        </div>
      </button>
      <div className="flex flex-wrap gap-1.5 border-t border-line pt-2">
        <span className="badge badge-neutral">{folder.owner.name}</span>
        <span className="badge badge-neutral">
          {new Date(folder.created_at).toLocaleDateString("ar-SY")}
        </span>
      </div>
      {controls && (
        <DriveActions
          publicUrl={folder.public_url}
          share={share}
          link={link}
          revoke={revoke}
          remove={remove}
        />
      )}
    </article>
  );
}
export function FileCard({
  file,
  controls,
  preview,
  download,
  share,
  link,
  revoke,
  remove,
  selected = false,
  toggleSelect,
}) {
  const { t } = useLanguage();
  return (
    <article
      className={`relative flex flex-col gap-2 rounded border bg-surface p-3 ${
        selected ? "border-brand-500" : "border-line"
      }`}
    >
      {toggleSelect && (
        <button
          type="button"
          className="absolute top-2 end-2 z-10 inline-grid h-7 w-7 place-items-center rounded border border-line bg-white text-brand-500"
          title={selected ? t("drive_deselectFile") : t("drive_selectFile")}
          onClick={toggleSelect}
        >
          {selected ? <CheckSquare size={14} /> : <Square size={14} />}
        </button>
      )}
      <div className="flex items-start gap-2 pe-9">
        <div className="inline-grid h-10 w-10 shrink-0 place-items-center rounded border border-line bg-subtle text-muted [&_svg]:size-[17px]">
          {fileIcon(file)}
        </div>
        <div className="flex min-w-0 flex-1 flex-col leading-tight">
          <span className="text-[11px] font-semibold text-brand-500">
            {file.scope === "organization"
              ? t("drive_fileScopeOrg")
              : file.scope === "task"
                ? t("drive_fileScopeTask")
                : file.scope === "department"
                  ? t("drive_fileScopeDept")
                  : t("drive_fileScopePersonal")}
          </span>
          <h3 className="truncate text-[13px] font-semibold text-ink">
            {file.name}
          </h3>
          <p className="truncate text-xs text-muted">
            {file.task?.title || file.department?.name || t("drive_privateSpace")}
          </p>
        </div>
      </div>
      <div className="flex flex-wrap gap-1.5 border-t border-line pt-2">
        <span className="badge badge-neutral">{file.uploader.name}</span>
        <span className="badge badge-neutral">{formatSize(file.size)}</span>
        <span className="badge badge-neutral">
          {new Date(file.created_at).toLocaleDateString("ar-SY")}
        </span>
      </div>
      <footer className="flex flex-wrap items-center gap-1">
        <button className={driveActionButton} title={t("drive_preview")} onClick={preview}>
          <Eye size={14} />
        </button>
        <button className={driveActionButton} title={t("drive_download")} onClick={download}>
          <Download size={14} />
        </button>
        {controls && (
          <>
            <button className={driveActionButton} title={t("drive_share")} onClick={share}>
              <Share2 size={14} />
            </button>
            <button
              className={
                file.public_url
                  ? `${driveActionButton} border-ok-500/20 bg-ok-50 text-ok-500`
                  : driveActionButton
              }
              title={t("drive_copyDirectLink")}
              onClick={link}
            >
              <Link2 size={14} />
            </button>
            {file.public_url && (
              <button
                className={driveActionButton}
                title={t("drive_unpublish")}
                onClick={revoke}
              >
                <Globe2 size={14} />
              </button>
            )}
            <button
              className={`${driveActionButton} ms-auto border-danger-500/20 bg-danger-50 text-danger-500`}
              title={t("delete")}
              onClick={remove}
            >
              <Trash2 size={14} />
            </button>
          </>
        )}
      </footer>
    </article>
  );
}
const driveActionButton =
  "inline-grid h-8 w-8 place-items-center rounded border border-line bg-white text-muted hover:bg-canvas hover:text-ink";

function DriveActions({ publicUrl, share, link, revoke, remove }) {
  const { t } = useLanguage();
  return (
    <footer className="flex flex-wrap items-center gap-1">
      <button className={driveActionButton} title={t("drive_share")} onClick={share}>
        <Share2 size={14} />
      </button>
      <button
        className={
          publicUrl
            ? `${driveActionButton} border-ok-500/20 bg-ok-50 text-ok-500`
            : driveActionButton
        }
        title={t("drive_publicLink")}
        onClick={link}
      >
        <Link2 size={14} />
      </button>
      {publicUrl && (
        <button
          className={driveActionButton}
          title={t("drive_revokeLinkConfirm")}
          onClick={revoke}
        >
          <Globe2 size={14} />
        </button>
      )}
      <button
        className={`${driveActionButton} ms-auto border-danger-500/20 bg-danger-50 text-danger-500`}
        title={t("delete")}
        onClick={remove}
      >
        <Trash2 size={14} />
      </button>
    </footer>
  );
}
export function UploadModal({ data, user, folder, close, done, notify }) {
  const { t } = useLanguage();
  const [files, setFiles] = useState([]);
  const [scope, setScope] = useState("personal");
  const [departmentId, setDepartmentId] = useState(
    String(user.department_id || data.departments[0]?.id || ""),
  );
  const [saving, setSaving] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(null);
  const totalSize = files.reduce((sum, file) => sum + file.size, 0);
  const submit = async (event) => {
    event.preventDefault();
    setSaving(true);
    setUploadProgress({ loaded: 0, total: totalSize, percent: 0 });
    try {
      await api.uploadDriveFiles(files, {
        scope,
        folder_id: folder?.id,
        department_id:
          scope === "department" ? Number(departmentId) : undefined,
      }, setUploadProgress);
      notify(t("drive_filesUploaded"), "success");
      done();
    } catch (error) {
      notify(error.message, "error");
    } finally {
      setSaving(false);
      setUploadProgress(null);
    }
  };
  return (
    <DriveModal
      title={
        folder
          ? t("drive_uploadFilesInto", { name: folder.name })
          : t("drive_uploadFiles")
      }
      close={close}
    >
      <form onSubmit={submit} className="flex flex-col gap-4">
        <label className="relative flex min-h-32 cursor-pointer flex-col items-center justify-center gap-2 rounded border border-dashed border-line bg-subtle p-4 text-center">
          <UploadCloud size={22} className="text-muted" />
          <b className="text-[13px] font-semibold text-ink">
            {t("drive_chooseFiles")}
          </b>
          <input
            required
            multiple
            type="file"
            disabled={saving}
            onChange={(event) => setFiles(Array.from(event.target.files || []))}
            className="absolute inset-0 cursor-pointer opacity-0"
          />
          {files.length > 0 && (
            <em className="badge badge-neutral not-italic">
              {t("drive_filesSelected", {
                count: files.length,
                size: formatSize(totalSize),
              })}
            </em>
          )}
        </label>
        {uploadProgress && (
          <UploadProgress progress={uploadProgress} format={formatSize} />
        )}
        {!folder && (
          <ScopeFields
            data={data}
            scope={scope}
            setScope={setScope}
            departmentId={departmentId}
            setDepartmentId={setDepartmentId}
            allowOrganization={user.role === "database_manager"}
          />
        )}
        <ModalFooter
          close={close}
          saving={saving}
          label={t("drive_uploadFilesAction")}
        />
      </form>
    </DriveModal>
  );
}
function UploadProgress({ progress, format }) {
  const { t } = useLanguage();
  const loaded = Math.min(progress.loaded || 0, progress.total || progress.loaded || 0);
  return (
    <div
      className="flex flex-col gap-2 rounded border border-line bg-subtle p-3"
      role="status"
      aria-live="polite"
    >
      <div className="flex items-center justify-between gap-2 text-xs">
        <span className="text-muted">{t("drive_uploading")}</span>
        <b className="font-semibold text-ink">{progress.percent}%</b>
      </div>
      <div
        className="h-1 w-full rounded bg-canvas"
        aria-label={t("drive_uploadProgress")}
        aria-valuemin="0"
        aria-valuemax="100"
        aria-valuenow={progress.percent}
        role="progressbar"
      >
        <i
          className="block h-1 rounded bg-brand-500 not-italic"
          style={{ width: `${progress.percent}%` }}
        />
      </div>
      <small className="text-[11px] text-muted">
        {t("drive_progressOf", {
          loaded: format(loaded),
          total: format(progress.total || 0),
        })}
      </small>
    </div>
  );
}
export function FolderModal({ data, user, parent, close, done, notify }) {
  const { t } = useLanguage();
  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [scope, setScope] = useState("personal");
  const [departmentId, setDepartmentId] = useState(
    String(user.department_id || data.departments[0]?.id || ""),
  );
  const [saving, setSaving] = useState(false);
  const submit = async (event) => {
    event.preventDefault();
    setSaving(true);
    try {
      await api.createDriveFolder({
        name,
        description,
        scope,
        parent_id: parent?.id,
        department_id:
          scope === "department" ? Number(departmentId) : undefined,
      });
      notify(t("drive_folderCreated"), "success");
      done();
    } catch (error) {
      notify(error.message, "error");
    } finally {
      setSaving(false);
    }
  };
  return (
    <DriveModal
      title={
        parent
          ? t("drive_createFolderInto", { name: parent.name })
          : t("drive_createFolder")
      }
      close={close}
    >
      <form onSubmit={submit} className="flex flex-col gap-4">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <label className="field mb-0">
            <span className="label">{t("drive_folderName")}</span>
            <input
              className="input"
              required
              value={name}
              onChange={(event) => setName(event.target.value)}
            />
          </label>
          {!parent && (
            <ScopeFields
              data={data}
              scope={scope}
              setScope={setScope}
              departmentId={departmentId}
              setDepartmentId={setDepartmentId}
              allowOrganization={user.role === "database_manager"}
            />
          )}
          <label className="field mb-0 sm:col-span-2">
            <span className="label">{t("drive_optionalDesc")}</span>
            <textarea
              className="textarea"
              value={description}
              onChange={(event) => setDescription(event.target.value)}
            />
          </label>
        </div>
        <ModalFooter
          close={close}
          saving={saving}
          label={t("drive_createFolderAction")}
        />
      </form>
    </DriveModal>
  );
}
function ScopeFields({
  data,
  scope,
  setScope,
  departmentId,
  setDepartmentId,
  allowOrganization = false,
}) {
  const { t } = useLanguage();
  return (
    <>
      <label className="field mb-0">
        <span className="label">{t("drive_scope")}</span>
        <select
          className="select"
          value={scope}
          onChange={(event) => setScope(event.target.value)}
        >
          <option value="personal">{t("drive_scopePersonal")}</option>
          <option value="department">{t("drive_scopeDepartment")}</option>
          {allowOrganization && (
            <option value="organization">{t("drive_scopeOrganization")}</option>
          )}
        </select>
      </label>
      {scope === "department" && (
        <label className="field mb-0">
          <span className="label">{t("drive_scopeDepartment")}</span>
          <select
            className="select"
            value={departmentId}
            onChange={(event) => setDepartmentId(event.target.value)}
          >
            {data.departments.map((item) => (
              <option key={item.id} value={item.id}>
                {item.name}
              </option>
            ))}
          </select>
        </label>
      )}
    </>
  );
}
export function ShareModal({ target, data, refresh, close, done, notify }) {
  const { t } = useLanguage();
  const confirm = useConfirm();
  const [currentItem, setCurrentItem] = useState(target.item);
  const [type, setType] = useState("users");
  const [selected, setSelected] = useState([]);
  const [departmentId, setDepartmentId] = useState("");
  const [branchId, setBranchId] = useState("");
  const [removing, setRemoving] = useState("");
  const branches = Array.from(
    new Map(
      data.recipients
        .filter((item) => item.branch)
        .map((item) => [item.branch.id, item.branch]),
    ).values(),
  );
  const sharedUsers = currentItem.shared_users || [];
  const sharedDepartments = groupSharedUsersByDepartment(sharedUsers, t);
  const allRecipientIds = data.recipients.map((item) => item.id);
  const allSelected =
    allRecipientIds.length > 0 &&
    allRecipientIds.every((id) => selected.includes(id));
  const submit = async (event) => {
    event.preventDefault();
    const payload = {
      target: type,
      user_ids: selected,
      department_id: Number(departmentId) || undefined,
      branch_id: Number(branchId) || undefined,
    };
    if (target.kind === "file")
      await api.shareDriveFile(target.item.id, payload);
    else await api.shareDriveFolder(target.item.id, payload);
    notify(t("drive_shared"), "success");
    done();
  };
  const revokeShare = async (payload, label, key) => {
    if (
      !(await confirm({
        title: t("drive_revokeShare"),
        message: t("drive_revokeShareMessage", { label }),
        confirmLabel: t("drive_revokeShare"),
        danger: false,
      }))
    )
      return;
    setRemoving(key);
    try {
      const updated =
        target.kind === "file"
          ? await api.revokeDriveFileShare(target.item.id, payload)
          : await api.revokeDriveFolderShare(target.item.id, payload);
      setCurrentItem(updated);
      refresh();
      notify(t("drive_shareRevoked"), "success");
    } catch (error) {
      notify(error.message, "error");
    } finally {
      setRemoving("");
    }
  };
  return (
    <DriveModal title={t("drive_shareItem", { name: currentItem.name })} close={close}>
      <form onSubmit={submit} className="flex flex-col gap-4">
        <div className="flex flex-wrap gap-1">
          {[
            ["users", t("drive_typeUsers")],
            ["department", t("drive_typeDepartment")],
            ["branch", t("drive_typeBranch")],
          ].map(([value, label]) => (
            <button
              type="button"
              className={
                type === value
                  ? "btn btn-primary btn-sm"
                  : "btn btn-secondary btn-sm"
              }
              onClick={() => setType(value)}
              key={value}
            >
              {label}
            </button>
          ))}
        </div>
        {type === "users" && (
          <>
            <button
              type="button"
              className="btn btn-secondary btn-sm self-start"
              onClick={() => setSelected(allSelected ? [] : allRecipientIds)}
            >
              {allSelected ? <CheckSquare size={14} /> : <Square size={14} />}
              {allSelected ? t("drive_deselectAll") : t("drive_selectAll")}
            </button>
            <div className="max-h-64 overflow-y-auto rounded border border-line">
              {data.recipients.map((item) => (
                <label
                  key={item.id}
                  className="flex items-center gap-2 border-b border-line px-3 py-2 last:border-b-0 hover:bg-subtle"
                >
                  <input
                    type="checkbox"
                    className="h-4 w-4 shrink-0 accent-brand-500"
                    checked={selected.includes(item.id)}
                    onChange={() =>
                      setSelected((ids) =>
                        ids.includes(item.id)
                          ? ids.filter((id) => id !== item.id)
                          : [...ids, item.id],
                      )
                    }
                  />
                  <span className="flex min-w-0 flex-col leading-tight">
                    <b className="truncate text-[13px] font-semibold text-ink">
                      {item.name}
                    </b>
                    <small className="truncate text-xs text-muted">
                      {item.department?.name || item.branch?.name}
                    </small>
                  </span>
                </label>
              ))}
            </div>
          </>
        )}
        {type === "department" && (
          <label className="field mb-0">
            <span className="label">{t("drive_scopeDepartment")}</span>
            <select
              className="select"
              required
              value={departmentId}
              onChange={(event) => setDepartmentId(event.target.value)}
            >
              <option value="">{t("drive_choose")}</option>
              {data.departments.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.name}
                </option>
              ))}
            </select>
          </label>
        )}
        {type === "branch" && (
          <label className="field mb-0">
            <span className="label">{t("drive_branch")}</span>
            <select
              className="select"
              required
              value={branchId}
              onChange={(event) => setBranchId(event.target.value)}
            >
              <option value="">{t("drive_choose")}</option>
              {branches.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.name}
                </option>
              ))}
            </select>
          </label>
        )}
        <section className="card">
          <header className="card-head">
            <div className="flex min-w-0 flex-col leading-tight">
              <b className="text-[13px] font-semibold text-ink">
                {t("drive_currentShares")}
              </b>
              <small className="text-xs text-muted">
                {t("drive_currentSharesHint")}
              </small>
            </div>
            <span className="badge badge-neutral">{sharedUsers.length}</span>
          </header>
          {!sharedUsers.length ? (
            <div className="flex items-center justify-center gap-2 px-4 py-6 text-[13px] text-muted">
              <Users size={16} />
              {t("drive_noShares")}
            </div>
          ) : (
            <div className="flex flex-col gap-3 p-4">
              {sharedDepartments.map((group) => (
                <article
                  key={group.id}
                  className="rounded border border-line bg-subtle"
                >
                  <div className="flex items-center justify-between gap-2 border-b border-line px-3 py-2">
                    <span className="flex min-w-0 flex-col leading-tight">
                      <b className="truncate text-[13px] font-semibold text-ink">
                        {group.name}
                      </b>
                      <small className="text-xs text-muted">
                        {t("drive_userCount", { count: group.users.length })}
                      </small>
                    </span>
                    {group.departmentId && (
                      <button
                        type="button"
                        className="btn btn-danger btn-sm"
                        disabled={removing === `department-${group.id}`}
                        onClick={() =>
                          revokeShare(
                            {
                              target: "department",
                              department_id: group.departmentId,
                            },
                            t("drive_departmentLabel", { name: group.name }),
                            `department-${group.id}`,
                          )
                        }
                      >
                        {t("drive_revokeDepartment")}
                      </button>
                    )}
                  </div>
                  <div className="flex flex-col">
                    {group.users.map((item) => (
                      <div
                        key={item.id}
                        className="flex items-center justify-between gap-2 border-b border-line px-3 py-2 last:border-b-0"
                      >
                        <span className="flex min-w-0 flex-col leading-tight">
                          <b className="truncate text-[13px] font-semibold text-ink">
                            {item.name}
                          </b>
                          <small className="truncate text-xs text-muted">
                            {item.job_title ||
                              item.branch?.name ||
                              t("drive_userFallback")}
                          </small>
                        </span>
                        <button
                          type="button"
                          className="inline-grid h-8 w-8 shrink-0 place-items-center rounded border border-line bg-white text-muted hover:bg-danger-50 hover:text-danger-500"
                          title={t("drive_revokeUserShare")}
                          disabled={removing === `user-${item.id}`}
                          onClick={() =>
                            revokeShare(
                              { target: "users", user_ids: [item.id] },
                              item.name,
                              `user-${item.id}`,
                            )
                          }
                        >
                          <X size={14} />
                        </button>
                      </div>
                    ))}
                  </div>
                </article>
              ))}
            </div>
          )}
        </section>
        <ModalFooter close={close} label={t("drive_share")} />
      </form>
    </DriveModal>
  );
}
function groupSharedUsersByDepartment(users, t) {
  const groups = new Map();
  users.forEach((user) => {
    const departmentId = user.department?.id || "";
    const key = departmentId || `no-department-${user.branch?.id || "unknown"}`;
    if (!groups.has(key)) {
      groups.set(key, {
        id: key,
        departmentId,
        name: user.department?.name || user.branch?.name || t("drive_noDepartment"),
        users: [],
      });
    }
    groups.get(key).users.push(user);
  });
  return Array.from(groups.values());
}
function DriveModal({ title, close, children }) {
  const { t } = useLanguage();
  return (
    <div className="overlay overflow-y-auto" onClick={close}>
      <section className="modal" onClick={(event) => event.stopPropagation()}>
        <header className="modal-head items-start">
          <div className="flex min-w-0 flex-col gap-1">
            <small className="eyebrow">{t("drive_title")}</small>
            <h2 className="truncate text-base font-semibold text-ink">
              {title}
            </h2>
          </div>
          <button className="btn-icon shrink-0" onClick={close}>
            <X size={16} />
          </button>
        </header>
        <div className="modal-body">{children}</div>
      </section>
    </div>
  );
}
export function PreviewModal({ preview, close }) {
  const { t } = useLanguage();
  const kind = previewKind(preview.mime, preview.name);
  return (
    <div className="overlay" onClick={close}>
      <section
        className="mx-auto flex h-[92vh] w-full max-w-[1100px] flex-col overflow-hidden rounded border border-line bg-surface"
        onClick={(event) => event.stopPropagation()}
      >
        <header className="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
          <div className="flex min-w-0 flex-col gap-1">
            <small className="eyebrow">{t("drive_filePreview")}</small>
            <h2 className="truncate text-base font-semibold text-ink">
              {preview.name}
            </h2>
          </div>
          <div className="flex shrink-0 items-center gap-2">
            <button
              className="btn btn-secondary"
              disabled={!preview.directUrl}
              title={
                preview.directUrl
                  ? t("drive_openDirectLink")
                  : t("drive_makePublicFirst")
              }
              onClick={() =>
                preview.directUrl &&
                window.open(preview.directUrl, "_blank", "noopener,noreferrer")
              }
            >
              <ExternalLink size={15} />
              {t("drive_openInPlace")}
            </button>
            <button
              className="btn-icon"
              title={t("drive_closePreview")}
              onClick={close}
            >
              <X size={16} />
            </button>
          </div>
        </header>
        <div className="grid min-h-0 flex-1 place-items-center bg-canvas p-4">
          {kind === "image" ? (
            <img
              src={preview.url}
              alt={preview.name}
              className="max-h-full max-w-full rounded object-contain"
            />
          ) : kind === "video" ? (
            <video
              src={preview.url}
              controls
              className="max-h-full w-full rounded bg-black"
            />
          ) : kind === "audio" ? (
            <audio
              src={preview.url}
              controls
              className="w-full max-w-xl rounded border border-line bg-surface p-4"
            />
          ) : kind === "frame" ? (
            <iframe
              src={preview.url}
              title={preview.name}
              className="h-full w-full rounded border-0 bg-white"
            />
          ) : kind === "text" ? (
            <iframe
              src={preview.url}
              title={preview.name}
              className="h-full w-full rounded border-0 bg-white"
            />
          ) : (
            <div className="flex w-full max-w-md flex-col items-center gap-2 rounded border border-line bg-surface p-6 text-center">
              <FileText size={32} className="text-muted" />
              <b className="text-[13px] font-semibold text-ink">
                {t("drive_cannotPreview")}
              </b>
              <span className="text-xs text-muted">
                {t("drive_cannotPreviewHint")}
              </span>
            </div>
          )}
        </div>
      </section>
    </div>
  );
}

function previewKind(mime, name = "") {
  const normalized = (mime || "").toLowerCase();
  const extension = name.toLowerCase().split(".").pop() || "";
  if (normalized.startsWith("image/")) return "image";
  if (normalized.startsWith("video/")) return "video";
  if (normalized.startsWith("audio/")) return "audio";
  if (normalized === "application/pdf") return "frame";
  if (["text/html", "application/xhtml+xml"].includes(normalized) || ["html", "htm"].includes(extension)) return "frame";
  if (normalized.startsWith("text/") || ["txt", "csv", "json", "xml", "log", "md"].includes(extension)) return "text";
  return "unsupported";
}
function ModalFooter({ close, saving = false, label }) {
  const { t } = useLanguage();
  return (
    <footer className="flex items-center justify-end gap-2 border-t border-line pt-3">
      <button type="button" className="btn btn-secondary" onClick={close}>
        {t("cancel")}
      </button>
      <button className="btn btn-primary" disabled={saving}>
        {saving ? t("drive_saving") : label}
      </button>
    </footer>
  );
}
