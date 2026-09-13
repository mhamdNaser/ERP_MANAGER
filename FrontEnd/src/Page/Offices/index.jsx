import { Landmark, Pencil, Trash2 } from "lucide-react";
import { useEffect, useState } from "react";
import { ReusableFormModal } from "../../Components/ReusableFormModal";
import { useLanguage } from "../../Provider/LanguageContext";
import { api } from "../../lib";
import { useConfirm } from "../../Provider/ConfirmContext";
export function OfficesPage({ notify }) {
  const { t } = useLanguage();
  const confirm = useConfirm();
  const [items, setItems] = useState([]);
  const [editing, setEditing] = useState(undefined);
  const load = () =>
    api
      .offices()
      .then(setItems)
      .catch((e) => notify(e.message, "error")); // eslint-disable-next-line react-hooks/exhaustive-deps
  useEffect(() => {
    void load();
  }, []);
  const fields = [
    { name: "name", label: t("officeName"), required: true },
    { name: "code", label: t("code"), required: true },
    {
      name: "description",
      label: t("description"),
      type: "textarea",
      wide: true,
    },
  ];
  const save = async (values) => {
    try {
      await api.saveOffice(
        {
          name: String(values.name),
          code: String(values.code),
          description: String(values.description || ""),
        },
        editing?.id,
      );
      notify(t("dataSaved"), "success");
      load();
    } catch (e) {
      notify(e.message, "error");
      throw e;
    }
  };
  const remove = async (id) => {
    if (
      !(await confirm({
        message: t("ui_officeDeleteMessage"),
        confirmLabel: t("ui_deleteOffice"),
      }))
    )
      return;
    try {
      await api.deleteOffice(id);
      notify(t("ui_officeDeleted"), "delete");
      load();
    } catch (e) {
      notify(e.message, "error");
    }
  };
  return (
    <div className="page">
      <div className="page-header">
        <div>
          <p className="eyebrow">{t("independentUnits")}</p>
          <h1 className="page-title">{t("offices")}</h1>
          <p className="page-subtitle">{t("officesIntro")}</p>
        </div>
        <button className="btn btn-primary" onClick={() => setEditing(null)}>
          <Landmark size={17} />
          {t("addOffice")}
        </button>
      </div>
      <div className="table-wrap">
        <table className="table">
          <thead>
            <tr>
              <th>{t("officeName")}</th>
              <th>{t("code")}</th>
              <th>{t("employees")}</th>
              <th>{t("actions")}</th>
            </tr>
          </thead>
          <tbody>
            {items.map((item) => (
              <tr key={item.id}>
                <td>
                  <div className="flex items-center gap-2.5">
                    <span className="inline-grid h-8 w-8 shrink-0 place-items-center rounded border border-line bg-subtle text-brand-500">
                      <Landmark className="h-4 w-4" />
                    </span>
                    <b className="font-semibold text-ink">{item.name}</b>
                  </div>
                </td>
                <td className="text-muted">{item.code}</td>
                <td className="text-muted">{item.users_count || 0}</td>
                <td>
                  <div className="flex items-center gap-1">
                    <button
                      className="btn-icon h-8 w-8"
                      onClick={() => setEditing(item)}
                    >
                      <Pencil className="h-4 w-4" />
                    </button>
                    <button
                      className="btn-icon h-8 w-8 hover:text-danger-500"
                      onClick={() => remove(item.id)}
                    >
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {editing !== undefined && (
        <ReusableFormModal
          title={editing ? t("editOffice") : t("addOffice")}
          subtitle={t("officeFormHint")}
          fields={fields}
          initial={{
            name: editing?.name || "",
            code: editing?.code || "",
            description: editing?.description || "",
          }}
          onSubmit={save}
          onClose={() => setEditing(undefined)}
        />
      )}
    </div>
  );
}
