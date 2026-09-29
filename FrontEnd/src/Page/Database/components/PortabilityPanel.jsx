import { Download, HardDriveDownload, Package, Save } from "lucide-react";
import { useState } from "react";
import { useLanguage } from "../../../Provider/LanguageContext";

/**
 * سحب كيانٍ كامل مع ملفاته، وحزمة ترحيل الخادم.
 *
 * الحزمة الجاهزة تعرف جداول كيانها ومرشِّحات ملفاته، فلا يحتاج المستخدم أن
 * يعرف أن «المهام» ثلاثة جداول وأن ملفاتها تسكن في جدول الدرايف مع غيرها.
 */
export function PortabilityPanel({ presets, busy, canMigrate, onPreset, onMigration }) {
  const { t } = useLanguage();
  const [lastPackage, setLastPackage] = useState(null);

  const buildPackage = async () => {
    const result = await onMigration();
    if (result) setLastPackage(result);
  };

  return (
    <section className="card">
      <div className="card-head">
        <div>
          <h2 className="section-title">{t("portabilityTitle")}</h2>
          <p className="text-xs text-muted">{t("portabilityIntro")}</p>
        </div>
      </div>

      <div className="flex flex-col gap-4 p-4 pt-0">
        <div>
          <p className="mb-2 text-xs font-medium text-muted">{t("presetsLabel")}</p>
          {presets.length === 0 ? (
            <p className="text-xs text-muted">{t("presetsEmpty")}</p>
          ) : (
            <div className="grid grid-cols-1 gap-2 md:grid-cols-2">
              {presets.map((preset) => (
                <div
                  key={preset.key}
                  className="flex flex-col gap-2 rounded-lg border border-line bg-surface p-3"
                >
                  <div className="min-w-0">
                    <b className="block text-[13px] font-semibold text-ink">{preset.label}</b>
                    <small className="block text-xs text-muted">{preset.description}</small>
                    <small className="mt-1 block font-mono text-[10px] text-muted">
                      {preset.tables.join(" · ")}
                    </small>
                  </div>
                  <div className="flex items-center gap-2">
                    <button
                      className="btn btn-secondary btn-sm"
                      disabled={busy}
                      onClick={() => onPreset(preset, true)}
                    >
                      <Download size={14} />
                      {t("presetDownload")}
                    </button>
                    <button
                      className="btn btn-ghost btn-sm"
                      disabled={busy}
                      onClick={() => onPreset(preset, false)}
                    >
                      <Save size={14} />
                      {t("presetKeep")}
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {canMigrate && (
          <div className="rounded-lg border border-line bg-subtle p-3">
            <div className="flex flex-wrap items-start justify-between gap-2">
              <div className="min-w-0">
                <b className="flex items-center gap-1.5 text-[13px] font-semibold text-ink">
                  <Package size={15} />
                  {t("migrationPackageTitle")}
                </b>
                <small className="mt-0.5 block text-xs text-muted">
                  {t("migrationPackageIntro")}
                </small>
              </div>
              <button className="btn btn-primary btn-sm" disabled={busy} onClick={buildPackage}>
                <HardDriveDownload size={15} />
                {t("migrationPackageBuild")}
              </button>
            </div>

            {lastPackage && (
              <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 border-t border-line pt-3 text-xs sm:grid-cols-4">
                <div>
                  <dt className="text-muted">{t("migrationPackageVersion")}</dt>
                  <dd className="text-ink">{lastPackage.manifest?.app_version}</dd>
                </div>
                <div>
                  <dt className="text-muted">{t("migrationPackageMigrations")}</dt>
                  <dd className="text-ink">
                    {lastPackage.manifest?.database?.migrations?.count}
                  </dd>
                </div>
                <div>
                  <dt className="text-muted">{t("migrationPackageFiles")}</dt>
                  <dd className="text-ink">{lastPackage.manifest?.storage?.files}</dd>
                </div>
                <div>
                  <dt className="text-muted">{t("migrationPackageTemplates")}</dt>
                  <dd className="text-ink">{lastPackage.manifest?.templates?.files}</dd>
                </div>
                <div className="col-span-2 sm:col-span-4">
                  <dt className="text-muted">{t("migrationPackageFile")}</dt>
                  <dd className="font-mono text-[10px] break-all text-ink">
                    {lastPackage.file_name}
                  </dd>
                </div>
              </dl>
            )}

            <p className="mt-2 text-[11px] text-muted">{t("migrationPackageCliHint")}</p>
          </div>
        )}
      </div>
    </section>
  );
}
