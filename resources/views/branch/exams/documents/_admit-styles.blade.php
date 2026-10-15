@push('head')
<style>
    .admit { border: 2px solid #1e3a8a; padding: 14px 18px; margin-bottom: 14px; position: relative; page-break-inside: avoid; break-inside: avoid; }
    .admit .photo { width: 86px; height: 100px; object-fit: cover; border: 1px solid #94a3b8; }
    .admit .ph { width: 86px; height: 100px; border: 1px dashed #94a3b8; display: grid; place-items: center; font-size: 11px; color: #64748b; }
    .admit .lh { border-bottom-width: 1px; }
    .admit .lh-logo { height: 42px; }
    .admit .lh-inst { font-size: 16px; }
    @media print { .admit:nth-of-type(2n) { page-break-after: always; break-after: page; } }
</style>
@endpush
