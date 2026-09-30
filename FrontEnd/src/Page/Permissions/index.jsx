import { useState } from "react";
import { useLanguage } from "../../Provider/LanguageContext";
import { RolePermissionsTab } from "./components/RolePermissionsTab";
import { TabAccessTab } from "./components/TabAccessTab";
import { UserPermissionsTab } from "./components/UserPermissionsTab";
export function PermissionsPage({ notify }) {
  const { t } = useLanguage();
  const [tab, setTab] = useState("roles");
  return (
    <div className="page">
      <div className="page-header">
        <div>
          <p className="eyebrow">{t("accessGovernance")}</p>
          <h1 className="page-title">{t("rolePermissions")}</h1>
          <p className="page-subtitle">{t("permissionsIntro")}</p>
        </div>
      </div>
      <div className="flex items-center gap-1 border-b border-line">
        {[
          ["roles", "permTabRoles"],
          ["users", "permTabUsers"],
          ["tabs", "permTabTabs"],
        ].map(([id, labelKey]) => (
          <button
            key={id}
            className={`border-b-2 px-3 py-2.5 text-[13px] font-semibold ${
              tab === id
                ? "border-brand-500 text-brand-700"
                : "border-transparent text-muted hover:text-ink"
            }`}
            onClick={() => setTab(id)}
          >
            {t(labelKey)}
          </button>
        ))}
      </div>
      {tab === "roles" && <RolePermissionsTab notify={notify} />}
      {tab === "users" && <UserPermissionsTab notify={notify} />}
      {tab === "tabs" && <TabAccessTab notify={notify} />}
    </div>
  );
}
