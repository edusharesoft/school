@push('head')
<style>
    body { background: #f1f5f9; }
    .sheet { background: #fff; color: #111827; max-width: {{ $width ?? '800px' }}; margin: 20px auto; padding: 28px 32px; box-shadow: 0 1px 4px rgba(0,0,0,.08); font-family: 'Roboto','Hind Siliguri',sans-serif; position: relative; }
    .sheet + .sheet { margin-top: 24px; }
    .lh { display: flex; align-items: center; justify-content: center; gap: 14px; text-align: center; border-bottom: 2px solid #1e3a8a; padding-bottom: 8px; margin-bottom: 12px; }
    .lh-logo { height: 60px; }
    .lh-inst { font-size: 20px; font-weight: 700; color: #1e3a8a; }
    .lh-branch { font-weight: 600; }
    .lh-meta { font-size: 12px; color: #475569; }
    .doc-title { text-align: center; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; margin: 6px 0 12px; }
    .sheet table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .sheet th, .sheet td { border: 1px solid #94a3b8; padding: 4px 6px; }
    .sheet th { background: #eef2ff; }
    .sheet .noborder td { border: 0; padding: 2px 4px; }
    .sig { border-top: 1px solid #111827; padding-top: 3px; width: 170px; text-align: center; font-size: 12px; }
    .stamp { position: absolute; top: 45%; left: 0; right: 0; text-align: center; font-size: 70px; font-weight: 800; color: rgba(220,38,38,.15); transform: rotate(-18deg); pointer-events: none; }
    @page { size: {{ $page ?? 'A4' }}; margin: 10mm; }
    @media print {
        body { background: #fff; }
        .no-print { display: none !important; }
        .sheet { box-shadow: none; margin: 0 auto; padding: 0; max-width: none; }
        .sheet + .sheet { margin-top: 0; }
        .page-break { page-break-after: always; break-after: page; }
    }
</style>
@endpush
