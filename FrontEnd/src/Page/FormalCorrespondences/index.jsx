import {
  Building2,
  Landmark,
  PenLine,
  Route,
  Search,
  Trash2,
} from "lucide-react";
import { useCallback, useEffect, useState } from "react";
import { useConfirm } from "../../Provider/ConfirmContext";
import { useLanguage } from "../../Provider/LanguageContext";
import { api } from "../../lib";
import { CreateFormalModal } from "./components/CreateFormalModal";
import { FormalDetails } from "./components/FormalDetails";
import { canCreateFormal, canDeleteFormal, statusLabelKeys, typeLabelKeys } from "./components/formalUtils";


export function FormalCorrespondencesPage({ user, notify }) {
  const { t } = useLanguage();
  const [items, setItems] = useState([]);
  const [searchQuery, setSearchQuery] = useState("");
  const [directory, setDirectory] = useState(null);
  const [creatorOpen, setCreatorOpen] = useState(false);
  const [selected, setSelected] = useState(null);
  const confirm = useConfirm();
  const canDelete = canDeleteFormal(user);
  const canCreate = canCreateFormal(user);

  const load = useCallback(() =>
    api
      .formalCorrespondences()
      .then((data) => {
        setItems(data);
        setSelected((current) =>
          current ? data.find((item) => item.id === current.id) || current : current,
        );
      })
      .catch((error) => notify?.(error.message, "error")), [notify]);

  useEffect(() => {
    load();
  }, [load]);

  useEffect(() => {
    api
      .formalCorrespondenceDirectory()
      .then(setDirectory)
      .catch(() => {});
  }, []);

  const openCreator = async () => {
    try {
      if (!directory) setDirectory(await api.formalCorrespondenceDirectory());
      setCreatorOpen(true);
    } catch (error) {
      notify?.(error.message, "error");
    }
  };

  const deleteItem = async (item) => {
    if (
      !(await confirm({
        title: t("formal_deleteTitle"),
        message: t("formal_deleteMsg", { subject: item.subject }),
        confirmLabel: t("formal_deleteConfirm"),
      }))
    ) return;

    try {
      await api.deleteFormalCorrespondence(item.id);
      setItems((current) => current.filter((entry) => entry.id !== item.id));
      setSelected((current) => (current?.id === item.id ? null : current));
      notify?.(t("formal_deletedToast"), "success");
    } catch (error) {
      notify?.(error.message, "error");
    }
  };

  return (
    <div className="page">
      <div className="page-header">
        <div className="min-w-0">
          <p className="eyebrow">{t("formal_registryEyebrow")}</p>
          <h1 className="page-title mt-1">{t("formal_correspondencesTitle")}</h1>
          <span className="page-subtitle mt-1 block">{t("formal_pageSubtitle")}</span>
        </div>
        {canCreate && (
          <button className="btn btn-primary" onClick={openCreator}>
            <PenLine size={16} />
            {t("formal_createBtn")}
          </button>
        )}
      </div>

      <section className="grid grid-cols-1 items-start gap-4 lg:grid-cols-[minmax(300px,390px)_minmax(0,1fr)]">
        <div className="card overflow-hidden">
          <div className="flex items-center gap-2 border-b border-line bg-subtle px-3 py-2">
            <Search size={15} className="shrink-0 text-muted" />
            <input
              className="w-full min-w-0 border-0 bg-transparent text-[13px] text-ink outline-none"
              placeholder={t("formal_searchPlaceholder")}
              value={searchQuery}
              onChange={(event) => setSearchQuery(event.target.value)}
            />
          </div>
          {items
            .filter((item) =>
              item.subject.toLowerCase().includes(searchQuery.toLowerCase()) ||
              item.reference_code.toLowerCase().includes(searchQuery.toLowerCase()) ||
              t(typeLabelKeys[item.direction]).toLowerCase().includes(searchQuery.toLowerCase())
            )
            .map((item) => (
              <div className="flex items-stretch border-b border-line last:border-b-0" key={item.id}>
                <button
                  className={`grid flex-1 grid-cols-[32px_minmax(0,1fr)_auto] items-center gap-2.5 px-3 py-2.5 text-start hover:bg-subtle ${
                    selected?.id === item.id ? "bg-subtle" : ""
                  }`}
                  onClick={() => setSelected(item)}
                >
                  <span className="inline-grid h-8 w-8 place-items-center rounded border border-line bg-canvas text-brand-500">
                    {item.direction.includes("external") ? <Landmark size={15} /> : <Building2 size={15} />}
                  </span>
                  <div className="min-w-0">
                    <b className="block text-[13px] font-semibold break-words text-ink">{item.subject}</b>
                    <small className="block text-xs text-muted">{item.reference_code} · {t(typeLabelKeys[item.direction])}</small>
                  </div>
                  <em className="badge badge-warn not-italic">{statusLabelKeys[item.status] ? t(statusLabelKeys[item.status]) : item.status}</em>
                </button>
                {canDelete && (
                  <button
                    type="button"
                    className="btn-icon my-2 me-2 self-center border-danger-500/25 bg-danger-50 text-danger-500 hover:bg-danger-50/70 hover:text-danger-500"
                    title={t("formal_deleteConfirm")}
                    onClick={() => deleteItem(item)}
                  >
                    <Trash2 size={15} />
                  </button>
                )}
              </div>
          ))}
          {!items.length && <p className="px-3 py-10 text-center text-[13px] text-muted">{t("formal_emptyList")}</p>}
        </div>

        <div className="min-w-0">
          {selected ? (
            <FormalDetails
              key={selected.id}
              item={selected}
              notify={notify}
              user={user}
              directory={directory}
              canDelete={canDelete}
              changed={(item) => {
                setSelected(item);
                load();
              }}
              deleted={() => {
                setSelected(null);
                load();
              }}
            />
          ) : (
            <div className="empty-state min-h-[320px]">
              <Route size={28} className="text-muted" />
              <b className="text-sm font-semibold text-ink">{t("formal_selectPrompt")}</b>
              <p>{t("formal_selectHint")}</p>
            </div>
          )}
        </div>
      </section>

      {creatorOpen && directory && (
        <CreateFormalModal
          directory={directory}
          close={() => setCreatorOpen(false)}
          done={(item) => {
            setCreatorOpen(false);
            setSelected(item);
            load();
          }}
          notify={notify}
        />
      )}
    </div>
  );
}
