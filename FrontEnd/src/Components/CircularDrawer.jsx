import { Megaphone, X } from 'lucide-react';
import { useLanguage } from '../Provider/LanguageContext';
export function CircularDrawer({ circular, close }) {
    const { t } = useLanguage();
    return <div className="overlay" onClick={close}>
    <aside className="drawer" onClick={event => event.stopPropagation()}>
      <header className="flex items-start justify-between gap-3 border-b border-line px-4 py-3">
        <div className="min-w-0">
          <span className="eyebrow">{t('linkedCircular')}</span>
          <h2 className="mt-1 text-base font-semibold text-ink">{circular.title}</h2>
          <small className="text-xs text-muted">{new Date(circular.created_at).toLocaleString()}</small>
        </div>
        <button className="btn-icon shrink-0" onClick={close} aria-label={t('ui_close')}><X size={16} /></button>
      </header>

      <section className="flex items-start gap-3 border-b border-line bg-subtle px-4 py-3">
        <div className="inline-grid h-9 w-9 shrink-0 place-items-center rounded border border-line bg-surface text-brand-500"><Megaphone size={18}/></div>
        <div className="min-w-0">
          <small className="block text-xs font-semibold text-muted">{t('sourceRole')}</small>
          <h2 className="mt-0.5 text-sm font-semibold text-ink">{t('circularIssuedBy', { role: t(circular.issuer?.role) })}</h2>
          <p className="text-[13px] text-muted">{circular.issuer?.job_title || circular.issuer?.name}</p>
        </div>
      </section>

      <div className="grid grid-cols-1 gap-3 border-b border-line px-4 py-3 sm:grid-cols-3">
        <p className="flex flex-col gap-1"><small className="text-xs font-semibold text-muted">{t('sourceRole')}</small><b className="text-[13px] font-semibold break-words text-ink">{t('circularIssuedBy', { role: t(circular.issuer?.role) })}</b></p>
        <p className="flex flex-col gap-1"><small className="text-xs font-semibold text-muted">{t('targetAudience')}</small><b className="text-[13px] font-semibold break-words text-ink">{t(circular.audience)}</b></p>
        <p className="flex flex-col gap-1"><small className="text-xs font-semibold text-muted">{t('notificationDetails')}</small><b className="text-[13px] font-semibold break-words text-ink">{circular.issuer?.job_title || circular.issuer?.name}</b></p>
      </div>

      <article className="flex flex-1 flex-col gap-3 overflow-y-auto p-4">
        <h3 className="text-sm font-semibold text-ink">{circular.title}</h3>
        <p className="text-[13px] leading-relaxed whitespace-pre-wrap text-ink">{circular.content}</p>
        {circular.attachment_url && <a className="text-[13px] font-semibold text-brand-500 hover:underline" href={circular.attachment_url} target="_blank" rel="noreferrer">{circular.attachment_name || t('downloadAttachment')}</a>}
      </article>
    </aside>
  </div>;
}
