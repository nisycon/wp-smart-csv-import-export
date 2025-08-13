<?php
/**
 * Plugin Name: WP Smart CSV Import/Export
 * Plugin URI: https://qoox.co.jp/wp-smart-csv-import-export
 * Description: 汎用的なCSVインポート・エクスポートプラグイン。全ての投稿タイプ、カスタムフィールド、タクソノミーに対応。エクスポートしたCSVファイルをそのままインポート可能。
 * Version: 1.0.0
 * Author: Qoox
 * Author URI: https://qoox.co.jp
 * Donate link: https://www.paypal.com/ncp/payment/JKL3WTQLH5NXA
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-smart-csv-import-export
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// セキュリティ：直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

// プラグインの定数定義
define('WP_SMART_CSV_VERSION', '1.0.0');
define('WP_SMART_CSV_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_SMART_CSV_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_SMART_CSV_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * メインプラグインクラス
 */
class WpSmartCsvImportExport {
    
    /**
     * シングルトンインスタンス
     */
    private static $instance = null;
    
    /**
     * テキストドメイン
     */
    protected $textdomain = 'wp-smart-csv-import-export';
    
    /**
     * シングルトンパターン
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * コンストラクタ
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * フック初期化
     */
    private function init_hooks() {
        add_action('init', array($this, 'load_textdomain'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_smart_csv_export', array($this, 'handle_csv_export'));
        add_action('wp_ajax_smart_csv_import', array($this, 'handle_csv_import'));
        add_action('wp_ajax_smart_csv_get_fields', array($this, 'handle_get_fields'));
        
        // バッチ処理用エンドポイント
        add_action('wp_ajax_smart_csv_import_batch', array($this, 'handle_csv_import_batch'));
        add_action('wp_ajax_smart_csv_import_count', array($this, 'handle_csv_import_count'));
        add_action('wp_ajax_smart_csv_cleanup', array($this, 'handle_csv_cleanup'));
        
        // プラグイン有効化・無効化フック
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * 言語ファイル読み込み
     */
    public function load_textdomain() {
        load_plugin_textdomain($this->textdomain, false, dirname(WP_SMART_CSV_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * 管理メニュー追加
     */
    public function add_admin_menu() {
        add_menu_page(
            __('WP Smart CSV Import/Export', $this->textdomain),
            __('CSV IMP/EXP', $this->textdomain),
            'manage_options',
            'wp-smart-csv-import-export',
            array($this, 'admin_page'),
            'dashicons-editor-table',
            30
        );
    }
    
    /**
     * 管理画面スクリプト・スタイル読み込み
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'wp-smart-csv-import-export') === false) {
            return;
        }
        
        wp_enqueue_script(
            'wp-smart-csv-admin',
            WP_SMART_CSV_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            WP_SMART_CSV_VERSION,
            true
        );
        
        wp_enqueue_style(
            'wp-smart-csv-admin',
            WP_SMART_CSV_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WP_SMART_CSV_VERSION
        );
        
        // Ajax用のローカライズ
        wp_localize_script('wp-smart-csv-admin', 'wpSmartCsv', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_smart_csv_nonce'),
            'strings' => array(
                'export_success' => __('エクスポートが完了しました。', $this->textdomain),
                'import_success' => __('インポートが完了しました。', $this->textdomain),
                'error' => __('エラーが発生しました。', $this->textdomain),
                'processing' => __('処理中...', $this->textdomain),
                'select_post_type' => __('投稿タイプを選択してください。', $this->textdomain),
                'select_csv_file' => __('CSVファイルを選択してください。', $this->textdomain),
            )
        ));
    }
    
    /**
     * 管理画面ページ表示
     */
    public function admin_page() {
        // 利用可能な投稿タイプを取得
        $post_types = get_post_types(array('public' => true), 'objects');
        
        // 除外する投稿タイプ
        $exclude_types = array('attachment', 'revision', 'nav_menu_item');
        foreach ($exclude_types as $exclude) {
            unset($post_types[$exclude]);
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
                         <div class="csv-manager">
                 <div class="csv-manager__tabs">
                     <button class="csv-manager__tab csv-manager__tab--active" data-tab="export">
                         <?php _e('CSVエクスポート', $this->textdomain); ?>
                     </button>
                     <button class="csv-manager__tab" data-tab="import">
                         <?php _e('CSVインポート', $this->textdomain); ?>
                     </button>
                     <button class="csv-manager__tab" data-tab="help">
                         <?php _e('ヘルプ', $this->textdomain); ?>
                     </button>
                 </div>
                 
                 <!-- エクスポートタブ -->
                 <div class="csv-manager__panel csv-manager__panel--active" data-panel="export">
                    <h2><?php _e('CSVエクスポート', $this->textdomain); ?></h2>
                    <p><?php _e('投稿データをCSVファイルとしてエクスポートします。', $this->textdomain); ?></p>
                    
                    <form id="export-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="export_post_type"><?php _e('投稿タイプ', $this->textdomain); ?></label>
                                </th>
                                <td>
                                    <select id="export_post_type" name="post_type" required>
                                        <option value=""><?php _e('選択してください', $this->textdomain); ?></option>
                                        <option value="all"><?php _e('すべての投稿タイプ', $this->textdomain); ?></option>
                                        <?php foreach ($post_types as $post_type): ?>
                                            <option value="<?php echo esc_attr($post_type->name); ?>">
                                                <?php echo esc_html($post_type->labels->name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('投稿ステータス', $this->textdomain); ?></th>
                                <td>
                                    <fieldset>
                                        <label><input type="checkbox" name="post_status[]" value="publish" checked> <?php _e('公開済み', $this->textdomain); ?></label><br>
                                        <label><input type="checkbox" name="post_status[]" value="draft"> <?php _e('下書き', $this->textdomain); ?></label><br>
                                        <label><input type="checkbox" name="post_status[]" value="private"> <?php _e('非公開', $this->textdomain); ?></label><br>
                                        <label><input type="checkbox" name="post_status[]" value="pending"> <?php _e('レビュー待ち', $this->textdomain); ?></label><br>
                                        <label><input type="checkbox" name="post_status[]" value="future"> <?php _e('予約投稿', $this->textdomain); ?></label><br>
                                        <label><input type="checkbox" name="post_status[]" value="trash"> <?php _e('ゴミ箱', $this->textdomain); ?></label>
                                    </fieldset>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('エクスポート件数', $this->textdomain); ?></th>
                                <td>
                                    <input type="number" name="limit" value="0" min="0" style="width: 100px;">
                                    <p class="description"><?php _e('0を指定すると全件エクスポートします。', $this->textdomain); ?></p>
                                </td>
                            </tr>
                            
                                                         <tr>
                                 <th scope="row"><?php _e('日付範囲', $this->textdomain); ?></th>
                                 <td>
                                     <label for="date_from"><?php _e('開始日', $this->textdomain); ?>:</label>
                                     <input type="date" id="date_from" name="date_from" class="form__input">
                                     <label for="date_to"><?php _e('終了日', $this->textdomain); ?>:</label>
                                     <input type="date" id="date_to" name="date_to" class="form__input">
                                 </td>
                             </tr>
                            <tr>
                                                                 <th scope="row"><?php _e('フィールド選択', $this->textdomain); ?></th>
                                 <td>
                                     <div class="field-selector" id="export-fields-container">
                                         <p class="field-selector__placeholder"><?php _e('投稿タイプを選択すると、利用可能なフィールドが表示されます。', $this->textdomain); ?></p>
                                     </div>
                                 </td>
                            </tr>
                        </table>
                        
                        <?php wp_nonce_field('wp_smart_csv_nonce', 'csv_nonce'); ?>
                        <p class="submit">
                            <button type="submit" class="button-primary" id="export-btn">
                                <?php _e('CSVエクスポート', $this->textdomain); ?>
                            </button>
                        </p>
                    </form>
                    
                                             <div id="export-result" class="notification" style="display:none;"></div>
                    
                    <!-- 寄付セクション -->
                    <div style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; border-radius: 6px; margin-top: 30px;">
                        <h4 style="margin-top: 0; color: #0073aa; font-size: 14px;"><?php _e('このプラグインが役に立ちましたか？', $this->textdomain); ?></h4>
                        <p style="font-size: 13px; margin: 8px 0;"><?php _e('開発支援をご検討ください。', $this->textdomain); ?></p>
                        
                        <div style="text-align: center;">
                            <style>.pp-JKL3WTQLH5NXA{text-align:center;border:none;border-radius:0.25rem;min-width:11.625rem;padding:0 2rem;height:2.625rem;font-weight:bold;background-color:#394887;color:#ffffff;font-family:"Helvetica Neue",Arial,sans-serif;font-size:1rem;line-height:1.25rem;cursor:pointer;}</style>
                            <form action="https://www.paypal.com/ncp/payment/JKL3WTQLH5NXA" method="post" target="_blank" style="display:inline-grid;justify-items:center;align-content:start;gap:0.5rem;">
                                <input class="pp-JKL3WTQLH5NXA" type="submit" value="💝 <?php _e('寄付する', $this->textdomain); ?>" style="transform: scale(0.8);" />
                            </form>
                        </div>
                    </div>
                </div>
                
                                 <!-- インポートタブ -->
                 <div class="csv-manager__panel" data-panel="import">
                    <h2><?php _e('CSVインポート', $this->textdomain); ?></h2>
                    <p><?php _e('CSVファイルから投稿データをインポートします。', $this->textdomain); ?></p>
                    
                    <div class="csv-manager__warning">
                        <h3>⚠️ <?php _e('重要な注意事項', $this->textdomain); ?></h3>
                        <ul>
                            <li><strong><?php _e('処理中はブラウザタブを閉じないでください', $this->textdomain); ?></strong></li>
                            <li><strong><?php _e('別のページに移動しないでください', $this->textdomain); ?></strong></li>
                            <li><strong><?php _e('PCをスリープさせないでください', $this->textdomain); ?></strong></li>
                            <li><?php _e('大きなファイルの場合は、処理完了まで画面を開いたままにしてください', $this->textdomain); ?></li>
                        </ul>
                    </div>
                    
                    <form id="import-form" enctype="multipart/form-data">
                        <table class="form-table">

                            <tr>
                                <th scope="row">
                                    <label for="csv_file"><?php _e('CSVファイル', $this->textdomain); ?></label>
                                </th>
                                <td>
                                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                                    <p class="description">
                                        <?php _e('UTF-8エンコードのCSVファイルをアップロードしてください。最大10MBまで。', $this->textdomain); ?>
                                    </p>
                                </td>
                            </tr>
                                                         <tr>
                                 <th scope="row"><?php _e('インポートモード', $this->textdomain); ?></th>
                                 <td>
                                     <fieldset>
                                         <label>
                                             <input type="radio" name="import_mode" value="update" checked>
                                             <?php _e('更新＋新規作成', $this->textdomain); ?>
                                         </label><br>
                                         <label>
                                             <input type="radio" name="import_mode" value="create">
                                             <?php _e('新規作成のみ（非推奨）', $this->textdomain); ?>
                                         </label>
                                     </fieldset>
                                     <p class="description">
                                         <strong><?php _e('更新＋新規作成', $this->textdomain); ?>:</strong> <?php _e('IDが一致する投稿は更新、IDがない場合は新規作成します（推奨）', $this->textdomain); ?><br>
                                         <strong><?php _e('新規作成のみ（非推奨）', $this->textdomain); ?>:</strong> <?php _e('IDが設定されていても無視して、常に新規投稿を作成します', $this->textdomain); ?>
                                     </p>
                                 </td>
                                                         </tr>
                        </table>
                        
                        <?php wp_nonce_field('wp_smart_csv_nonce', 'csv_nonce'); ?>
                        <p class="submit">
                            <button type="submit" class="button-primary" id="import-btn">
                                <?php _e('CSVインポート', $this->textdomain); ?>
                            </button>
                        </p>
                    </form>
                    
                                             <div id="import-result" class="notification" style="display:none;"></div>
                    <div id="import-progress" class="progress-container" style="display: none;">
                        <div class="progress-bar">
                            <div class="progress-fill" id="progress-fill"></div>
                        </div>
                        <div class="progress-text" id="progress-text">0%</div>
                        <div class="progress-details" id="progress-details">準備中...</div>
                        <div class="progress-counts" id="progress-counts">
                            <span class="progress-current">0</span> / <span class="progress-total">0</span> 件処理済み
                            <span class="progress-status" id="progress-status"></span>
                        </div>
                    </div>
                    
                    <!-- 寄付セクション -->
                    <div style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; border-radius: 6px; margin-top: 30px;">
                        <h4 style="margin-top: 0; color: #0073aa; font-size: 14px;"><?php _e('このプラグインが役に立ちましたか？', $this->textdomain); ?></h4>
                        <p style="font-size: 13px; margin: 8px 0;"><?php _e('開発支援をご検討ください。', $this->textdomain); ?></p>
                        
                        <div style="text-align: center;">
                            <style>.pp-JKL3WTQLH5NXA{text-align:center;border:none;border-radius:0.25rem;min-width:11.625rem;padding:0 2rem;height:2.625rem;font-weight:bold;background-color:#394887;color:#ffffff;font-family:"Helvetica Neue",Arial,sans-serif;font-size:1rem;line-height:1.25rem;cursor:pointer;}</style>
                            <form action="https://www.paypal.com/ncp/payment/JKL3WTQLH5NXA" method="post" target="_blank" style="display:inline-grid;justify-items:center;align-content:start;gap:0.5rem;">
                                <input class="pp-JKL3WTQLH5NXA" type="submit" value="💝 <?php _e('寄付する', $this->textdomain); ?>" style="transform: scale(0.8);" />
                            </form>
                        </div>
                    </div>
                </div>
                
                                 <!-- ヘルプタブ -->
                 <div class="csv-manager__panel" data-panel="help">
                    <h2><?php _e('使用方法', $this->textdomain); ?></h2>
                    
                    <h3><?php _e('基本的な使い方', $this->textdomain); ?></h3>
                    <ol>
                        <li><?php _e('エクスポートタブで投稿タイプを選択し、必要なフィールドにチェックを入れてエクスポート', $this->textdomain); ?></li>
                        <li><?php _e('ダウンロードされたCSVファイルを編集', $this->textdomain); ?></li>
                        <li><?php _e('インポートタブで編集したCSVファイルをアップロードしてインポート', $this->textdomain); ?></li>
                    </ol>
                    
                    <h3><?php _e('対応フィールド', $this->textdomain); ?></h3>
                    <ul>
                        <li><strong><?php _e('基本フィールド', $this->textdomain); ?>:</strong> ID, post_type（投稿タイプ）, タイトル, 内容, 抜粋, ステータス, 公開日時, 変更日時, 投稿者, スラッグ, 親投稿, メニュー順序</li>
                        <li><strong><?php _e('カスタムフィールド', $this->textdomain); ?>:</strong> 全てのカスタムフィールド（ACF含む）</li>
                        <li><strong><?php _e('タクソノミー', $this->textdomain); ?>:</strong> カテゴリー、タグ、カスタムタクソノミー</li>
                        <li><strong><?php _e('アイキャッチ画像', $this->textdomain); ?>:</strong> 画像URL、添付ファイルID</li>
                    </ul>
                    
                    <h3><?php _e('技術仕様', $this->textdomain); ?></h3>
                    
                    <h4><?php _e('post_type列について', $this->textdomain); ?></h4>
                    <ul>
                        <li><strong><?php _e('エクスポート時', $this->textdomain); ?>:</strong> 各投稿の投稿タイプ名が出力されます（例：post, page, items, product）</li>
                        <li><strong><?php _e('インポート時', $this->textdomain); ?>:</strong> post_type列の値に基づいて投稿タイプが自動判別されます</li>
                        <li><strong><?php _e('投稿タイプ選択不要', $this->textdomain); ?>:</strong> インポート時は投稿タイプを選択する必要がありません</li>
                        <li><strong><?php _e('混在データ対応', $this->textdomain); ?>:</strong> 異なる投稿タイプが混在するCSVでも一括インポート可能</li>
                    </ul>
                    
                    <h4><?php _e('ID判別システム', $this->textdomain); ?></h4>
                    <ul>
                        <li><strong><?php _e('一意性', $this->textdomain); ?>:</strong> WordPressでは全投稿タイプでIDが一意に管理されます</li>
                        <li><strong><?php _e('更新判定', $this->textdomain); ?>:</strong> IDが存在する場合は投稿タイプに関係なく既存投稿として更新されます</li>
                        <li><strong><?php _e('新規作成', $this->textdomain); ?>:</strong> IDが空または存在しない場合は新規投稿として作成されます</li>
                        <li><strong><?php _e('投稿タイプ変更', $this->textdomain); ?>:</strong> 既存投稿の投稿タイプをCSVで変更することが可能です</li>
                    </ul>
                    
                    <h3><?php _e('CSVフォーマット', $this->textdomain); ?></h3>
                    
                    <h4><?php _e('エクスポート時（自動生成）', $this->textdomain); ?></h4>
                    <ul>
                        <li><strong><?php _e('文字エンコード', $this->textdomain); ?>:</strong> UTF-8（BOM付き）- 日本語文字化け防止</li>
                        <li><strong><?php _e('区切り文字', $this->textdomain); ?>:</strong> カンマ（,）</li>
                        <li><strong><?php _e('囲み文字', $this->textdomain); ?>:</strong> ダブルクォート（"）- データ内のカンマや改行を保護</li>
                        <li><strong><?php _e('改行文字', $this->textdomain); ?>:</strong> CRLF（Windows標準）</li>
                        <li><strong><?php _e('データ保護', $this->textdomain); ?>:</strong> カンマや改行を含むデータは自動的にクォートで囲まれます</li>
                    </ul>
                    
                    <h4><?php _e('インポート時（要注意）', $this->textdomain); ?></h4>
                    <ul>
                        <li><strong><?php _e('文字エンコード', $this->textdomain); ?>:</strong> UTF-8（BOM付き推奨）</li>
                        <li><strong><?php _e('区切り文字', $this->textdomain); ?>:</strong> カンマ（,）必須</li>
                        <li><strong><?php _e('データ内カンマ', $this->textdomain); ?>:</strong> データにカンマが含まれる場合は必ずダブルクォートで囲んでください</li>
                        <li><strong><?php _e('改行の扱い', $this->textdomain); ?>:</strong> データ内改行はクォート内であれば保持されます</li>
                        <li><strong><?php _e('Excel注意', $this->textdomain); ?>:</strong> Excelで編集時は保存形式を「CSV UTF-8」にしてください</li>
                    </ul>
                    
                    <h4><?php _e('データ内特殊文字の扱い', $this->textdomain); ?></h4>
                    <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 4px; margin: 10px 0;">
                        <strong><?php _e('⚠️ 重要', $this->textdomain); ?>:</strong> <?php _e('以下の文字を含むデータは必ずダブルクォートで囲んでください', $this->textdomain); ?>
                        <ul style="margin-top: 8px;">
                            <li><strong><?php _e('カンマ', $this->textdomain); ?> (,):</strong> "<?php _e('商品A, 商品B, 商品C', $this->textdomain); ?>"</li>
                            <li><strong><?php _e('改行', $this->textdomain); ?>:</strong> "<?php _e('1行目', $this->textdomain); ?><br><?php _e('2行目', $this->textdomain); ?>"</li>
                            <li><strong><?php _e('ダブルクォート', $this->textdomain); ?> ("):</strong> "<?php _e('彼は""こんにちは""と言った', $this->textdomain); ?>"（データ内の"は""でエスケープ）</li>
                        </ul>
                        <p style="margin-top: 8px; margin-bottom: 0;"><strong><?php _e('推奨', $this->textdomain); ?>:</strong> <?php _e('当プラグインでエクスポートしたCSVを編集することで、フォーマットエラーを防げます', $this->textdomain); ?></p>
                    </div>
                    
                    <h3><?php _e('実用例', $this->textdomain); ?></h3>
                    
                    <h4><?php _e('CSVデータ例', $this->textdomain); ?></h4>
                    <pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto;">
ID,post_type,post_title,post_content,post_status
1,post,"ブログ記事タイトル","記事の内容...",publish
2,page,"会社概要","会社の説明...",publish
,items,"新しいアイテム","アイテムの説明...",draft
123,product,"商品A","商品の詳細...",publish</pre>
                    
                    <h4><?php _e('動作パターン', $this->textdomain); ?></h4>
                    <ul>
                        <li><strong>ID=1:</strong> <?php _e('既存の投稿（ID:1）を更新', $this->textdomain); ?></li>
                        <li><strong>ID=2:</strong> <?php _e('既存の固定ページ（ID:2）を更新', $this->textdomain); ?></li>
                        <li><strong>ID空白:</strong> <?php _e('新しいitemsタイプの投稿を作成', $this->textdomain); ?></li>
                        <li><strong>ID=123:</strong> <?php _e('ID:123の投稿をproductタイプに変更して更新', $this->textdomain); ?></li>
                    </ul>
                    
                    <h3><?php _e('注意事項', $this->textdomain); ?></h3>
                    <ul>
                        <li><?php _e('大量データのインポート時は、サーバーのメモリ制限やタイムアウトにご注意ください', $this->textdomain); ?></li>
                        <li><?php _e('本番環境での使用前に、必ずテスト環境で動作確認を行ってください', $this->textdomain); ?></li>
                        <li><?php _e('インポート前にデータベースのバックアップを取得することを強く推奨します', $this->textdomain); ?></li>
                        <li><?php _e('投稿タイプ変更時は、関連するカスタムフィールドやタクソノミーとの整合性をご確認ください', $this->textdomain); ?></li>
                    </ul>
                    
                    <h3><?php _e('開発支援', $this->textdomain); ?></h3>
                    <div style="background: #f0f8ff; border: 1px solid #0073aa; padding: 20px; border-radius: 8px; margin: 15px 0;">
                        <h4 style="margin-top: 0; color: #0073aa;"><?php _e('このプラグインが役に立ちましたか？', $this->textdomain); ?></h4>
                        <p><?php _e('WP Smart CSV Import/Exportは無料で提供していますが、開発とメンテナンスには時間と労力がかかります。もしこのプラグインがあなたの作業に役立ったなら、開発支援をご検討ください。', $this->textdomain); ?></p>
                        
                        <div style="text-align: center; margin: 20px 0;">
                            <style>.pp-JKL3WTQLH5NXA{text-align:center;border:none;border-radius:0.25rem;min-width:11.625rem;padding:0 2rem;height:2.625rem;font-weight:bold;background-color:#394887;color:#ffffff;font-family:"Helvetica Neue",Arial,sans-serif;font-size:1rem;line-height:1.25rem;cursor:pointer;}</style>
                            <form action="https://www.paypal.com/ncp/payment/JKL3WTQLH5NXA" method="post" target="_blank" style="display:inline-grid;justify-items:center;align-content:start;gap:0.5rem;">
                                <input class="pp-JKL3WTQLH5NXA" type="submit" value="💝 <?php _e('寄付する', $this->textdomain); ?>" />
                                <img src="https://www.paypalobjects.com/images/Debit_Credit_APM.svg" alt="cards" style="max-width: 200px;" />
                                <section style="font-size: 0.75rem;"> 
                                    Powered by <img src="https://www.paypalobjects.com/paypal-ui/logos/svg/paypal-wordmark-color.svg" alt="paypal" style="height:0.875rem;vertical-align:middle;"/>
                                </section>
                            </form>
                        </div>
                        
                        <p style="font-size: 14px; color: #666; text-align: center; margin-bottom: 0;">
                            <?php _e('寄付は任意です。金額もお気持ちで結構です。', $this->textdomain); ?><br>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * フィールド情報取得
     */
    public function handle_get_fields() {
        // nonce確認
        if (!wp_verify_nonce($_POST['nonce'], 'wp_smart_csv_nonce')) {
            wp_send_json_error(array('message' => __('セキュリティチェックに失敗しました。', $this->textdomain)));
        }
        
        $post_type = sanitize_text_field($_POST['post_type']);
        
        try {
            $fields = $this->get_available_fields($post_type);
            wp_send_json_success($fields);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * 利用可能なフィールドを取得
     */
    private function get_available_fields($post_type) {
        $fields = array();
        
        // 基本フィールド
        $basic_fields = array(
            'ID' => __('投稿ID', $this->textdomain),
            'post_type' => __('投稿タイプ', $this->textdomain),
            'post_title' => __('タイトル', $this->textdomain),
            'post_content' => __('内容', $this->textdomain),
            'post_excerpt' => __('抜粋', $this->textdomain),
            'post_status' => __('ステータス', $this->textdomain),
            'post_date' => __('公開日時', $this->textdomain),
            'post_modified' => __('変更日時', $this->textdomain),
            'post_author' => __('投稿者', $this->textdomain),
            'post_name' => __('スラッグ', $this->textdomain),
            'post_parent' => __('親投稿', $this->textdomain),
            'menu_order' => __('メニュー順序', $this->textdomain),
        );
        
        $fields['basic'] = array(
            'title' => __('基本フィールド', $this->textdomain),
            'fields' => $basic_fields
        );
        
        // アイキャッチ画像
        if (post_type_supports($post_type, 'thumbnail')) {
            $fields['thumbnail'] = array(
                'title' => __('アイキャッチ画像', $this->textdomain),
                'fields' => array(
                    'featured_image' => __('アイキャッチ画像URL', $this->textdomain),
                    'featured_image_id' => __('アイキャッチ画像ID', $this->textdomain),
                )
            );
        }
        
        // タクソノミー
        $taxonomies = get_object_taxonomies($post_type, 'objects');
        if (!empty($taxonomies)) {
            $taxonomy_fields = array();
            foreach ($taxonomies as $taxonomy) {
                $taxonomy_fields[$taxonomy->name] = $taxonomy->labels->name;
            }
            
            $fields['taxonomies'] = array(
                'title' => __('タクソノミー', $this->textdomain),
                'fields' => $taxonomy_fields
            );
        }
        
        // カスタムフィールド
        $custom_fields = $this->get_custom_fields($post_type);
        if (!empty($custom_fields)) {
            $fields['custom_fields'] = array(
                'title' => __('カスタムフィールド', $this->textdomain),
                'fields' => $custom_fields
            );
        }
        
        return $fields;
    }
    
    /**
     * カスタムフィールドを取得（包括的検出）
     */
    private function get_custom_fields($post_type) {
        $custom_fields = array();
        
        // 1. ACFフィールドグループから取得
        $acf_fields = $this->get_acf_fields($post_type);
        $custom_fields = array_merge($custom_fields, $acf_fields);
        
        // 2. 既存投稿のpostmetaから取得
        $existing_fields = $this->get_existing_meta_fields($post_type);
        $custom_fields = array_merge($custom_fields, $existing_fields);
        
        // 3. 他のカスタムフィールドプラグインから取得
        $plugin_fields = $this->get_plugin_meta_fields($post_type);
        $custom_fields = array_merge($custom_fields, $plugin_fields);
        
        // 4. 重複を除去してソート
        $custom_fields = array_unique($custom_fields, SORT_REGULAR);
        ksort($custom_fields);
        
        return $custom_fields;
    }
    
    /**
     * ACFフィールドを取得
     */
    private function get_acf_fields($post_type) {
        $acf_fields = array();
        
        if (function_exists('acf_get_field_groups')) {
            $field_groups = acf_get_field_groups(array('post_type' => $post_type));
            
            foreach ($field_groups as $field_group) {
                $fields = acf_get_fields($field_group['key']);
                
                if ($fields) {
                    foreach ($fields as $field) {
                        // タブフィールドはスキップ
                        if ($field['type'] !== 'tab') {
                            $acf_fields[$field['name']] = $field['label'] . ' (' . $field['name'] . ')';
                        }
                    }
                }
            }
        }
        
        return $acf_fields;
    }
    
    /**
     * 既存投稿のメタフィールドを取得
     */
    private function get_existing_meta_fields($post_type) {
        global $wpdb;
        
        $existing_fields = array();
        
        $query = $wpdb->prepare("
            SELECT DISTINCT pm.meta_key
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND pm.meta_key NOT LIKE '\_%'
            AND pm.meta_key NOT REGEXP '^field_[a-f0-9]+$'
            ORDER BY pm.meta_key
        ", $post_type);
        
        $results = $wpdb->get_results($query);
        
        foreach ($results as $result) {
            $existing_fields[$result->meta_key] = $result->meta_key;
        }
        
        return $existing_fields;
    }
    
    /**
     * 他のプラグインのメタフィールドを取得
     */
    private function get_plugin_meta_fields($post_type) {
        $plugin_fields = array();
        
        // Meta Box プラグイン対応
        if (function_exists('rwmb_get_registry')) {
            $meta_boxes = rwmb_get_registry('meta_box')->all();
            
            foreach ($meta_boxes as $meta_box) {
                if (in_array($post_type, $meta_box->post_types)) {
                    foreach ($meta_box->fields as $field) {
                        if ($field['type'] !== 'heading' && $field['type'] !== 'divider') {
                            $plugin_fields[$field['id']] = ($field['name'] ?? $field['id']) . ' (' . $field['id'] . ')';
                        }
                    }
                }
            }
        }
        
        // Toolset Types プラグイン対応
        if (function_exists('wpcf_admin_fields_get_fields')) {
            $toolset_fields = wpcf_admin_fields_get_fields();
            
            foreach ($toolset_fields as $field_key => $field_data) {
                $field_slug = 'wpcf-' . $field_key;
                $plugin_fields[$field_slug] = $field_data['name'] . ' (' . $field_slug . ')';
            }
        }
        
        // Pods プラグイン対応
        if (function_exists('pods_api')) {
            $pods_api = pods_api();
            $pod = $pods_api->load_pod(array('name' => $post_type));
            
            if ($pod && isset($pod['fields'])) {
                foreach ($pod['fields'] as $field_name => $field_data) {
                    $plugin_fields[$field_name] = $field_data['label'] . ' (' . $field_name . ')';
                }
            }
        }
        
        // CMB2 プラグイン対応
        if (class_exists('CMB2_Boxes')) {
            $cmb2_boxes = CMB2_Boxes::get_all();
            
            foreach ($cmb2_boxes as $cmb_id => $cmb) {
                if (in_array($post_type, $cmb->object_types())) {
                    foreach ($cmb->fields() as $field) {
                        if ($field->type() !== 'title') {
                            $plugin_fields[$field->id()] = $field->get_name() . ' (' . $field->id() . ')';
                        }
                    }
                }
            }
        }
        
        return $plugin_fields;
    }
    
    /**
     * CSVエクスポート処理
     */
    public function handle_csv_export() {
        // nonce確認
        if (!wp_verify_nonce($_POST['nonce'], 'wp_smart_csv_nonce')) {
            wp_send_json_error(array('message' => __('セキュリティチェックに失敗しました。', $this->textdomain)));
        }
        
        // 権限確認
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('権限がありません。', $this->textdomain)));
        }
        
        try {
            $post_type = sanitize_text_field($_POST['post_type']);
            $post_status = isset($_POST['post_status']) ? array_map('sanitize_text_field', $_POST['post_status']) : array('publish');
            $limit = intval($_POST['limit']);
            $offset = intval($_POST['offset']);
            $date_from = sanitize_text_field($_POST['date_from']);
            $date_to = sanitize_text_field($_POST['date_to']);
            $selected_fields = isset($_POST['selected_fields']) ? array_map('sanitize_text_field', $_POST['selected_fields']) : array();
            
            $csv_data = $this->export_posts_to_csv($post_type, $post_status, $limit, $offset, $date_from, $date_to, $selected_fields);
            
            wp_send_json_success(array(
                'message' => __('エクスポートが完了しました。', $this->textdomain),
                'download_url' => $csv_data['url'],
                'filename' => $csv_data['filename']
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => __('エクスポート中にエラーが発生しました: ', $this->textdomain) . $e->getMessage()
            ));
        }
    }
    
    /**
     * CSV行数カウント処理
     */
    public function handle_csv_import_count() {
        // nonce確認
        if (!wp_verify_nonce($_POST['nonce'], 'wp_smart_csv_nonce')) {
            wp_send_json_error(array('message' => __('セキュリティチェックに失敗しました。', $this->textdomain)));
        }
        
        // 第一クリーンアップ：既存の一時ファイルを全削除
        $this->cleanup_all_temp_files();
        
        // ファイルアップロード処理
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => __('ファイルのアップロードに失敗しました。', $this->textdomain)));
        }
        
        $csv_file = $_FILES['csv_file']['tmp_name'];
        
        try {
            // CSVファイル読み込み
            $file = fopen($csv_file, 'r');
            if (!$file) {
                throw new Exception(__('CSVファイルを開けませんでした。', $this->textdomain));
            }
            
            // BOM除去
            $bom = fread($file, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($file);
            }
            
            // ヘッダー行をスキップ
            fgetcsv($file);
            
            // データ行をカウント
            $total_rows = 0;
            while (($data = fgetcsv($file)) !== FALSE) {
                if (!empty(array_filter($data))) { // 空行をスキップ
                    $total_rows++;
                }
            }
            
            fclose($file);
            
            // 一時ファイルとして保存（バッチ処理用）
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/wp-smart-csv-temp';
            
            if (!file_exists($temp_dir)) {
                wp_mkdir_p($temp_dir);
            }
            
            $temp_filename = 'import_' . uniqid() . '.csv';
            $temp_filepath = $temp_dir . '/' . $temp_filename;
            
            if (!move_uploaded_file($_FILES['csv_file']['tmp_name'], $temp_filepath)) {
                throw new Exception(__('一時ファイルの保存に失敗しました。', $this->textdomain));
            }
            
            wp_send_json_success(array(
                'total_rows' => $total_rows,
                'temp_file' => $temp_filename,
                'message' => sprintf(__('%d件のデータが見つかりました。', $this->textdomain), $total_rows)
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * CSVバッチインポート処理
     */
    public function handle_csv_import_batch() {
        // nonce確認
        if (!wp_verify_nonce($_POST['nonce'], 'wp_smart_csv_nonce')) {
            wp_send_json_error(array('message' => __('セキュリティチェックに失敗しました。', $this->textdomain)));
        }
        
        $import_mode = sanitize_text_field($_POST['import_mode']);
        $temp_filename = sanitize_text_field($_POST['temp_file']);
        $batch_start = intval($_POST['batch_start']);
        $batch_size = intval($_POST['batch_size']) ?: 10; // デフォルト10件ずつ
        
        try {
            $upload_dir = wp_upload_dir();
            $temp_filepath = $upload_dir['basedir'] . '/wp-smart-csv-temp/' . $temp_filename;
            
            if (!file_exists($temp_filepath)) {
                throw new Exception(__('一時ファイルが見つかりません。', $this->textdomain));
            }
            
            // CSVファイル読み込み
            $file = fopen($temp_filepath, 'r');
            if (!$file) {
                throw new Exception(__('CSVファイルを開けませんでした。', $this->textdomain));
            }
            
            // BOM除去
            $bom = fread($file, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($file);
            }
            
            // ヘッダー行取得
            $headers = fgetcsv($file);
            if (!$headers) {
                fclose($file);
                throw new Exception(__('CSVファイルのヘッダーが読み取れませんでした。', $this->textdomain));
            }
            
            // 指定された開始位置まで移動
            $current_row = 0;
            while ($current_row < $batch_start && ($data = fgetcsv($file)) !== FALSE) {
                $current_row++;
            }
            
            // バッチ処理
            $processed = 0;
            $results = array('created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0);
            
            while ($processed < $batch_size && ($data = fgetcsv($file)) !== FALSE) {
                if (empty(array_filter($data))) {
                    continue; // 空行をスキップ
                }
                
                try {
                    // データをヘッダーとマッピング
                    $row_data = $this->map_csv_data($headers, $data);
                    
                    // 投稿処理
                    $post_result = $this->process_import_row($row_data, 'post', $import_mode, 'skip'); // デフォルトは'post'、CSVから自動判別
                    
                    switch ($post_result) {
                        case 'created':
                            $results['created']++;
                            break;
                        case 'updated':
                            $results['updated']++;
                            break;
                        case 'skipped':
                            $results['skipped']++;
                            break;
                    }
                } catch (Exception $e) {
                    $results['errors']++;
                }
                
                $processed++;
            }
            
            fclose($file);
            
            wp_send_json_success(array(
                'processed' => $processed,
                'results' => $results,
                'has_more' => $processed === $batch_size,
                'next_batch_start' => $batch_start + $processed
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * 第二クリーンアップ処理（完全削除）
     */
    public function handle_csv_cleanup() {
        // nonce確認
        if (!wp_verify_nonce($_POST['nonce'], 'wp_smart_csv_nonce')) {
            wp_send_json_error(array('message' => __('セキュリティチェックに失敗しました。', $this->textdomain)));
        }
        
        try {
            // 第二クリーンアップ：ディレクトリごと完全削除
            $this->cleanup_complete_temp_directory();
            
            wp_send_json_success(array('message' => __('一時ディレクトリを完全にクリーンアップしました。', $this->textdomain)));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * 第一クリーンアップ：すべての一時ファイルを削除
     */
    private function cleanup_all_temp_files() {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/wp-smart-csv-temp';
        
        if (!file_exists($temp_dir)) {
            return;
        }
        
        // すべての一時ファイルを削除
        $files = glob($temp_dir . '/import_*.csv');
        $deleted_count = 0;
        
        foreach ($files as $file) {
            if (file_exists($file)) {
                if (unlink($file)) {
                    $deleted_count++;
                }
            }
        }
        
        // ディレクトリも削除
        if (is_dir($temp_dir) && count(scandir($temp_dir)) == 2) { // . と .. のみ
            rmdir($temp_dir);
        }
        
        // ログ出力（デバッグ用）
        if ($deleted_count > 0) {
            error_log("WP Smart CSV: 第一クリーンアップで{$deleted_count}個の一時ファイルを削除しました");
        }
    }
    
    /**
     * 第二クリーンアップ：処理完了後の完全クリーンアップ
     */
    private function cleanup_complete_temp_directory() {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/wp-smart-csv-temp';
        
        if (!file_exists($temp_dir)) {
            return;
        }
        
        // すべてのファイルを削除
        $files = glob($temp_dir . '/*');
        $deleted_count = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                if (unlink($file)) {
                    $deleted_count++;
                }
            }
        }
        
        // ディレクトリを削除
        if (is_dir($temp_dir)) {
            rmdir($temp_dir);
        }
        
        // ログ出力（デバッグ用）
        if ($deleted_count > 0) {
            error_log("WP Smart CSV: 第二クリーンアップで{$deleted_count}個のファイルを削除し、ディレクトリを削除しました");
        }
    }
    
    /**
     * CSVインポート処理
     */
    public function handle_csv_import() {
        // nonce確認
        if (!wp_verify_nonce($_POST['nonce'], 'wp_smart_csv_nonce')) {
            wp_send_json_error(array('message' => __('セキュリティチェックに失敗しました。', $this->textdomain)));
        }
        
        // 権限確認
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('権限がありません。', $this->textdomain)));
        }
        
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => __('ファイルのアップロードに失敗しました。', $this->textdomain)));
        }
        
        try {
            $post_type = sanitize_text_field($_POST['post_type']);
            $import_mode = sanitize_text_field($_POST['import_mode']);
            $duplicate_action = sanitize_text_field($_POST['duplicate_action']);
            
            $result = $this->import_posts_from_csv($_FILES['csv_file']['tmp_name'], $post_type, $import_mode, $duplicate_action);
            
            wp_send_json_success(array(
                'message' => sprintf(
                    __('インポートが完了しました。作成: %d件、更新: %d件、スキップ: %d件、エラー: %d件', $this->textdomain),
                    $result['created'],
                    $result['updated'],
                    $result['skipped'],
                    $result['errors']
                )
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => __('インポート中にエラーが発生しました: ', $this->textdomain) . $e->getMessage()
            ));
        }
    }
    
    /**
     * 投稿をCSVにエクスポート
     */
    private function export_posts_to_csv($post_type, $post_status, $limit, $offset, $date_from, $date_to, $selected_fields) {
        // クエリ引数作成
        $args = array(
            'post_status' => $post_status,
            'posts_per_page' => $limit > 0 ? $limit : -1,
            'offset' => $offset,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        // 投稿タイプの設定
        if ($post_type !== 'all') {
            $args['post_type'] = $post_type;
        } else {
            // すべての投稿タイプを取得（添付ファイル等は除外）
            $all_post_types = get_post_types(array('public' => true), 'names');
            $exclude_types = array('attachment', 'revision', 'nav_menu_item');
            $args['post_type'] = array_diff($all_post_types, $exclude_types);
        }
        
        // 日付範囲指定
        if ($date_from || $date_to) {
            $date_query = array();
            if ($date_from) {
                $date_query['after'] = $date_from;
            }
            if ($date_to) {
                $date_query['before'] = $date_to;
            }
            $args['date_query'] = array($date_query);
        }
        
        $posts = get_posts($args);
        
        if (empty($posts)) {
            throw new Exception(__('エクスポートするデータがありません。', $this->textdomain));
        }
        
        // 利用可能なフィールドを取得（すべての投稿タイプの場合は基本フィールドのみ）
        if ($post_type === 'all') {
            $available_fields = $this->get_basic_fields_for_all_types();
        } else {
            $available_fields = $this->get_available_fields($post_type);
        }
        
        // ヘッダー作成
        $headers = array();
        $field_map = array();
        
        foreach ($available_fields as $group_key => $group) {
            foreach ($group['fields'] as $field_key => $field_label) {
                if (empty($selected_fields) || in_array($field_key, $selected_fields)) {
                    $headers[] = $field_key;
                    $field_map[$field_key] = $group_key;
                }
            }
        }
        
        // CSVデータ作成
        $csv_data = array();
        $csv_data[] = $headers;
        
        foreach ($posts as $post) {
            $row = array();
            
            foreach ($headers as $field_key) {
                $value = $this->get_field_value($post, $field_key, $field_map[$field_key]);
                $row[] = $value;
            }
            
            $csv_data[] = $row;
        }
        
        // CSVファイル作成
        return $this->create_csv_file($csv_data, $post_type);
    }
    
    /**
     * すべての投稿タイプ用の基本フィールドを取得
     */
    private function get_basic_fields_for_all_types() {
        return array(
            'basic' => array(
                'label' => '基本情報',
                'fields' => array(
                    'ID' => 'ID',
                    'post_type' => '投稿タイプ',
                    'post_title' => 'タイトル',
                    'post_content' => '本文',
                    'post_excerpt' => '抜粋',
                    'post_status' => 'ステータス',
                    'post_date' => '投稿日',
                    'post_modified' => '更新日',
                    'post_author' => '投稿者ID',
                    'menu_order' => '並び順',
                    'post_parent' => '親投稿ID'
                )
            )
        );
    }
    
    /**
     * フィールド値を取得
     */
    private function get_field_value($post, $field_key, $group_key) {
        switch ($group_key) {
            case 'basic':
                return $this->get_basic_field_value($post, $field_key);
            case 'thumbnail':
                return $this->get_thumbnail_field_value($post, $field_key);
            case 'taxonomies':
                return $this->get_taxonomy_field_value($post, $field_key);
            case 'custom_fields':
                return $this->get_custom_field_value($post, $field_key);
            default:
                return '';
        }
    }
    
    /**
     * 基本フィールド値を取得
     */
    private function get_basic_field_value($post, $field_key) {
        switch ($field_key) {
            case 'post_author':
                $author = get_userdata($post->post_author);
                return $author ? $author->user_login : '';
            case 'post_type':
                return $post->post_type;
            default:
                return isset($post->$field_key) ? $post->$field_key : '';
        }
    }
    
    /**
     * アイキャッチ画像フィールド値を取得
     */
    private function get_thumbnail_field_value($post, $field_key) {
        $thumbnail_id = get_post_thumbnail_id($post->ID);
        
        switch ($field_key) {
            case 'featured_image':
                return $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '';
            case 'featured_image_id':
                return $thumbnail_id ? $thumbnail_id : '';
            default:
                return '';
        }
    }
    
    /**
     * タクソノミーフィールド値を取得（slugで出力）
     */
    private function get_taxonomy_field_value($post, $taxonomy) {
        $terms = wp_get_post_terms($post->ID, $taxonomy, array('fields' => 'slugs'));
        return is_array($terms) ? implode('/', $terms) : '';
    }
    
    /**
     * カスタムフィールド値を取得
     */
    private function get_custom_field_value($post, $field_key) {
        $value = get_post_meta($post->ID, $field_key, true);
        
        // 配列の場合はJSON形式で出力
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        
        return $value;
    }
    
    /**
     * CSVファイルを作成
     */
    private function create_csv_file($csv_data, $post_type) {
        // WordPressアップロードディレクトリ内に専用フォルダを作成
        $upload_dir = wp_upload_dir();
        $csv_dir = $upload_dir['basedir'] . '/wp-smart-csv';
        $csv_url = $upload_dir['baseurl'] . '/wp-smart-csv';
        
        // CSVディレクトリが存在しない場合は作成
        if (!file_exists($csv_dir)) {
            wp_mkdir_p($csv_dir);
        }
        
        // 既存ファイルをチェックして5件制限を適用
        $this->cleanup_csv_files($csv_dir);
        
        $filename = sprintf('%s_export_%s.csv', $post_type, date('Y-m-d_H-i-s'));
        $filepath = $csv_dir . '/' . $filename;
        
        $file = fopen($filepath, 'w');
        
        // BOM付きUTF-8で出力
        fwrite($file, "\xEF\xBB\xBF");
        
        foreach ($csv_data as $row) {
            fputcsv($file, $row);
        }
        
        fclose($file);
        
        return array(
            'url' => $csv_url . '/' . $filename,
            'filename' => $filename,
            'path' => $filepath
        );
    }
    
    /**
     * CSVディレクトリのファイル数制限管理
     */
    private function cleanup_csv_files($csv_dir) {
        // CSVファイルのみを対象
        $csv_files = glob($csv_dir . '/*_export_*.csv');
        
        if (count($csv_files) >= 5) {
            // ファイルを更新日時でソート（古い順）
            usort($csv_files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // 5件以上の場合、古いファイルから削除
            $files_to_delete = array_slice($csv_files, 0, count($csv_files) - 4);
            
            foreach ($files_to_delete as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }
    
    /**
     * CSVから投稿をインポート
     */
    private function import_posts_from_csv($csv_file, $post_type, $import_mode, $duplicate_action) {
        $result = array(
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0
        );
        
        // CSVファイル読み込み
        $file = fopen($csv_file, 'r');
        if (!$file) {
            throw new Exception(__('CSVファイルを開けませんでした。', $this->textdomain));
        }
        
        // BOM除去
        $bom = fread($file, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($file);
        }
        
        // ヘッダー行取得
        $headers = fgetcsv($file);
        if (!$headers) {
            fclose($file);
            throw new Exception(__('CSVファイルのヘッダーが読み取れませんでした。', $this->textdomain));
        }
        
        // データ行処理
        $line_number = 1;
        while (($data = fgetcsv($file)) !== FALSE) {
            $line_number++;
            
            try {
                // データをヘッダーとマッピング（柔軟な対応）
                $row_data = $this->map_csv_data($headers, $data);
                
                // 投稿処理
                $post_result = $this->process_import_row($row_data, $post_type, $import_mode, $duplicate_action);
                
                switch ($post_result) {
                    case 'created':
                        $result['created']++;
                        break;
                    case 'updated':
                        $result['updated']++;
                        break;
                    case 'skipped':
                        $result['skipped']++;
                        break;
                    default:
                        $result['errors']++;
                }
                
            } catch (Exception $e) {
                $result['errors']++;
                error_log("CSV Import Error (Line {$line_number}): " . $e->getMessage());
            }
        }
        
        fclose($file);
        
        return $result;
    }
    
    /**
     * CSVデータをヘッダーとマッピング（柔軟な対応）
     */
    private function map_csv_data($headers, $data) {
        $row_data = array();
        
        // ヘッダーの数だけループ
        for ($i = 0; $i < count($headers); $i++) {
            $header = trim($headers[$i]);
            
            // 空のヘッダーはスキップ
            if (empty($header)) {
                continue;
            }
            
            // データが存在する場合は値を設定、存在しない場合は空文字
            $row_data[$header] = isset($data[$i]) ? trim($data[$i]) : '';
        }
        
        return $row_data;
    }
    
    /**
     * インポート行を処理
     */
    private function process_import_row($row_data, $post_type, $import_mode, $duplicate_action) {
        // post_type列から投稿タイプを自動判別（指定されていない場合はデフォルト値を使用）
        if (isset($row_data['post_type']) && !empty($row_data['post_type'])) {
            $post_type = $row_data['post_type'];
        }
        
        // 既存投稿チェック（更新モードの場合のみ）
        $existing_post = null;
        
        if ($import_mode === 'update' && isset($row_data['ID']) && !empty($row_data['ID']) && is_numeric($row_data['ID'])) {
            $existing_post = get_post(intval($row_data['ID']));
            // IDが存在すれば、投稿タイプに関係なく更新対象とする
        }
        // 新規作成モードの場合は、IDが設定されていても無視して常に新規作成
        
        // 投稿データ準備
        $post_data = array(
            'post_type' => $post_type,
            'post_status' => isset($row_data['post_status']) ? $row_data['post_status'] : 'draft',
        );
        
        // post_titleが空の場合は「notitle」を自動設定
        if (empty($row_data['post_title'])) {
            $post_data['post_title'] = 'notitle';
        }
        
        // 基本フィールド設定（存在するフィールドのみ）
        $basic_fields = array('post_title', 'post_content', 'post_excerpt', 'post_name', 'post_parent', 'menu_order', 'post_date', 'post_modified');
        foreach ($basic_fields as $field) {
            if (isset($row_data[$field]) && $row_data[$field] !== '') {
                // データ型に応じた処理
                if (in_array($field, array('post_parent', 'menu_order'))) {
                    // 数値フィールド
                    $post_data[$field] = intval($row_data[$field]);
                } else {
                    // テキストフィールド
                    $post_data[$field] = sanitize_text_field($row_data[$field]);
                }
            }
        }
        
        // 投稿者設定
        if (isset($row_data['post_author']) && !empty($row_data['post_author'])) {
            $user = get_user_by('login', $row_data['post_author']);
            if ($user) {
                $post_data['post_author'] = $user->ID;
            }
        }
        
        // 投稿作成・更新
        if ($existing_post) {
            $post_data['ID'] = $existing_post->ID;
            $post_id = wp_update_post($post_data);
            $action = 'updated';
        } else {
            $post_id = wp_insert_post($post_data);
            $action = 'created';
        }
        
        if (is_wp_error($post_id) || !$post_id) {
            throw new Exception('投稿の作成・更新に失敗しました。');
        }
        
        // アイキャッチ画像設定
        $this->set_featured_image($post_id, $row_data);
        
        // タクソノミー設定
        $this->set_taxonomies($post_id, $row_data, $post_type);
        
        // カスタムフィールド設定
        $this->set_custom_fields($post_id, $row_data);
        
        return $action;
    }
    
    /**
     * アイキャッチ画像を設定
     */
    private function set_featured_image($post_id, $row_data) {
        if (isset($row_data['featured_image_id']) && !empty($row_data['featured_image_id'])) {
            set_post_thumbnail($post_id, intval($row_data['featured_image_id']));
        } elseif (isset($row_data['featured_image']) && !empty($row_data['featured_image'])) {
            // URLから添付ファイルIDを取得
            $attachment_id = attachment_url_to_postid($row_data['featured_image']);
            if ($attachment_id) {
                set_post_thumbnail($post_id, $attachment_id);
            }
        }
    }
    
    /**
     * タクソノミーを設定（存在しないタクソノミーは自動作成）
     */
    private function set_taxonomies($post_id, $row_data, $post_type) {
        // 既存のタクソノミー
        $existing_taxonomies = get_object_taxonomies($post_type);
        
        // 全てのタクソノミーフィールドをチェック
        foreach ($row_data as $field_key => $value) {
            if (empty($value)) {
                continue;
            }
            
            // 基本フィールドはスキップ
            $basic_fields = array('ID', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_date', 'post_modified', 'post_author', 'post_name', 'post_parent', 'menu_order', 'featured_image', 'featured_image_id');
            if (in_array($field_key, $basic_fields)) {
                continue;
            }
            
            // 既存のタクソノミーまたは新しいタクソノミーの可能性
            $is_taxonomy = in_array($field_key, $existing_taxonomies) || $this->looks_like_taxonomy($field_key, $value);
            
            if ($is_taxonomy) {
                // タクソノミーが存在しない場合は作成
                if (!taxonomy_exists($field_key)) {
                    $this->create_taxonomy($field_key, $post_type);
                }
                
                // タームを設定（/区切りで分割、slugとして処理）
                $term_slugs = array_map('trim', explode('/', $value));
                $term_slugs = array_filter($term_slugs); // 空の要素を除去
                
                if (!empty($term_slugs)) {
                    $this->set_terms_by_slug($post_id, $term_slugs, $field_key);
                }
            }
        }
    }
    
    /**
     * slugでタームを設定
     */
    private function set_terms_by_slug($post_id, $term_slugs, $taxonomy) {
        $term_ids = array();
        
        foreach ($term_slugs as $slug) {
            // 既存のタームをslugで検索
            $term = get_term_by('slug', $slug, $taxonomy);
            
            if ($term) {
                // 既存のターム
                $term_ids[] = $term->term_id;
            } else {
                // 新しいタームを作成（名前もslugと同じにする）
                $result = wp_insert_term($slug, $taxonomy, array('slug' => $slug));
                
                if (!is_wp_error($result)) {
                    $term_ids[] = $result['term_id'];
                }
            }
        }
        
        // タームを投稿に設定
        if (!empty($term_ids)) {
            wp_set_post_terms($post_id, $term_ids, $taxonomy);
        }
    }
    
    /**
     * タクソノミーらしいかどうかを判定
     */
    private function looks_like_taxonomy($field_key, $value) {
        // 値に「/」が含まれている場合はタクソノミーの可能性が高い
        if (strpos($value, '/') !== false) {
            return true;
        }
        
        // フィールド名がタクソノミーっぽい場合
        $taxonomy_keywords = array('category', 'tag', 'type', 'genre', 'brand', 'model', 'product');
        foreach ($taxonomy_keywords as $keyword) {
            if (strpos(strtolower($field_key), $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 新しいタクソノミーを作成
     */
    private function create_taxonomy($taxonomy_name, $post_type) {
        $labels = array(
            'name' => ucfirst($taxonomy_name),
            'singular_name' => ucfirst($taxonomy_name),
            'menu_name' => ucfirst($taxonomy_name),
            'all_items' => 'All ' . ucfirst($taxonomy_name),
            'edit_item' => 'Edit ' . ucfirst($taxonomy_name),
            'view_item' => 'View ' . ucfirst($taxonomy_name),
            'update_item' => 'Update ' . ucfirst($taxonomy_name),
            'add_new_item' => 'Add New ' . ucfirst($taxonomy_name),
            'new_item_name' => 'New ' . ucfirst($taxonomy_name) . ' Name',
            'search_items' => 'Search ' . ucfirst($taxonomy_name),
            'popular_items' => 'Popular ' . ucfirst($taxonomy_name),
            'not_found' => 'No ' . strtolower($taxonomy_name) . ' found.',
        );
        
        $args = array(
            'labels' => $labels,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'hierarchical' => true, // デフォルトで階層ありに設定
            'rewrite' => array('slug' => $taxonomy_name),
        );
        
        register_taxonomy($taxonomy_name, $post_type, $args);
        
        // データベースに保存（オプション）
        $existing_custom_taxonomies = get_option('wp_smart_csv_custom_taxonomies', array());
        if (!in_array($taxonomy_name, $existing_custom_taxonomies)) {
            $existing_custom_taxonomies[] = $taxonomy_name;
            update_option('wp_smart_csv_custom_taxonomies', $existing_custom_taxonomies);
        }
    }
    
    /**
     * カスタムフィールドを設定
     */
    private function set_custom_fields($post_id, $row_data) {
        $basic_fields = array('ID', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_date', 'post_modified', 'post_author', 'post_name', 'post_parent', 'menu_order', 'featured_image', 'featured_image_id');
        
        foreach ($row_data as $field_key => $value) {
            // 基本フィールドとタクソノミーをスキップ
            if (in_array($field_key, $basic_fields) || taxonomy_exists($field_key) || $this->looks_like_taxonomy($field_key, $value)) {
                continue;
            }
            
            if ($value !== '') {
                // JSON形式の場合はデコード
                $decoded_value = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $value = $decoded_value;
                }
                
                update_post_meta($post_id, $field_key, $value);
            }
        }
    }
    
    /**
     * プラグイン有効化
     */
    public function activate() {
        // 権限チェック
        if (!current_user_can('activate_plugins')) {
            return;
        }
        
        // WordPressアップロードディレクトリ内にCSV専用フォルダを作成
        $upload_dir = wp_upload_dir();
        $csv_dir = $upload_dir['basedir'] . '/wp-smart-csv';
        
        if (!file_exists($csv_dir)) {
            wp_mkdir_p($csv_dir);
        }
        
        // 初期設定の保存
        add_option('wp_smart_csv_version', WP_SMART_CSV_VERSION);
    }
    
    /**
     * プラグイン無効化
     */
    public function deactivate() {
        // CSVディレクトリのクリーンアップ
        $upload_dir = wp_upload_dir();
        $csv_dir = $upload_dir['basedir'] . '/wp-smart-csv';
        
        if (file_exists($csv_dir)) {
            // CSVファイルを削除
            $csv_files = glob($csv_dir . '/*_export_*.csv');
            foreach ($csv_files as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
            
            // ディレクトリを削除（空の場合）
            if (is_dir($csv_dir) && count(scandir($csv_dir)) == 2) { // . と .. のみ
                rmdir($csv_dir);
            }
        }
        
        // 一時ファイルディレクトリのクリーンアップ
        $temp_dir = $upload_dir['basedir'] . '/wp-smart-csv-temp';
        
        if (file_exists($temp_dir)) {
            // 一時ファイルを削除
            $temp_files = glob($temp_dir . '/import_*.csv');
            foreach ($temp_files as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
            
            // ディレクトリを削除（空の場合）
            if (is_dir($temp_dir) && count(scandir($temp_dir)) == 2) { // . と .. のみ
                rmdir($temp_dir);
            }
        }
        
        // プラグインで作成したカスタムタクソノミーの記録を削除
        delete_option('wp_smart_csv_custom_taxonomies');
    }
}

// プラグイン初期化
function wp_smart_csv_init() {
    return WpSmartCsvImportExport::get_instance();
}

// WordPress読み込み後にプラグイン開始
add_action('plugins_loaded', 'wp_smart_csv_init');
