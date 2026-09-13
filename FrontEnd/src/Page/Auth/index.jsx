import { Activity, AlertTriangle, Database, RefreshCw, Server, ShieldCheck, } from "lucide-react";
import { useCallback, useEffect, useState } from "react";
import { BrandLogo } from "../../Components/BrandLogo";
import { useLanguage } from "../../Provider/LanguageContext";
import { api, sound, token } from "../../lib";
export function ConnectionGate({ ready }) {
    const { t } = useLanguage();
    const [state, setState] = useState("checking");
    const [health, setHealth] = useState({});
    const check = useCallback(async () => {
        setState("checking");
        try {
            const data = await api.health();
            setHealth(data);
            setState("ready");
            setTimeout(ready, 1100);
        }
        catch (e) {
            setHealth({ message: e.message });
            setState("error");
        }
    }, [ready]);
    useEffect(() => {
        const id = setTimeout(check, 0);
        return () => clearTimeout(id);
    }, [check]);
    return (<main className="flex min-h-screen flex-col items-center justify-center gap-6 bg-canvas p-6">
      <BrandLogo compact/>
      <div className="w-full max-w-[520px] rounded border border-line bg-surface p-6 text-center">
        <span className={`mx-auto mb-4 inline-grid h-14 w-14 place-items-center rounded border ${state === "error"
            ? "border-danger-500/25 bg-danger-50 text-danger-500"
            : "border-line bg-subtle text-brand-500"}`}>
          <Server size={24}/>
        </span>
        <p className="eyebrow">{t("gateEyebrow")}</p>
        <h1 className="mt-1 text-xl font-semibold text-ink">
          {state === "checking"
            ? t("checking")
            : state === "ready"
                ? t("systemsReady")
                : t("systemFailed")}
        </h1>
        <p className="mt-1 text-[13px] text-muted">
          {state === "error" ? health.message : t("gateHint")}
        </p>
        <div className="my-5 flex flex-col border-t border-line">
          <Check icon={Server} label={t("appServer")} state={state === "checking" ? t("connecting") : t("connected")} ok={state !== "error"}/>
          <Check icon={Database} label={t("database")} state={state === "ready"
            ? `${health.connection} · ${health.latency_ms}ms`
            : state === "error"
                ? t("disconnected")
                : t("underCheck")} ok={state === "ready"}/>
          <Check icon={ShieldCheck} label={t("permissionsService")} state={state === "ready" ? t("ready") : t("waitingData")} ok={state === "ready"}/>
        </div>
        {state === "error" && (<button className="btn btn-primary w-full" onClick={check}>
            <RefreshCw size={16}/>
            {t("retry")}
          </button>)}
      </div>
      <p className="text-xs text-muted">
        CND {t("ui_brandSubtitle")} · {t("secureChannel")}
      </p>
    </main>);
}
function Check({ icon: Icon, label, state, ok, }) {
    return (<div className="flex items-center gap-3 border-b border-line py-3 text-start">
      <span className={`inline-grid h-8 w-8 shrink-0 place-items-center rounded border ${ok
            ? "border-ok-500/20 bg-ok-50 text-ok-500"
            : "border-line bg-canvas text-muted"}`}>
        <Icon size={16}/>
      </span>
      <b className="min-w-0 flex-1 truncate text-[13px] font-semibold text-ink">{label}</b>
      <small className="shrink-0 text-xs text-muted">{state}</small>
    </div>);
}
export function LoginPage({ logged }) {
    const { t } = useLanguage();
    const [email, setEmail] = useState("");
    const [password, setPassword] = useState("");
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");
    const submit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setError("");
        try {
            const data = await api.login(email, password);
            token.set(data.token);
            sound("success");
            logged(data.user);
        }
        catch (err) {
            setError(err.message);
            sound("error");
        }
        finally {
            setLoading(false);
        }
    };
    return (<main className="grid min-h-screen bg-surface lg:grid-cols-[minmax(360px,40%)_1fr]">
      <section className="hidden flex-col justify-between gap-10 bg-brand-800 p-8 text-white lg:flex">
        <BrandLogo />
        <div className="min-w-0">
          <p className="text-[11px] font-bold tracking-wide text-brand-200 uppercase">CND Operations</p>
          <h1 className="mt-2 mb-3 text-2xl font-semibold text-white">{t("reportsTitle")}</h1>
          <p className="max-w-[48ch] text-[13px] leading-relaxed text-white/70">{t("reportsIntro")}</p>
        </div>
        <div className="grid grid-cols-3 border-t border-white/15">
          <div className="flex flex-col gap-1 py-4">
            <b className="text-xl font-semibold">5</b>
            <span className="text-xs text-white/60">{t("accessScope")}</span>
          </div>
          <div className="flex flex-col gap-1 py-4">
            <b className="text-xl font-semibold">4</b>
            <span className="text-xs text-white/60">{t("documentType")}</span>
          </div>
          <div className="flex flex-col gap-1 py-4">
            <Activity className="h-5 w-5 text-white/70"/>
            <span className="text-xs text-white/60">{t("operationalView")}</span>
          </div>
        </div>
      </section>
      <section className="flex items-center justify-center p-6">
        <form onSubmit={submit} className="flex w-full max-w-[380px] flex-col">
          <BrandLogo />
          <p className="eyebrow mt-6">{t("loginEyebrow")}</p>
          <h2 className="mt-1 text-2xl font-semibold text-ink">{t("welcomeBack")}</h2>
          <p className="mt-1 mb-6 text-[13px] text-muted">{t("loginHint")}</p>
          {error && (<div className="mb-4 flex items-center gap-2 rounded border border-danger-500/25 bg-danger-50 px-3 py-2.5 text-[13px] text-danger-500">
              <AlertTriangle size={18}/>
              {error}
            </div>)}
          <label className="field">
            <span className="label">{t("email")}</span>
            <input className="input" value={email} onChange={(e) => setEmail(e.target.value)} type="email"/>
          </label>
          <label className="field">
            <span className="label">{t("password")}</span>
            <input className="input" value={password} onChange={(e) => setPassword(e.target.value)} type="password"/>
          </label>
          <button className="btn btn-primary w-full" disabled={loading}>
            {loading ? (<RefreshCw size={16}/>) : (<ShieldCheck size={16}/>)}{" "}
            {t("login")}
          </button>
        </form>
      </section>
    </main>);
}
