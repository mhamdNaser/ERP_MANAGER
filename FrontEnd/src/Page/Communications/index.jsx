import {
  Download,
  ExternalLink,
  FileText,
  ImageIcon,
  MailPlus,
  MessageSquareReply,
  Paperclip,
  Send,
  Trash2,
  X,
} from "lucide-react";
import { useEffect, useState } from "react";
import { useLanguage } from "../../Provider/LanguageContext";
import { useConfirm } from "../../Provider/ConfirmContext";
import { api } from "../../lib";
import { createEcho } from "../../realtime";
export function CommunicationsPage({ user, notify, unreadChanged }) {
  const { t } = useLanguage();
  const confirm = useConfirm();
  const [items, setItems] = useState([]);
  const [directory, setDirectory] = useState(null);
  const [compose, setCompose] = useState(false);
  const [selected, setSelected] = useState(null);
  const load = () =>
    api
      .messages()
      .then((data) => {
        setItems(data);
        unreadChanged?.(data.filter((item) => item.unread_for_user).length);
      })
      .catch((e) => notify(e.message, "error")); // eslint-disable-next-line react-hooks/exhaustive-deps
  useEffect(() => {
    void load();
  }, []);
  useEffect(() => {
    const echo = createEcho();
    if (!echo) return undefined;
    const channel = echo.private(`user.${user.id}`);
    const upsert = (message) => {
      setItems((current) => [
        message,
        ...current.filter((item) => item.id !== message.id),
      ]);
      setSelected((current) =>
        current?.id === message.id ? message : current,
      );
    };
    channel.listen(".message.created", (event) => upsert(event.message));
    channel.listen(".message.reply.created", (event) => upsert(event.message));
    return () => {
      echo.leave(`user.${user.id}`);
      echo.disconnect();
    };
  }, [user.id]);
  const openCompose = async () => {
    try {
      setDirectory(await api.communicationDirectory());
      setCompose(true);
    } catch (e) {
      notify(e.message, "error");
    }
  };
  const removeMessage = async (item) => {
    if (
      !(await confirm({
        message: t("ui_messageDeleteMessage", { subject: item.subject }),
        confirmLabel: t("deleteMessage"),
      }))
    )
      return;
    try {
      await api.deleteMessage(item.id);
      setItems((current) => current.filter((message) => message.id !== item.id));
      setSelected((current) => (current?.id === item.id ? null : current));
      notify(t("messageDeleted"), "delete");
    } catch (e) {
      notify(e.message, "error");
    }
  };
  const openMessage = async (item) => {
    setSelected(item);
    if (!item.unread_for_user) return;
    try {
      const updated = await api.markMessageRead(item.id);
      setSelected(updated);
      setItems((current) =>
        current.map((message) => (message.id === updated.id ? updated : message)),
      );
      unreadChanged?.((count) => Math.max(0, count - 1));
    } catch (e) {
      notify(e.message, "error");
    }
  };
  return (
    <div className="page">
      <div className="page-header">
        <div className="min-w-0">
          <p className="eyebrow">{t("internalNetwork")}</p>
          <h1 className="page-title mt-1">{t("messages")}</h1>
          <p className="page-subtitle mt-1">{t("messagesIntro")}</p>
        </div>
        <button className="btn btn-primary" onClick={openCompose}>
          <MailPlus size={16} />
          {t("newMessage")}
        </button>
      </div>
      <section className="card divide-y divide-line">
        {items.map((item) => (
          <div
            className={`grid grid-cols-[minmax(0,1fr)_auto_auto] items-center gap-3 px-4 py-3 hover:bg-subtle ${
              item.unread_for_user ? "bg-brand-50/50" : ""
            }`}
            key={item.id}
          >
            <button
              className="grid min-w-0 grid-cols-[32px_minmax(0,1fr)] items-center gap-3 text-start"
              onClick={() => openMessage(item)}
            >
              <span
                className={`inline-grid h-8 w-8 place-items-center rounded border ${
                  item.sender.id === user.id
                    ? "border-line bg-canvas text-muted"
                    : "border-brand-200 bg-brand-50 text-brand-500"
                }`}
              >
                <Send size={15} />
              </span>
              <div className="min-w-0">
                <b className="flex items-center gap-2 truncate text-[13px] font-semibold text-ink">
                  {item.subject}
                  {item.unread_for_user && (
                    <i
                      title={t("unread")}
                      className="inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-brand-500 not-italic"
                    />
                  )}
                </b>
                <p className="truncate text-xs text-muted">
                  {item.sender.id === user.id
                    ? `${t("to")}: ${item.recipient.name}`
                    : `${t("from")}: ${item.sender.name}`}
                </p>
              </div>
            </button>
            <small className="text-xs whitespace-nowrap text-muted">
              {new Date(item.created_at).toLocaleDateString()}
            </small>
            {item.sender.id === user.id && (
              <div className="flex items-center">
                <button
                  className="btn-icon"
                  onClick={() => removeMessage(item)}
                  title={t("deleteMessage")}
                  aria-label={t("deleteMessage")}
                >
                  <Trash2 size={15} />
                </button>
              </div>
            )}
          </div>
        ))}
      </section>
      {compose && directory && (
        <ComposeModal
          directory={directory}
          close={() => setCompose(false)}
          done={() => {
            setCompose(false);
            load();
          }}
          notify={notify}
        />
      )}
      {selected && (
        <CorrespondenceDrawer
          item={selected}
          user={user}
          close={() => setSelected(null)}
          changed={(item) => {
            setSelected(item);
            load();
          }}
          notify={notify}
        />
      )}
    </div>
  );
}
function ComposeModal({ directory, close, done, notify }) {
  const { t } = useLanguage();
  const [branch, setBranch] = useState("");
  const [department, setDepartment] = useState("");
  const [attachment, setAttachment] = useState(null);
  const [data, setData] = useState({
    recipient_id: "",
    subject: "",
    content: "",
    purpose: "",
    allow_reply: true,
  });
  const departments =
    directory.branches.find((item) => String(item.id) === branch)
      ?.departments || [];
  const users =
    departments.find((item) => String(item.id) === department)?.users || [];
  const offices = directory.offices.flatMap((office) =>
    (office.users || []).map((user) => ({ ...user, officeName: office.name })),
  );
  const submit = async (e) => {
    e.preventDefault();
    try {
      await api.createMessage({
        ...data,
        recipient_id: Number(data.recipient_id),
        allow_reply: data.allow_reply ? 1 : 0,
        attachment,
      });
      notify(t("messageSent"), "success");
      done();
    } catch (error) {
      notify(error.message, "error");
    }
  };
  return (
    <div className="overlay grid place-items-center overflow-y-auto">
      <section className="modal">
        <header className="modal-head">
          <div className="min-w-0">
            <small className="eyebrow">{t("dataEntry")}</small>
            <h2 className="mt-1 text-base font-semibold text-ink">
              {t("newMessage")}
            </h2>
            <p className="text-xs text-muted">{t("messageFormHint")}</p>
          </div>
          <button className="btn-icon shrink-0" onClick={close} aria-label={t("ui_close")}>
            <X size={16} />
          </button>
        </header>
        <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
          <div className="modal-body grid grid-cols-1 gap-4 sm:grid-cols-2">
            <label className="field mb-0">
              <span className="label">{t("branch")}</span>
              <select
                className="select"
                value={branch}
                onChange={(e) => {
                  setBranch(e.target.value);
                  setDepartment("");
                  setData({ ...data, recipient_id: "" });
                }}
              >
                <option value="">{t("chooseValue")}</option>
                {directory.branches.map((item) => (
                  <option value={item.id} key={item.id}>
                    {item.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="field mb-0">
              <span className="label">{t("department")}</span>
              <select
                className="select"
                value={department}
                onChange={(e) => {
                  setDepartment(e.target.value);
                  setData({ ...data, recipient_id: "" });
                }}
              >
                <option value="">{t("chooseValue")}</option>
                {departments.map((item) => (
                  <option value={item.id} key={item.id}>
                    {item.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="field mb-0">
              <span className="label">{t("recipient")} *</span>
              <select
                className="select"
                required
                value={data.recipient_id}
                onChange={(e) =>
                  setData({ ...data, recipient_id: e.target.value })
                }
              >
                <option value="">{t("chooseValue")}</option>
                {users.map((item) => (
                  <option value={item.id} key={item.id}>
                    {item.name}
                  </option>
                ))}
                {offices.map((item) => (
                  <option value={item.id} key={item.id}>
                    {item.name} · {item.officeName}
                  </option>
                ))}
              </select>
            </label>
            <label className="field mb-0">
              <span className="label">{t("messageSubject")} *</span>
              <input
                className="input"
                required
                value={data.subject}
                onChange={(e) => setData({ ...data, subject: e.target.value })}
              />
            </label>
            <label className="field mb-0">
              <span className="label">{t("attachment")}</span>
              <input
                className="input h-auto py-2 file:me-3 file:rounded file:border file:border-line file:bg-canvas file:px-2 file:py-1 file:text-xs file:font-semibold file:text-ink"
                type="file"
                accept=".pdf,.doc,.docx,.png,.jpg,.jpeg"
                onChange={(e) => setAttachment(e.target.files?.[0] ?? null)}
              />
            </label>
            <label className="field mb-0 sm:col-span-2">
              <span className="label">{t("messageContent")} *</span>
              <textarea
                className="textarea"
                required
                value={data.content}
                onChange={(e) => setData({ ...data, content: e.target.value })}
              />
            </label>
            <label className="field mb-0 sm:col-span-2">
              <span className="label">{t("messagePurpose")} *</span>
              <textarea
                className="textarea"
                required
                value={data.purpose}
                onChange={(e) => setData({ ...data, purpose: e.target.value })}
              />
            </label>
            <label className="flex items-center gap-2 rounded border border-line bg-subtle p-3 sm:col-span-2">
              <input
                className="h-4 w-4 shrink-0 accent-brand-800"
                type="checkbox"
                checked={data.allow_reply}
                onChange={(e) =>
                  setData({ ...data, allow_reply: e.target.checked })
                }
              />
              <span className="label">{t("allowReply")}</span>
            </label>
          </div>
          <footer className="modal-foot">
            <button type="button" className="btn btn-secondary" onClick={close}>
              {t("cancel")}
            </button>
            <button className="btn btn-primary">
              <Send size={16} />
              {t("send")}
            </button>
          </footer>
        </form>
      </section>
    </div>
  );
}
function CorrespondenceDrawer({ item, user, close, changed, notify }) {
  const { t } = useLanguage();
  const [reply, setReply] = useState("");
  const [replyAttachment, setReplyAttachment] = useState(null);
  const send = async () => {
    try {
      const updated = await api.replyMessage(item.id, {
        content: reply,
        attachment: replyAttachment,
      });
      setReply("");
      setReplyAttachment(null);
      changed(updated);
      notify(t("replySent"), "success");
    } catch (e) {
      notify(e.message, "error");
    }
  };
  return (
    <div className="overlay" onClick={close}>
      <aside className="drawer" onClick={(e) => e.stopPropagation()}>
        <header className="flex items-start justify-between gap-3 border-b border-line px-4 py-3">
          <div className="min-w-0">
            <span className="eyebrow">{t("message")}</span>
            <h2 className="mt-1 text-base font-semibold text-ink">
              {item.subject}
            </h2>
          </div>
          <button className="btn-icon shrink-0" onClick={close} aria-label={t("ui_close")}>
            <X size={16} />
          </button>
        </header>
        <div className="grid grid-cols-1 gap-3 border-b border-line bg-subtle px-4 py-3 sm:grid-cols-3">
          <p className="flex flex-col gap-1">
            <small className="text-xs font-semibold text-muted">
              {t("from")}
            </small>
            <b className="text-[13px] font-semibold break-words text-ink">
              {item.sender.name}
            </b>
          </p>
          <p className="flex flex-col gap-1">
            <small className="text-xs font-semibold text-muted">{t("to")}</small>
            <b className="text-[13px] font-semibold break-words text-ink">
              {item.recipient.name}
            </b>
          </p>
          <p className="flex flex-col gap-1">
            <small className="text-xs font-semibold text-muted">
              {t("messagePurpose")}
            </small>
            <b className="text-[13px] font-semibold break-words text-ink">
              {item.purpose}
            </b>
          </p>
        </div>
        <article className="flex flex-1 flex-col gap-3 overflow-y-auto p-4">
          {item.attachment_url && <MessageAttachment item={item} />}
          <p className="text-[13px] leading-relaxed whitespace-pre-wrap text-ink">
            {item.content}
          </p>
          <h3 className="section-title">{t("replies")}</h3>
          {item.replies.map((reply) => (
            <div
              className="flex flex-col gap-1.5 rounded border border-line bg-subtle p-3"
              key={reply.id}
            >
              <b className="text-[13px] font-semibold text-ink">
                {reply.sender.name}
              </b>
              <p className="text-[13px] leading-relaxed whitespace-pre-wrap text-muted">
                {reply.content}
              </p>
              {reply.attachment_url && <MessageAttachment item={reply} compact />}
            </div>
          ))}
        </article>
        {item.allow_reply &&
          [item.sender.id, item.recipient.id].includes(user.id) && (
            <footer className="flex flex-col gap-2 border-t border-line p-4">
              <textarea
                className="textarea min-h-20"
                placeholder={t("writeReply")}
                value={reply}
                onChange={(e) => setReply(e.target.value)}
              />
              <div className="flex flex-wrap items-center justify-between gap-2">
                <label className="inline-flex cursor-pointer items-center gap-2 rounded border border-line bg-white px-3 py-2 text-xs font-semibold text-muted hover:bg-canvas">
                  <PaperclipIcon />
                  <span className="max-w-[200px] truncate">
                    {replyAttachment?.name || t("attachment")}
                  </span>
                  <input
                    className="hidden"
                    type="file"
                    accept=".pdf,.doc,.docx,.png,.jpg,.jpeg"
                    onChange={(e) =>
                      setReplyAttachment(e.target.files?.[0] ?? null)
                    }
                  />
                </label>
                <button
                  className="btn btn-primary"
                  disabled={!reply}
                  onClick={send}
                >
                  <MessageSquareReply size={16} />
                  {t("reply")}
                </button>
              </div>
            </footer>
          )}
      </aside>
    </div>
  );
}

function PaperclipIcon() {
  return <Paperclip size={15} className="shrink-0" />;
}

function MessageAttachment({ item, compact = false }) {
  const { t } = useLanguage();
  const fileName = item.attachment_name || t("downloadAttachment");
  const isImage =
    item.attachment_mime?.startsWith("image/") ||
    /\.(png|jpe?g|gif|webp|bmp|svg)$/i.test(fileName);

  return (
    <section className="rounded border border-line bg-surface">
      <header className="flex flex-wrap items-center justify-between gap-2 border-b border-line bg-subtle px-3 py-2">
        <span className="inline-flex items-center gap-2 text-xs font-semibold text-ink">
          {isImage ? <ImageIcon size={14} /> : <FileText size={14} />}
          {t("attachment")}
        </span>
        <div className="flex flex-wrap items-center gap-3">
          <a
            className="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-500 hover:underline"
            href={item.attachment_url}
            target="_blank"
            rel="noreferrer"
          >
            <ExternalLink size={14} />
            {t("openAttachment")}
          </a>
          <a
            className="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-500 hover:underline"
            href={item.attachment_url}
            download={fileName}
          >
            <Download size={14} />
            {t("downloadAttachment")}
          </a>
        </div>
      </header>
      {isImage ? (
        <a
          className="block bg-canvas"
          href={item.attachment_url}
          target="_blank"
          rel="noreferrer"
        >
          <img
            className={`mx-auto block w-full object-contain ${
              compact ? "max-h-40" : "max-h-80"
            }`}
            src={item.attachment_url}
            alt={fileName}
          />
        </a>
      ) : (
        <a
          className="flex items-center gap-2 p-3 text-[13px] font-medium text-ink hover:bg-subtle"
          href={item.attachment_url}
          target="_blank"
          rel="noreferrer"
        >
          <FileText size={16} className="shrink-0 text-muted" />
          <span className="min-w-0 truncate">{fileName}</span>
        </a>
      )}
    </section>
  );
}
