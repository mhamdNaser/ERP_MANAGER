import { Megaphone } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { ReusableFormModal } from "../../Components/ReusableFormModal";
import { useLanguage } from "../../Provider/LanguageContext";
import { api } from "../../lib";
export function CircularsPage({ user, notify }) {
  const { t } = useLanguage();
  const [items, setItems] = useState([]);
  const [form, setForm] = useState(false);
  const [activeTab, setActiveTab] = useState("incoming");
  const load = () =>
    api
      .circulars()
      .then(setItems)
      .catch((error) => notify(error.message, "error"));
  useEffect(() => {
    void load();
  }, []);
  const audiences =
    user.role === "department_head"
      ? [
          { value: "department_all", label: t("department_all") },
          {
            value: "department_fixed_employees",
            label: t("department_fixed_employees"),
          },
          {
            value: "department_contract_employees",
            label: t("department_contract_employees"),
          },
        ]
      : user.role === "branch_manager"
        ? [
            { value: "branch_heads", label: t("branch_heads") },
            { value: "branch_all", label: t("branch_all") },
            {
              value: "branch_fixed_employees",
              label: t("branch_fixed_employees"),
            },
            {
              value: "branch_contract_employees",
              label: t("branch_contract_employees"),
            },
          ]
        : [
            {
              value: "general_branch_managers",
              label: t("general_branch_managers"),
            },
            { value: "general_management", label: t("general_management") },
            { value: "general_all", label: t("general_all") },
            {
              value: "general_fixed_employees",
              label: t("general_fixed_employees"),
            },
            {
              value: "general_contract_employees",
              label: t("general_contract_employees"),
            },
          ];
  const fields = [
    { name: "title", label: t("circularTitle"), required: true, wide: true },
    {
      name: "audience",
      label: t("targetAudience"),
      type: "select",
      required: true,
      wide: true,
      options: audiences,
    },
    {
      name: "content",
      label: t("circularContent"),
      type: "textarea",
      required: true,
      wide: true,
    },
    {
      name: "attachment",
      label: t("attachment"),
      type: "file",
      hint: t("attachmentHint"),
      accept: ".pdf,.doc,.docx,.png,.jpg,.jpeg",
    },
  ];
  const save = async (values) => {
    try {
      await api.createCircular(values);
      notify(t("circularPublished"), "success");
      load();
      setActiveTab("outgoing");
      setForm(false);
    } catch (error) {
      notify(error.message, "error");
      throw error;
    }
  };
  const incoming = useMemo(
    () => items.filter((item) => item.issuer.id !== user.id),
    [items, user.id],
  );
  const outgoing = useMemo(
    () => items.filter((item) => item.issuer.id === user.id),
    [items, user.id],
  );
  const visibleItems = activeTab === "incoming" ? incoming : outgoing;
  const activeCount = visibleItems.length;
  const totalCount = items.length;
  return (
    <div className="page">
      <div className="page-header">
        <div className="min-w-0">
          <p className="eyebrow">{t("officialPublishing")}</p>
          <h1 className="page-title mt-1">{t("circulars")}</h1>
          <p className="page-subtitle mt-1">{t("circularsIntro")}</p>
        </div>
        {user.permissions?.includes("circulars.create") && (
          <button className="btn btn-primary" onClick={() => setForm(true)}>
            <Megaphone size={16} />
            {t("newCircular")}
          </button>
        )}
      </div>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <article className="panel flex flex-col gap-1">
          <b className="text-xl font-semibold text-ink">{incoming.length}</b>
          <span className="text-xs text-muted">{t("incomingCirculars")}</span>
        </article>
        <article className="panel flex flex-col gap-1">
          <b className="text-xl font-semibold text-ink">{outgoing.length}</b>
          <span className="text-xs text-muted">{t("outgoingCirculars")}</span>
        </article>
        <article className="panel flex flex-col gap-1">
          <b className="text-xl font-semibold text-ink">{totalCount}</b>
          <span className="text-xs text-muted">{t("circulars")}</span>
        </article>
      </div>

      <div className="flex flex-wrap gap-2">
        <button
          className={`btn btn-sm ${activeTab === "incoming" ? "btn-primary" : "btn-secondary"}`}
          onClick={() => setActiveTab("incoming")}
        >
          <span>{t("incomingCirculars")}</span>
          <small className="text-[11px] font-bold opacity-70">
            {incoming.length}
          </small>
        </button>
        <button
          className={`btn btn-sm ${activeTab === "outgoing" ? "btn-primary" : "btn-secondary"}`}
          onClick={() => setActiveTab("outgoing")}
        >
          <span>{t("outgoingCirculars")}</span>
          <small className="text-[11px] font-bold opacity-70">
            {outgoing.length}
          </small>
        </button>
      </div>

      <section className="card">
        <div className="card-head">
          <div>
            <h2 className="section-title">
              {activeTab === "incoming"
                ? t("incomingCirculars")
                : t("outgoingCirculars")}
            </h2>
            <p className="text-xs text-muted">
              {activeTab === "incoming"
                ? t("incomingCircularsHint")
                : t("outgoingCircularsHint")}
            </p>
          </div>
          <span className="badge badge-neutral">{activeCount}</span>
        </div>

        {visibleItems.length ? (
          <div className="grid grid-cols-1 gap-3 p-4 md:grid-cols-2 xl:grid-cols-3">
            {visibleItems.map((item) => (
              <article
                key={item.id}
                className="flex flex-col gap-2 rounded border border-line bg-surface p-4"
              >
                <div className="flex items-start justify-between gap-2">
                  <div className="inline-grid h-8 w-8 shrink-0 place-items-center rounded border border-line bg-canvas text-brand-500">
                    <Megaphone size={15} />
                  </div>
                  <em
                    className={`badge not-italic ${
                      item.issuer.id === user.id ? "badge-warn" : "badge-brand"
                    }`}
                  >
                    {t(item.audience)}
                  </em>
                </div>
                <small className="text-xs text-muted">
                  {t("circularIssuedBy", { role: t(item.issuer.role) })} ·{" "}
                  {new Date(item.created_at).toLocaleDateString()}
                </small>
                <h2 className="text-sm font-semibold text-ink">{item.title}</h2>
                <p className="text-[13px] leading-relaxed whitespace-pre-wrap text-muted">
                  {item.content}
                </p>
                {item.attachment_url && (
                  <a
                    className="text-[13px] font-semibold text-brand-500 hover:underline"
                    href={item.attachment_url}
                    target="_blank"
                    rel="noreferrer"
                  >
                    {item.attachment_name || t("downloadAttachment")}
                  </a>
                )}
              </article>
            ))}
          </div>
        ) : (
          <div className="p-4">
            <div className="empty-state">
              <Megaphone size={28} className="text-muted" />
              <p>
                {activeTab === "incoming"
                  ? t("noIncomingCirculars")
                  : t("noOutgoingCirculars")}
              </p>
            </div>
          </div>
        )}
      </section>

      {form && (
        <ReusableFormModal
          title={t("newCircular")}
          subtitle={t("circularFormHint")}
          fields={fields}
          initial={{ audience: audiences[0]?.value || "" }}
          onSubmit={save}
          onClose={() => setForm(false)}
        />
      )}
    </div>
  );
}
