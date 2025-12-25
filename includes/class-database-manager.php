<?php
class AdvancedSearch_Database_Manager {
    public function backup_database() {
        global $wpdb;

        $backup_file = ASB_DB_BACKUP_DIR . 'backup-' . date('Y-m-d-H-i-s') . '.sql';
        $handle = fopen($backup_file, 'w');

        if (!$handle) {
            error_log('Advanced Search: Cannot create backup file');
            return false;
        }

        // 获取所有表
        $tables = $wpdb->get_col('SHOW TABLES');

        foreach ($tables as $table) {
            // 创建表结构
            $create_table = $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N);
            fwrite($handle, "DROP TABLE IF EXISTS `$table`;\n");
            fwrite($handle, $create_table[1] . ";\n\n");

            // 获取表数据
            $rows = $wpdb->get_results("SELECT * FROM `$table`", ARRAY_A);

            if (count($rows) > 0) {
                $columns = array_keys($rows[0]);
                $columns_str = '`' . implode('`,`', $columns) . '`';

                foreach ($rows as $row) {
                    $values = array_map([$wpdb, '_escape'], $row);
                    $values_str = "'" . implode("','", $values) . "'";
                    fwrite($handle, "INSERT INTO `$table` ($columns_str) VALUES ($values_str);\n");
                }
            }

            fwrite($handle, "\n");
        }

        fclose($handle);

        // 更新最后备份时间
        update_option('asb_last_backup', current_time('mysql'));

        // 清理旧备份（保留最近7天）
        $this->cleanup_old_backups();

        return $backup_file;
    }

    private function cleanup_old_backups() {
        $backups = glob(ASB_DB_BACKUP_DIR . 'backup-*.sql');

        if (count($backups) > 7) {
            usort($backups, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });

            $to_delete = array_slice($backups, 0, count($backups) - 7);

            foreach ($to_delete as $backup) {
                @unlink($backup);
            }
        }
    }

    public function get_post_types() {
        return get_post_types(['public' => true], 'names');
    }

    public function get_taxonomy_terms($taxonomy) {
        return get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ]);
    }
}