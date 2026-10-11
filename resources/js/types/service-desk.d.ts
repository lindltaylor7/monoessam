/**
 * Tipos del modulo Service Desk. Se mantienen aparte de `types/index.d.ts` porque solo los
 * consumen las pantallas de `pages/service-desk/*`.
 */

export interface SdCategory {
    id: number;
    code?: string;
    name: string;
    description?: string | null;
    parent_id?: number | null;
    is_active?: boolean;
    tickets_count?: number;
}

export interface SdSla {
    id: number;
    name: string;
    priority: number;
    response_minutes: number;
    resolution_minutes: number;
    description: string | null;
    is_active: boolean;
}

export interface SdNamed {
    id: number;
    name: string;
}

export interface SdAudit {
    id: number;
    ticket_id: number;
    auditor_id: number | null;
    auditor?: SdNamed | null;
    category_correct: boolean;
    impact_correct: boolean;
    urgency_correct: boolean;
    priority_correct: boolean;
    is_correct: boolean;
    suggested_category_id: number | null;
    suggested_category?: SdNamed | null;
    suggested_impact: number | null;
    suggested_urgency: number | null;
    findings: string | null;
    audited_at: string;
}

export interface SdSurvey {
    id: number;
    ticket_id: number | null;
    ticket?: { id: number; code: string; title: string } | null;
    respondent_name: string | null;
    respondent_area: string | null;
    score: number;
    q_attention: number | null;
    q_response_time: number | null;
    q_solution: number | null;
    q_communication: number | null;
    comments: string | null;
    answered_at: string;
}

export interface SdEvent {
    id: number;
    type: string;
    from_status: string | null;
    to_status: string | null;
    note: string | null;
    created_at: string;
    user?: SdNamed | null;
}

export interface SdTicket {
    id: number;
    code: string;
    title: string;
    description: string | null;
    requester_user_id: number | null;
    requester_name: string | null;
    requester_email: string | null;
    requester_area: string | null;
    unit_id: number | null;
    channel: string;
    category_id: number | null;
    category?: SdCategory | null;
    impact: number;
    urgency: number;
    priority: number;
    priority_label: string;
    sla_id: number | null;
    sla?: SdSla | null;
    status: string;
    status_label: string;
    assigned_to: number | null;
    assignee?: SdNamed | null;
    requester?: SdNamed | null;
    unit?: SdNamed | null;
    reported_at: string;
    response_due_at: string | null;
    resolution_due_at: string | null;
    first_response_at: string | null;
    resolved_at: string | null;
    closed_at: string | null;
    resolution_notes: string | null;
    kedb_article_id: number | null;
    kedb_article?: { id: number; code: string; title: string } | null;
    resolved_with_kedb: boolean;
    reopened_count: number;
    response_minutes: number | null;
    resolution_minutes: number | null;
    sla_response_met: boolean | null;
    sla_resolution_met: boolean | null;
    sla_met: boolean | null;
    is_overdue: boolean;
    audit?: SdAudit | null;
    survey?: SdSurvey | null;
    events?: SdEvent[];
}

export interface SdKedbArticle {
    id: number;
    code: string;
    title: string;
    symptoms: string | null;
    root_cause: string | null;
    workaround: string | null;
    solution: string | null;
    category_id: number | null;
    category?: SdNamed | null;
    status: string;
    times_used: number;
    tickets_count?: number;
    author?: SdNamed | null;
    created_at: string;
}

export interface SdMaturityQuestion {
    id: number;
    code: string;
    dimension: string;
    statement: string;
    order: number;
    is_active: boolean;
    answers_count?: number;
}

export interface SdAssessment {
    id: number;
    respondent_name: string;
    respondent_role: string | null;
    period: string;
    answered_at: string;
    comments: string | null;
    average: number | null;
}

/** Ficha completa de un indicador tal como la entrega IndicatorCalculator. */
export interface SdIndicator {
    key: string;
    variable: 'V1' | 'V2';
    dimension: string;
    name: string;
    unit: string;
    source: string;
    formula: string;
    value: number | null;
    display: string;
    target: number;
    target_display: string;
    comparator: 'gte' | 'lte';
    format: string;
    scale?: number;
    status: 'cumple' | 'riesgo' | 'incumple' | 'sin_datos';
    gap: number | null;
    sample: number;
    sample_label: string;
    min_sample: number;
    reliable: boolean;
    sample_notice: string | null;
    numerator?: number;
    denominator?: number;
    extra?: Record<string, number | null>;
}

export interface SdValidation {
    key: string;
    label: string;
    count: number;
    affects: string;
    hint: string;
    severity: 'ok' | 'info' | 'warning' | 'error';
    samples: { id: number; code: string; title: string }[];
}

export interface SdTrendPoint {
    month: string;
    tickets: number;
    mttr: number | null;
    mttr_response: number | null;
    sla: number | null;
}
