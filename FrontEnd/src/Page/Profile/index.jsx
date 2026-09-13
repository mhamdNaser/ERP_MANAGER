import { Edit3, Eraser, FileText, Heart, MapPin, PenLine, Ruler, Save, UserRound, X } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { EmployeeDetailsModal } from "../../Components/EmployeeDetailsModal";
import { UserFormHistoryDrawer } from "../../Components/UserFormHistoryDrawer";
import { useLanguage } from "../../Provider/LanguageContext";
import { api } from "../../lib";
export function MyProfilePage({ user, notify }) {
  const { t } = useLanguage();
  const [profile, setProfile] = useState(user);
  const [edit, setEdit] = useState(false);
  const [history, setHistory] = useState(false);
  const [signatureOpen, setSignatureOpen] = useState(false);
  useEffect(() => {
    api
      .employeeDetails()
      .then(setProfile)
      .catch((e) => notify(e.message, "error"));
  }, [notify]);
  return (
    <div className="page">
      <div className="page-header">
        <div>
          <p className="eyebrow">{t("myInformation")}</p>
          <h1 className="page-title">{t("myProfile")}</h1>
          <p className="page-subtitle">{t("myProfileIntro")}</p>
        </div>
        <div className="flex flex-wrap items-center justify-end gap-2">
          {profile.can_manage_digital_signature && (
            <button
              className="btn btn-secondary"
              onClick={() => setSignatureOpen(true)}
            >
              <PenLine className="h-4 w-4" />
              {profile.digital_signature_url ? t("ui_editSignature") : t("ui_createSignature")}
            </button>
          )}
          <button className="btn btn-secondary" onClick={() => setHistory(true)}>
            <FileText className="h-4 w-4" />
            {t("formHistory")}
          </button>
          <button className="btn btn-primary" onClick={() => setEdit(true)}>
            <Edit3 className="h-4 w-4" />
            {t("editMyDetails")}
          </button>
        </div>
      </div>
      <section className="card flex items-center gap-3 p-4">
        <span className="inline-grid h-12 w-12 shrink-0 place-items-center rounded-full border border-line bg-subtle text-brand-500">
          <UserRound className="h-5 w-5" />
        </span>
        <div className="min-w-0">
          <h2 className="section-title truncate">{profile.name}</h2>
          <p className="text-[13px] text-muted">{profile.job_title}</p>
          <small className="block text-xs text-muted">
            {profile.office?.name ||
              `${profile.branch?.name || ""} · ${profile.department?.name || ""}`}
          </small>
        </div>
      </section>
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        {profile.can_manage_digital_signature && (
          <article className="panel flex flex-col gap-3">
            <header className="flex items-center gap-2 border-b border-line pb-3 text-brand-500">
              <PenLine className="h-4 w-4" />
              <h2 className="section-title">{t("ui_digitalSignature")}</h2>
            </header>
            {profile.digital_signature_url ? (
              <div className="grid min-h-24 place-items-center rounded border border-dashed border-line bg-subtle p-3">
                <img
                  className="max-h-24 max-w-full object-contain"
                  src={profile.digital_signature_url}
                  alt={t("ui_digitalSignature")}
                />
              </div>
            ) : (
              <p className="grid min-h-24 place-items-center rounded border border-dashed border-line bg-subtle p-3 text-center text-[13px] text-muted">
                {t("ui_noSignatureYet")}
              </p>
            )}
            <button
              className="btn btn-secondary btn-sm self-start"
              onClick={() => setSignatureOpen(true)}
            >
              <PenLine className="h-4 w-4" />
              {profile.digital_signature_url ? t("ui_updateSignature") : t("ui_createSignature")}
            </button>
          </article>
        )}
        <Detail
          icon={Ruler}
          title={t("personalMeasurements")}
          rows={[
            [t("heightCm"), profile.personal_details?.height_cm],
            [t("weightKg"), profile.personal_details?.weight_kg],
            [t("shoeSize"), profile.personal_details?.shoe_size],
            [t("trouserSize"), profile.personal_details?.trouser_size],
            [t("shirtSize"), profile.personal_details?.shirt_size],
          ]}
        />
        <Detail
          icon={MapPin}
          title={t("currentAddress")}
          rows={[
            [t("country"), profile.address?.country],
            [t("city"), profile.address?.city],
            [t("district"), profile.address?.district],
            [t("street"), profile.address?.street],
          ]}
        />
        <Detail
          icon={Heart}
          title={t("familyInformation")}
          rows={[
            [t("maritalStatus"), profile.family_details?.marital_status],
            [t("spouseName"), profile.family_details?.spouse_name],
            [t("childrenCount"), profile.family_details?.children_count],
            [
              t("emergencyContactName"),
              profile.family_details?.emergency_contact_name,
            ],
          ]}
        />
      </div>
      {edit && (
        <EmployeeDetailsModal
          employee={profile}
          self
          close={() => setEdit(false)}
          done={(updated) => {
            setProfile(updated);
            setEdit(false);
          }}
          notify={notify}
        />
      )}{" "}
      {history && (
        <UserFormHistoryDrawer
          user={profile}
          title={t("myFormHistory")}
          close={() => setHistory(false)}
        />
      )}
      {signatureOpen && (
        <SignatureModal
          close={() => setSignatureOpen(false)}
          done={(updated) => {
            setProfile(updated);
            setSignatureOpen(false);
            notify?.(t("ui_signatureSaved"), "success");
          }}
          notify={notify}
        />
      )}
    </div>
  );
}

function SignatureModal({ close, done, notify }) {
  const { t } = useLanguage();
  const canvasRef = useRef(null);
  const drawingRef = useRef(false);
  const lastPointRef = useRef(null);
  const hasInkRef = useRef(false);
  const [color, setColor] = useState("#111111");
  const colors = ["#111111", "#06312D", "#0d655b", "#1f4f9a", "#8b1e1e"];

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const context = canvas.getContext("2d");
    const resize = () => {
      const rect = canvas.getBoundingClientRect();
      const image = context.getImageData(0, 0, canvas.width || 1, canvas.height || 1);
      canvas.width = Math.max(640, Math.floor(rect.width * window.devicePixelRatio));
      canvas.height = Math.max(240, Math.floor(rect.height * window.devicePixelRatio));
      context.putImageData(image, 0, 0);
      context.lineCap = "round";
      context.lineJoin = "round";
      context.lineWidth = 3.2 * window.devicePixelRatio;
    };
    resize();
    window.addEventListener("resize", resize);
    return () => window.removeEventListener("resize", resize);
  }, []);

  const point = (event) => {
    const canvas = canvasRef.current;
    const rect = canvas.getBoundingClientRect();
    return {
      x: (event.clientX - rect.left) * window.devicePixelRatio,
      y: (event.clientY - rect.top) * window.devicePixelRatio,
    };
  };

  const start = (event) => {
    event.preventDefault();
    drawingRef.current = true;
    lastPointRef.current = point(event);
    canvasRef.current?.setPointerCapture?.(event.pointerId);
  };

  const draw = (event) => {
    if (!drawingRef.current) return;
    event.preventDefault();
    const canvas = canvasRef.current;
    const context = canvas.getContext("2d");
    const next = point(event);
    const previous = lastPointRef.current || next;
    context.strokeStyle = color;
    context.lineWidth = 3.2 * window.devicePixelRatio;
    context.beginPath();
    context.moveTo(previous.x, previous.y);
    context.lineTo(next.x, next.y);
    context.stroke();
    lastPointRef.current = next;
    hasInkRef.current = true;
  };

  const stop = (event) => {
    drawingRef.current = false;
    lastPointRef.current = null;
    if (event?.pointerId) canvasRef.current?.releasePointerCapture?.(event.pointerId);
  };

  const clear = () => {
    const canvas = canvasRef.current;
    canvas.getContext("2d").clearRect(0, 0, canvas.width, canvas.height);
    hasInkRef.current = false;
  };

  const save = async () => {
    if (!hasInkRef.current) {
      notify?.(t("ui_drawSignatureFirst"), "error");
      return;
    }

    try {
      const updated = await api.updateMySignature(canvasRef.current.toDataURL("image/png"));
      done(updated);
    } catch (error) {
      notify?.(error.message, "error");
    }
  };

  return (
    <div className="overlay grid place-items-center">
      <section className="modal max-w-3xl">
        <header className="modal-head">
          <div>
            <p className="eyebrow">{t("ui_digitalSignature")}</p>
            <h2 className="section-title">{t("ui_createSignature")}</h2>
            <span className="block text-xs text-muted">
              {t("ui_signatureHint")}
            </span>
          </div>
          <button className="btn-icon" onClick={close} type="button">
            <X className="h-4 w-4" />
          </button>
        </header>
        <div className="modal-body grid gap-3">
          <div className="flex items-center justify-between gap-3 rounded border border-line bg-subtle p-2">
            <div className="flex flex-wrap gap-2">
              {colors.map((item) => (
                <button
                  key={item}
                  type="button"
                  className={`h-7 w-7 rounded-full border-2 bg-[var(--signature-color)] ${
                    item === color ? "border-brand-800" : "border-line"
                  }`}
                  style={{ "--signature-color": item }}
                  onClick={() => setColor(item)}
                  title={item}
                />
              ))}
            </div>
            <button
              type="button"
              className="btn btn-secondary btn-sm"
              onClick={clear}
            >
              <Eraser className="h-4 w-4" />
              {t("ui_clear")}
            </button>
          </div>
          <canvas
            ref={canvasRef}
            className="h-64 w-full cursor-crosshair touch-none rounded border border-line bg-white"
            onPointerDown={start}
            onPointerMove={draw}
            onPointerUp={stop}
            onPointerLeave={stop}
          />
        </div>
        <footer className="modal-foot">
          <button type="button" className="btn btn-secondary" onClick={close}>
            {t("cancel")}
          </button>
          <button type="button" className="btn btn-primary" onClick={save}>
            <Save className="h-4 w-4" />
            {t("ui_saveSignature")}
          </button>
        </footer>
      </section>
    </div>
  );
}

function Detail({ icon: Icon, title, rows }) {
  return (
    <article className="panel">
      <header className="mb-2 flex items-center gap-2 border-b border-line pb-3 text-brand-500">
        <Icon className="h-4 w-4" />
        <h2 className="section-title">{title}</h2>
      </header>
      {rows.map(([label, value]) => (
        <p
          key={label}
          className="flex items-center justify-between gap-2 border-b border-line py-2 text-[13px] last:border-b-0"
        >
          <span className="text-muted">{label}</span>
          <b className="font-semibold text-ink">{String(value || "-")}</b>
        </p>
      ))}
    </article>
  );
}
