<?php
/**
 * Plugin Name: Advanced Search Block
 * Plugin URI: http://www.ngghost.com:84/
 * Description: Gutenberg block for advanced search with AJAX functionality
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: advanced-search
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 定义插件常量
define('ASB_VERSION', '1.0.0');
define('ASB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ASB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ASB_DB_BACKUP_DIR', ABSPATH . 'advanced-search-backups/');

// 包含必要文件
require_once ASB_PLUGIN_DIR . 'includes/class-database-manager.php';
require_once ASB_PLUGIN_DIR . 'includes/class-ajax-handler.php';
require_once ASB_PLUGIN_DIR . 'includes/class-search-query.php';

// 初始化插件
class AdvancedSearchBlock {
    private static $instance = null;
    private $db_manager;
    private $ajax_handler;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init();
    }

    private function init() {
        // 初始化组件
        $this->db_manager = new AdvancedSearch_Database_Manager();
        $this->ajax_handler = new AdvancedSearch_Ajax_Handler();

        // 注册钩子 - 确保在正确的时间注册区块
        add_action('init', [$this, 'register_blocks'], 20); // 提高优先级
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_init', [$this, 'schedule_backup']);
        add_action('asb_daily_backup', [$this, 'perform_backup']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // 短码支持
        add_shortcode('advanced_search', [$this, 'render_shortcode']);

        // 添加区块类别
        add_filter('block_categories_all', [$this, 'add_block_category'], 10, 2);
    }

//    private function init() {
//        // 初始化组件
//        $this->db_manager = new AdvancedSearch_Database_Manager();
//        $this->ajax_handler = new AdvancedSearch_Ajax_Handler();
//
//        // 注册钩子
//        add_action('init', [$this, 'register_blocks']);
//        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
//        add_action('admin_init', [$this, 'schedule_backup']);
//        add_action('asb_daily_backup', [$this, 'perform_backup']);
//        add_action('rest_api_init', [$this, 'register_rest_routes']);
//
//        // 短码支持
//        add_shortcode('advanced_search', [$this, 'render_shortcode']);
//    }

    // 添加区块类别
    public function add_block_category($categories, $post) {
        return array_merge(
            $categories,
            [
                [
                    'slug' => 'advanced-search',
                    'title' => __('Advanced Search', 'advanced-search'),
                    'icon' => 'search',
                ],
            ]
        );
    }

    public function register_blocks() {
        if (!function_exists('register_block_type')) {
            error_log('Advanced Search: Gutenberg not available');
            return;
        }

        // 首先注册脚本和样式
        $this->register_assets();

        // 注册区块类型
        $result = register_block_type(
            ASB_PLUGIN_DIR . 'build/block.json',
            [
                'editor_script' => 'advanced-search-block-editor',
                'editor_style'  => 'advanced-search-block-editor-style',
                'style'         => 'advanced-search-block-style',
                'render_callback' => [$this, 'render_block'],
            ]
        );

        if (!$result) {
            error_log('Advanced Search: Failed to register block type');
        } else {
            error_log('Advanced Search: Block registered successfully');
        }
    }

    private function register_assets() {
        // 注册编辑器脚本
        wp_register_script(
            'advanced-search-block-editor',
            ASB_PLUGIN_URL . 'assets/block.js',
            [
                'wp-blocks',
                'wp-i18n',
                'wp-element',
                'wp-editor',
                'wp-components',
                'wp-data',
                'wp-api-fetch',
                'wp-block-editor'
            ],
            ASB_VERSION,
            true
        );

        // 注册编辑器样式
        wp_register_style(
            'advanced-search-block-editor-style',
            ASB_PLUGIN_URL . 'assets/editor-style.css',
            ['wp-edit-blocks'],
            ASB_VERSION
        );

        // 注册前端样式
        wp_register_style(
            'advanced-search-block-style',
            ASB_PLUGIN_URL . 'assets/style.css',
            [],
            ASB_VERSION
        );
    }

    public function enqueue_editor_assets() {
        // 仅在区块编辑器中加载
        if (function_exists('get_current_screen') && get_current_screen()->is_block_editor()) {
            wp_enqueue_script('advanced-search-block-editor');
            wp_enqueue_style('advanced-search-block-editor-style');

            // 添加调试信息
            wp_add_inline_script('advanced-search-block-editor', '
                console.log("Advanced Search Block editor script loaded");
            ');
        }
    }

    public function enqueue_frontend_assets() {
        // 检查是否使用了区块
        global $post;
        $has_block = false;

        if ($post && has_blocks($post->post_content)) {
            $blocks = parse_blocks($post->post_content);
            foreach ($blocks as $block) {
                if ($block['blockName'] === 'advanced-search/block') {
                    $has_block = true;
                    break;
                }
            }
        }

        // 或者检查URL参数
        if ($has_block || isset($_GET['asb_search']) || (is_search() && isset($_GET['s']))) {
            // 确保jQuery已加载
            wp_enqueue_script('jquery');

            // 加载前端脚本
            wp_enqueue_script(
                'advanced-search-frontend',
                ASB_PLUGIN_URL . 'assets/frontend.js',
                ['jquery'],
                ASB_VERSION,
                true
            );

            // 加载前端样式
            wp_enqueue_style(
                'advanced-search-block-style',
                ASB_PLUGIN_URL . 'assets/style.css',
                [],
                ASB_VERSION
            );

            // 本地化脚本
            wp_localize_script('advanced-search-frontend', 'asb_frontend', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('asb_frontend_nonce'),
                'current_url' => home_url($_SERVER['REQUEST_URI']),
                'rest_url' => rest_url('wp/v2/'),
                'strings' => [
                    'search' => __('Search', 'advanced-search'),
                    'loading' => __('Loading...', 'advanced-search'),
                    'no_results' => __('No results found', 'advanced-search'),
                    'all_categories' => __('All Categories', 'advanced-search'),
                    'all_tags' => __('All Tags', 'advanced-search'),
                    'by' => __('by', 'advanced-search'),
                    'in' => __('in', 'advanced-search'),
                    'previous' => __('Previous', 'advanced-search'),
                    'next' => __('Next', 'advanced-search'),
                    'results_found' => __('results found', 'advanced-search'),
                    'error' => __('An error occurred', 'advanced-search'),
                    'load_error' => __('Failed to load posts', 'advanced-search')
                ]
            ]);

            // 添加调试信息
            wp_add_inline_script('advanced-search-frontend', '
                console.log("Advanced Search Frontend script loaded");
                console.log("jQuery version:", jQuery.fn.jquery);
            ');
        }
    }

//    public function register_blocks() {
//        if (!function_exists('register_block_type')) {
//            return;
//        }
//
//        // 注册区块
//        register_block_type('advanced-search/block', [
//            'editor_script' => 'advanced-search-block-editor',
//            'editor_style' => 'advanced-search-block-editor',
//            'style' => 'advanced-search-block',
//            'render_callback' => [$this, 'render_block'],
//            'attributes' => [
//                'blockId' => [
//                    'type' => 'string',
//                    'default' => ''
//                ],
//                'postsPerPage' => [
//                    'type' => 'number',
//                    'default' => 10
//                ],
//                'showCategory' => [
//                    'type' => 'boolean',
//                    'default' => true
//                ],
//                'showTags' => [
//                    'type' => 'boolean',
//                    'default' => false
//                ],
//                'showPagination' => [
//                    'type' => 'boolean',
//                    'default' => false
//                ]
//            ]
//        ]);
//
//        // 本地化脚本
//        wp_localize_script('advanced-search-block-editor', 'asb_ajax', [
//            'ajax_url' => admin_url('admin-ajax.php'),
//            'nonce' => wp_create_nonce('asb_ajax_nonce'),
//            'rest_url' => rest_url('advanced-search/v1/')
//        ]);
//    }

    public function enqueue_assets() {
        // 编辑器脚本
        if (is_admin()) {
            wp_enqueue_script(
                'advanced-search-block-editor',
                ASB_PLUGIN_URL . 'assets/block.js',
                ['wp-blocks', 'wp-i18n', 'wp-element', 'wp-editor', 'wp-components', 'wp-data', 'wp-api-fetch'],
                ASB_VERSION,
                true
            );

            wp_enqueue_style(
                'advanced-search-block-editor',
                ASB_PLUGIN_URL . 'assets/editor-style.css',
                ['wp-edit-blocks'],
                ASB_VERSION
            );
        }

        // 前端脚本
        if (has_block('advanced-search/block') || is_search() || get_query_var('asb_search')) {
            wp_enqueue_script(
                'advanced-search-frontend',
                ASB_PLUGIN_URL . 'assets/frontend.js',
                ['jquery'],
                ASB_VERSION,
                true
            );

            wp_enqueue_style(
                'advanced-search-block',
                ASB_PLUGIN_URL . 'assets/style.css',
                [],
                ASB_VERSION
            );

            wp_localize_script('advanced-search-frontend', 'asb_frontend', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('asb_frontend_nonce'),
                'current_url' => home_url($_SERVER['REQUEST_URI']),
                'rest_url' => rest_url('wp/v2/'),
                'strings' => [
                    'search' => __('Search', 'advanced-search'),
                    'loading' => __('Loading...', 'advanced-search'),
                    'no_results' => __('No results found', 'advanced-search'),
                    'all_categories' => __('All Categories', 'advanced-search'),
                    'all_tags' => __('All Tags', 'advanced-search')
                ]
            ]);
        }
    }


    public function render_block($attributes, $content = '') {
        if (is_admin()) {
            return '';
        }

        // 获取当前页面ID
        global $post;
        $page_id = $post ? $post->ID : 0;

        // 获取URL参数
        $search_params = $this->get_search_params_from_url();

        // 确保page_id在参数中
        if ($page_id && !isset($search_params['page_id'])) {
            $search_params['page_id'] = $page_id;
        }

        // 获取当前页码
        $current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;

        // 获取初始文章数据
        $initial_data = $this->get_initial_posts_data($attributes, $current_page);

        ob_start();
        ?>
        <div id="advanced-search-block-<?php echo esc_attr($attributes['blockId'] ?: uniqid()); ?>"
             class="advanced-search-block"
             data-attributes="<?php echo esc_attr(wp_json_encode($attributes)); ?>"
             data-params="<?php echo esc_attr(wp_json_encode($search_params)); ?>"
             data-initial-data="<?php echo esc_attr(wp_json_encode($initial_data)); ?>">

            <form class="asb-search-form" method="GET" action="<?php echo esc_url(get_permalink($page_id)); ?>">
                <!-- 隐藏字段：page_id -->
                <input type="hidden" name="page_id" value="<?php echo esc_attr($page_id); ?>">
                <input type="hidden" name="asb_search" value="1">

                <!-- 关键词搜索 -->
                <div class="asb-form-group">
                    <label for="asb-keyword"><?php _e('Keyword', 'advanced-search'); ?></label>
                    <input type="text"
                           id="asb-keyword"
                           name="q"
                           class="asb-input"
                           value="<?php echo esc_attr($search_params['q'] ?? ''); ?>"
                           placeholder="<?php esc_attr_e('Enter keywords...', 'advanced-search'); ?>"
                           autocomplete="off">
                </div>

                <!-- 分类筛选 -->
                <?php if ($attributes['showCategory'] ?? true) : ?>
                    <div class="asb-form-group">
                        <label for="asb-category"><?php _e('Category', 'advanced-search'); ?></label>
                        <select id="asb-category" name="cat" class="asb-select">
                            <option value=""><?php _e('All Categories', 'advanced-search'); ?></option>
                            <?php
                            $categories = get_categories(['hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC']);
                            foreach ($categories as $category) {
                                $selected = (isset($search_params['cat']) && $search_params['cat'] == $category->term_id) ? 'selected' : '';
                                echo '<option value="' . esc_attr($category->term_id) . '" ' . $selected . '>'
                                    . esc_html($category->name) . ' (' . $category->count . ')</option>';
                            }
                            ?>
                        </select>
                    </div>
                <?php endif; ?>

                <!-- 标签筛选 - 改为复选框组 -->
                <?php if ($attributes['showTags'] ?? false) : ?>
                    <div class="asb-form-group asb-tags-checkbox-group">
                        <label><?php _e('Tags', 'advanced-search'); ?></label>
                        <div class="asb-checkbox-list" id="asb-tags-checkbox">
                            <?php
                            $tags = get_tags([
                                'hide_empty' => false,
                                'orderby' => 'count',
                                'order' => 'DESC',
                                'number' => 20 // 限制显示数量
                            ]);
                            $selected_tags = $search_params['tags'] ?? [];

                            foreach ($tags as $tag) {
                                $checked = in_array($tag->term_id, (array)$selected_tags) ? 'checked' : '';
                                $checkbox_id = 'asb-tag-' . $tag->term_id;
                                ?>
                                <div class="asb-checkbox-item">
                                    <input type="checkbox"
                                           id="<?php echo esc_attr($checkbox_id); ?>"
                                           name="tags[]"
                                           value="<?php echo esc_attr($tag->term_id); ?>"
                                        <?php echo $checked; ?>
                                           class="asb-checkbox">
                                    <label for="<?php echo esc_attr($checkbox_id); ?>" class="asb-checkbox-label">
                                        <?php echo esc_html($tag->name); ?>
                                        <span class="asb-tag-count">(<?php echo $tag->count; ?>)</span>
                                    </label>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                        <small style="display: block; margin-top: 5px; color: #666;">
                            <?php _e('Select multiple tags', 'advanced-search'); ?>
                        </small>
                    </div>
                <?php endif; ?>

                <!-- 隐藏页码字段 -->
                <input type="hidden" name="page" value="<?php echo esc_attr($current_page); ?>">

                <!-- 操作按钮 -->
                <div class="asb-form-actions">
                    <button type="submit" class="asb-button asb-button-primary">
                        <?php _e('Search', 'advanced-search'); ?>
                    </button>
                    <button type="button" class="asb-button asb-button-secondary asb-reset-button">
                        <?php _e('Reset', 'advanced-search'); ?>
                    </button>
                </div>
            </form>

            <!-- 搜索结果 -->
            <div class="asb-results-container">
                <div class="asb-results-header">
                    <h3><?php _e('Search Results', 'advanced-search'); ?></h3>
                    <div class="asb-results-count">
                        <?php
                        if ($initial_data['total'] > 0) {
                            echo esc_html($initial_data['total']) . ' ' . __('results found', 'advanced-search');
                        }
                        ?>
                    </div>
                </div>
                <div class="asb-results-list">
                    <?php
                    // 初始加载时显示文章
                    if (!empty($initial_data['posts'])) {
                        echo '<div class="asb-results-grid">';
                        foreach ($initial_data['posts'] as $post) {
                            echo $this->render_post_item($post);
                        }
                        echo '</div>';
                    }
                    ?>
                </div>

                <!-- 初始分页 -->
                <?php if ($initial_data['total_pages'] > 1 && ($attributes['showPagination'] ?? false)) : ?>
                    <div class="asb-pagination">
                        <?php echo $this->render_pagination($current_page, $initial_data['total_pages'], $page_id, $search_params); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 加载指示器 -->
            <div class="asb-loading" style="display: none;">
                <div class="asb-spinner"></div>
                <span><?php _e('Loading...', 'advanced-search'); ?></span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }


    private function get_initial_posts_data($attributes, $current_page = 1) {
        $posts_per_page = $attributes['postsPerPage'] ?? 10;

        $args = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $posts_per_page,
            'paged' => $current_page,
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        $query = new WP_Query($args);
        $posts = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                global $post;

                $posts[] = [
                    'id' => $post->ID,
                    'title' => get_the_title(),
                    'excerpt' => wp_trim_words(get_the_excerpt(), 30),
                    'permalink' => get_permalink(),
                    'date' => get_the_date(),
                    'author' => get_the_author(),
                    'categories' => wp_get_post_categories($post->ID, ['fields' => 'names']),
                    'thumbnail' => get_the_post_thumbnail_url($post, 'medium')
                ];
            }
            wp_reset_postdata();
        }

        return [
            'posts' => $posts,
            'total' => $query->found_posts,
            'total_pages' => $query->max_num_pages,
            'current_page' => $current_page
        ];
    }


    private function render_pagination($current_page, $total_pages, $page_id, $params = []) {
        if ($total_pages <= 1) {
            return '';
        }

        $output = '<div class="asb-pagination-links">';

        // 上一页
        if ($current_page > 1) {
            $prev_params = array_merge($params, ['page' => $current_page - 1]);
            $prev_url = add_query_arg($prev_params, get_permalink($page_id));
            $output .= '<a href="' . esc_url($prev_url) . '" class="asb-page-prev">« ' . __('Previous', 'advanced-search') . '</a>';
        }

        // 页码
        for ($i = 1; $i <= $total_pages; $i++) {
            if ($i == 1 || $i == $total_pages || ($i >= $current_page - 2 && $i <= $current_page + 2)) {
                $active = ($i == $current_page) ? ' active' : '';
                $page_params = array_merge($params, ['page' => $i]);
                $page_url = add_query_arg($page_params, get_permalink($page_id));
                $output .= '<a href="' . esc_url($page_url) . '" class="asb-page-number' . $active . '">' . $i . '</a>';
            } elseif ($i == $current_page - 3 || $i == $current_page + 3) {
                $output .= '<span class="asb-page-dots">...</span>';
            }
        }

        // 下一页
        if ($current_page < $total_pages) {
            $next_params = array_merge($params, ['page' => $current_page + 1]);
            $next_url = add_query_arg($next_params, get_permalink($page_id));
            $output .= '<a href="' . esc_url($next_url) . '" class="asb-page-next">' . __('Next', 'advanced-search') . ' »</a>';
        }

        $output .= '</div>';
        return $output;
    }


    private function get_initial_posts($posts_per_page = 10) {
        $args = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $posts_per_page,
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        $query = new WP_Query($args);
        $posts = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                global $post;

                $posts[] = [
                    'id' => $post->ID,
                    'title' => get_the_title(),
                    'excerpt' => wp_trim_words(get_the_excerpt(), 30),
                    'permalink' => get_permalink(),
                    'date' => get_the_date(),
                    'author' => get_the_author(),
                    'thumbnail' => get_the_post_thumbnail_url($post, 'medium')
                ];
            }
            wp_reset_postdata();
        }

        return $posts;
    }

    private function render_post_item($post) {
        ob_start();
        ?>
        <article class="asb-post-item">
            <div class="asb-post-content">
                <h3 class="asb-post-title">
                    <a href="<?php echo esc_url($post['permalink']); ?>">
                        <?php echo esc_html($post['title']); ?>
                    </a>
                </h3>

                <?php if (!empty($post['thumbnail'])) : ?>
                    <div class="asb-post-thumbnail">
                        <img src="<?php echo esc_url($post['thumbnail']); ?>" alt="<?php echo esc_attr($post['title']); ?>">
                    </div>
                <?php endif; ?>

                <div class="asb-post-excerpt">
                    <?php echo esc_html($post['excerpt']); ?>
                </div>

                <div class="asb-post-meta">
                    <span class="asb-post-date"><?php echo esc_html($post['date']); ?></span>
                    <span class="asb-post-author"><?php _e('by', 'advanced-search'); ?> <?php echo esc_html($post['author']); ?></span>
                </div>
            </div>
        </article>
        <?php
        return ob_get_clean();
    }


    private function get_search_params_from_url() {
        $params = [];

        if (isset($_GET['asb_search'])) {
            if (!empty($_GET['q'])) {
                $params['q'] = sanitize_text_field($_GET['q']);
            }
            if (!empty($_GET['cat'])) {
                $params['cat'] = intval($_GET['cat']);
            }
            if (!empty($_GET['tags'])) {
                $params['tags'] = array_map('intval', (array)$_GET['tags']);
            }
            if (!empty($_GET['page'])) {
                $params['page'] = intval($_GET['page']);
            }
        }

        return $params;
    }

    public function perform_search($params = []) {
        $search_query = new AdvancedSearch_Search_Query();
        return $search_query->search($params);
    }

    public function schedule_backup() {
        if (!wp_next_scheduled('asb_daily_backup')) {
            wp_schedule_event(time(), 'daily', 'asb_daily_backup');
        }
    }

    public function perform_backup() {
        $this->db_manager->backup_database();
    }

    public function register_rest_routes() {
        register_rest_route('advanced-search/v1', '/search', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_rest_search'],
            'permission_callback' => '__return_true',
            'args' => [
                'q' => [
                    'validate_callback' => function($param) {
                        return is_string($param);
                    }
                ],
                'cat' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ],
                'tags' => [
                    'validate_callback' => function($param) {
                        if (is_string($param)) {
                            $tags = explode(',', $param);
                            foreach ($tags as $tag) {
                                if (!is_numeric($tag)) {
                                    return false;
                                }
                            }
                        }
                        return true;
                    }
                ],
                'page' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);
    }

    public function handle_rest_search($request) {
        $params = $request->get_params();
        $results = $this->perform_search($params);

        return rest_ensure_response([
            'success' => true,
            'data' => $results['posts'],
            'total' => $results['total'],
            'total_pages' => $results['total_pages'],
            'current_page' => $results['current_page']
        ]);
    }

    public function render_shortcode($atts) {
        $atts = shortcode_atts([
            'posts_per_page' => 10,
            'show_category' => true,
            'show_tags' => false,
            'show_pagination' => false
        ], $atts, 'advanced_search');

        $attributes = [
            'postsPerPage' => intval($atts['posts_per_page']),
            'showCategory' => filter_var($atts['show_category'], FILTER_VALIDATE_BOOLEAN),
            'showTags' => filter_var($atts['show_tags'], FILTER_VALIDATE_BOOLEAN),
            'showPagination' => filter_var($atts['show_pagination'], FILTER_VALIDATE_BOOLEAN),
            'blockId' => 'shortcode-' . uniqid()
        ];

        return $this->render_block($attributes);
    }
}

// 初始化插件
//AdvancedSearchBlock::get_instance();

// 初始化插件
function advanced_search_init() {
    AdvancedSearchBlock::get_instance();
}
add_action('plugins_loaded', 'advanced_search_init');

// 添加激活时的设置
register_activation_hook(__FILE__, function() {
    // 创建备份目录
    if (!file_exists(ASB_DB_BACKUP_DIR)) {
        wp_mkdir_p(ASB_DB_BACKUP_DIR);
    }

    // 设置默认选项
    add_option('asb_version', ASB_VERSION);
    add_option('asb_last_backup', '');

    // 刷新重写规则
    flush_rewrite_rules();
});

//// 激活钩子
//register_activation_hook(__FILE__, function() {
//    // 创建备份目录
//    if (!file_exists(ASB_DB_BACKUP_DIR)) {
//        wp_mkdir_p(ASB_DB_BACKUP_DIR);
//    }
//
//    // 设置默认选项
//    add_option('asb_version', ASB_VERSION);
//    add_option('asb_last_backup', '');
//});

// 停用钩子
register_deactivation_hook(__FILE__, function() {
    // 清除定时任务
    wp_clear_scheduled_hook('asb_daily_backup');
});