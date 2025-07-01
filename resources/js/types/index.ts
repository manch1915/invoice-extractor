import type { Locale } from '@/i18n';

export type DocumentStatus = 'queued' | 'processing' | 'needs_review' | 'ready' | 'exported' | 'rejected' | 'failed';
export type FieldStatus = 'auto' | 'review' | 'confirmed' | 'corrected';
export type FieldKey =
    | 'vendor_name'
    | 'vendor_tax_id'
    | 'buyer_name'
    | 'buyer_tax_id'
    | 'document_number'
    | 'issue_date'
    | 'due_date'
    | 'currency'
    | 'subtotal'
    | 'tax_rate'
    | 'tax_amount'
    | 'total';

/** x, y, width, height relative to the page, 0..1 */
export type BBox = [number, number, number, number];

export interface Category {
    id: number;
    code: string;
    name_en: string;
    name_ru: string;
    color: string;
    account_code: string | null;
}

export interface DocumentRow {
    id: string;
    status: DocumentStatus;
    source: string;
    filename: string;
    doc_type: string | null;
    vendor_name: string | null;
    document_number: string | null;
    issue_date: string | null;
    currency: string | null;
    total: number | null;
    confidence: number | null;
    engine: string | null;
    auto_approved: boolean;
    category: Category | null;
    issues_count?: number;
    thumbnail: string | null;
    created_at: string;
    error: string | null;
}

export interface Field {
    key: FieldKey;
    value: string | null;
    original_value: string | null;
    raw: string | null;
    confidence: number;
    page: number | null;
    bbox: BBox | null;
    status: FieldStatus;
}

export interface LineItem {
    id: number;
    position: number;
    description: string;
    quantity: string | null;
    unit_price: number | null;
    amount: number | null;
    confidence: number;
    page: number | null;
    bbox: BBox | null;
}

export interface Issue {
    id: number;
    code: string;
    severity: 'blocking' | 'warning';
    field: FieldKey | null;
    context: Record<string, string | number | null>;
    resolved: boolean;
}

export interface PageWord {
    text: string;
    bbox: BBox;
    conf: number;
}

export interface DocumentPage {
    number: number;
    width: number;
    height: number;
    url: string;
    words: PageWord[];
}

export interface DocumentDetail extends DocumentRow {
    language: string | null;
    text_source: string | null;
    ocr_quality: number | null;
    processing_ms: number | null;
    warnings: string[];
    category_source: 'rule' | 'keyword' | 'llm' | 'user' | null;
    suggested_category: string | null;
    duplicate_of_id: string | null;
    reviewed_at: string | null;
    reviewer: string | null;
    original_url: string;
    mime_type: string;
    vendor: { id: number; name: string; tax_id: string | null; documents: number } | null;
    pages: DocumentPage[];
    fields: Field[];
    line_items: LineItem[];
    issues: Issue[];
}

export interface Paginated<T> {
    data: T[];
    links: { first: string | null; last: string | null; prev: string | null; next: string | null };
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        per_page: number;
    };
}

export interface SharedProps {
    [key: string]: unknown;
    auth: { user: { id: number; name: string; email: string; role: string | null; can_manage: boolean } | null };
    locale: Locale;
    demo: boolean;
    flash: { success: string | null; error: string | null };
    queue_count: number;
    errors: Record<string, string>;
}
