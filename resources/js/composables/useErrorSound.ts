// Beep de error sintetizado con Web Audio API: dos tonos descendentes.
// Se genera en runtime para no depender de un archivo de audio en public/.
let ctx: AudioContext | null = null;

const getContext = (): AudioContext | null => {
    if (typeof window === 'undefined') return null;

    const AudioCtx = window.AudioContext ?? (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
    if (!AudioCtx) return null;

    if (!ctx) ctx = new AudioCtx();

    return ctx;
};

// Un tono corto: frecuencia en Hz, inicio y duracion en segundos.
const tone = (audio: AudioContext, frequency: number, startAt: number, duration: number, volume: number) => {
    const oscillator = audio.createOscillator();
    const gain = audio.createGain();

    oscillator.type = 'square';
    oscillator.frequency.setValueAtTime(frequency, startAt);

    // Envolvente: ataque rapido y caida exponencial para que no suene a "click".
    gain.gain.setValueAtTime(0, startAt);
    gain.gain.linearRampToValueAtTime(volume, startAt + 0.01);
    gain.gain.exponentialRampToValueAtTime(0.0001, startAt + duration);

    oscillator.connect(gain);
    gain.connect(audio.destination);

    oscillator.start(startAt);
    oscillator.stop(startAt + duration);
};

export function playErrorSound(volume = 0.12) {
    const audio = getContext();
    if (!audio) return;

    // El navegador suspende el contexto hasta que haya interaccion del usuario;
    // para cuando aparece el modal ya hubo (escaneo/tipeo del DNI).
    if (audio.state === 'suspended') void audio.resume();

    const now = audio.currentTime;

    tone(audio, 440, now, 0.14, volume);
    tone(audio, 300, now + 0.16, 0.2, volume);
}

export function useErrorSound() {
    return { playErrorSound };
}
