/**
 * Constantes y formateadores compartidos por las pantallas del Service Desk.
 *
 * La matriz de prioridad vive duplicada aquí y en `ServiceDeskTicket::PRIORITY_MATRIX`: el
 * backend es la fuente de verdad (lo que se guarda), esta copia solo sirve para previsualizar
 * la prioridad mientras el usuario elige impacto y urgencia. Si cambia una, cambie la otra.
 */

export const PRIORITY_MATRIX: Record<number, Record<number, number>> = {
    1: { 1: 1, 2: 2, 3: 3 },
    2: { 1: 2, 2: 3, 3: 4 },
    3: { 1: 3, 2: 4, 3: 4 },
};

export const calculatePriority = (impact: number, urgency: number): number => PRIORITY_MATRIX[impact]?.[urgency] ?? 4;

export const IMPACT_OPTIONS = [
    { value: 1, label: 'Alto — afecta a toda la sede o a un servicio crítico' },
    { value: 2, label: 'Medio — afecta a un área o grupo de usuarios' },
    { value: 3, label: 'Bajo — afecta a un solo usuario' },
];

export const URGENCY_OPTIONS = [
    { value: 1, label: 'Alta — detiene la operación, sin alternativa' },
    { value: 2, label: 'Media — entorpece la operación, hay alternativa' },
    { value: 3, label: 'Baja — puede esperar la próxima ventana' },
];

export const PRIORITY_LABELS: Record<number, string> = { 1: 'P1 Crítica', 2: 'P2 Alta', 3: 'P3 Media', 4: 'P4 Baja' };

export const STATUS_LABELS: Record<string, string> = {
    nuevo: 'Nuevo',
    asignado: 'Asignado',
    en_proceso: 'En proceso',
    en_espera: 'En espera',
    resuelto: 'Resuelto',
    cerrado: 'Cerrado',
};

export const CHANNEL_LABELS: Record<string, string> = {
    portal: 'Portal',
    telefono: 'Teléfono',
    correo: 'Correo',
    presencial: 'Presencial',
    whatsapp: 'WhatsApp',
};

export const DIMENSION_LABELS: Record<string, string> = {
    estandarizacion: 'Estandarización del flujo',
    conocimiento: 'Gestión del conocimiento',
    priorizacion: 'Priorización y categorización',
};

export const priorityClass = (priority: number): string =>
    ({
        1: 'bg-red-50 text-red-700 ring-red-600/20',
        2: 'bg-orange-50 text-orange-700 ring-orange-600/20',
        3: 'bg-amber-50 text-amber-700 ring-amber-600/20',
        4: 'bg-slate-50 text-slate-600 ring-slate-500/20',
    })[priority] ?? 'bg-slate-50 text-slate-600 ring-slate-500/20';

export const statusClass = (status: string): string =>
    ({
        nuevo: 'bg-sky-50 text-sky-700 ring-sky-600/20',
        asignado: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
        en_proceso: 'bg-violet-50 text-violet-700 ring-violet-600/20',
        en_espera: 'bg-amber-50 text-amber-700 ring-amber-600/20',
        resuelto: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        cerrado: 'bg-zinc-100 text-zinc-600 ring-zinc-500/20',
    })[status] ?? 'bg-zinc-100 text-zinc-600 ring-zinc-500/20';

/** Semáforo del tablero de indicadores. */
export const statusTone = (status: string) =>
    ({
        cumple: { label: 'Cumple', badge: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', bar: 'bg-emerald-500' },
        riesgo: { label: 'En riesgo', badge: 'bg-amber-50 text-amber-700 ring-amber-600/20', bar: 'bg-amber-500' },
        incumple: { label: 'No cumple', badge: 'bg-red-50 text-red-700 ring-red-600/20', bar: 'bg-red-500' },
        sin_datos: { label: 'Sin datos', badge: 'bg-zinc-100 text-zinc-500 ring-zinc-400/20', bar: 'bg-zinc-300' },
    })[status] ?? { label: status, badge: 'bg-zinc-100 text-zinc-500 ring-zinc-400/20', bar: 'bg-zinc-300' };

export const severityTone = (severity: string) =>
    ({
        ok: { label: 'Conforme', badge: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' },
        info: { label: 'Informativo', badge: 'bg-sky-50 text-sky-700 ring-sky-600/20' },
        warning: { label: 'Advertencia', badge: 'bg-amber-50 text-amber-700 ring-amber-600/20' },
        error: { label: 'Crítico', badge: 'bg-red-50 text-red-700 ring-red-600/20' },
    })[severity] ?? { label: severity, badge: 'bg-zinc-100 text-zinc-600 ring-zinc-400/20' };

export const formatDateTime = (value: string | null | undefined): string => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

export const formatDate = (value: string | null | undefined): string => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

/** Minutos a una lectura en horas, que es como se reporta el MTTR. */
export const formatMinutes = (minutes: number | null | undefined): string => {
    if (minutes === null || minutes === undefined) return '—';
    const total = Math.round(minutes);
    if (total < 60) return `${total} min`;
    const hours = Math.floor(total / 60);
    const rest = total % 60;
    if (hours < 24) return `${hours} h ${rest} min`;
    return `${Math.floor(hours / 24)} d ${hours % 24} h`;
};

/** Valor para un <input type="datetime-local"> a partir de una fecha ISO. */
export const toLocalInput = (value: string | null | undefined): string => {
    const date = value ? new Date(value) : new Date();
    if (Number.isNaN(date.getTime())) return '';
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};
