/**
 * Account Store CMS - Visual Inline Editor
 * Визуальный редактор для администраторов.
 * Активируется только при наличии data-editable атрибутов и авторизации.
 */
(function () {
    'use strict';

    // Проверяем, включён ли редактор (только для авторизованных администраторов)
    if (!window.INLINE_EDITOR_ENABLED) return;

    const BASE = window.APP_BASE_PATH || '';
    let activeEl = null;
    let toolbar = null;
    let originalContent = {};
    let pendingChanges = {};
    let saveTimer = null;

    // ========== Toolbar ==========
    function createToolbar() {
        if (toolbar) return toolbar;

        toolbar = document.createElement('div');
        toolbar.id = 'inline-editor-toolbar';
        toolbar.innerHTML = `
            <div class="iet-inner">
                <span class="iet-title"><i class="fa-solid fa-pen-to-square"></i> Визуальный редактор</span>
                <div class="iet-actions">
                    <button class="iet-btn iet-btn-bold" title="Жирный" onclick="window.IET.format('bold')"><i class="fa-solid fa-bold"></i></button>
                    <button class="iet-btn iet-btn-italic" title="Курсив" onclick="window.IET.format('italic')"><i class="fa-solid fa-italic"></i></button>
                    <button class="iet-btn iet-btn-link" title="Ссылка" onclick="window.IET.insertLink()"><i class="fa-solid fa-link"></i></button>
                    <div class="iet-sep"></div>
                    <button class="iet-btn iet-btn-save" title="Сохранить изменения" onclick="window.IET.saveAll()"><i class="fa-solid fa-floppy-disk"></i> Сохранить</button>
                    <button class="iet-btn iet-btn-cancel" title="Отменить изменения" onclick="window.IET.cancelAll()"><i class="fa-solid fa-rotate-left"></i> Отменить</button>
                    <button class="iet-btn iet-btn-exit" title="Выйти из редактора" onclick="window.IET.exit()"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <span class="iet-status" id="iet-status"></span>
            </div>
        `;
        document.body.appendChild(toolbar);

        // Стили
        const style = document.createElement('style');
        style.textContent = `
            #inline-editor-toolbar {
                position: fixed;
                top: 0; left: 0; right: 0;
                z-index: 99999;
                background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
                border-bottom: 2px solid #4F46E5;
                box-shadow: 0 4px 20px rgba(0,0,0,0.4);
                font-family: Inter, system-ui, sans-serif;
                transition: transform 0.3s ease;
            }
            .iet-inner {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 8px 16px;
                max-width: 1400px;
                margin: 0 auto;
                flex-wrap: wrap;
            }
            .iet-title {
                color: #a5b4fc;
                font-size: 0.85rem;
                font-weight: 600;
                white-space: nowrap;
            }
            .iet-actions {
                display: flex;
                align-items: center;
                gap: 6px;
                flex-wrap: wrap;
            }
            .iet-btn {
                background: rgba(255,255,255,0.1);
                border: 1px solid rgba(255,255,255,0.2);
                color: #e0e7ff;
                padding: 5px 10px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 0.8rem;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                gap: 5px;
            }
            .iet-btn:hover { background: rgba(255,255,255,0.2); }
            .iet-btn-save { background: rgba(16,185,129,0.2); border-color: #10B981; color: #6ee7b7; }
            .iet-btn-save:hover { background: rgba(16,185,129,0.35); }
            .iet-btn-cancel { background: rgba(245,158,11,0.2); border-color: #F59E0B; color: #fcd34d; }
            .iet-btn-cancel:hover { background: rgba(245,158,11,0.35); }
            .iet-btn-exit { background: rgba(239,68,68,0.2); border-color: #EF4444; color: #fca5a5; }
            .iet-btn-exit:hover { background: rgba(239,68,68,0.35); }
            .iet-sep { width: 1px; height: 24px; background: rgba(255,255,255,0.2); margin: 0 4px; }
            .iet-status {
                margin-left: auto;
                font-size: 0.78rem;
                color: #a5b4fc;
                white-space: nowrap;
            }
            .iet-status.saved { color: #6ee7b7; }
            .iet-status.error { color: #fca5a5; }
            /* Editable elements highlight */
            [data-editable]:not([data-editable=""]) {
                outline: 2px dashed rgba(79,70,229,0.4) !important;
                outline-offset: 2px;
                cursor: text;
                transition: outline-color 0.2s;
                border-radius: 4px;
            }
            [data-editable]:not([data-editable=""]):hover {
                outline-color: rgba(79,70,229,0.8) !important;
                background: rgba(79,70,229,0.05) !important;
            }
            [data-editable]:not([data-editable=""]).iet-active {
                outline: 2px solid #4F46E5 !important;
                background: rgba(79,70,229,0.08) !important;
            }
            [data-editable]:not([data-editable=""]).iet-modified {
                outline-color: rgba(245,158,11,0.7) !important;
            }
            body.iet-editor-active {
                padding-top: 52px !important;
            }
        `;
        document.head.appendChild(style);
        return toolbar;
    }

    function setStatus(msg, type = '') {
        const el = document.getElementById('iet-status');
        if (!el) return;
        el.textContent = msg;
        el.className = 'iet-status ' + type;
        if (type === 'saved') {
            setTimeout(() => { el.textContent = ''; el.className = 'iet-status'; }, 3000);
        }
    }

    // ========== Editor Init ==========
    function initEditableElements() {
        const elements = document.querySelectorAll('[data-editable]');
        elements.forEach(el => {
            const key = el.dataset.editable;
            if (!key) return;

            // Сохраняем оригинальный контент
            originalContent[key] = el.innerHTML;

            // Делаем редактируемым
            el.contentEditable = 'true';
            el.spellcheck = true;

            el.addEventListener('focus', () => {
                activeEl = el;
                el.classList.add('iet-active');
            });

            el.addEventListener('blur', () => {
                el.classList.remove('iet-active');
                activeEl = null;
            });

            el.addEventListener('input', () => {
                pendingChanges[key] = el.innerHTML;
                el.classList.add('iet-modified');
                setStatus('Есть несохранённые изменения...', '');
                // Автосохранение через 5 секунд
                clearTimeout(saveTimer);
                saveTimer = setTimeout(() => saveAll(true), 5000);
            });

            el.addEventListener('keydown', (e) => {
                // Ctrl+S - сохранить
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    saveAll();
                }
                // Escape - снять фокус
                if (e.key === 'Escape') {
                    el.blur();
                }
            });
        });

        if (elements.length > 0) {
            setStatus(`Редактируемых элементов: ${elements.length}. Нажмите на текст для редактирования.`);
        } else {
            setStatus('На этой странице нет редактируемых элементов.');
        }
    }

    // ========== Format commands ==========
    function format(cmd) {
        document.execCommand(cmd, false, null);
        if (activeEl) activeEl.focus();
    }

    function insertLink() {
        const url = prompt('Введите URL ссылки:', 'https://');
        if (url) {
            document.execCommand('createLink', false, url);
            if (activeEl) activeEl.focus();
        }
    }

    // ========== Save ==========
    async function saveAll(isAuto = false) {
        if (Object.keys(pendingChanges).length === 0) {
            if (!isAuto) setStatus('Нет изменений для сохранения.', '');
            return;
        }

        setStatus('Сохранение...', '');

        try {
            const res = await fetch(BASE + '/api/?path=admin/inline-save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    page: window.location.pathname,
                    changes: pendingChanges
                })
            });

            const result = await res.json();

            if (result.success) {
                // Обновляем оригинальный контент
                Object.assign(originalContent, pendingChanges);
                pendingChanges = {};

                // Снимаем метку изменений
                document.querySelectorAll('[data-editable].iet-modified').forEach(el => {
                    el.classList.remove('iet-modified');
                });

                setStatus(isAuto ? 'Автосохранено ✓' : 'Сохранено ✓', 'saved');
            } else {
                setStatus('Ошибка сохранения: ' + (result.error || 'Неизвестная ошибка'), 'error');
            }
        } catch (e) {
            setStatus('Ошибка соединения с сервером', 'error');
        }
    }

    function cancelAll() {
        if (Object.keys(pendingChanges).length === 0) {
            setStatus('Нет изменений для отмены.', '');
            return;
        }

        if (!confirm('Отменить все несохранённые изменения?')) return;

        // Восстанавливаем оригинальный контент
        document.querySelectorAll('[data-editable]').forEach(el => {
            const key = el.dataset.editable;
            if (key && originalContent[key] !== undefined) {
                el.innerHTML = originalContent[key];
                el.classList.remove('iet-modified');
            }
        });

        pendingChanges = {};
        setStatus('Изменения отменены.', '');
    }

    function exit() {
        if (Object.keys(pendingChanges).length > 0) {
            if (!confirm('Есть несохранённые изменения. Выйти без сохранения?')) return;
        }

        // Деактивируем редактор
        document.querySelectorAll('[data-editable]').forEach(el => {
            el.contentEditable = 'false';
            el.classList.remove('iet-active', 'iet-modified');
        });

        if (toolbar) toolbar.style.display = 'none';
        document.body.classList.remove('iet-editor-active');
        setStatus('Редактор закрыт.');
    }

    // ========== Public API ==========
    window.IET = { format, insertLink, saveAll, cancelAll, exit };

    // ========== Init ==========
    function init() {
        createToolbar();
        document.body.classList.add('iet-editor-active');
        initEditableElements();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
