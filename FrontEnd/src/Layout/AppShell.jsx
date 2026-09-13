import {
  BookOpenCheck,
  CalendarCheck,
  Building2,
  ChartColumn,
  ChevronDown,
  ChevronLeft,
  ClipboardList,
  Database,
  FileInput,
  FilePlus2,
  FileText,
  HardDrive,
  KanbanSquare,
  Landmark,
  LayoutDashboard,
  LogOut,
  Mail,
  Megaphone,
  Menu,
  ShieldCheck,
  UserRound,
  Users,
  X,
} from "lucide-react";
import {
  Fragment,
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
} from "react";
import { useNavigate, useParams } from "react-router-dom";
import { BrandLogo } from "../Components/BrandLogo";
import { Toast } from "../Components/Feedback";
import { SoundToggle } from "../Components/SoundToggle";
import { LanguageToggle } from "../Components/LanguageToggle";
import { NotificationCenter } from "../Components/NotificationCenter";
import { CircularDrawer } from "../Components/CircularDrawer";
import { ReportDrawer } from "../Components/ReportDrawer";
import { ReportFormModal } from "../Components/ReportFormModal";
import { ReusableFormModal } from "../Components/ReusableFormModal";
import { InterfaceGuide } from "../Components/InterfaceGuide";
import { TaskAssignedPopup } from "../Components/TaskAssignedPopup";
import { useLanguage } from "../Provider/LanguageContext";
import { useRealtimeUpdates } from "./useRealtimeUpdates";
import { useDashboardData } from "../Apihooks/useDashboard";
import { useSubmitFormPublication } from "../Apihooks/useForms";
import { useUnreadMessageCount } from "../Apihooks/useMessages";
import { useNotificationActions } from "../Apihooks/useNotifications";
import { tabRouteIds } from "../Apihooks/route";
import navigationItems from "../../json/navigation.json";
import interfaceGuide from "../../helpJson/interfaceGuide.json";
import { DashboardPage } from "../Page/Dashboard";
import { NotificationPage } from "../Page/Notifications";
import { OrganizationPage } from "../Page/Organization";
import { OrgTreePage } from "../Page/OrgTree";
import { PermissionsPage } from "../Page/Permissions";
import { ReportsPage } from "../Page/Reports";
import { CommunicationsPage } from "../Page/Communications";
import { FormalCorrespondencesPage } from "../Page/FormalCorrespondences";
import { CircularsPage } from "../Page/Circulars";
import { OfficesPage } from "../Page/Offices";
import { EmployeesPage } from "../Page/Employees";
import { HrPage } from "../Page/Hr";
import { HrMyRequestsPage } from "../Page/HrRequests";
import { MyProfilePage } from "../Page/Profile";
import { DatabaseManagerPage } from "../Page/Database";
import { FormsPage } from "../Page/Forms";
import { TasksBoardPage } from "../Page/Tasks";
import { TaskStatsPage } from "../Page/TaskStats";
import { DrivePage } from "../Page/Drive";
import { UserGuidePage } from "../Page/Guide";
export function AppShell({ user, exit }) {
  const { t } = useLanguage();
  const navigate = useNavigate();
  const { tab } = useParams();
  const view = tabRouteIds.includes(tab) ? tab : "dashboard";
  const [mobile, setMobile] = useState(false);
  const [accountOpen, setAccountOpen] = useState(false);
  const [selected, setSelected] = useState(null);
  const [selectedCircular, setSelectedCircular] = useState(null);
  const [selectedFormPublication, setSelectedFormPublication] = useState(null);
  const [editing, setEditing] = useState(null);
  const [reportForm, setReportForm] = useState(false);
  const [notice, setNotice] = useState(null);
  const [toast, setToast] = useState(null);
  const [focusTaskId, setFocusTaskId] = useState(null);
  const notify = useCallback((text, kind) => setToast({ text, kind }), []);
  const { data, reports, notifications, setNotifications, load } =
    useDashboardData(user.permissions || [], notify);
  const unreadQuery = useUnreadMessageCount(user.permissions || []);
  const {
    unreadMessageCountOverride,
    setUnreadMessageCountOverride,
    taskPopup,
    clearTaskPopup,
  } = useRealtimeUpdates(user.id, unreadQuery.data?.count, setNotifications);
  const unreadMessageCount =
    unreadMessageCountOverride ?? unreadQuery.data?.count ?? 0;
  const notificationActions = useNotificationActions();
  const submitFormPublication = useSubmitFormPublication();
  const accountRef = useRef(null);
  const navigateToView = useCallback(
    (nextView) => {
      navigate(nextView === "dashboard" ? "/" : `/${nextView}`);
    },
    [navigate],
  );
  useEffect(() => {
    if (tab && !tabRouteIds.includes(tab)) navigate("/", { replace: true });
  }, [navigate, tab]);
  useEffect(() => {
    const closeAccountMenu = (event) => {
      if (!accountRef.current?.contains(event.target)) setAccountOpen(false);
    };
    document.addEventListener("mousedown", closeAccountMenu);
    return () => document.removeEventListener("mousedown", closeAccountMenu);
  }, []);
  const viewAssignedTask = useCallback(
    (taskId) => {
      clearTaskPopup();
      setFocusTaskId(taskId);
      navigateToView("tasks");
    },
    [clearTaskPopup, navigateToView],
  );
  const openNotification = async (item) => {
    try {
      const detail = await notificationActions.notification.mutateAsync(item.id);
      setNotifications((items) =>
        items.map((n) => (n.id === item.id ? detail : n)),
      );
      if (detail.source_type === "task" && detail.task) {
        viewAssignedTask(detail.task.id);
        return;
      }
      setNotice(detail);
      navigateToView("notification");
      setSelected(null);
      setSelectedCircular(null);
      setSelectedFormPublication(null);
      if (detail.source_type === "report" && detail.report)
        setSelected(detail.report);
      if (detail.source_type === "circular" && detail.circular)
        setSelectedCircular(detail.circular);
      if (detail.source_type === "form" && detail.custom_form_publication)
        setSelectedFormPublication(detail.custom_form_publication);
    } catch (e) {
      notify(e.message, "error");
    }
  };
  const readAll = async () => {
    try {
      await notificationActions.readAll.mutateAsync();
      setNotifications((items) =>
        items.map((n) => ({
          ...n,
          read_at: n.read_at || new Date().toISOString(),
        })),
      );
    } catch (e) {
      notify(e.message, "error");
    }
  };
  const editReport = (report) => {
    setSelected(null);
    setEditing(report);
    setReportForm(true);
  };
  const openCircular = (circular) => {
    setSelectedCircular(circular);
    navigateToView("notification");
  };
  const openFormPublication = (publication) => {
    setSelectedFormPublication(publication);
    navigateToView("forms");
  };
  const nav = useMemo(
    () =>
      navigationItems
        .filter((item) => {
          const canUsePermission =
            !item.permission ||
            user.permissions?.includes(item.permission) ||
            user.permissions?.includes(item.fallbackPermission);
          const canUseRole = !item.roles || item.roles.includes(user.role);
          return canUsePermission && canUseRole;
        })
        .map((item) => [
          item.id,
          item.labelKey ? t(item.labelKey) : item.label,
          icons[item.icon],
          item.section,
        ]),
    [t, user.permissions, user.role],
  );
  const guideItems = useMemo(
    () =>
      nav
        .filter(([id]) => id !== "new")
        .map(([id, label]) => ({
          id,
          label,
          description: interfaceGuide.items[id] || interfaceGuide.fallback,
        })),
    [nav],
  );
  return (
    <div className="min-h-screen bg-canvas lg:grid lg:grid-cols-[var(--spacing-sidebar)_1fr]">
      {mobile && (
        <button
          aria-label={t("ui_closeMenu")}
          className="fixed inset-0 z-30 bg-brand-900/40 lg:hidden"
          onClick={() => setMobile(false)}
        />
      )}
      <aside
        data-guide="sidebar"
        className={`fixed inset-y-0 start-0 z-40 flex w-[var(--spacing-sidebar)] flex-col
          border-e border-brand-700 bg-brand-800 lg:sticky lg:top-0 lg:z-auto lg:h-screen lg:translate-x-0
          ${mobile ? "flex" : "hidden lg:flex"}`}
      >
        <div className="flex items-center justify-between gap-2 border-b border-white/10 px-4 py-3 text-white">
          <BrandLogo />
          <button
            className="inline-grid h-8 w-8 place-items-center rounded text-white/70 hover:bg-white/10 hover:text-white lg:hidden"
            onClick={() => setMobile(false)}
            aria-label={t("ui_closeMenu")}
          >
            <X size={18} />
          </button>
        </div>
        <nav
          data-guide="actions"
          className="flex flex-1 flex-col gap-0.5 overflow-y-auto px-2 py-3"
        >
          {nav.map(([id, label, Icon, section], index) => (
            <Fragment key={id}>
              {section !== nav[index - 1]?.[3] && (
                <small
                  className={`px-2 pb-2 text-[10px] font-bold tracking-wider text-white/40 uppercase ${
                    index === 0 ? "" : "pt-3"
                  }`}
                >
                  {t(section)}
                </small>
              )}
              <button
                data-guide={id}
                onClick={() => {
                  if (id === "new") {
                    setEditing(null);
                    setReportForm(true);
                  } else navigateToView(id);
                  setMobile(false);
                }}
                className={`flex items-center gap-2.5 rounded px-2.5 py-2 text-start text-[13px] font-medium
                ${
                  view === id
                    ? "bg-white/10 text-white"
                    : "text-white/65 hover:bg-white/5 hover:text-white"
                }`}
              >
              <Icon size={17} className="shrink-0" />
              <span className="flex-1 truncate">{label}</span>
              {id === "reports" && data?.stats.pending ? (
                <em className="not-italic rounded bg-white/15 px-1.5 py-0.5 text-[10px] font-bold text-white">
                  {data.stats.pending}
                </em>
              ) : null}
              {id === "communications" && unreadMessageCount > 0 ? (
                <em className="not-italic rounded bg-white/15 px-1.5 py-0.5 text-[10px] font-bold text-white">
                  {unreadMessageCount}
                </em>
              ) : null}
              </button>
            </Fragment>
          ))}
        </nav>
        <div className="flex items-start gap-2.5 border-t border-white/10 px-4 py-3 text-white">
          <Building2 size={16} className="mt-0.5 shrink-0 text-white/50" />
          <div className="min-w-0">
            <small className="block text-[10px] tracking-wide text-white/45 uppercase">
              {t("accessScope")}
            </small>
            <b className="block truncate text-[13px] font-semibold">
              {user.role === "general_manager"
                ? t("fullOrganization")
                : user.department?.name || t("systemAdmin")}
            </b>
            <span className="block truncate text-[11px] text-white/50">
              {user.branch?.name}
            </span>
          </div>
        </div>
      </aside>
      <main className="flex min-w-0 flex-col">
        <header className="sticky top-0 z-20 flex h-[var(--spacing-topbar)] items-center gap-3 border-b border-line bg-surface px-4">
          <button
            className="btn-icon lg:hidden"
            onClick={() => setMobile(true)}
            aria-label={t("ui_openMenu")}
          >
            <Menu size={18} />
          </button>
          <div className="flex min-w-0 items-center gap-1.5 text-[13px]">
            <span className="hidden text-muted sm:inline">
              {t("systemName")}
            </span>
            <ChevronLeft size={14} className="hidden text-muted sm:inline rtl:rotate-180" />
            <b className="truncate font-semibold text-ink">
              {nav.find((n) => n[0] === view)?.[1] || t("notifications")}
            </b>
          </div>
          <div className="ms-auto flex items-center gap-1.5">
            <LanguageToggle />
            <SoundToggle />
            <div data-guide="notifications">
              {user.permissions?.includes("notifications.view") && (
                <NotificationCenter
                  notifications={notifications}
                  open={openNotification}
                  readAll={readAll}
                />
              )}
            </div>
            <span className="hidden items-center gap-1.5 rounded border border-line bg-subtle px-2 py-1 text-[11px] font-medium text-muted xl:inline-flex">
              <i className="h-1.5 w-1.5 rounded-full bg-ok-500 not-italic" />
              {t("systemsOnline")}
            </span>
            <div className="relative" ref={accountRef}>
              <button
                className="flex items-center gap-2 rounded border border-line bg-white px-2 py-1 hover:bg-canvas"
                onClick={() => setAccountOpen((open) => !open)}
                aria-expanded={accountOpen}
              >
                <span className="inline-grid h-7 w-7 shrink-0 place-items-center rounded bg-brand-800 text-[13px] font-bold text-white">
                  {user.name.charAt(0)}
                </span>
                <span className="hidden min-w-0 flex-col items-start leading-tight md:flex">
                  <b className="max-w-[140px] truncate text-[12px] font-semibold">
                    {user.name}
                  </b>
                  <small className="max-w-[140px] truncate text-[10px] text-muted">
                    {user.job_title}
                  </small>
                </span>
                <ChevronDown size={14} className="shrink-0 text-muted" />
              </button>
              {accountOpen && (
                <div className="absolute end-0 top-[calc(100%+6px)] z-50 w-64 rounded border border-line bg-surface">
                  <div className="flex items-center gap-3 border-b border-line p-3">
                    <span className="inline-grid h-10 w-10 shrink-0 place-items-center rounded bg-brand-800 text-base font-bold text-white">
                      {user.name.charAt(0)}
                    </span>
                    <div className="min-w-0 leading-tight">
                      <b className="block truncate text-[13px] font-semibold">
                        {user.name}
                      </b>
                      <small className="block truncate text-[11px] text-muted">
                        {user.job_title}
                      </small>
                      <span className="block truncate text-[11px] text-muted">
                        {user.department?.name || t("systemAdmin")}
                      </span>
                    </div>
                  </div>
                  <div className="flex flex-col p-1">
                    <button
                      className="flex items-center gap-2 rounded px-2.5 py-2 text-start text-[13px] text-ink hover:bg-canvas"
                      onClick={() => {
                        navigateToView("profile");
                        setAccountOpen(false);
                      }}
                    >
                      <UserRound size={15} />
                      {t("myProfile")}
                    </button>
                    <button
                      className="flex items-center gap-2 rounded px-2.5 py-2 text-start text-[13px] text-danger-500 hover:bg-danger-50"
                      onClick={exit}
                    >
                      <LogOut size={15} />
                      {t("ui_logout")}
                    </button>
                  </div>
                </div>
              )}
            </div>
          </div>
        </header>
        <div data-guide="content" className="min-w-0 flex-1">
          {view === "dashboard" && data && (
            <DashboardPage
              data={{ ...data, notifications }}
              user={user}
              open={setSelected}
            />
          )}
          {view === "tasks" && (
            <TasksBoardPage
              user={user}
              notify={notify}
              focusTaskId={focusTaskId}
              onTaskFocused={() => setFocusTaskId(null)}
            />
          )}
          {view === "task-stats" && (
            <TaskStatsPage user={user} notify={notify} />
          )}
          {view === "drive" && <DrivePage user={user} notify={notify} />}
          {view === "reports" && (
            <ReportsPage reports={reports} open={setSelected} user={user} />
          )}
          {view === "organization" && (
            <OrganizationPage user={user} notify={notify} />
          )}
          {view === "org-tree" && <OrgTreePage notify={notify} />}
          {view === "communications" && (
            <CommunicationsPage
              user={user}
              notify={notify}
              unreadChanged={setUnreadMessageCountOverride}
            />
          )}
          {view === "formal-correspondences" && (
            <FormalCorrespondencesPage user={user} notify={notify} />
          )}
          {view === "circulars" && (
            <CircularsPage user={user} notify={notify} />
          )}
          {view === "forms" && (
            <FormsPage
              user={user}
              notify={notify}
              openPublication={openFormPublication}
            />
          )}
          {view === "offices" && <OfficesPage notify={notify} />}
          {view === "database" && (
            <DatabaseManagerPage
              reports={reports}
              open={setSelected}
              notify={notify}
              user={user}
            />
          )}
          {view === "employees" && <EmployeesPage notify={notify} />}
          {view === "hr" && <HrPage notify={notify} />}
          {view === "hr-requests" && <HrMyRequestsPage user={user} notify={notify} />}
          {view === "profile" && <MyProfilePage user={user} notify={notify} />}
          {view === "guide" && <UserGuidePage />}
          {view === "permissions" && <PermissionsPage notify={notify} />}
          {view === "notification" && notice && (
            <NotificationPage
              notice={notice}
              openReport={setSelected}
              openCircular={openCircular}
              openForm={openFormPublication}
            />
          )}
        </div>
      </main>
      {selected && (
        <ReportDrawer
          report={selected}
          user={user}
          close={() => setSelected(null)}
          changed={load}
          notify={notify}
          edit={editReport}
        />
      )}
      {selectedCircular && (
        <CircularDrawer
          circular={selectedCircular}
          close={() => setSelectedCircular(null)}
        />
      )}
      {selectedFormPublication && (
        <ReusableFormModal
          title={selectedFormPublication.form.title}
          subtitle={
            selectedFormPublication.message ||
            selectedFormPublication.form.description ||
            t("formAssignmentHint")
          }
          fields={selectedFormPublication.form.fields.map((field) => ({
            name: field.field_key,
            label: field.label,
            type: field.input_type,
            required: field.is_required,
            placeholder: field.placeholder,
            hint: field.help_text,
            options: field.options?.map((option) =>
              typeof option === "string"
                ? { value: option, label: option }
                : { value: option.value, label: option.label },
            ),
          }))}
          initial={selectedFormPublication.form.fields.reduce(
            (values, field) => ({
              ...values,
              [field.field_key]: field.input_type === "checkbox"
                ? false
                : field.input_type === "checkbox_group"
                  ? []
                  : "",
            }),
            {},
          )}
          onSubmit={async (values) => {
            try {
              await submitFormPublication.mutateAsync({
                publicationId: selectedFormPublication.id,
                values,
              });
              notify(t("formSubmitted"), "success");
              setSelectedFormPublication(null);
              navigateToView("forms");
            } catch (error) {
              notify(error.message, "error");
              throw error;
            }
          }}
          onClose={() => setSelectedFormPublication(null)}
          submitLabel={t("submitForm")}
        />
      )}
      {reportForm && (
        <ReportFormModal
          report={editing || undefined}
          notify={notify}
          close={() => {
            setReportForm(false);
            setEditing(null);
          }}
          done={() => {
            load();
            navigateToView("reports");
          }}
        />
      )}
      <InterfaceGuide items={guideItems} navigate={navigateToView} />
      {toast && <Toast {...toast} close={() => setToast(null)} />}
      {taskPopup && (
        <TaskAssignedPopup
          event={taskPopup}
          onView={() => viewAssignedTask(taskPopup.task.id)}
          onClose={clearTaskPopup}
        />
      )}
    </div>
  );
}
const icons = {
  CalendarCheck,
  BookOpenCheck,
  Building2,
  ChartColumn,
  ClipboardList,
  Database,
  FileInput,
  FilePlus2,
  FileText,
  HardDrive,
  KanbanSquare,
  Landmark,
  LayoutDashboard,
  Mail,
  Megaphone,
  ShieldCheck,
  UserRound,
  Users,
};
