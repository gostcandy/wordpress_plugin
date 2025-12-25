# Advanced Search Block Plugin

一个功能强大的WordPress Gutenberg区块插件，提供高级搜索功能和AJAX支持。

## 功能特性

- ✅ Gutenberg区块编辑器集成
- ✅ 高级搜索表单（关键词、分类、标签）
- ✅ URL参数同步和页面刷新状态保持
- ✅ AJAX搜索无需页面刷新
- ✅ 分页支持
- ✅ REST API支持
- ✅ 响应式设计


## 安装方法

1. 下载插件文件
2. 上传到 `/wp-content/plugins/` 目录
3. 需要保证插件的根目录有写入权限
3. 在WordPress后台激活插件
4. 在Gutenberg编辑器中添加"Advanced Search"区块

## 使用方法

### 1. 区块使用
- 在Gutenberg编辑器中搜索"Advanced Search"
- 拖放区块到页面
- 在区块设置中配置显示选项



### 2. URL参数
支持的URL参数：
- `q` - 搜索关键词
- `cat` - 分类ID
- `tags[]` - 标签ID（多个）
- `page` - 页码

示例：`https://domain.com/?q=test&cat=2&tags[]=1&tags[]=2&page=1&asb_search=1`


## 文件结构
advanced-search-plugin/
├── advanced-search-plugin.php # 主插件文件
├── README.md # 说明文档
├── includes/ # PHP类文件
│ ├── class-database-manager.php
│ ├── class-ajax-handler.php
│ └── class-search-query.php
├── assets/ # 静态资源
│ ├── block.js # 区块编辑器脚本
│ ├── frontend.js # 前端脚本
│ ├── style.css # 前端样式
│ └── editor-style.css # 编辑器样式
└── templates/ # 模板文件



## 技术要求

- WordPress 6.9+
- PHP 7.4+
- MySQL 5.6+
- JavaScript enabled

