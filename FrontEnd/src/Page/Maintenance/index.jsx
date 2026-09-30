import { Plus } from "lucide-react";
import { useCallback, useEffect, useState } from "react";
import { useLanguage } from "../../Provider/LanguageContext";
import { api } from "../../lib";
import { CatalogManager } from "./components/CatalogManager";
import { ImportPanel } from "./components/ImportPanel";
import { ItemDrawer } from "./components/ItemDrawer";
import { ItemFormModal } from "./components/ItemFormModal";
import { ItemsTable } from "./components/ItemsTable";
import { MaintenanceStats } from "./components/MaintenanceStats";
import { MovementModal } from "./components/MovementModal";
import { emptyFilters } from "./maintenanceMeta";

const TABS = [
  ["items", "mt_tabItems"],
  ["stats", "mt_tabStats"],
  ["catalog", "mt_tabCatalog"],
  ["import", "mt_tabImport"],
];

const emptyBoard = { items: [], catalog: { categories: [], brands: [], devices: [], units: [] }, transitions: {}, can_manage: false };

/**
 * تبويب «إنتاجية» لقسم الصيانة: سجل القطع وحالاتها، وسير عملها وإحصاءاته،
 * وقوائم الفئات والأنواع والماركات، ورفع ملفات Excel التي يعمل بها الفرع.
 */
export function MaintenancePage({ notify }) {
  const { t } = useLanguage();
  const [tab, setTab] = useState("items");
  const [filters, setFilters] = useState(emptyFilters);
  const [board, setBoard] = useState(emptyBoard);
  const [loading, setLoading] = useState(true);
  const [editing, setEditing] = useState(null);
  const [movement, setMovement] = useState(null);
  const [openedId, setOpenedId] = useState(null);
  const [revision, setRevision] = useState(0);

  // مؤشر التحميل للمرة الأولى فقط، كي لا يومض الفراغ بين كل تحديث وآخر.
  const load = useCallback(() => {
    return api
      .maintenanceItems(filters)
      .then(setBoard)
      .catch((error) => notify?.(error.message, "error"))
      .finally(() => setLoading(false));
  }, [filters, notify]);

  useEffect(() => { load(); }, [load, revision]);

  // كل كتابة تعيد تحميل السجل، ويعيد الدرج والإحصاءات قراءة ما يخصهما.
  const refresh = () => setRevision((value) => value + 1);
  const canManage = board.can_manage === true;

  const remove = async (item) => {
    if (!window.confirm(t("mt_confirmDelete", { name: item.name }))) return;
    try {
      await api.deleteMaintenanceItem(item.id);
      notify?.(t("mt_deleted"), "success");
      setOpenedId(null);
      refresh();
    } catch (error) {
      notify?.(error.message, "error");
    }
  };

  return (
    <div className="page">
      <div className="page-header">
        <div className="min-w-0">
          <p className="eyebrow">{t("mt_eyebrow")}</p>
          <h1 className="page-title mt-1">{t("navMaintenance")}</h1>
          <span className="page-subtitle mt-1 block">{t("mt_intro")}</span>
        </div>
        {canManage && (
          <button className="btn btn-primary" onClick={() => setEditing({})}>
            <Plus size={16} />
            {t("mt_newItem")}
          </button>
        )}
      </div>

      <div className="scroll-hidden flex items-center gap-1 overflow-x-auto border-b border-line">
        {TABS.filter(([key]) => canManage || key !== "import").map(([key, label]) => (
          <button
            key={key}
            type="button"
            className={`shrink-0 px-3 py-2 text-[13px] font-semibold ${tab === key ? "border-b-2 border-brand-500 text-ink" : "text-muted hover:text-ink"}`}
            onClick={() => setTab(key)}
          >
            {t(label)}
          </button>
        ))}
      </div>

      {tab === "items" && (
        <ItemsTable
          board={board}
          loading={loading}
          filters={filters}
          setFilters={setFilters}
          canManage={canManage}
          notify={notify}
          onOpen={(item) => setOpenedId(item.id)}
          onEdit={setEditing}
          onMove={(item, kind) => setMovement({ item, kind })}
          onDelete={remove}
        />
      )}
      {tab === "stats" && <MaintenanceStats revision={revision} notify={notify} />}
      {tab === "catalog" && (
        <CatalogManager catalog={board.catalog} canManage={canManage} notify={notify} changed={refresh} />
      )}
      {tab === "import" && canManage && (
        <ImportPanel catalog={board.catalog} notify={notify} imported={refresh} />
      )}

      {editing && (
        <ItemFormModal
          item={editing.id ? editing : null}
          catalog={board.catalog}
          notify={notify}
          close={() => setEditing(null)}
          catalogChanged={refresh}
          saved={() => {
            setEditing(null);
            refresh();
          }}
        />
      )}

      {movement && (
        <MovementModal
          item={movement.item}
          kind={movement.kind}
          transitions={board.transitions}
          notify={notify}
          close={() => setMovement(null)}
          done={() => {
            setMovement(null);
            refresh();
          }}
        />
      )}

      {openedId && (
        <ItemDrawer
          id={openedId}
          revision={revision}
          canManage={canManage}
          notify={notify}
          close={() => setOpenedId(null)}
          onEdit={setEditing}
          onMove={(item, kind) => setMovement({ item, kind })}
          onDelete={remove}
        />
      )}
    </div>
  );
}
