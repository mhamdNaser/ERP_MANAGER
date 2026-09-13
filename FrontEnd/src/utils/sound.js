export function sound(kind) {
  if (!sounds.enabled()) return;
  const AudioContextClass = window.AudioContext || window.webkitAudioContext;
  if (!AudioContextClass) return;
  const context = new AudioContextClass();
  const oscillator = context.createOscillator();
  const gain = context.createGain();
  oscillator.connect(gain);
  gain.connect(context.destination);
  oscillator.type = kind === "delete" ? "sawtooth" : "sine";
  oscillator.frequency.setValueAtTime(
    kind === "delete"
      ? 420
      : kind === "error"
        ? 180
        : kind === "notice"
          ? 480
          : 620,
    context.currentTime,
  );
  oscillator.frequency.exponentialRampToValueAtTime(
    kind === "delete" ? 95 : kind === "error" ? 120 : 880,
    context.currentTime + (kind === "delete" ? 0.32 : 0.18),
  );
  gain.gain.setValueAtTime(0.045, context.currentTime);
  gain.gain.exponentialRampToValueAtTime(
    0.001,
    context.currentTime + (kind === "delete" ? 0.36 : 0.22),
  );
  oscillator.start();
  oscillator.stop(context.currentTime + (kind === "delete" ? 0.37 : 0.23));
}
export const sounds = {
  enabled: () => localStorage.getItem("cnd_sounds_enabled") !== "false",
  setEnabled: (enabled) =>
    localStorage.setItem("cnd_sounds_enabled", String(enabled)),
  play: sound,
};
