import { FileDown, RefreshCw, Database } from "lucide-react";
import { useMemo, useState } from "react";
import { ReportTable } from "../../Components/Reports";
import { useLanguage } from "../../Provider/LanguageContext";
import { useConfirm } from "../../Provider/ConfirmContext";
import { exportReports } from "../../lib";
import { useDatabaseManager } from "./useDatabaseManager";
import { BackupPanel } from "./components/BackupPanel";
import { BackupList } from "./components/BackupList";
import { MaintenancePanel } from "./components/MaintenancePanel";
import { RestorePanel } from "./components/RestorePanel";
import { MaintenanceLog } from "./components/MaintenanceLog";
import { PasswordConfirmModal } from "./components/PasswordConfirmModal";
import { ExcelExportPanel } from "./components/ExcelExportPanel";
import { ExcelImportPanel } from "./components/ExcelImportPanel";
import { PortabilityPanel } from "./components/PortabilityPanel";

export function DatabaseManagerPage({ reports, open, notify, user }) {
  const { t } = useLanguage();
  const confirm = useConfirm();
  const [query, setQuery] = useState("");
  const [filter, setFilter] = useState("all");
  const canMaintain = Boolean(user?.permissions?.includes("database.maintenance.manage"));
  const canImport = Boolean(user?.permissions?.includes("database.import.manage"));

  const manager = useDatabaseManager({ t, notify, confirm, canMaintain, canImport });

  const filteredReports = useMemo(
    () =>
      reports.filter(
        (report) =>
          (filter === "all" || report.status === filter) &&
          report.title.toLowerCase().includes(query.toLowerCase()),
      ),
    [reports, query, filter],
  );

  return (
    <div className="page">
      <div className="page-header">
        <div>
          <p className="eyebrow">{t("databaseCenter")}</p>
          <h1 className="page-title">{t("databaseManagement")}</h1>
          <p className="page-subtitle">{t("databaseManagementIntro")}</p>
        </div>
      </div>

      <section className="card">
        <div className="card-head">
          <div>
            <h2 className="section-title">{t("backupCenter")}</h2>
            <p className="text-xs text-muted">{t("backupHistory")}</p>
          </div>
          <button className="btn btn-secondary btn-sm" onClick={manager.loadBackups}>
            <RefreshCw size={15} />
            {t("backupsLoaded")}
          </button>
        </div>
        <div className="p-4 pb-0">
          <BackupPanel tables={manager.tables} onCreate={manager.createBackup} />
        </div>
        <BackupList
          backups={manager.backups}
          onDownload={manager.downloadExisting}
          onDelete={manager.deleteExisting}
        />
      </section>

      <PortabilityPanel
        presets={manager.presets}
        busy={manager.busy}
        canMigrate={canMaintain}
        onPreset={manager.createPresetBackup}
        onMigration={manager.buildMigrationPackage}
      />

      <ExcelExportPanel tables={manager.tables} onExport={manager.exportTable} />

      {canImport && (
        <ExcelImportPanel
          tables={manager.importTables}
          onPreview={manager.previewImport}
          onCommit={manager.commitImport}
        />
      )}

      {canMaintain && (
        <>
          <MaintenancePanel
            tables={manager.tables}
            onTruncateAll={manager.requestTruncateAll}
            onTruncateTables={manager.requestTruncateTables}
          />
          <RestorePanel
            backups={manager.backups}
            loadBackupContents={manager.loadBackupContents}
            onRestore={manager.requestRestore}
          />
          <MaintenanceLog logs={manager.logs} />
        </>
      )}

      <section className="flex flex-col gap-3">
        <div className="flex flex-wrap items-center justify-between gap-3 rounded border border-line bg-surface px-4 py-3">
          <div>
            <h2 className="section-title">{t("reportsTitle")}</h2>
            <p className="text-xs text-muted">{t("reportsIntro")}</p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <button
              className="btn btn-secondary btn-sm"
              onClick={() => exportReports(filteredReports, "word")}
            >
              <FileDown size={15} />
              Word
            </button>
            <button
              className="btn btn-secondary btn-sm"
              onClick={() => exportReports(filteredReports, "pdf")}
            >
              <FileDown size={15} />
              PDF
            </button>
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2 rounded border border-line bg-surface p-3">
          <label className="relative flex min-w-56 flex-1 items-center sm:max-w-xs">
            <Database
              size={16}
              className="pointer-events-none absolute start-3 text-muted"
            />
            <input
              className="input ps-9"
              placeholder={t("searchReports")}
              value={query}
              onChange={(e) => setQuery(e.target.value)}
            />
          </label>
          <select
            className="select w-auto min-w-40"
            value={filter}
            onChange={(e) => setFilter(e.target.value)}
          >
            <option value="all">{t("allStatuses")}</option>
            <option value="draft">{t("draft")}</option>
            <option value="department_review">{t("departmentReview")}</option>
            <option value="branch_review">{t("branchReview")}</option>
            <option value="general_review">{t("generalReview")}</option>
            <option value="returned">{t("returned")}</option>
            <option value="approved">{t("approved")}</option>
          </select>
          <span className="ms-auto text-xs text-muted">
            {filteredReports.length} {t("results")}
          </span>
        </div>
        <ReportTable reports={filteredReports} open={open} />
      </section>

      <PasswordConfirmModal
        state={manager.confirmState}
        busy={manager.busy}
        onConfirm={(password) => manager.confirmState.run(password)}
        onClose={manager.closeConfirm}
      />
    </div>
  );
}
