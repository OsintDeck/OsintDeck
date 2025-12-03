(function($){
    const AJAX  = OSDAdmin.ajaxUrl;
    const NONCE = OSDAdmin.nonce;

    function toast(msg){
        const $t = $('<div class="osd-toast"></div>').text(msg);
        $('body').append($t);
        setTimeout(()=>{$t.addClass('show');}, 20);
        setTimeout(()=>{
            $t.removeClass('show');
            setTimeout(()=>{$t.remove();}, 300);
        }, 2500);
    }

    function escapeHtml(s){
        return String(s || '')
            .replace(/&/g,'&amp;')
            .replace(/</g,'&lt;')
            .replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;')
            .replace(/'/g,'&#39;');
    }

    // Tabs principales
    $(document).on('click', '.osd-tab', function(){
        const tab = $(this).data('tab');
        $('.osd-tab').removeClass('active');
        $(this).addClass('active');
        $('.osd-section').removeClass('active');
        $('#osd-tab-' + tab).addClass('active');
        if(tab === 'logs'){
            loadUserLogs();
            loadAdminLogs();
            loadMetricsSummary();
        }
    });

    // Subtabs
    $(document).on('click', '.osd-subtab', function(){
        const group = $(this).data('group');
        const sub   = $(this).data('sub');

        $('.osd-subtab[data-group="'+group+'"]').removeClass('active');
        $(this).addClass('active');
        $('.osd-subsection[data-group="'+group+'"]').removeClass('active');
        $('#osd-sub-' + sub).addClass('active');

        if(group === 'logs' && sub === 'logs-metrics'){
            loadMetricsSummary();
        }
    });

    // Normaliza lista
    function normalizeToolsList(src){
        let arr;
        if (Array.isArray(src)) {
            arr = src;
        } else if (src && typeof src === "object") {
            arr = Object.values(src);
        } else {
            arr = [];
        }
        return arr.filter(t => {
            if (!t || typeof t !== "object") return false;
            const name = String(t.name || "").trim();
            const cards = Array.isArray(t.cards) ? t.cards : [];
            return name !== "" && cards.length > 0;
        });
    }

    function validateTool(obj){
        if(!obj || typeof obj !== 'object') return 'Debe ser un objeto JSON.';
        if(!obj.name || String(obj.name).trim()==='') return 'El campo "name" es obligatorio.';
        if(!Array.isArray(obj.cards) || obj.cards.length<1) return 'Debe incluir al menos 1 item en "cards".';
        const first = obj.cards[0] || {};
        if(!first.title || !first.url) return 'La primera card debe incluir "title" y "url".';
        return '';
    }

    // Herramientas: carga lista
    function loadTools(){
        $.post(AJAX, {
            action: 'osd_tools_get',
            _ajax_nonce: NONCE
        }, function(res){
            const listRaw = res && res.data ? res.data : [];
            const list = normalizeToolsList(listRaw);
            renderToolsTable(list);
            $('#osd-backup-text').val(JSON.stringify(list, null, 2));
            updateJsonStatus();
            cacheTools = list;
        }, 'json');
    }

    let cacheTools = [];

    function renderToolsTable(list){
        const $tbody = $('#osd-tools-tbody').empty();
        if(!list.length){
            $tbody.append('<tr class="osd-row"><td colspan="9" style="padding:10px">No hay herramientas cargadas.</td></tr>');
            return;
        }
        list.forEach(t => {
            const tags = (t.tags || []).join(', ');
            const cat  = t.category || '-';
            const acc  = t.access   || '-';
            const badges = (t.meta && t.meta.badges || []).join(' ');
            const row = `
                <tr class="osd-row">
                    <td>${escapeHtml(t.name||'')}</td>
                    <td><span class="badge">${escapeHtml(cat)}</span></td>
                    <td><span class="badge">${escapeHtml(acc)}</span></td>
                    <td><code class="osd-mono">${escapeHtml(t.color||'')}</code></td>
                    <td>${escapeHtml(tags)}</td>
                    <td>${escapeHtml(t.desc||'')}</td>
                    <td>${escapeHtml(badges)}</td>
                    <td>${(t.cards && t.cards[0] && t.cards[0].url) ? '<a href="'+escapeHtml(t.cards[0].url)+'" target="_blank" rel="noopener noreferrer">Abrir</a>' : '-'}</td>
                    <td class="osd-actions-inline">
                        <button type="button" class="osd-btn osd-btn-secondary js-osd-edit" data-name="${encodeURIComponent(t.name||'')}">Editar</button>
                        <button type="button" class="osd-btn osd-btn-secondary js-osd-delete" data-name="${encodeURIComponent(t.name||'')}">Eliminar</button>
                    </td>
                </tr>
            `;
            $tbody.append(row);
        });
    }

    // Editar herramienta
    $(document).on('click', '.js-osd-edit', function(){
        const name = decodeURIComponent($(this).data('name')||'').toLowerCase();
        if(!name) return;

        const tool = cacheTools.find(t => (t.name||'').toLowerCase() === name);
        if(!tool){
            toast('Herramienta no encontrada');
            return;
        }
        $('.osd-subtab[data-sub="editor"]').trigger('click');
        $('#osd-editor-text').val(JSON.stringify(tool, null, 2));
        updateEditorLineNumbers();
    });

    // Eliminar herramienta
    $(document).on('click', '.js-osd-delete', function(){
        const name = decodeURIComponent($(this).data('name')||'');
        if(!name) return;
        if(!confirm(`¿Eliminar "${name}"? Esta acción no se puede deshacer.`)) return;

        $.post(AJAX, {
            action: 'osd_tool_delete',
            _ajax_nonce: NONCE,
            name: name
        }, function(res){
            if(!res || !res.ok){
                toast(res && res.msg ? res.msg : 'No se pudo eliminar');
                return;
            }
            toast('Herramienta eliminada');
            loadTools();
        }, 'json');
    });

    // Validar JSON de herramienta individual
    $('#osd-validate-tool').on('click', function(){
        const raw = $('#osd-editor-text').val();
        try{
            const obj = JSON.parse(raw);
            const msg = validateTool(obj);
            if(msg){
                alert('⚠ ' + msg);
            } else {
                alert('✅ JSON válido (estructura mínima OK).');
            }
        }catch(e){
            alert('⚠ JSON inválido: '+e.message);
        }
    });

    // Guardar / actualizar herramienta individual
    $('#osd-save-tool').on('click', function(){
        const raw = $('#osd-editor-text').val();
        let obj = null;
        try{
            obj = JSON.parse(raw);
        } catch(e){
            alert('⚠ JSON inválido: '+e.message);
            return;
        }

        const msg = validateTool(obj);
        if(msg){
            alert('⚠ ' + msg);
            return;
        }

        // Validar duplicado si ya existe otro con distinto contenido
        const exists = cacheTools.find(t => (t.name||'').toLowerCase() === (obj.name||'').toLowerCase());
        if(exists){
            // permitimos update, solo avisamos
            if(!confirm('La herramienta ya existe. ¿Deseas actualizarla?')){
                return;
            }
        }

        $.post(AJAX, {
            action: 'osd_tool_upsert',
            _ajax_nonce: NONCE,
            tool_json: JSON.stringify(obj)
        }, function(res){
            if(!res || !res.ok){
                toast(res && res.msg ? res.msg : 'No se pudo guardar');
                return;
            }
            toast('Herramienta guardada');
            loadTools();
        }, 'json');
    });

    // Nueva herramienta (plantilla)
    $('#osd-add-new').on('click', function(e){
        e.preventDefault();
        const tpl = {
            "name": "",
            "category": "",
            "access": "",
            "color": "#333333",
            "favicon": "",
            "tags": [],
            "info": {
                "tipo": "",
                "licencia": "",
                "acceso": ""
            },
            "cards": [
                {
                    "title": "Abrir",
                    "desc": "Descripción breve de la herramienta.",
                    "url": "https://ejemplo.tld/?q={input}"
                }
            ]
        };
        $('.osd-subtab[data-sub="editor"]').trigger('click');
        $('#osd-editor-text').val(JSON.stringify(tpl, null, 2));
        updateEditorLineNumbers();
        toast('Nueva herramienta base creada en el editor');
    });

    // Limpiar editor individual
    $('#osd-clear-tool').on('click', function(){
        $('#osd-editor-text').val('');
        updateEditorLineNumbers();
    });

    // Exportar JSON completo (descarga)
    $('#osd-export-json').on('click', function(){
        const url = AJAX +
            '?action=osd_export_json' +
            '&_ajax_nonce=' + encodeURIComponent(NONCE);
        window.location = url;
    });

    // Importar JSON desde archivo
    $('#osd-import-json').on('click', function(){
        $('#osd-json-file').click();
    });

    $('#osd-json-file').on('change', function(e){
        const file = e.target.files[0];
        if(!file) return;

        const reader = new FileReader();
        reader.onload = function(ev){
            const text = ev.target.result || '';
            try{
                const parsed = JSON.parse(text);
                const norm   = normalizeToolsList(parsed);
                $('#osd-backup-text').val(JSON.stringify(norm.length ? norm : parsed, null, 2));
                updateJsonStatus();
                toast('JSON cargado en el editor. No olvides "Guardar JSON completo".');
            }catch(err){
                alert('⚠ El archivo no contiene JSON válido: '+err.message);
            }
        };
        reader.readAsText(file);
        $(this).val('');
    });

    // Logs
    function loadUserLogs(){
        $('#osd-logs-user-body').html('<tr><td colspan="7">Cargando...</td></tr>');
        $.post(AJAX, {
            action: 'osd_logs_user',
            _ajax_nonce: NONCE
        }, function(res){
            const $tbody = $('#osd-logs-user-body').empty();
            if(!res || !res.ok){
                $tbody.append('<tr><td colspan="7">No se pudo cargar.</td></tr>');
                return;
            }
            const rows = res.data || [];
            if(!rows.length){
                $tbody.append('<tr><td colspan="7">No hay registros disponibles.</td></tr>');
                return;
            }
            rows.forEach(r => {
                let meta = {};
                try { meta = r.meta ? JSON.parse(r.meta) : {}; } catch(e){}
                const tr = `
                    <tr>
                        <td>${escapeHtml(r.created_at || "")}</td>
                        <td>${escapeHtml(r.ip || "")}</td>
                        <td>${escapeHtml(r.input_type || "")}</td>
                        <td>${escapeHtml(r.input_value || "")}</td>
                        <td>${escapeHtml(r.tool || "")}</td>
                        <td>${escapeHtml(meta.card_title || "")}</td>
                        <td>${escapeHtml(r.action || "")}</td>
                    </tr>
                `;
                $tbody.append(tr);
            });
        }, "json");
    }

    function loadAdminLogs(){
        $('#osd-logs-admin-body').html('<tr><td colspan="6">Cargando...</td></tr>');
        $.post(AJAX, {
            action: 'osd_logs_admin',
            _ajax_nonce: NONCE
        }, function(res){
            const $tbody = $('#osd-logs-admin-body').empty();
            if(!res || !res.ok){
                $tbody.append('<tr><td colspan="6">No se pudo cargar.</td></tr>');
                return;
            }
            const rows = res.data || [];
            if(!rows.length){
                $tbody.append('<tr><td colspan="6">No hay registros disponibles.</td></tr>');
                return;
            }
            rows.forEach(r => {
                let meta = {};
                try { meta = r.meta ? JSON.parse(r.meta) : {}; } catch(e){}
                const severity = meta.severity || '-';
                const details  = meta.details  || r.input_value || '';
                const tr = `
                    <tr>
                        <td>${escapeHtml(r.created_at || "")}</td>
                        <td>${escapeHtml(r.actor_id || "")}</td>
                        <td>${escapeHtml(r.action || "")}</td>
                        <td>${escapeHtml(r.tool || "")}</td>
                        <td>${escapeHtml(severity)}</td>
                        <td>${escapeHtml(details)}</td>
                    </tr>
                `;
                $tbody.append(tr);
            });
        }, "json");
    }

    function loadMetricsSummary(){
        $('#osd-logs-metrics-body').html('<tr><td colspan="5">Cargando...</td></tr>');
        $.post(AJAX, {
            action: 'osd_metrics_summary',
            _ajax_nonce: NONCE
        }, function(res){
            const $tbody = $('#osd-logs-metrics-body').empty();
            if(!res || !res.ok){
                $tbody.append('<tr><td colspan="5">No se pudo cargar.</td></tr>');
                return;
            }
            const rows = res.data || [];
            if(!rows.length){
                $tbody.append('<tr><td colspan="5">No hay métricas disponibles.</td></tr>');
                renderCharts([]);
                return;
            }
            rows.forEach(r => {
                const badges = (r.badges || []).join(' ');
                const created = r.created_at ? new Date(r.created_at * 1000).toLocaleDateString() : '';
                const tr = `
                    <tr>
                        <td>${escapeHtml(r.tool_id || "")}</td>
                        <td>${escapeHtml(String(r.clicks_7d||0))}</td>
                        <td>${escapeHtml(String(r.reports_7d||0))}</td>
                        <td>${escapeHtml(badges)}</td>
                        <td>${escapeHtml(created)}</td>
                    </tr>
                `;
                $tbody.append(tr);
            });
            renderCharts(rows);
        }, "json");
    }

    function renderBars(elId, rows, key, color){
        const el = document.getElementById(elId);
        if(!el) return;
        if(!Array.isArray(rows) || !rows.length){
            el.innerHTML = '<div class="osd-bar-empty">Sin datos</div>';
            return;
        }
        const max = Math.max(...rows.map(r => Number(r[key]||0)), 1);
        const topRows = rows.slice(0, 10);
        el.innerHTML = topRows.map(r => {
            const val = Number(r[key]||0);
            const w = Math.round((val / max) * 100);
            const label = String(r.tool_id || '').slice(0, 20);
            return `
                <div class="osd-bar">
                    <span class="osd-bar-label">${escapeHtml(label)}</span>
                    <span class="osd-bar-fill" style="width:${w}%;background:${color};"></span>
                    <span class="osd-bar-val">${val}</span>
                </div>
            `;
        }).join('');
    }

    // Charts with Chart.js
    let chartClicks = null;
    let chartReports = null;
    function renderCharts(rows){
        const labels = rows.slice(0, 12).map(r => r.tool_id || '');
        const clicks = rows.slice(0, 12).map(r => Number(r.clicks_7d||0));
        const reports = rows.slice(0, 12).map(r => Number(r.reports_7d||0));

        const ctxClicks = document.getElementById('osd-chart-clicks');
        const ctxReports = document.getElementById('osd-chart-reports');
        if(!ctxClicks || !ctxReports || typeof Chart === 'undefined') return;

        const baseOpts = {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { x: { ticks: { color: '#4b5563' } }, y: { ticks: { color: '#4b5563' }, beginAtZero: true } }
        };

        if(chartClicks){ chartClicks.destroy(); }
        chartClicks = new Chart(ctxClicks, {
            type: 'bar',
            data: { labels, datasets: [{ label: 'Clicks 7d', data: clicks, backgroundColor: '#0ea5e9' }] },
            options: baseOpts
        });

        if(chartReports){ chartReports.destroy(); }
        chartReports = new Chart(ctxReports, {
            type: 'bar',
            data: { labels, datasets: [{ label: 'Reportes 7d', data: reports, backgroundColor: '#f97316' }] },
            options: baseOpts
        });
    }

    // JSON status helper
    function updateJsonStatus(){
        const ta = $('#osd-backup-text').get(0);
        if(!ta) return;
        const pos  = ta.selectionStart || 0;
        const text = ta.value.slice(0, pos);
        const lines = text.split('\n');
        const line  = lines.length;
        const col   = lines[lines.length-1].length + 1;
        $('#osd-json-status').text('Ln '+line+', Col '+col);
    }

    // Export logs CSV
    $(document).on('click', '#osd-export-logs', function(){
        const url = AJAX +
            '?action=osd_export_logs_csv' +
            '&_ajax_nonce=' + encodeURIComponent(NONCE);
        window.location = url;
    });

    // Editor: line numbers
    function ensureEditorWrap(){
        const ta = document.getElementById('osd-editor-text');
        if(!ta) return;
        if(ta.parentElement.classList.contains('osd-editor-wrap')) return;
        const wrap = document.createElement('div');
        wrap.className = 'osd-editor-wrap';
        const lines = document.createElement('div');
        lines.id = 'osd-editor-lines';
        lines.className = 'osd-editor-lines';
        ta.parentNode.insertBefore(wrap, ta);
        wrap.appendChild(lines);
        wrap.appendChild(ta);
    }

    function updateEditorLineNumbers(){
        const ta = document.getElementById('osd-editor-text');
        const linesEl = document.getElementById('osd-editor-lines');
        if(!ta || !linesEl) return;
        const count = ta.value.split('\n').length;
        let html = '';
        for(let i=1;i<=count;i++){
            html += `<span>${i}</span>`;
        }
        linesEl.innerHTML = html;
    }

    ensureEditorWrap();
    $('#osd-editor-text').on('input keyup click scroll', function(){
        updateEditorLineNumbers();
        const linesEl = document.getElementById('osd-editor-lines');
        if(linesEl){
            linesEl.scrollTop = this.scrollTop;
        }
    });

    // Estado inicial
    $('.osd-tab[data-tab="config"]').addClass('active');
    $('#osd-tab-config').addClass('active');
    const firstConfig = $('.osd-subtab[data-group="config"]').first();
    if(firstConfig.length){
        firstConfig.addClass('active');
        $('#osd-sub-' + firstConfig.data('sub')).addClass('active');
    }
    $('.osd-subtab[data-group="tools"][data-sub="list"]').addClass('active');
    $('#osd-sub-list').addClass('active');
    $('.osd-subtab[data-group="logs"][data-sub="logs-user"]').addClass('active');
    $('#osd-sub-logs-user').addClass('active');

    // Preview de colores (claro/oscuro) en vivo
    const COLOR_FIELDS = ['bg','card','border','ink','ink_sub','accent','muted','btn_bg','btn_text'];
    function getPalette(mode){
        const out = {};
        COLOR_FIELDS.forEach(key => {
            const el = document.getElementById(`osd_color_${mode}_${key}`);
            if(el){
                out[key] = el.value || '';
            }
        });
        return out;
    }
    function applyPreview(mode){
        const target = document.getElementById(`osd-preview-${mode}`);
        if(!target) return;
        const palette = getPalette(mode);
        const map = {
            bg: '--osint-bg',
            card: '--osint-card',
            border: '--osint-border',
            ink: '--osint-ink',
            ink_sub: '--osint-ink-sub',
            accent: '--osint-accent',
            muted: '--osint-muted',
            btn_bg: '--osint-btn-bg',
            btn_text: '--osint-btn-text'
        };
        Object.entries(map).forEach(([key,varName])=>{
            if(palette[key]){
                target.style.setProperty(varName, palette[key]);
            }
        });
        // actualizar swatches
        document.querySelectorAll(`#osd-preview-${mode} .osd-swatch`).forEach((el)=>{
            const v = el.dataset.var;
            if(v && palette[v]){
                const chip = el.querySelector('.osd-swatch-chip');
                if(chip){ chip.style.background = palette[v]; }
            }
        });
    }
    function applyAllPreviews(){
        applyPreview('light');
        applyPreview('dark');
    }
    $(document).on('input change', 'input[id^="osd_color_light_"], input[id^="osd_color_dark_"]', function(){
        const mode = this.id.indexOf('osd_color_light_') === 0 ? 'light' : 'dark';
        applyPreview(mode);
    });
    $(document).on('click', '.osd-reset-colors', function(){
        const mode = $(this).data('mode');
        COLOR_FIELDS.forEach(key => {
            const el = document.getElementById(`osd_color_${mode}_${key}`);
            if(el && el.dataset.default){
                el.value = el.dataset.default;
            }
        });
        applyPreview(mode);
    });
    applyAllPreviews();

    // Inicializaciones
    loadTools();
})(jQuery);
