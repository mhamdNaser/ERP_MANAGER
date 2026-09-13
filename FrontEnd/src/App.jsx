import { RefreshCw } from "lucide-react";
import { useEffect, useState } from "react";
import { LanguageProvider, useLanguage } from "./Provider/LanguageContext";
import { AppShell } from "./Layout/AppShell";
import { api, token } from "./lib";
import { ConnectionGate, LoginPage } from "./Page/Auth";
import { ConfirmProvider } from "./Provider/ConfirmContext";
import { queryClient } from "./Apihooks/queryClient";
function Application() {
  const { t } = useLanguage();
  const [gate, setGate] = useState(false);
  const [user, setUser] = useState(null);
  const [restoring, setRestoring] = useState(Boolean(token.get()));
  useEffect(() => {
    if (!gate) return;
    const id = setTimeout(() => {
      if (token.get())
        api
          .me()
          .then(setUser)
          .catch(() => {
            queryClient.clear();
            token.clear();
          })
          .finally(() => setRestoring(false));
      else setRestoring(false);
    }, 0);
    return () => clearTimeout(id);
  }, [gate]);
  if (!gate) return <ConnectionGate ready={() => setGate(true)} />;
  if (restoring)
    return (
      <div className="flex min-h-screen flex-col items-center justify-center gap-3 bg-canvas text-[13px] text-muted">
        <RefreshCw size={22} className="text-brand-500" />
        <span>{t("restoring")}</span>
      </div>
    );
  if (!user)
    return (
      <LoginPage
        logged={(nextUser) => {
          queryClient.clear();
          setUser(nextUser);
        }}
      />
    );
  return (
    <AppShell
      user={user}
      exit={() => {
        queryClient.clear();
        token.clear();
        setUser(null);
      }}
    />
  );
}
export default function App() {
  return (
    <LanguageProvider>
      <ConfirmProvider>
        <Application />
      </ConfirmProvider>
    </LanguageProvider>
  );
}
