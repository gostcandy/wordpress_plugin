<?php
class AdvancedSearch_Ajax_Handler {
    public function __construct() {
        // 前端AJAX
        add_action('wp_ajax_asb_search', [$this, 'handle_search']);
        add_action('wp_ajax_nopriv_asb_search', [$this, 'handle_search']);

        // 后台AJAX
        add_action('wp_ajax_asb_get_categories', [$this, 'get_categories']);
        add_action('wp_ajax_asb_get_tags', [$this, 'get_tags']);
        add_action('wp_ajax_asb_get_initial_data', [$this, 'get_initial_data']);
    }

    public function handle_search() {
        // 验证nonce
        if (!check_ajax_referer('asb_frontend_nonce', 'nonce', false)) {
            wp_die('Security check failed', 403);
        }

        $search_params = [
            'q' => !empty($_POST['q']) ? sanitize_text_field($_POST['q']) : '',
            'cat' => !empty($_POST['cat']) ? intval($_POST['cat']) : 0,
            'tags' => !empty($_POST['tags']) ? array_map('intval', (array)$_POST['tags']) : [],
            'page' => !empty($_POST['page']) ? intval($_POST['page']) : 1,
            'posts_per_page' => !empty($_POST['posts_per_page']) ? intval($_POST['posts_per_page']) : 10
        ];

        $search_query = new AdvancedSearch_Search_Query();
        $results = $search_query->search($search_params);

        wp_send_json([
            'success' => true,
            'data' => $results['posts'],
            'total' => $results['total'],
            'total_pages' => $results['total_pages'],
            'current_page' => $results['current_page']
        ]);
    }

    public function get_categories() {
        $categories = get_categories([
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ]);

        $formatted = [];
        foreach ($categories as $category) {
            $formatted[] = [
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'count' => $category->count
            ];
        }

        wp_send_json([
            'success' => true,
            'data' => $formatted
        ]);
    }

    public function get_tags() {
        $tags = get_tags([
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ]);

        $formatted = [];
        foreach ($tags as $tag) {
            $formatted[] = [
                'id' => $tag->term_id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'count' => $tag->count
            ];
        }

        wp_send_json([
            'success' => true,
            'data' => $formatted
        ]);
    }

    public function get_initial_data() {
        $params = [];

        if (!empty($_GET['q'])) {
            $params['q'] = sanitize_text_field($_GET['q']);
        }
        if (!empty($_GET['cat'])) {
            $params['cat'] = intval($_GET['cat']);
        }
        if (!empty($_GET['tags'])) {
            $params['tags'] = array_map('intval', explode(',', $_GET['tags']));
        }
        if (!empty($_GET['page'])) {
            $params['page'] = intval($_GET['page']);
        }

        $search_query = new AdvancedSearch_Search_Query();
        $results = $search_query->search($params);

        wp_send_json([
            'success' => true,
            'data' => $results['posts'],
            'total' => $results['total'],
            'params' => $params
        ]);
    }
}