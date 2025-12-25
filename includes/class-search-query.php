<?php
class AdvancedSearch_Search_Query {
    public function search($params = []) {
        global $wpdb;

        $defaults = [
            'q' => '',
            'cat' => 0,
            'tags' => [],
            'page' => 1,
            'posts_per_page' => 10
        ];

        $params = wp_parse_args($params, $defaults);

        // 构建查询
        $query_args = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $params['posts_per_page'],
            'paged' => $params['page'],
            'ignore_sticky_posts' => true,
            'suppress_filters' => false
        ];

        // 关键词搜索
        if (!empty($params['q'])) {
            $query_args['s'] = $params['q'];
        }

        // 分类筛选
        if (!empty($params['cat'])) {
            $query_args['cat'] = $params['cat'];
        }

        // 标签筛选
        if (!empty($params['tags'])) {
            $query_args['tag__in'] = $params['tags'];
        }

        // 执行查询
        $query = new WP_Query($query_args);

        // 格式化结果
        $posts = [];
        foreach ($query->posts as $post) {
            $posts[] = $this->format_post($post);
        }

        return [
            'posts' => $posts,
            'total' => (int)$query->found_posts,
            'total_pages' => (int)$query->max_num_pages,
            'current_page' => (int)$params['page']
        ];
    }

    private function format_post($post) {
        $categories = wp_get_post_categories($post->ID, ['fields' => 'names']);
        $tags = wp_get_post_tags($post->ID, ['fields' => 'names']);

        return [
            'id' => $post->ID,
            'title' => get_the_title($post),
            'excerpt' => wp_trim_words(get_the_excerpt($post), 30),
            'content' => wp_trim_words($post->post_content, 50),
            'permalink' => get_permalink($post),
            'date' => get_the_date('', $post),
            'author' => get_the_author_meta('display_name', $post->post_author),
            'categories' => $categories,
            'tags' => $tags,
            'thumbnail' => get_the_post_thumbnail_url($post, 'medium'),
            'comment_count' => (int)$post->comment_count
        ];
    }

    public function get_search_suggestions($keyword, $limit = 5) {
        global $wpdb;

        if (empty($keyword)) {
            return [];
        }

        $like_keyword = '%' . $wpdb->esc_like($keyword) . '%';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT post_title, ID 
             FROM {$wpdb->posts} 
             WHERE post_status = 'publish' 
             AND post_type = 'post' 
             AND post_title LIKE %s 
             ORDER BY post_date DESC 
             LIMIT %d",
            $like_keyword,
            $limit
        ));

        $suggestions = [];
        foreach ($results as $result) {
            $suggestions[] = [
                'title' => $result->post_title,
                'url' => get_permalink($result->ID)
            ];
        }

        return $suggestions;
    }
}