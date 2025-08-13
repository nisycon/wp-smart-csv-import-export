/**
 * WP Smart CSV Import/Export - Modern JavaScript
 * BEM CSS Architecture & Vanilla JS
 */

class CSVManager {
    constructor() {
        this.activeTab = 'export';
        this.isImporting = false;
        this.init();
    }

    init() {
        this.bindEvents();
        this.initializeUI();
        this.setupPageUnloadWarning();
    }

    bindEvents() {
        // タブ切り替え
        document.querySelectorAll('.csv-manager__tab').forEach(tab => {
            tab.addEventListener('click', (e) => this.switchTab(e));
        });

        // 投稿タイプ変更時のフィールド取得
        const exportPostType = document.getElementById('export_post_type');
        if (exportPostType) {
            exportPostType.addEventListener('change', (e) => {
                if (e.target.value) {
                    this.loadExportFields(e.target.value);
                } else {
                    this.resetFieldSelector();
                }
            });
        }

        // エクスポートフォーム処理
        const exportForm = document.getElementById('export-form');
        if (exportForm) {
            exportForm.addEventListener('submit', (e) => this.handleExport(e));
        }

        // インポートフォーム処理
        const importForm = document.getElementById('import-form');
        if (importForm) {
            importForm.addEventListener('submit', (e) => this.handleImport(e));
        }

        // ファイル選択時の表示更新
        const csvFile = document.getElementById('csv_file');
        if (csvFile) {
            csvFile.addEventListener('change', (e) => this.updateFileInfo(e));
        }
    }

    initializeUI() {
        // 初期状態の設定
        this.resetFieldSelector();
    }

    /**
     * ページ離脱警告の設定
     */
    setupPageUnloadWarning() {
        window.addEventListener('beforeunload', (e) => {
            if (this.isImporting) {
                const message = 'CSVインポート処理中です。ページを離れると処理が中断されます。本当に離れますか？';
                e.preventDefault();
                e.returnValue = message;
                return message;
            }
        });

        // ページ内リンクのクリックも監視
        document.addEventListener('click', (e) => {
            if (this.isImporting && e.target.tagName === 'A' && e.target.href) {
                const confirmed = confirm('CSVインポート処理中です。ページを離れると処理が中断されます。本当に移動しますか？');
                if (!confirmed) {
                    e.preventDefault();
                }
            }
        });
    }

    /**
     * タブ切り替え
     */
    switchTab(e) {
        e.preventDefault();
        const targetTab = e.target.dataset.tab;
        
        if (targetTab === this.activeTab) return;

        // 現在のアクティブタブを非アクティブに
        document.querySelector('.csv-manager__tab--active')?.classList.remove('csv-manager__tab--active');
        document.querySelector('.csv-manager__panel--active')?.classList.remove('csv-manager__panel--active');

        // 新しいタブをアクティブに
        e.target.classList.add('csv-manager__tab--active');
        document.querySelector(`[data-panel="${targetTab}"]`)?.classList.add('csv-manager__panel--active');

        this.activeTab = targetTab;
    }

    /**
     * フィールドセレクターをリセット
     */
    resetFieldSelector() {
        const container = document.getElementById('export-fields-container');
        if (container) {
            container.innerHTML = '<p class="field-selector__placeholder">投稿タイプを選択すると、利用可能なフィールドが表示されます。</p>';
        }
    }

    /**
     * エクスポートフィールドを読み込み
     */
    async loadExportFields(postType) {
        const container = document.getElementById('export-fields-container');
        if (!container) return;

        container.innerHTML = '<p class="field-selector__loading">読み込み中...</p>';

        try {
            const formData = new FormData();
            formData.append('action', 'smart_csv_get_fields');
            formData.append('nonce', wpSmartCsv.nonce);
            formData.append('post_type', postType);

            const response = await fetch(wpSmartCsv.ajax_url, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.renderFieldGroups(data.data);
            } else {
                container.innerHTML = '<p class="field-selector__error">フィールドの取得に失敗しました。</p>';
            }
        } catch (error) {
            container.innerHTML = '<p class="field-selector__error">フィールドの取得に失敗しました。</p>';
        }
    }

    /**
     * フィールドグループを描画
     */
    renderFieldGroups(fieldGroups) {
        const container = document.getElementById('export-fields-container');
        if (!container) return;

        let html = '';

        Object.entries(fieldGroups).forEach(([groupKey, group]) => {
            html += `
                <div class="field-group">
                    <div class="field-group__header">
                        <h4 class="field-group__title">${group.title}</h4>
                        <div class="field-group__actions">
                            <button type="button" class="field-group__action" data-action="select-all" data-group="${groupKey}">
                                全選択
                            </button>
                            <button type="button" class="field-group__action" data-action="select-none" data-group="${groupKey}">
                                全解除
                            </button>
                        </div>
                    </div>
                    <div class="field-group__content">
            `;

            Object.entries(group.fields).forEach(([fieldKey, fieldLabel]) => {
                html += `
                    <label class="field-item">
                        <input type="checkbox" name="export_fields[]" value="${fieldKey}" checked class="field-item__checkbox">
                        <span class="field-item__label">${fieldLabel}</span>
                    </label>
                `;
            });

            html += `
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;

        // イベントリスナーを追加
        this.bindFieldGroupEvents();
    }

    /**
     * フィールドグループのイベントを追加
     */
    bindFieldGroupEvents() {
        document.querySelectorAll('.field-group__action').forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const action = e.target.dataset.action;
                const group = e.target.dataset.group;
                const fieldGroup = e.target.closest('.field-group');
                const checkboxes = fieldGroup.querySelectorAll('input[type="checkbox"]');

                if (action === 'select-all') {
                    checkboxes.forEach(checkbox => checkbox.checked = true);
                } else if (action === 'select-none') {
                    checkboxes.forEach(checkbox => checkbox.checked = false);
                }
            });
        });
    }

    /**
     * エクスポート処理
     */
    async handleExport(e) {
        e.preventDefault();

        const form = e.target;
        const button = form.querySelector('#export-btn');
        const result = document.getElementById('export-result');
        const postType = document.getElementById('export_post_type').value;

        // バリデーション
        if (!postType) {
            this.showError(result, wpSmartCsv.strings.select_post_type);
            return;
        }

        // 選択されたフィールドを取得
        const selectedFields = Array.from(form.querySelectorAll('#export-fields-container input[type="checkbox"]:checked'))
            .map(checkbox => checkbox.value);

        // ローディング状態
        this.setButtonLoading(button, true);
        this.hideElement(result);

        try {
            const formData = new FormData();
            formData.append('action', 'smart_csv_export');
            formData.append('nonce', wpSmartCsv.nonce);
            formData.append('post_type', postType);

            // 投稿ステータス
            form.querySelectorAll('input[name="post_status[]"]:checked').forEach(checkbox => {
                formData.append('post_status[]', checkbox.value);
            });

            // その他のフィールド
            formData.append('limit', form.querySelector('input[name="limit"]').value);
            formData.append('offset', 0);
            formData.append('date_from', form.querySelector('input[name="date_from"]').value);
            formData.append('date_to', form.querySelector('input[name="date_to"]').value);

            // 選択されたフィールド
            selectedFields.forEach(field => {
                formData.append('selected_fields[]', field);
            });

            const response = await fetch(wpSmartCsv.ajax_url, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.showSuccess(result, `
                    <p>${data.data.message}</p>
                    <p><a href="${data.data.download_url}" class="button button-primary" download="${data.data.filename}">
                        CSVファイルをダウンロード
                    </a></p>
                `);

                // 8秒後に自動で非表示
                setTimeout(() => this.hideElement(result), 8000);
            } else {
                this.showError(result, data.data.message || wpSmartCsv.strings.error);
            }
        } catch (error) {
            this.showError(result, `${wpSmartCsv.strings.error}: ${error.message}`);
        } finally {
            this.setButtonLoading(button, false);
        }
    }

    /**
     * インポート処理
     */
    async handleImport(e) {
        e.preventDefault();

        const form = e.target;
        const button = form.querySelector('#import-btn');
        const result = document.getElementById('import-result');
        const progressContainer = document.getElementById('import-progress');
        const progressFill = document.getElementById('progress-fill');
        const progressText = document.getElementById('progress-text');
        const progressDetails = document.getElementById('progress-details');
        const progressCounts = document.getElementById('progress-counts');
        const progressCurrent = progressCounts.querySelector('.progress-current');
        const progressTotal = progressCounts.querySelector('.progress-total');
        const progressStatus = document.getElementById('progress-status');
        const fileInput = document.getElementById('csv_file');

        if (!fileInput.files.length) {
            this.showError(result, wpSmartCsv.strings.select_csv_file);
            return;
        }

        // ファイルサイズチェック（10MB制限）
        const maxSize = 10 * 1024 * 1024; // 10MB
        if (fileInput.files[0].size > maxSize) {
            this.showError(result, 'ファイルサイズが大きすぎます。10MB以下のファイルを選択してください。');
            return;
        }

        // ファイル拡張子チェック
        const fileName = fileInput.files[0].name;
        if (!fileName.toLowerCase().endsWith('.csv')) {
            this.showError(result, 'CSVファイルを選択してください。');
            return;
        }

        // インポート開始フラグ
        this.isImporting = true;
        
        // ローディング状態
        this.setButtonLoading(button, true);
        this.hideElement(result);
        
        // 進捗表示開始
        progressContainer.style.display = 'block';
        this.updateProgressDetailed(progressFill, progressText, progressDetails, progressCurrent, progressTotal, progressStatus, 0, 0, 0, 'CSVファイルを分析中...');

        try {
            // Step 1: ファイルの総行数を取得
            const countFormData = new FormData();
            countFormData.append('action', 'smart_csv_import_count');
            countFormData.append('nonce', wpSmartCsv.nonce);
            countFormData.append('csv_file', fileInput.files[0]);

            const countResponse = await fetch(wpSmartCsv.ajax_url, {
                method: 'POST',
                body: countFormData
            });

            const countData = await countResponse.json();

            if (!countData.success) {
                throw new Error(countData.data.message);
            }

            const totalRows = countData.data.total_rows;
            const tempFile = countData.data.temp_file;
            
            this.updateProgressDetailed(progressFill, progressText, progressDetails, progressCurrent, progressTotal, progressStatus, 0, totalRows, 0, `${totalRows}件のデータを検出しました。処理を開始します...`);

            // Step 2: バッチ処理でインポート
            await this.processBatchImport(
                form.querySelector('input[name="import_mode"]:checked').value,
                tempFile,
                totalRows,
                progressFill,
                progressText,
                progressDetails,
                progressCurrent,
                progressTotal,
                progressStatus
            );

            // 第二クリーンアップ：一時ディレクトリを完全削除
            await this.cleanupTempFile();

            // インポート完了フラグ
            this.isImporting = false;

            // 完了メッセージ
            this.showSuccess(result, `インポート完了: ${totalRows}件のデータを処理しました`);
            form.reset();

            // 7秒後に自動で非表示
            setTimeout(() => {
                this.hideElement(result);
                progressContainer.style.display = 'none';
            }, 7000);

        } catch (error) {
            this.showError(result, `${wpSmartCsv.strings.error}: ${error.message}`);
            progressContainer.style.display = 'none';
            
            // エラー時も一時ディレクトリをクリーンアップ
            await this.cleanupTempFile().catch(() => {
                // クリーンアップエラーは無視
            });
        } finally {
            // インポート終了フラグ
            this.isImporting = false;
            this.setButtonLoading(button, false);
        }
    }

    /**
     * ファイル情報更新
     */
    updateFileInfo(e) {
        const files = e.target.files;
        const description = e.target.parentElement.querySelector('.description');

        if (files.length > 0 && description) {
            const file = files[0];
            const fileSize = (file.size / 1024 / 1024).toFixed(2); // MB
            description.innerHTML = `ファイル名: ${file.name} (${fileSize}MB)`;
        }
    }

    /**
     * ユーティリティメソッド
     */
    showSuccess(element, message) {
        element.className = 'notification notification--success';
        element.innerHTML = message;
        this.showElement(element);
    }

    showError(element, message) {
        element.className = 'notification notification--error';
        element.innerHTML = `<p>${message}</p>`;
        this.showElement(element);

        // 5秒後に自動で非表示
        setTimeout(() => this.hideElement(element), 5000);
    }

    showElement(element) {
        element.style.display = 'block';
    }

    hideElement(element) {
        element.style.display = 'none';
    }

    setButtonLoading(button, loading) {
        if (loading) {
            button.classList.add('button--loading');
            button.disabled = true;
        } else {
            button.classList.remove('button--loading');
            button.disabled = false;
        }
    }

    /**
     * バッチ処理でインポート実行
     */
    async processBatchImport(importMode, tempFile, totalRows, progressFill, progressText, progressDetails, progressCurrent, progressTotal, progressStatus) {
        const batchSize = 10; // 10件ずつ処理
        let processedRows = 0;
        let batchStart = 0;
        let totalResults = { created: 0, updated: 0, skipped: 0, errors: 0 };

        while (processedRows < totalRows) {
            const batchFormData = new FormData();
            batchFormData.append('action', 'smart_csv_import_batch');
            batchFormData.append('nonce', wpSmartCsv.nonce);
            batchFormData.append('import_mode', importMode);
            batchFormData.append('temp_file', tempFile);
            batchFormData.append('batch_start', batchStart);
            batchFormData.append('batch_size', batchSize);

            const batchResponse = await fetch(wpSmartCsv.ajax_url, {
                method: 'POST',
                body: batchFormData
            });

            const batchData = await batchResponse.json();

            if (!batchData.success) {
                throw new Error(batchData.data.message);
            }

            // 結果を累積
            const batchResults = batchData.data.results;
            totalResults.created += batchResults.created;
            totalResults.updated += batchResults.updated;
            totalResults.skipped += batchResults.skipped;
            totalResults.errors += batchResults.errors;

            processedRows += batchData.data.processed;
            batchStart = batchData.data.next_batch_start;

            // 進捗更新
            const percentage = Math.round((processedRows / totalRows) * 100);
            const statusText = `作成:${totalResults.created} 更新:${totalResults.updated} スキップ:${totalResults.skipped} エラー:${totalResults.errors}`;
            
            this.updateProgressDetailed(
                progressFill, 
                progressText, 
                progressDetails, 
                progressCurrent, 
                progressTotal, 
                progressStatus, 
                percentage, 
                totalRows, 
                processedRows, 
                'データを処理中...',
                statusText
            );

            // 少し待機（UI更新のため）
            await new Promise(resolve => setTimeout(resolve, 100));

            // 処理完了チェック
            if (!batchData.data.has_more) {
                break;
            }
        }

        // 完了
        this.updateProgressDetailed(
            progressFill, 
            progressText, 
            progressDetails, 
            progressCurrent, 
            progressTotal, 
            progressStatus, 
            100, 
            totalRows, 
            processedRows, 
            '完了！',
            `最終結果: 作成:${totalResults.created} 更新:${totalResults.updated} スキップ:${totalResults.skipped} エラー:${totalResults.errors}`
        );
    }

    /**
     * 第二クリーンアップ：完全ディレクトリ削除
     */
    async cleanupTempFile(tempFile) {
        try {
            const formData = new FormData();
            formData.append('action', 'smart_csv_cleanup');
            formData.append('nonce', wpSmartCsv.nonce);
            // tempFileパラメータは不要（ディレクトリごと削除するため）

            const response = await fetch(wpSmartCsv.ajax_url, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            
            // クリーンアップの結果は特に表示しない（サイレント）
            if (data.success) {
                console.log('一時ディレクトリを完全にクリーンアップしました');
            } else {
                console.warn('一時ディレクトリのクリーンアップに失敗:', data.data.message);
            }
        } catch (error) {
            console.warn('一時ディレクトリのクリーンアップエラー:', error.message);
        }
    }

    /**
     * 詳細進捗更新
     */
    updateProgressDetailed(progressFill, progressText, progressDetails, progressCurrent, progressTotal, progressStatus, percentage, total, current, message, status = '') {
        progressFill.style.width = `${percentage}%`;
        progressText.textContent = `${percentage}%`;
        progressDetails.textContent = message;
        progressCurrent.textContent = current;
        progressTotal.textContent = total;
        if (status) {
            progressStatus.textContent = status;
        }
    }

    /**
     * 進捗更新（レガシー）
     */
    updateProgress(progressFill, progressText, progressDetails, percentage, message) {
        progressFill.style.width = `${percentage}%`;
        progressText.textContent = `${percentage}%`;
        progressDetails.textContent = message;
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
}

// DOM読み込み完了後に初期化
document.addEventListener('DOMContentLoaded', () => {
    new CSVManager();
});