import {
  CheckSquare,
  FileInput,
  FilePlus2,
  Pencil,
  RefreshCw,
  ShieldQuestion,
  Table2,
  Trash2,
} from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { FormBuilderModal } from "../../Components/FormBuilderModal";
import { FormSubmissionsDrawer } from "../../Components/FormSubmissionsDrawer";
import { ReusableFormModal } from "../../Components/ReusableFormModal";
import { useLanguage } from "../../Provider/LanguageContext";
import { api } from "../../lib";
import { useConfirm } from "../../Provider/ConfirmContext";
export function FormsPage({ user, notify, openPublication }) {
  const { t } = useLanguage();
  const confirm = useConfirm();
  const [overview, setOverview] = useState({
    templates: [],
    assigned: [],
    history: [],
  });
  const [tab, setTab] = useState(
    user.role === "database_manager" ? "created" : "assigned",
  );
  const [builderForm, setBuilderForm] = useState(undefined);
  const [publishForm, setPublishForm] = useState(null);
  const [submissionsForm, setSubmissionsForm] = useState(null);
  const [loading, setLoading] = useState(false);
  const [formSearch, setFormSearch] = useState("");
  const [formFilter, setFormFilter] = useState("all");
  const canManage =
    user.permissions?.includes("forms.manage") ||
    user.role === "database_manager";
  const canPublish = [
    "database_manager",
    "general_manager",
    "branch_manager",
    "department_head",
  ].includes(user.role);
  const load = async () => {
    try {
      setLoading(true);
      setOverview(await api.forms());
    } catch (error) {
      notify(error.message, "error");
    } finally {
      setLoading(false);
    }
  };
  useEffect(() => {
    void load();
  }, []); // eslint-disable-line react-hooks/exhaustive-deps
  const historyByForm = useMemo(
    () =>
      overview.history.reduce((groups, item) => {
        const list = groups[item.form.id] || [];
        list.push(item);
        groups[item.form.id] = list;
        return groups;
      }, {}),
    [overview.history],
  );
  const filteredTemplates = useMemo(() => {
    const query = formSearch.trim().toLowerCase();
    return overview.templates.filter((form) => {
      const matchesSearch =
        !query ||
        form.title.toLowerCase().includes(query) ||
        (form.description || "").toLowerCase().includes(query) ||
        t(form.target_group).toLowerCase().includes(query) ||
        (form.creator?.name || "").toLowerCase().includes(query);
      const matchesFilter =
        formFilter === "all" ||
        (formFilter === "published" && Boolean(form.latest_publication?.id)) ||
        (formFilter === "unpublished" && !form.latest_publication?.id) ||
        form.target_group === formFilter;
      return matchesSearch && matchesFilter;
    });
  }, [formSearch, formFilter, overview.templates, t]);
  const saveForm = async (payload) => {
    try {
      await api.saveForm(payload, builderForm?.id);
      notify(builderForm ? t("formUpdated") : t("formCreated"), "success");
      setBuilderForm(undefined);
      await load();
    } catch (error) {
      notify(error.message, "error");
      throw error;
    }
  };
  const removeForm = async (form) => {
    if (
      !(await confirm({
        message: t("confirmDeleteForm"),
        confirmLabel: t("delete"),
      }))
    )
      return;
    try {
      await api.deleteForm(form.id);
      notify(t("formDeleted"), "delete");
      await load();
    } catch (error) {
      notify(error.message, "error");
    }
  };
  const publish = async (values) => {
    if (!publishForm) return;
    try {
      await api.publishForm(publishForm.id, values);
      notify(t("formPublished"), "success");
      setPublishForm(null);
      setTab("assigned");
      await load();
    } catch (error) {
      notify(error.message, "error");
      throw error;
    }
  };
  const openEdit = (form) => setBuilderForm(form ?? null);
  const openCreate = () => setBuilderForm(null);
  const submitPublication = (publication) => openPublication(publication);
  const formHasPublication = (form) => Boolean(form.latest_publication?.id);
  const canViewFormSubmissions = (form) =>
    canManage || canPublish || form.creator?.id === user.id;
  const publishFields = [
    {
      name: "scope",
      label: t("scopeType"),
      type: "select",
      required: true,
      wide: true,
      options: [
        { value: "organization", label: t("scopeOrganization") },
        { value: "branch", label: t("scopeBranch") },
        { value: "department", label: t("scopeDepartment") },
      ],
    },
    {
      name: "target_group",
      label: t("targetGroup"),
      type: "select",
      required: true,
      options: [
        { value: "branch_managers", label: t("branch_managers") },
        { value: "branch_managers_heads", label: t("branch_managers_heads") },
        {
          value: "branch_managers_heads_employees",
          label: t("branch_managers_heads_employees"),
        },
        { value: "fixed_employees", label: t("fixed_employees") },
        { value: "contract_employees", label: t("contract_employees") },
        { value: "all_staff", label: t("targetAllStaff") },
      ],
    },
    {
      name: "duration_days",
      label: t("visibilityDays"),
      type: "number",
      required: true,
      hint: t("visibilityDaysHint"),
    },
    {
      name: "message",
      label: t("publicationNote"),
      type: "textarea",
      wide: true,
    },
  ];
  return (
    <div className="page">
      <div className="page-header">
        <div>
          <p className="eyebrow">{t("customForms")}</p>
          <h1 className="page-title">{t("formsTitle")}</h1>
          <p className="page-subtitle">{t("formsIntro")}</p>
        </div>
        {canManage && (
          <button className="btn btn-primary" onClick={openCreate}>
            <FilePlus2 size={16} />
            {t("createForm")}
          </button>
        )}
      </div>

      <div className="stat-grid xl:grid-cols-3">
        <article className="panel flex flex-col gap-1">
          <b className="text-xl font-semibold text-ink">
            {overview.templates.length}
          </b>
          <span className="text-xs text-muted">{t("createdForms")}</span>
        </article>
        <article className="panel flex flex-col gap-1">
          <b className="text-xl font-semibold text-ink">
            {overview.assigned.length}
          </b>
          <span className="text-xs text-muted">{t("assignedForms")}</span>
        </article>
        <article className="panel flex flex-col gap-1">
          <b className="text-xl font-semibold text-ink">
            {overview.history.length}
          </b>
          <span className="text-xs text-muted">{t("submissions")}</span>
        </article>
      </div>

      <div className="flex flex-wrap gap-2 border-b border-line">
        {[
          [
            "created",
            canManage ? t("createdForms") : t("availableForms"),
            overview.templates.length,
          ],
          ["assigned", t("assignedForms"), overview.assigned.length],
          ["history", t("submissionHistory"), overview.history.length],
        ].map(([id, label, count]) => (
          <button
            key={id}
            onClick={() => setTab(id)}
            className={`-mb-px flex items-center gap-2 border-b-2 px-3 py-2 text-[13px] font-semibold ${
              tab === id
                ? "border-brand-500 text-brand-500"
                : "border-transparent text-muted hover:text-ink"
            }`}
          >
            <span>{label}</span>
            <small className="rounded border border-line bg-canvas px-1.5 py-0.5 text-[11px] font-semibold text-muted">
              {count}
            </small>
          </button>
        ))}
      </div>

      {tab === "created" && (
        <section className="card">
          <div className="card-head">
            <div>
              <h2 className="section-title">
                {canManage ? t("createdForms") : t("availableForms")}
              </h2>
              <p className="text-xs text-muted">
                {canManage ? t("createdFormsHint") : t("availableFormsHint")}
              </p>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <label className="flex items-center">
                <input
                  className="input min-w-56"
                  value={formSearch}
                  onChange={(event) => setFormSearch(event.target.value)}
                  placeholder={t("searchForms")}
                />
              </label>
              <select
                className="select w-auto min-w-44"
                value={formFilter}
                onChange={(event) => setFormFilter(event.target.value)}
              >
                <option value="all">{t("allForms")}</option>
                <option value="published">{t("publishedOnly")}</option>
                <option value="unpublished">{t("unpublishedOnly")}</option>
                <option value="branch_managers">{t("branch_managers")}</option>
                <option value="branch_managers_heads">
                  {t("branch_managers_heads")}
                </option>
                <option value="branch_managers_heads_employees">
                  {t("branch_managers_heads_employees")}
                </option>
                <option value="fixed_employees">{t("fixed_employees")}</option>
                <option value="contract_employees">
                  {t("contract_employees")}
                </option>
                <option value="all_staff">{t("targetAllStaff")}</option>
              </select>
              <button
                className="btn btn-secondary btn-sm"
                onClick={() => {
                  setFormSearch("");
                  setFormFilter("all");
                }}
              >
                {t("reset")}
              </button>
              <button className="btn btn-secondary btn-sm" onClick={load}>
                <RefreshCw size={15} />
                {t("refresh")}
              </button>
            </div>
          </div>
          {filteredTemplates.length ? (
            <div className="grid grid-cols-1 gap-3 p-4 md:grid-cols-2 xl:grid-cols-3">
              {filteredTemplates.map((form) => (
                <article
                  key={form.id}
                  className="flex flex-col gap-2 rounded border border-line bg-surface p-3"
                >
                  <div className="flex items-center justify-between gap-2">
                    <span className="inline-grid h-8 w-8 shrink-0 place-items-center rounded border border-line bg-canvas text-muted">
                      <CheckSquare size={15} />
                    </span>
                    <span className="badge badge-neutral">
                      {t(form.target_group)}
                    </span>
                  </div>
                  <h3 className="text-[13px] font-semibold text-ink">
                    {form.title}
                  </h3>
                  <p className="flex-1 text-xs leading-relaxed text-muted">
                    {form.description || t("noDescription")}
                  </p>
                  {form.latest_publication && (
                    <div className="flex flex-col gap-0.5 rounded border border-line bg-subtle p-2.5">
                      <b className="text-[11px] font-semibold text-brand-500">
                        {t("published")}
                      </b>
                      <span className="text-[11px] text-muted">
                        {new Date(
                          form.latest_publication.published_at ||
                            form.latest_publication.created_at,
                        ).toLocaleString()}
                      </span>
                      <small className="text-[11px] text-muted">
                        {t("targetAudience")}:{" "}
                        {t(form.latest_publication.target_group)}
                      </small>
                    </div>
                  )}
                  <div className="flex flex-wrap justify-between gap-2 text-[11px] text-muted">
                    <small>
                      {t("formFields")}: {form.fields.length}
                    </small>
                    <small>
                      {t("submissions")}: {form.submission_count ?? 0}
                    </small>
                    <small>
                      {t("defaultScope")}:{" "}
                      {t(
                        `scope${form.default_scope.charAt(0).toUpperCase()}${form.default_scope.slice(1)}`,
                      )}
                    </small>
                  </div>
                  <div className="flex flex-wrap gap-2 border-t border-line pt-2">
                    {canManage && (
                      <>
                        <button
                          className="btn btn-secondary btn-sm flex-1"
                          onClick={() => openEdit(form)}
                        >
                          <Pencil size={14} />
                          {t("edit")}
                        </button>
                        <button
                          className="btn btn-danger btn-sm flex-1"
                          onClick={() => removeForm(form)}
                        >
                          <Trash2 size={14} />
                          {t("delete")}
                        </button>
                      </>
                    )}
                    {canViewFormSubmissions(form) && (
                      <button
                        className="btn btn-secondary btn-sm flex-1"
                        onClick={() => setSubmissionsForm(form)}
                      >
                        <Table2 size={14} />
                        {t("formSubmissions")}
                      </button>
                    )}
                    {canPublish && (
                      <button
                        className="btn btn-primary btn-sm flex-1"
                        onClick={() => setPublishForm(form)}
                      >
                        <FileInput size={14} />
                        {formHasPublication(form)
                          ? t("republishForm")
                          : t("publishForm")}
                      </button>
                    )}
                  </div>
                </article>
              ))}
            </div>
          ) : (
            <div className="p-4">
              <div className="empty-state">
                <ShieldQuestion size={22} className="text-line-strong" />
                <p>
                  {overview.templates.length
                    ? t("noMatchingForms")
                    : canManage
                      ? t("noFormsYet")
                      : t("noAvailableForms")}
                </p>
              </div>
            </div>
          )}
        </section>
      )}

      {tab === "assigned" && (
        <section className="card">
          <div className="card-head">
            <div>
              <h2 className="section-title">{t("assignedForms")}</h2>
              <p className="text-xs text-muted">{t("assignedFormsHint")}</p>
            </div>
            <button className="btn btn-secondary btn-sm" onClick={load}>
              <RefreshCw size={15} />
              {t("refresh")}
            </button>
          </div>
          {overview.assigned.length ? (
            <div className="grid grid-cols-1 gap-3 p-4 md:grid-cols-2 xl:grid-cols-3">
              {overview.assigned.map((publication) => (
                <article
                  key={publication.id}
                  className="flex flex-col gap-2 rounded border border-line bg-surface p-3"
                >
                  <div className="flex items-center justify-between gap-2">
                    <span className="inline-grid h-8 w-8 shrink-0 place-items-center rounded border border-brand-200 bg-brand-50 text-brand-600">
                      <CheckSquare size={15} />
                    </span>
                    <span className="badge badge-brand">
                      {t(publication.target_group)}
                    </span>
                  </div>
                  <h3 className="text-[13px] font-semibold text-ink">
                    {publication.form.title}
                  </h3>
                  <p className="flex-1 text-xs leading-relaxed text-muted">
                    {publication.message ||
                      publication.form.description ||
                      t("formAssignmentHint")}
                  </p>
                  <div className="flex flex-wrap justify-between gap-2 text-[11px] text-muted">
                    <small>
                      {publication.issuer?.job_title ||
                        publication.issuer?.name}
                    </small>
                    <small>
                      {publication.visible_until
                        ? new Date(
                            publication.visible_until,
                          ).toLocaleDateString()
                        : t("openEnded")}
                    </small>
                  </div>
                  <div className="flex flex-wrap gap-2 border-t border-line pt-2">
                    <button
                      className="btn btn-secondary btn-sm flex-1"
                      onClick={() => submitPublication(publication)}
                    >
                      <FileInput size={14} />
                      {t("fillForm")}
                    </button>
                  </div>
                </article>
              ))}
            </div>
          ) : (
            <div className="p-4">
              <div className="empty-state">
                <ShieldQuestion size={22} className="text-line-strong" />
                <p>{t("noAssignedForms")}</p>
              </div>
            </div>
          )}
        </section>
      )}

      {tab === "history" && (
        <section className="card">
          <div className="card-head">
            <div>
              <h2 className="section-title">{t("submissionHistory")}</h2>
              <p className="text-xs text-muted">{t("submissionHistoryHint")}</p>
            </div>
            <button className="btn btn-secondary btn-sm" onClick={load}>
              <RefreshCw size={15} />
              {t("refresh")}
            </button>
          </div>
          {overview.history.length ? (
            <div className="flex flex-col gap-3 p-4">
              {Object.entries(historyByForm).map(([formId, submissions]) => (
                <article
                  key={formId}
                  className="rounded border border-line bg-surface"
                >
                  <header className="flex flex-wrap items-center justify-between gap-3 border-b border-line px-3 py-2.5">
                    <div>
                      <h3 className="text-[13px] font-semibold text-ink">
                        {submissions[0].form.title}
                      </h3>
                      <p className="text-xs text-muted">
                        {submissions[0].form.description || t("noDescription")}
                      </p>
                    </div>
                    <span className="badge badge-neutral">
                      {submissions.length} {t("versions")}
                    </span>
                  </header>
                  <div className="grid grid-cols-1 gap-3 p-3 md:grid-cols-2 xl:grid-cols-3">
                    {submissions.map((submission) => (
                      <div
                        key={submission.id}
                        className="flex flex-col gap-2 rounded border border-line bg-subtle p-3"
                      >
                        <div className="flex items-start justify-between gap-2">
                          <b className="text-xs font-semibold text-ink">
                            {t("version")} #{submission.version}
                          </b>
                          <small className="text-[11px] text-muted">
                            {new Date(submission.submitted_at).toLocaleString()}
                          </small>
                        </div>
                        <div className="flex flex-col">
                          {submissions[0].form.fields.map((field) => (
                            <p
                              key={field.id}
                              className="flex justify-between gap-3 border-b border-line py-1.5 text-xs last:border-b-0"
                            >
                              <span className="text-muted">{field.label}</span>
                              <b className="text-end font-semibold text-ink">
                                {String(
                                  submission.payload[field.field_key] ?? "—",
                                )}
                              </b>
                            </p>
                          ))}
                        </div>
                      </div>
                    ))}
                  </div>
                </article>
              ))}
            </div>
          ) : (
            <div className="p-4">
              <div className="empty-state">
                <ShieldQuestion size={22} className="text-line-strong" />
                <p>{t("noSubmissions")}</p>
              </div>
            </div>
          )}
        </section>
      )}

      {builderForm !== undefined && (
        <FormBuilderModal
          form={builderForm || undefined}
          onClose={() => setBuilderForm(undefined)}
          onSubmit={saveForm}
        />
      )}
      {submissionsForm && (
        <FormSubmissionsDrawer
          form={submissionsForm}
          close={() => setSubmissionsForm(null)}
          notify={notify}
        />
      )}
      {publishForm && (
        <ReusableFormModal
          title={`${publishForm.latest_publication ? t("republishForm") : t("publishForm")} · ${publishForm.title}`}
          subtitle={
            publishForm.latest_publication
              ? `${t("published")} · ${new Date(publishForm.latest_publication.published_at || publishForm.latest_publication.created_at).toLocaleString()}`
              : t("publishFormHint")
          }
          fields={publishFields}
          initial={{
            scope: publishForm.default_scope,
            target_group: publishForm.target_group,
            duration_days: publishForm.default_duration_days || 7,
            message: "",
          }}
          onSubmit={publish}
          onClose={() => setPublishForm(null)}
          submitLabel={
            publishForm.latest_publication
              ? t("republishForm")
              : t("publishForm")
          }
        />
      )}
      {loading && <div className="text-xs text-muted">{t("loading")}</div>}
    </div>
  );
}
