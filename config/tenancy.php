<?php

/*
|--------------------------------------------------------------------------
| Multi-tenancy configuration
|--------------------------------------------------------------------------
| Hierarchy: Platform → Institution (tenant) → Branch.
| Nothing here is hard-coded to a specific domain; everything comes from .env.
*/

return [

    // Branch hosts are "<branches.subdomain>.<base_domain>".
    'base_domain' => env('TENANCY_BASE_DOMAIN', 'localhost'),

    // Hosts (without port) that serve the public website and central administration.
    'central_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TENANCY_CENTRAL_DOMAINS', 'localhost,127.0.0.1'))
    ))),

    // URL prefix for the central (Super Admin) area on the central domain.
    'central_path' => trim((string) env('TENANCY_CENTRAL_PATH', 'super-admin'), '/'),

    // Sub-labels that can never be used as a branch subdomain.
    'reserved_subdomains' => ['www', 'api', 'admin', 'mail', 'smtp', 'ftp', 'cpanel', 'webmail', 'super-admin', 'static', 'cdn'],

    // Scheme / port used when generating links to another host (branch switching, "open branch").
    'url_scheme' => env('TENANCY_URL_SCHEME') ?: null,
    'url_port' => env('TENANCY_URL_PORT') ?: null,

    // Seconds a resolved host → branch mapping stays cached.
    'resolver_cache_ttl' => (int) env('TENANCY_RESOLVER_CACHE_TTL', 600),

    // Seconds a branch-switch handoff token is valid (single use).
    'handoff_ttl' => (int) env('TENANCY_HANDOFF_TTL', 60),

    // Root folder on the private disk for tenant files: {root}/{institution}/{branch}/{module}/...
    'storage_root' => 'tenants',
];
