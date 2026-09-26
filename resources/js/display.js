/**
 * Layar Utama / Display TV.
 * Broadcast dari server (QueueCalledEvent, channel 'queue-channel') -> bunyi bel.
 *
 * Tidak pakai TTS: petugas loket yang jackkan nomor secara manual (lebih jelas di
 * klinik ramai, dan tak bergantung voice id-ID yang bisa tak ada di TV).
 */
import "./echo";

const BOARD_LAST_CALL = document.querySelector("[data-last-call]");
const BOARD_NOW = document.querySelector("[data-now-serving]");
const BOARD_RESET = document.querySelector("[data-reset-notice]");

// Bel disintesis Web Audio — tak perlu file mp3 di public/ (aset tak bisa hilang).
let audioCtx;

function ensureAudio() {
    audioCtx ??= new (window.AudioContext || window.webkitAudioContext)();
    if (audioCtx.state === "suspended") audioCtx.resume();
}

// Chrome memblokir audio sebelum ada user gesture: bangunkan sekali di klik pertama.
document.addEventListener("click", ensureAudio, { once: true });

export function bell(times = 2) {
    ensureAudio();

    const start = audioCtx.currentTime;

    for (let i = 0; i < times; i++) {
        const at = start + i * 0.45;
        // Dua nada turun (G6 lalu D6) — pola bel pintu klinik.
        for (const [freq, offset] of [
            [1568, 0],
            [1175, 0.22],
        ]) {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();

            osc.type = "sine";
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0, at + offset);
            gain.gain.linearRampToValueAtTime(0.3, at + offset + 0.01);
            gain.gain.exponentialRampToValueAtTime(0.0001, at + offset + 0.8);

            osc.connect(gain).connect(audioCtx.destination);
            osc.start(at + offset);
            osc.stop(at + offset + 0.9);
        }
    }
}

export function renderCall({
    queue_label,
    queue_number,
    counter_number,
    status,
}) {
    const label =
        queue_label ?? `A-${String(queue_number ?? "").padStart(3, "0")}`;

    if (BOARD_LAST_CALL) BOARD_LAST_CALL.textContent = label;
    if (BOARD_NOW) BOARD_NOW.textContent = `Loket ${counter_number}`;

    bell();

    document.title =
        status === "CALLED"
            ? `${label} → Loket ${counter_number}`
            : "Display Antrian";
}

/** Admin mengosongkan antrean: layar & judul harus ikut kosong, jangan tampil nomor basi. */
export function renderReset() {
    if (BOARD_LAST_CALL) BOARD_LAST_CALL.textContent = "---";
    if (BOARD_NOW) BOARD_NOW.textContent = "...";
    document.title = "Display Antrian";

    if (!BOARD_RESET) return;

    BOARD_RESET.classList.remove("hidden");
    // Sembunyi lagi setelah 10 detik supaya tidak mengganggu pasien.
    setTimeout(() => BOARD_RESET.classList.add("hidden"), 10_000);
}

window.Echo.channel("queue-channel")
    .listen(".queue.called", (payload) => {
        renderCall(payload);
    })
    .listen(".queue.reset", () => {
        renderReset();
    });

//Tes cepat tanpa server: buka console -> renderCall({queue_label:'A-001',counter_number:1,status:'CALLED'})
window.renderCall = renderCall;
window.renderReset = renderReset;
