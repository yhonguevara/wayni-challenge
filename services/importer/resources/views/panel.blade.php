<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Panel de Pruebas API BCRA — Wayni</title>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #f5f5f5; min-height: 100vh; padding: 2rem 1rem; }
        .page-container { max-width: 900px; margin: 0 auto; }

        .tabs { display: flex; gap: 0.5rem; margin-bottom: 2rem; border-bottom: 2px solid #e5e7eb; }
        .tab { padding: 0.75rem 1.5rem; border: none; background: none; cursor: pointer; font-size: 0.875rem; font-weight: 600; color: #6b7280; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.2s; text-decoration: none; display: inline-block; }
        .tab:hover { color: #6366f1; }
        .tab.active { color: #6366f1; border-bottom-color: #6366f1; }

        h1 { font-size: 1.5rem; margin-bottom: 0.5rem; color: #1a1a1a; }
        p.subtitle { color: #666; margin-bottom: 2rem; font-size: 0.875rem; }

        .section { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 1.5rem; }
        .section h2 { font-size: 1.1rem; color: #1a1a1a; margin-bottom: 0.25rem; }
        .section .method-badge { display: inline-block; background: #dbeafe; color: #1e40af; font-size: 0.7rem; font-weight: 700; padding: 0.15rem 0.5rem; border-radius: 4px; margin-right: 0.5rem; vertical-align: middle; }
        .section .endpoint-path { font-size: 0.8rem; color: #6b7280; font-family: monospace; margin-bottom: 1rem; }

        .form-row { display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: flex-end; margin-bottom: 1rem; }
        .form-group { display: flex; flex-direction: column; flex: 1; min-width: 140px; }
        .form-group label { font-size: 0.75rem; font-weight: 600; color: #374151; margin-bottom: 0.35rem; }
        .form-group input, .form-group select { padding: 0.6rem 0.75rem; border: 2px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; transition: border-color 0.2s; font-family: inherit; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #6366f1; }

        .btn-row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn { padding: 0.6rem 1.25rem; border: none; border-radius: 8px; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-primary { background: #6366f1; color: white; }
        .btn-primary:hover:not(:disabled) { background: #4f46e5; }
        .btn-primary:disabled { background: #d1d5db; cursor: not-allowed; }
        .btn-secondary { background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; }
        .btn-secondary:hover { background: #e5e7eb; }

        .response-panel { margin-top: 1rem; border-radius: 8px; overflow: hidden; }
        .response-header { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 1rem; background: #2d2d2d; font-size: 0.75rem; color: #aaa; }
        .response-header .status-ok { color: #4ade80; font-weight: 700; }
        .response-header .status-err { color: #f87171; font-weight: 700; }
        .response-body { background: #1e1e1e; color: #d4d4d4; padding: 1rem; font-family: 'SF Mono', 'Fira Code', 'Cascadia Code', monospace; font-size: 0.8rem; line-height: 1.5; white-space: pre-wrap; word-break: break-word; max-height: 400px; overflow-y: auto; }

        .spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.6s linear infinite; vertical-align: middle; margin-right: 0.35rem; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .error-banner { background: #fee2e2; color: #991b1b; padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.8rem; margin-top: 0.75rem; }

        .collapsible { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 1.5rem; overflow: hidden; }
        .collapsible-toggle { width: 100%; padding: 1rem 1.5rem; border: none; background: none; cursor: pointer; font-size: 0.875rem; font-weight: 600; color: #374151; text-align: left; display: flex; justify-content: space-between; align-items: center; }
        .collapsible-toggle:hover { background: #f9fafb; }
        .collapsible-body { padding: 0 1.5rem 1rem; }
        .collapsible-body table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
        .collapsible-body th { text-align: left; padding: 0.4rem 0.75rem; border-bottom: 2px solid #e5e7eb; color: #6b7280; font-weight: 600; }
        .collapsible-body td { padding: 0.4rem 0.75rem; border-bottom: 1px solid #f3f4f6; color: #374151; }
        .collapsible-body td code { background: #f3f4f6; padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.8rem; }

        .arrow { transition: transform 0.2s; display: inline-block; }
        .arrow-open { transform: rotate(180deg); }

        .copied-toast { position: fixed; bottom: 2rem; right: 2rem; background: #1a1a1a; color: white; padding: 0.6rem 1.25rem; border-radius: 8px; font-size: 0.8rem; z-index: 100; }

        @media (max-width: 600px) {
            body { padding: 1rem 0.5rem; }
            .section { padding: 1rem; }
            .form-row { flex-direction: column; }
            .form-group { min-width: 100%; }
        }
    </style>
</head>
<body>
    <div class="page-container" x-data="apiPanel()">
        <div class="tabs">
            <a href="/upload" class="tab">Carga de Archivos</a>
            <span class="tab active">Panel API</span>
        </div>

        <h1>Panel de Pruebas API BCRA</h1>
        <p class="subtitle">Probá los endpoints de la Query API. Asegurate de que el servicio query esté corriendo en el puerto 8000.</p>

        {{-- Section 1: Get Debtor by CUIT --}}
        <div class="section">
            <h2><span class="method-badge">GET</span> Obtener Deudor por CUIT</h2>
            <div class="endpoint-path">/debtors/{cuit}</div>
            <div class="form-row">
                <div class="form-group">
                    <label>CUIT</label>
                    <input type="text" x-model="cuitForm.cuit" placeholder="20123456789" @keydown.enter="fetchDebtorByCuit()">
                </div>
            </div>
            <div class="btn-row">
                <button class="btn btn-primary" :disabled="!cuitForm.cuit || cuitForm.loading" @click="fetchDebtorByCuit()">
                    <template x-if="cuitForm.loading"><span class="spinner"></span></template>
                    <span x-text="cuitForm.loading ? 'Enviando...' : 'Enviar'"></span>
                </button>
                <button class="btn btn-secondary" :disabled="!cuitForm.cuit" @click="copyCurl('cuit')">Copiar curl</button>
            </div>
            <template x-if="cuitForm.response !== null || cuitForm.error">
                <div class="response-panel">
                    <div class="response-header">
                        <span>
                            <template x-if="cuitForm.response !== null">
                                <span :class="cuitForm.response.ok ? 'status-ok' : 'status-err'" x-text="'HTTP ' + cuitForm.response.status"></span>
                            </template>
                            <template x-if="cuitForm.error">
                                <span class="status-err">Error de Conexión</span>
                            </template>
                        </span>
                        <span x-show="cuitForm.responseTime !== null" x-text="cuitForm.responseTime + 'ms'"></span>
                    </div>
                    <div class="response-body" x-text="cuitForm.error || cuitForm.responseText"></div>
                </div>
            </template>
        </div>

        {{-- Section 2: Get Entity by Code --}}
        <div class="section">
            <h2><span class="method-badge">GET</span> Obtener Entidad por Código</h2>
            <div class="endpoint-path">/entities/{code}</div>
            <div class="form-row">
                <div class="form-group">
                    <label>Código de Entidad</label>
                    <input type="text" x-model="entityForm.code" placeholder="00011" @keydown.enter="fetchEntity()">
                </div>
            </div>
            <div class="btn-row">
                <button class="btn btn-primary" :disabled="!entityForm.code || entityForm.loading" @click="fetchEntity()">
                    <template x-if="entityForm.loading"><span class="spinner"></span></template>
                    <span x-text="entityForm.loading ? 'Enviando...' : 'Enviar'"></span>
                </button>
                <button class="btn btn-secondary" :disabled="!entityForm.code" @click="copyCurl('entity')">Copiar curl</button>
            </div>
            <template x-if="entityForm.response !== null || entityForm.error">
                <div class="response-panel">
                    <div class="response-header">
                        <span>
                            <template x-if="entityForm.response !== null">
                                <span :class="entityForm.response.ok ? 'status-ok' : 'status-err'" x-text="'HTTP ' + entityForm.response.status"></span>
                            </template>
                            <template x-if="entityForm.error">
                                <span class="status-err">Error de Conexión</span>
                            </template>
                        </span>
                        <span x-show="entityForm.responseTime !== null" x-text="entityForm.responseTime + 'ms'"></span>
                    </div>
                    <div class="response-body" x-text="entityForm.error || entityForm.responseText"></div>
                </div>
            </template>
        </div>

        {{-- Section 3: Top N Debtors --}}
        <div class="section">
            <h2><span class="method-badge">GET</span> Top N Deudores</h2>
            <div class="endpoint-path">/debtors/top/{n}</div>
            <div class="form-row">
                <div class="form-group" style="max-width:160px;">
                    <label>N (1–100)</label>
                    <input type="number" x-model.number="topForm.n" min="1" max="100" @keydown.enter="fetchTop()">
                </div>
            </div>
            <div class="btn-row">
                <button class="btn btn-primary" :disabled="!topForm.n || topForm.loading" @click="fetchTop()">
                    <template x-if="topForm.loading"><span class="spinner"></span></template>
                    <span x-text="topForm.loading ? 'Enviando...' : 'Enviar'"></span>
                </button>
                <button class="btn btn-secondary" :disabled="!topForm.n" @click="copyCurl('top')">Copiar curl</button>
            </div>
            <template x-if="topForm.response !== null || topForm.error">
                <div class="response-panel">
                    <div class="response-header">
                        <span>
                            <template x-if="topForm.response !== null">
                                <span :class="topForm.response.ok ? 'status-ok' : 'status-err'" x-text="'HTTP ' + topForm.response.status"></span>
                            </template>
                            <template x-if="topForm.error">
                                <span class="status-err">Error de Conexión</span>
                            </template>
                        </span>
                        <span x-show="topForm.responseTime !== null" x-text="topForm.responseTime + 'ms'"></span>
                    </div>
                    <div class="response-body" x-text="topForm.error || topForm.responseText"></div>
                </div>
            </template>
        </div>

        {{-- Section 4: List Debtors --}}
        <div class="section">
            <h2><span class="method-badge">GET</span> Listar Deudores (con filtros)</h2>
            <div class="endpoint-path">/debtors?situation={code}&per_page={n}&page={p}</div>
            <div class="form-row">
                <div class="form-group">
                    <label>Situación</label>
                    <select x-model="listForm.situation">
                        <option value="">Todas</option>
                        <option value="01">01 — Normal</option>
                        <option value="03">03 — Con observación</option>
                        <option value="04">04 — Incumplimiento</option>
                        <option value="05">05 — Deficiente</option>
                        <option value="11">11 — Dudoso</option>
                        <option value="21">21 — Irrecuperable</option>
                        <option value="23">23 — Irrecuperable (judicial)</option>
                    </select>
                </div>
                <div class="form-group" style="max-width:140px;">
                    <label>Por página (1–200)</label>
                    <input type="number" x-model.number="listForm.perPage" min="1" max="200">
                </div>
                <div class="form-group" style="max-width:120px;">
                    <label>Página</label>
                    <input type="number" x-model.number="listForm.page" min="1">
                </div>
            </div>
            <div class="btn-row">
                <button class="btn btn-primary" :disabled="listForm.loading" @click="fetchList()">
                    <template x-if="listForm.loading"><span class="spinner"></span></template>
                    <span x-text="listForm.loading ? 'Enviando...' : 'Enviar'"></span>
                </button>
                <button class="btn btn-secondary" @click="copyCurl('list')">Copiar curl</button>
            </div>
            <template x-if="listForm.response !== null || listForm.error">
                <div class="response-panel">
                    <div class="response-header">
                        <span>
                            <template x-if="listForm.response !== null">
                                <span :class="listForm.response.ok ? 'status-ok' : 'status-err'" x-text="'HTTP ' + listForm.response.status"></span>
                            </template>
                            <template x-if="listForm.error">
                                <span class="status-err">Error de Conexión</span>
                            </template>
                        </span>
                        <span x-show="listForm.responseTime !== null" x-text="listForm.responseTime + 'ms'"></span>
                    </div>
                    <div class="response-body" x-text="listForm.error || listForm.responseText"></div>
                </div>
            </template>
        </div>

        {{-- Situation codes reference --}}
        <div class="collapsible">
            <button class="collapsible-toggle" @click="refOpen = !refOpen">
                <span>Referencia de códigos de situación</span>
                <span class="arrow" :class="{ 'arrow-open': refOpen }">&#9660;</span>
            </button>
            <div x-show="refOpen" class="collapsible-body">
                <table>
                    <thead><tr><th>Código</th><th>Descripción</th></tr></thead>
                    <tbody>
                        <tr><td><code>01</code></td><td>Normal</td></tr>
                        <tr><td><code>03</code></td><td>Con observación</td></tr>
                        <tr><td><code>04</code></td><td>Incumplimiento</td></tr>
                        <tr><td><code>05</code></td><td>Deficiente</td></tr>
                        <tr><td><code>11</code></td><td>Dudoso</td></tr>
                        <tr><td><code>21</code></td><td>Irrecuperable</td></tr>
                        <tr><td><code>23</code></td><td>Irrecuperable (judicial)</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <template x-if="showToast">
            <div class="copied-toast" x-transition>Copiado al portapapeles</div>
        </template>
    </div>

    <script>
        function apiPanel() {
            const BASE = 'http://localhost:8000/api';

            function emptySection() {
                return { loading: false, response: null, responseText: '', responseTime: null, error: '' };
            }

            return {
                BASE_URL: BASE,
                refOpen: false,
                showToast: false,

                cuitForm:  { cuit: '', ...emptySection() },
                entityForm: { code: '', ...emptySection() },
                topForm:   { n: 10, ...emptySection() },
                listForm:  { situation: '', perPage: 50, page: 1, ...emptySection() },

                async doFetch(section, url) {
                    section.loading = true;
                    section.response = null;
                    section.responseText = '';
                    section.responseTime = null;
                    section.error = '';
                    const start = performance.now();
                    try {
                        const res = await fetch(url);
                        const elapsed = Math.round(performance.now() - start);
                        section.response = { ok: res.ok, status: res.status };
                        section.responseTime = elapsed;
                        const text = await res.text();
                        try {
                            section.responseText = JSON.stringify(JSON.parse(text), null, 2);
                        } catch {
                            section.responseText = text;
                        }
                    } catch (e) {
                        section.responseTime = Math.round(performance.now() - start);
                        section.error = 'No se pudo conectar a la Query API en ' + BASE + '\n' + e.message;
                    } finally {
                        section.loading = false;
                    }
                },

                buildUrl(type) {
                    if (type === 'cuit')   return `${BASE}/debtors/${this.cuitForm.cuit}`;
                    if (type === 'entity') return `${BASE}/entities/${this.entityForm.code}`;
                    if (type === 'top')    return `${BASE}/debtors/top/${this.topForm.n}`;
                    if (type === 'list') {
                        const params = new URLSearchParams();
                        if (this.listForm.situation) params.set('situation', this.listForm.situation);
                        params.set('per_page', this.listForm.perPage);
                        params.set('page', this.listForm.page);
                        return `${BASE}/debtors?${params.toString()}`;
                    }
                },

                fetchDebtorByCuit() { this.doFetch(this.cuitForm, this.buildUrl('cuit')); },
                fetchEntity()       { this.doFetch(this.entityForm, this.buildUrl('entity')); },
                fetchTop()          { this.doFetch(this.topForm, this.buildUrl('top')); },
                fetchList()         { this.doFetch(this.listForm, this.buildUrl('list')); },

                copyCurl(type) {
                    const url = this.buildUrl(type);
                    navigator.clipboard.writeText(`curl -s '${url}' | jq .`).then(() => {
                        this.showToast = true;
                        setTimeout(() => this.showToast = false, 1800);
                    });
                },
            };
        }
    </script>
</body>
</html>
