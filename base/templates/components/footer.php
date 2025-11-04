<footer class="cis-footer">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6">
                <p class="mb-0 text-muted">
                    &copy; {{ date('Y') }} CIS - Central Information System. All rights reserved.
                </p>
            </div>
            <div class="col-md-6 text-end">
                <p class="mb-0 text-muted">
                    Version {{ config('assets.version', '2.0.0') }}
                </p>
            </div>
        </div>
    </div>
</footer>

<style>
    .cis-footer {
        padding: 1rem 0;
        border-top: 1px solid #E5E7EB;
        background: white;
        margin-top: auto;
    }
</style>
