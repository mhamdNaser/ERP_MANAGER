import { useEffect, useMemo, useState } from "react";
import {
  Archive,
  ChevronLeft,
  CheckSquare,
  Folder,
  FolderOpen,
  FolderPlus,
  Globe2,
  HardDrive,
  Search,
  Square,
  Trash2,
  UploadCloud,
  Users,
} from "lucide-react";
import { api } from "../../lib";
import { useConfirm } from "../../Provider/ConfirmContext";
import { useLanguage } from "../../Provider/LanguageContext";
import {
  FileCard,
  FolderCard,
  FolderModal,
  PreviewModal,
  ShareModal,
  StorageQuotaPanel,
  UploadModal,
} from "./components/DriveComponents";
import {
  downloadBlob,
  formatSize,
  matches,
  matchesFilter,
} from "./components/driveUtils";
export function DrivePage({ user, notify }) {
  const confirm = useConfirm();
  const { t } = useLanguage();
  const [data, setData] = useState(null);
  const [modal, setModal] = useState(null);
  const [preview, setPreview] = useState(null);
  const [currentFolder, setCurrentFolder] = useState(null);
  const [search, setSearch] = useState("");
  const [filter, setFilter] = useState("all");
  const [selectedFiles, setSelectedFiles] = useState(() => new Set());
  const [selectedFolders, setSelectedFolders] = useState(() => new Set());
  const [archiveProgress, setArchiveProgress] = useState(null);
  const clearSelection = () => {
    setSelectedFiles(new Set());
    setSelectedFolders(new Set());
  };
  const load = () =>
    api
      .driveFiles()
      .then(setData)
      .catch((error) => notify(error.message, "error"));
  const openPreview = async (file) => {
    const directPreviewUrl = file.direct_url || file.public_url;
    if (directPreviewUrl && canPreviewDirectly(file.mime_type, file.name)) {
      setPreview({
        url: directPreviewUrl,
        name: file.name,
        mime: file.mime_type || "application/octet-stream",
        directUrl: file.direct_url,
      });
      return;
    }

    try {
      const blob = await api.previewDriveFile(file.id);
      setPreview({
        url: URL.createObjectURL(blob),
        name: file.name,
        mime: blob.type || file.mime_type || "application/octet-stream",
        directUrl: file.direct_url,
      });
    } catch (error) {
      notify(error.message, "error");
    }
  };
  const closePreview = () => {
    if (preview) URL.revokeObjectURL(preview.url);
    setPreview(null);
  };
  useEffect(() => {
    load();
  }, []); // eslint-disable-line react-hooks/exhaustive-deps
  const folders = useMemo(
    () =>
      data?.folders.filter(
        (folder) =>
          (folder.parent_id || null) === currentFolder &&
          matches(folder.name, folder.owner.name, search) &&
          matchesFilter(folder.owner.id, folder.scope, filter, user.id),
      ) || [],
    [currentFolder, data, filter, search, user.id],
  );
  const files = useMemo(
    () =>
      data?.files.filter(
        (file) =>
          (file.folder_id || null) === currentFolder &&
          matches(file.name, file.uploader.name, search) &&
          matchesFilter(file.uploader.id, file.scope, filter, user.id),
      ) || [],
    [currentFolder, data, filter, search, user.id],
  );
  if (!data)
    return (
      <div className="page">
        <div className="empty-state">{t("drive_preparing")}</div>
      </div>
    );
  const folder = data.folders.find((item) => item.id === currentFolder);
  const totalSize = data.files.reduce((sum, file) => sum + file.size, 0);
  const canControl = (target) =>
    target.kind === "file"
      ? target.item.uploader.id === user.id ||
        ["department_head", "branch_manager", "general_manager"].includes(
          user.role,
        )
      : target.item.owner.id === user.id ||
        ["department_head", "branch_manager", "general_manager"].includes(
          user.role,
        );
  const controllableFolders = folders.filter((item) =>
    canControl({ kind: "folder", item }),
  );
  const controllableFiles = files.filter((item) =>
    canControl({ kind: "file", item }),
  );
  const selectedFileIds = Array.from(selectedFiles);
  const selectedFolderIds = Array.from(selectedFolders);
  const selectedDeletableFileIds = controllableFiles
    .filter((item) => selectedFiles.has(item.id))
    .map((item) => item.id);
  const selectedDeletableFolderIds = controllableFolders
    .filter((item) => selectedFolders.has(item.id))
    .map((item) => item.id);
  const selectedCount = selectedFileIds.length + selectedFolderIds.length;
  const selectedDeletableCount =
    selectedDeletableFileIds.length + selectedDeletableFolderIds.length;
  const selectableCount = files.length + folders.length;
  const allVisibleSelected =
    selectableCount > 0 &&
    files.every((item) => selectedFiles.has(item.id)) &&
    folders.every((item) => selectedFolders.has(item.id));
  const toggleFileSelection = (id) =>
    setSelectedFiles((current) => {
      const next = new Set(current);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  const toggleFolderSelection = (id) =>
    setSelectedFolders((current) => {
      const next = new Set(current);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  const toggleAllVisible = () => {
    if (allVisibleSelected) {
      clearSelection();
      return;
    }
    setSelectedFiles(new Set(files.map((item) => item.id)));
    setSelectedFolders(new Set(folders.map((item) => item.id)));
  };
  const remove = async (target) => {
    if (
      !(await confirm({
        message: t("drive_deleteConfirmMessage", {
          target: t(
            target.kind === "folder"
              ? "drive_deleteTargetFolder"
              : "drive_deleteTargetFile",
          ),
          name: target.item.name,
        }),
        confirmLabel: t(
          target.kind === "folder" ? "drive_deleteFolder" : "drive_deleteFile",
        ),
      }))
    )
      return;
    try {
      if (target.kind === "file") await api.deleteDriveFile(target.item.id);
      else await api.deleteDriveFolder(target.item.id);
      load();
      notify(t("drive_deleted"), "delete");
    } catch (error) {
      notify(error.message, "error");
    }
  };
  const removeSelected = async () => {
    if (!selectedDeletableCount) return;
    if (
      !(await confirm({
        message: t("drive_deleteSelectedMessage", {
          count: selectedDeletableCount,
        }),
        confirmLabel: t("drive_deleteSelected"),
      }))
    )
      return;
    try {
      await Promise.all([
        ...selectedDeletableFileIds.map((id) => api.deleteDriveFile(id)),
        ...selectedDeletableFolderIds.map((id) => api.deleteDriveFolder(id)),
      ]);
      clearSelection();
      load();
      notify(t("drive_deletedSelected"), "delete");
    } catch (error) {
      notify(error.message, "error");
    }
  };
  const publicLink = async (target) => {
    try {
      const updated = target.item.public_url
        ? target.item
        : target.kind === "file"
          ? await api.createPublicFileLink(target.item.id)
          : await api.createPublicFolderLink(target.item.id);
      const link = updated.direct_url || updated.public_url;
      if (link) {
        await navigator.clipboard.writeText(link);
        notify(
          t(
            updated.direct_url
              ? "drive_directLinkCopied"
              : "drive_publicLinkCopied",
          ),
          "success",
        );
      }
      load();
    } catch (error) {
      notify(error.message, "error");
    }
  };
  const revokeLink = async (target) => {
    if (
      !(await confirm({
        title: t("drive_revokeLinkTitle"),
        message: t("drive_revokeLinkMessage"),
        confirmLabel: t("drive_revokeLinkConfirm"),
        danger: false,
      }))
    )
      return;
    try {
      if (target.kind === "file")
        await api.revokePublicFileLink(target.item.id);
      else await api.revokePublicFolderLink(target.item.id);
      load();
      notify(t("drive_publicLinkRevoked"), "success");
    } catch (error) {
      notify(error.message, "error");
    }
  };
  const downloadArchive = async () => {
    if (!selectedCount) {
      notify(t("drive_selectForZip"), "error");
      return;
    }
    const withProgress = allVisibleSelected;
    if (withProgress) {
      setArchiveProgress({ phase: "preparing", percent: 0, lengthComputable: false });
    }
    try {
      const payload = {
        file_ids: selectedFileIds,
        folder_ids: selectedFolderIds,
      };
      const blob = withProgress
        ? await api.downloadDriveArchiveWithProgress(payload, setArchiveProgress)
        : await api.downloadDriveArchive(payload);
      downloadBlob(
        blob,
        `drive-archive-${new Date().toISOString().slice(0, 10)}.zip`,
      );
      notify(t("drive_archiveReady"), "success");
    } catch (error) {
      notify(error.message, "error");
    } finally {
      if (withProgress) setArchiveProgress(null);
    }
  };
  return (
    <div className="page">
      <header className="page-header">
        <div className="min-w-0">
          <p className="eyebrow">{t("drive_eyebrow")}</p>
          <h1 className="page-title">{t("drive_title")}</h1>
          <span className="page-subtitle">
            {user.role === "database_manager"
              ? t("drive_subtitleManager")
              : t("drive_subtitleDefault")}
          </span>
        </div>
      </header>
      <StorageQuotaPanel data={data} user={user} notify={notify} load={load} />
      <section className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
        {[
          [HardDrive, data.files.length, t("drive_statFiles")],
          [Folder, data.folders.length, t("drive_statFolders")],
          [FolderOpen, formatSize(totalSize), t("drive_statSize")],
          [
            Globe2,
            data.files.filter((item) => item.public_url).length +
              data.folders.filter((item) => item.public_url).length,
            t("drive_statPublic"),
          ],
          [
            Users,
            data.files.filter((item) => item.uploader.id !== user.id).length +
              data.folders.filter((item) => item.owner.id !== user.id).length,
            t("drive_statShared"),
          ],
        ].map(([Icon, value, label]) => (
          <div
            key={label}
            className="flex items-center gap-3 rounded border border-line bg-surface p-3"
          >
            <Icon
              size={16}
              className="inline-grid h-8 w-8 shrink-0 rounded border border-line bg-subtle p-2 text-muted"
            />
            <span className="flex min-w-0 flex-col leading-tight">
              <b className="truncate text-base font-semibold text-ink">
                {value}
              </b>
              <small className="text-xs text-muted">{label}</small>
            </span>
          </div>
        ))}
      </section>
      <section className="card">
        <header className="card-head">
          <div className="flex flex-wrap gap-1">
            {["all", "mine", "shared", "task"].map((item) => (
              <button
                key={item}
                className={
                  filter === item
                    ? "btn btn-primary btn-sm"
                    : "btn btn-secondary btn-sm"
                }
                onClick={() => {
                  setFilter(item);
                  clearSelection();
                }}
              >
                {item === "all"
                  ? t("drive_filterAll")
                  : item === "mine"
                    ? t("drive_filterMine")
                    : item === "shared"
                      ? t("drive_filterShared")
                      : t("drive_filterTask")}
              </button>
            ))}
          </div>
          <label className="flex h-9 min-w-[240px] flex-1 items-center gap-2 rounded border border-line bg-white px-3">
            <Search size={15} className="shrink-0 text-muted" />
            <input
              className="w-full border-0 bg-transparent text-[13px] text-ink outline-none"
              value={search}
              onChange={(event) => {
                setSearch(event.target.value);
                clearSelection();
              }}
              placeholder={t("drive_searchPlaceholder")}
            />
          </label>
          <div className="flex flex-wrap items-center justify-end gap-2">
            <button
              className="btn btn-secondary btn-sm"
              disabled={!selectableCount}
              onClick={toggleAllVisible}
            >
              {allVisibleSelected ? (
                <CheckSquare size={14} />
              ) : (
                <Square size={14} />
              )}
              {allVisibleSelected
                ? t("drive_deselectAll")
                : t("drive_selectAll")}
            </button>
            <button
              className="btn btn-secondary btn-sm"
              disabled={!selectedCount || archiveProgress}
              onClick={downloadArchive}
            >
              <Archive size={14} />
              {t("drive_downloadZip")}
            </button>
            <button
              className="btn btn-danger btn-sm"
              disabled={!selectedDeletableCount}
              onClick={removeSelected}
            >
              <Trash2 size={14} />
              {t("drive_deleteSelected")}
            </button>
            <button
              className="btn btn-secondary btn-sm"
              onClick={() => setModal({ kind: "folder" })}
            >
              <FolderPlus size={14} />
              {t("drive_newFolder")}
            </button>
            <button
              className="btn btn-primary btn-sm"
              onClick={() => setModal({ kind: "upload" })}
            >
              <UploadCloud size={14} />
              {t("drive_uploadFiles")}
            </button>
          </div>
        </header>
        {selectedCount > 0 && (
          <div className="flex items-center justify-between gap-3 border-b border-line bg-subtle px-4 py-2">
            <span className="text-xs font-semibold text-ink">
              {t("drive_selectedCount", { count: selectedCount })}
            </span>
            <button className="btn btn-secondary btn-sm" onClick={clearSelection}>
              {t("drive_clearSelection")}
            </button>
          </div>
        )}
        {archiveProgress && (
          <div className="flex flex-col gap-2 border-b border-line bg-subtle px-4 py-3">
            <span className="text-xs font-semibold text-ink">
              {archiveProgress.phase === "downloading"
                ? archiveProgress.lengthComputable
                  ? t("drive_downloadingArchivePercent", {
                      percent: archiveProgress.percent,
                    })
                  : t("drive_downloadingArchive")
                : t("drive_compressing")}
            </span>
            <div className="h-1 w-full rounded bg-canvas">
              <i
                className="block h-1 rounded bg-brand-500 not-italic"
                style={{
                  width: archiveProgress.lengthComputable
                    ? `${archiveProgress.percent}%`
                    : "35%",
                }}
              />
            </div>
          </div>
        )}
        <div className="flex flex-wrap items-center gap-2 border-b border-line bg-subtle px-4 py-2">
          <button
            className="btn btn-secondary btn-sm"
            onClick={() => {
              setCurrentFolder(null);
              clearSelection();
            }}
          >
            <HardDrive size={14} />
            {t("drive_home")}
          </button>
          {folder && (
            <>
              <ChevronLeft size={14} className="text-muted rtl:rotate-180" />
              <button
                className="btn btn-secondary btn-sm"
                onClick={() => {
                  setCurrentFolder(folder.parent_id || null);
                  clearSelection();
                }}
              >
                {folder.name}
              </button>
            </>
          )}
        </div>
        <div className="grid grid-cols-[repeat(auto-fill,minmax(230px,1fr))] gap-3 p-4">
          {folders.map((item) => (
            <FolderCard
              key={item.id}
              folder={item}
              open={() => {
                setCurrentFolder(item.id);
                clearSelection();
              }}
              controls={canControl({ kind: "folder", item })}
              share={() =>
                setModal({ kind: "share", target: { kind: "folder", item } })
              }
              link={() => publicLink({ kind: "folder", item })}
              revoke={() => revokeLink({ kind: "folder", item })}
              remove={() => remove({ kind: "folder", item })}
              selected={selectedFolders.has(item.id)}
              toggleSelect={() => toggleFolderSelection(item.id)}
            />
          ))}
          {files.map((item) => (
            <FileCard
              key={item.id}
              file={item}
              controls={canControl({ kind: "file", item })}
              preview={() => openPreview(item)}
              download={async () =>
                downloadBlob(await api.downloadDriveFile(item.id), item.name)
              }
              share={() =>
                setModal({ kind: "share", target: { kind: "file", item } })
              }
              link={() => publicLink({ kind: "file", item })}
              revoke={() => revokeLink({ kind: "file", item })}
              remove={() => remove({ kind: "file", item })}
              selected={selectedFiles.has(item.id)}
              toggleSelect={() => toggleFileSelection(item.id)}
            />
          ))}
        </div>
        {!folders.length && !files.length && (
          <div className="m-4 empty-state">
            <FolderOpen size={22} />
            <h3 className="text-sm font-semibold text-ink">
              {t("drive_emptyTitle")}
            </h3>
            <p>{t("drive_emptyDesc")}</p>
          </div>
        )}
      </section>
      {modal?.kind === "upload" && (
        <UploadModal
          data={data}
          user={user}
          folder={folder}
          close={() => setModal(null)}
          done={() => {
            setModal(null);
            load();
          }}
          notify={notify}
        />
      )}
      {modal?.kind === "folder" && (
        <FolderModal
          data={data}
          user={user}
          parent={folder}
          close={() => setModal(null)}
          done={() => {
            setModal(null);
            load();
          }}
          notify={notify}
        />
      )}
      {modal?.kind === "share" && (
        <ShareModal
          target={modal.target}
          data={data}
          refresh={load}
          close={() => setModal(null)}
          done={() => {
            setModal(null);
            load();
          }}
          notify={notify}
        />
      )}
      {preview && <PreviewModal preview={preview} close={closePreview} />}
    </div>
  );
}

function canPreviewDirectly(mime = "", name = "") {
  const normalized = mime.toLowerCase();
  const extension = name.toLowerCase().split(".").pop() || "";
  return (
    normalized.startsWith("image/") ||
    normalized.startsWith("video/") ||
    normalized.startsWith("audio/") ||
    normalized.startsWith("text/") ||
    normalized === "application/pdf" ||
    normalized === "application/xhtml+xml" ||
    ["html", "htm", "txt", "csv", "json", "xml", "log", "md"].includes(extension)
  );
}
