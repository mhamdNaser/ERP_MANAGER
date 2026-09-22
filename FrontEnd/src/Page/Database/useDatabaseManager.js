import { useEffect, useState } from "react";
import { api, downloadBlob } from "../../lib";

export function useDatabaseManager({ t, notify, confirm, canMaintain, canImport }) {
  const [backups, setBackups] = useState([]);
  const [tables, setTables] = useState([]);
  const [importTables, setImportTables] = useState([]);
  const [logs, setLogs] = useState([]);
  const [confirmState, setConfirmState] = useState(null);
  const [busy, setBusy] = useState(false);

  const loadBackups = async () => {
    try {
      setBackups(await api.databaseBackups());
    } catch (error) {
      notify(error.message, "error");
    }
  };

  const loadTables = async () => {
    try {
      setTables(await api.databaseTables());
    } catch (error) {
      notify(error.message, "error");
    }
  };

  const loadLogs = async () => {
    if (!canMaintain) return;
    try {
      setLogs(await api.databaseMaintenanceLogs());
    } catch (error) {
      notify(error.message, "error");
    }
  };

  const loadImportTables = async () => {
    if (!canImport) return;
    try {
      setImportTables(await api.importableTables());
    } catch (error) {
      notify(error.message, "error");
    }
  };

  useEffect(() => {
    void loadBackups();
    void loadTables();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    void loadLogs();
    void loadImportTables();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [canMaintain, canImport]);

  const createBackup = async (kind, { format, tables: scopedTables, bundleFiles }) => {
    try {
      if (kind === "internal") {
        await api.createInternalBackup({ format, tables: scopedTables, bundleFiles });
        notify(t("backupCreated"), "success");
      } else {
        const blob = await api.createExternalBackup({ format, tables: scopedTables, bundleFiles });
        const stamp = new Date().toISOString().slice(0, 10);
        const extension = bundleFiles ? `${format}.zip` : format;
        downloadBlob(blob, `cnd-external-backup-${stamp}.${extension}`);
        notify(t("backupDownloaded"), "success");
      }
      await loadBackups();
    } catch (error) {
      notify(error.message, "error");
    }
  };

  const exportTable = async (table) => {
    try {
      const blob = await api.exportTableExcel(table);
      downloadBlob(blob, `${table}-${new Date().toISOString().slice(0, 10)}.xlsx`);
      notify(t("backupDownloaded"), "success");
    } catch (error) {
      notify(error.message, "error");
    }
  };

  const previewImport = async (table, file) => {
    try {
      return await api.previewTableImport(table, file);
    } catch (error) {
      notify(error.message, "error");
      return null;
    }
  };

  const commitImport = async (table, file) => {
    try {
      const result = await api.commitTableImport(table, file);
      notify(t("importCommitted", { count: result.inserted }), "success");
      return result;
    } catch (error) {
      notify(error.message, "error");
      return null;
    }
  };

  const downloadExisting = async (fileName) => {
    try {
      const blob = await api.downloadDatabaseBackup(fileName);
      downloadBlob(blob, fileName);
      notify(t("backupDownloaded"), "success");
    } catch (error) {
      notify(error.message, "error");
    }
  };

  const deleteExisting = async (fileName) => {
    if (
      !(await confirm({
        title: t("deleteBackup"),
        message: `${t("confirmDeleteBackup")}\n${fileName}`,
        confirmLabel: t("deleteBackup"),
        danger: true,
      }))
    )
      return;
    try {
      await api.deleteDatabaseBackup(fileName);
      notify(t("backupDeleted"), "delete");
      await loadBackups();
    } catch (error) {
      notify(error.message, "error");
    }
  };

  const loadBackupContents = async (fileName) => {
    try {
      return await api.backupTables(fileName);
    } catch (error) {
      notify(error.message, "error");
      return [];
    }
  };

  const runTruncate = async (payloadTables, password) => {
    setBusy(true);
    try {
      await api.truncateDatabase({ password, tables: payloadTables });
      notify(t("truncateSuccess"), "success");
      setConfirmState(null);
      await Promise.all([loadBackups(), loadLogs()]);
      return true;
    } catch (error) {
      notify(error.message, "error");
      return false;
    } finally {
      setBusy(false);
    }
  };

  const requestTruncateAll = () => {
    setConfirmState({
      title: t("truncateConfirmTitle"),
      message: t("truncateAllConfirmMessage"),
      run: (password) => runTruncate(["all"], password),
    });
  };

  const requestTruncateTables = (selected) => {
    setConfirmState({
      title: t("truncateConfirmTitle"),
      message: t("truncateTablesConfirmMessage"),
      run: (password) => runTruncate(selected, password),
    });
  };

  const requestRestore = (fileName, selected) => {
    setConfirmState({
      title: t("restoreConfirmTitle"),
      message: t("restoreConfirmMessage"),
      run: async (password) => {
        setBusy(true);
        try {
          await api.restoreDatabaseBackup({ password, fileName, tables: selected });
          notify(t("restoreSuccess"), "success");
          setConfirmState(null);
          await loadLogs();
          return true;
        } catch (error) {
          notify(error.message, "error");
          return false;
        } finally {
          setBusy(false);
        }
      },
    });
  };

  return {
    backups,
    tables,
    importTables,
    logs,
    busy,
    confirmState,
    closeConfirm: () => setConfirmState(null),
    loadBackups,
    createBackup,
    downloadExisting,
    deleteExisting,
    loadBackupContents,
    requestTruncateAll,
    requestTruncateTables,
    requestRestore,
    exportTable,
    previewImport,
    commitImport,
  };
}
