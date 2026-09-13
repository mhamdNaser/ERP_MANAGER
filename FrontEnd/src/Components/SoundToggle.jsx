import { Volume2, VolumeX } from "lucide-react";
import { useState } from "react";
import { sounds } from "../utils/sound";
import { useLanguage } from "../Provider/LanguageContext";
export function SoundToggle() {
  const { t } = useLanguage();
  const [enabled, setEnabled] = useState(sounds.enabled());
  const toggle = () => {
    const next = !enabled;
    sounds.setEnabled(next);
    setEnabled(next);
    if (next) sounds.play("notice");
  };
  return (
    <button
      className="btn-icon"
      onClick={toggle}
      title={enabled ? t("ui_muteSystemSounds") : t("ui_enableSystemSounds")}
    >
      {enabled ? <Volume2 size={17} /> : <VolumeX size={17} />}
    </button>
  );
}
