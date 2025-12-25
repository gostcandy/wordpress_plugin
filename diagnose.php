<?php
/**
 * Advanced Search Block 诊断脚本
 * 访问：https://yourdomain.com/wp-content/plugins/advanced-search-plugin/diagnose.php
 */

require_once('../../../wp-load.php');

echo '<h1>Advanced Search Block 诊断</h1>';
echo '<pre>';

// 检查WordPress版本
echo "WordPress版本: " . get_bloginfo('version') . "\n";

// 检查Gutenberg支持
if (!function_exists('register_block_type')) {
    echo "❌ Gutenberg不可用\n";
} else {
    echo "✅ Gutenberg可用\n";
}

// 检查区块是否注册
$block_type = WP_Block_Type_Registry::get_instance()->get_all_registered();
if (isset($block_type['advanced-search/block'])) {
    echo "✅ 区块已注册\n";
} else {
    echo "❌ 区块未注册\n";
}

// 检查脚本是否注册
global $wp_scripts;
echo "\n已注册的脚本:\n";
foreach ($wp_scripts->registered as $handle => $script) {
    if (strpos($handle, 'advanced-search') !== false) {
        echo "✅ {$handle}: " . $script->src . "\n";
    }
}

// 检查插件是否激活
if (is_plugin_active('advanced-search-plugin/advanced-search-plugin.php')) {
    echo "✅ 插件已激活\n";
} else {
    echo "❌ 插件未激活\n";
}

// 检查目录权限
echo "\n目录权限检查:\n";
$plugin_dir = plugin_dir_path(__FILE__);
echo "插件目录: " . $plugin_dir . "\n";
echo "可读: " . (is_readable($plugin_dir) ? '✅' : '❌') . "\n";
echo "可写: " . (is_writable($plugin_dir) ? '✅' : '❌') . "\n";

// 检查文件存在
echo "\n文件存在检查:\n";
$files = [
    'advanced-search-plugin.php' => '主插件文件',
    'assets/block.js' => '区块脚本',
    'assets/frontend.js' => '前端脚本',
    'assets/style.css' => '样式文件',
    'build/block.json' => '区块定义'
];

foreach ($files as $file => $desc) {
    $path = $plugin_dir . $file;
    if (file_exists($path)) {
        echo "✅ {$desc}: 存在\n";
    } else {
        echo "❌ {$desc}: 不存在\n";
    }
}

echo '</pre>';

// 测试区块注册
echo '<h2>测试区块注册</h2>';
add_action('admin_footer', function() {
    ?>
    <script>
        console.log('测试区块注册...');
        console.log('wp.blocks:', typeof wp.blocks);
        console.log('wp.blocks.getBlockTypes():', wp.blocks.getBlockTypes());

        // 查找我们的区块
        var blocks = wp.blocks.getBlockTypes();
        var found = blocks.find(function(block) {
            return block.name === 'advanced-search/block';
        });

        if (found) {
            console.log('✅ 在JavaScript中找到区块');
            document.write('<div style="color:green;">✅ 区块在JavaScript中已注册</div>');
        } else {
            console.log('❌ 在JavaScript中未找到区块');
            document.write('<div style="color:red;">❌ 区块在JavaScript中未注册</div>');
        }
    </script>
    <?php
});