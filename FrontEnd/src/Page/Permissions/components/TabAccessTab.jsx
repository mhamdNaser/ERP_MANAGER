import { Building2, Globe, Network, Plus, ShieldAlert, Trash2, UserRound } from "lucide-react";
import { Fragment, useCallback, useEffect, useMemo, useState } from "react";
import navigation from "../../../../json/navigation.json";
import { useLanguage } from "../../../Provider/LanguageContext";
import { api } from "../../../lib";

const NO_BRANCH = "none";
const navOf = (tab) => navigation.find((item) => item.id === tab);

function LevelSelect({ value, hasManage, onChange }) {
  const { t } = useLanguage();
  if (!hasManage) return <span className="badge badge-neutral shrink-0">{t("ta_level_view")}</span>;

  return (
    <select className="select h-8 w-auto shrink-0 text-xs" value={value} onChange={(event) => onChange(event.target.value)}>
      <option value="view">{t("ta_level_view")}</option>
      <option value="manage">{t("ta_level_manage")}</option>
    </select>
  );
}

/**
 * سطر الإضافة بالتسلسل الذي يفكّر به المستخدم: الفرع أولاً، ثم إما الفرع كله
 * أو قسم فيه أو موظف فيه. «بدون فرع» للإدارة العامة ومن لا فرع له.
 */
function AddAudience({ data, hasManage, onAdd }) {
  const { t } = useLanguage();
  const [branch, setBranch] = useState("");
  const [department, setDepartment] = useState("");
  const [person, setPerson] = useState("");
  const [level, setLevel] = useState("view");

  const noBranch = branch === NO_BRANCH;
  const departments = data.departments.filter((d) => String(d.branch_id) === branch);
  const people = data.users.filter((user) =>
    noBranch
      ? !user.branch_id
      : String(user.branch_id) === branch && (!department || String(user.department_id) === department),
  );
  const target = person
    ? { user_id: Number(person) }
    : department
      ? { department_id: Number(department) }
      : branch && !noBranch
        ? { branch_id: Number(branch) }
        : null;

  const reset = () => {
    setBranch("");
    setDepartment("");
    setPerson("");
    setLevel("view");
  };

  return (
    <div className="flex flex-wrap items-center gap-1.5 border-t border-line bg-subtle p-2.5">
      <select className="select h-8 min-w-36 flex-1 text-xs" value={branch} onChange={(event) => { setBranch(event.target.value); setDepartment(""); setPerson(""); }}>
        <option value="">{t("ta_pickBranch")}</option>
        {data.branches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
        <option value={NO_BRANCH}>{t("ta_noBranch")}</option>
      </select>
      {branch && !noBranch && (
        <select className="select h-8 min-w-36 flex-1 text-xs" value={department} onChange={(event) => { setDepartment(event.target.value); setPerson(""); }}>
          <option value="">{t("ta_wholeBranch")}</option>
          {departments.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
        </select>
      )}
      {branch && (
        <select className="select h-8 min-w-36 flex-1 text-xs" value={person} onChange={(event) => setPerson(event.target.value)}>
          <option value="">{noBranch ? t("ta_pickPerson") : t("ta_anyPerson")}</option>
          {people.map((user) => (
            <option key={user.id} value={user.id}>{[user.name, user.job_title].filter(Boolean).join(" — ")}</option>
          ))}
        </select>
      )}
      <LevelSelect value={level} hasManage={hasManage} onChange={setLevel} />
      <button
        className="btn btn-primary btn-sm h-8"
        disabled={!target}
        onClick={async () => {
          if (await onAdd({ ...target, level })) reset();
        }}
      >
        <Plus size={14} />
        {t("ta_add")}
      </button>
    </div>
  );
}

/**
 * جمهور كل تبويب في الشريط الجانبي. بلا جمهور يعمل التبويب حسب الأدوار كما
 * هو؛ ومتى اختير له جمهور — الجميع أو أفرع أو أقسام أو موظفون، معاً — صار
 * مقصوراً عليه (ومدير قواعد البيانات يراه دائماً).
 */
export function TabAccessTab({ notify }) {
  const { t } = useLanguage();
  const [data, setData] = useState({ tabs: [], grants: [], branches: [], departments: [], users: [] });
  const [picked, setPicked] = useState(null);

  const load = useCallback(() => api.tabAccess().then(setData).catch((error) => notify(error.message, "error")), [notify]);
  useEffect(() => { load(); }, [load]);

  // بترتيب الشريط الجانبي وأقسامه.
  const tabs = useMemo(
    () => navigation.map((item) => data.tabs.find((tab) => tab.id === item.id)).filter(Boolean),
    [data.tabs],
  );
  const tab = tabs.find((entry) => entry.id === picked) || tabs[0];
  const grants = data.grants.filter((grant) => grant.tab === tab?.id);
  const everyone = grants.find((grant) => grant.everyone);
  const audience = grants.filter((grant) => !grant.everyone);
  const names = useMemo(
    () => ({
      branch: Object.fromEntries(data.branches.map((b) => [b.id, b.name])),
      department: Object.fromEntries(data.departments.map((d) => [d.id, d])),
    }),
    [data.branches, data.departments],
  );

  const run = async (action, message) => {
    try {
      await action();
      notify(t(message), "success");
      await load();
      return true;
    } catch (error) {
      notify(error.message, "error");
      return false;
    }
  };
  const add = (payload) => run(() => api.saveTabAccess({ tab: tab.id, ...payload }), "ta_linked");
  const setLevel = (grant, level) => run(() => api.updateTabAccess(grant.id, level), "ta_levelSaved");
  const remove = (grant) => run(() => api.deleteTabAccess(grant.id), "ta_unlinked");

  const describe = (grant) => {
    if (grant.branch_id) return { icon: Network, title: grant.branch?.name, subtitle: t("ta_wholeBranch") };
    if (grant.department_id) {
      const branchName = names.branch[names.department[grant.department_id]?.branch_id];
      return { icon: Building2, title: grant.department?.name, subtitle: branchName ? t("ta_inBranch", { name: branchName }) : null };
    }
    const branchName = names.branch[grant.user?.branch_id];
    return {
      icon: UserRound,
      title: grant.user?.name,
      subtitle: [grant.user?.job_title, branchName ? t("ta_inBranch", { name: branchName }) : t("ta_noBranch")].filter(Boolean).join(" · "),
    };
  };

  const status = (entry) => {
    const own = data.grants.filter((grant) => grant.tab === entry.id);
    if (!own.length) return [t("ta_byRoles"), "text-muted"];
    if (own.some((grant) => grant.everyone)) return [t("ta_everyoneShort"), "text-ok-500"];
    return [t("ta_audienceCount", { count: own.length }), "text-brand-600"];
  };

  return (
    <div className="flex flex-col gap-3">
      <p className="text-sm text-muted">{t("ta_intro")}</p>
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-[300px_minmax(0,1fr)]">
        <section className="card flex min-w-0 flex-col">
          <header className="card-head"><h2 className="section-title">{t("ta_tabs")}</h2></header>
          <ul className="flex max-h-[640px] flex-col overflow-y-auto">
            {tabs.map((entry, index) => {
              const item = navOf(entry.id);
              const [label, tone] = status(entry);
              const newSection = item?.section !== navOf(tabs[index - 1]?.id)?.section;
              return (
                <Fragment key={entry.id}>
                  {newSection && item?.section && (
                    <li className="border-t border-line bg-subtle px-3 pt-2 pb-1 text-[11px] font-bold text-muted first:border-t-0">{t(item.section)}</li>
                  )}
                  <li>
                    <button
                      type="button"
                      className={`flex w-full items-center justify-between gap-2 border-t border-line px-3 py-2.5 text-start text-sm ${entry.id === tab?.id ? "bg-brand-50 font-semibold text-brand-700" : "text-ink hover:bg-subtle"}`}
                      onClick={() => setPicked(entry.id)}
                    >
                      <span className="truncate">{t(item?.labelKey || entry.id)}</span>
                      <small className={`shrink-0 text-xs ${tone}`}>{label}</small>
                    </button>
                  </li>
                </Fragment>
              );
            })}
          </ul>
        </section>

        {tab && (
          <div className="flex min-w-0 flex-col gap-4">
            <header className="flex flex-col gap-1">
              <h2 className="text-base font-semibold text-ink">{t(navOf(tab.id)?.labelKey || tab.id)}</h2>
              <p className="text-xs text-muted">
                {grants.length ? t("ta_restrictedNow") : t("ta_byRolesNow")} {t("ta_reloginHint")}
              </p>
              {tab.role_limited && (
                <p className="flex items-start gap-1.5 rounded border border-warn-500/20 bg-warn-50 px-3 py-2 text-xs text-warn-500">
                  <ShieldAlert size={14} className="mt-0.5 shrink-0" />
                  {t("ta_roleLimited")}
                </p>
              )}
            </header>

            <section className="card flex items-center gap-3 px-4 py-3">
              <Globe size={18} className="shrink-0 text-brand-500" />
              <label className="flex min-w-0 flex-1 cursor-pointer items-center gap-2">
                <input
                  type="checkbox"
                  checked={!!everyone}
                  onChange={(event) => (event.target.checked ? add({ everyone: true, level: "view" }) : remove(everyone))}
                />
                <span className="min-w-0">
                  <b className="block text-sm font-semibold text-ink">{t("ta_everyone")}</b>
                  <small className="block text-xs text-muted">{t("ta_everyoneHint")}</small>
                </span>
              </label>
              {everyone && <LevelSelect value={everyone.level} hasManage={tab.has_manage} onChange={(level) => setLevel(everyone, level)} />}
            </section>

            <section className="card flex min-w-0 flex-col">
              <header className="card-head">
                <div>
                  <h3 className="section-title">{t("ta_audience")}</h3>
                  <p className="page-subtitle mt-0.5 text-xs">{t("ta_audienceHint")}</p>
                </div>
                {tab.has_manage && <small className="text-xs text-muted">{t("ta_levelHint")}</small>}
              </header>
              {audience.length ? (
                <ul className="flex flex-col divide-y divide-line">
                  {audience.map((grant) => {
                    const { icon: Icon, title, subtitle } = describe(grant);
                    return (
                      <li key={grant.id} className="flex items-center gap-2 px-3 py-2">
                        <Icon size={15} className="shrink-0 text-muted" aria-hidden />
                        <div className="min-w-0 flex-1">
                          <b className="block truncate text-sm font-semibold text-ink">{title}</b>
                          {subtitle && <small className="block truncate text-xs text-muted">{subtitle}</small>}
                        </div>
                        <LevelSelect value={grant.level} hasManage={tab.has_manage} onChange={(level) => setLevel(grant, level)} />
                        <button className="btn-icon text-danger-500" title={t("ta_unlink")} onClick={() => remove(grant)}>
                          <Trash2 size={14} />
                        </button>
                      </li>
                    );
                  })}
                </ul>
              ) : (
                <p className="px-3 py-4 text-xs text-muted">{t("ta_noAudience")}</p>
              )}
              <AddAudience data={data} hasManage={tab.has_manage} onAdd={add} />
            </section>
          </div>
        )}
      </div>
    </div>
  );
}
