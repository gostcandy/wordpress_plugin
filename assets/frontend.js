(function($) {
    'use strict';

    var AdvancedSearch = {
        init: function() {
            this.bindEvents();
            this.initializeCheckboxes();
            this.loadInitialResults();
        },

        initializeCheckboxes: function() {
            // 初始化复选框事件
            $('.asb-checkbox').on('change', function() {
                var $form = $(this).closest('.asb-search-form');
                AdvancedSearch.updateFormState($form);

                // 复选框变化时立即搜索
                setTimeout(function() {
                    AdvancedSearch.handleSearch($form);
                }, 300);
            });
        },

        bindEvents: function() {
            var self = this;

            // 表单提交
            $(document).on('submit', '.asb-search-form', function(e) {
                e.preventDefault();
                self.handleSearch($(this));
            });

            // 表单字段变化
            $(document).on('change', '.asb-search-form select', function() {
                var $form = $(this).closest('.asb-search-form');
                self.updateFormState($form);
                self.handleSearch($form);
            });

            // 文本输入变化（防抖）
            var searchTimer;
            $(document).on('input', '.asb-search-form input[type="text"]', function() {
                var $form = $(this).closest('.asb-search-form');
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function() {
                    self.handleSearch($form);
                }, 800);
            });

            // 重置按钮
            $(document).on('click', '.asb-reset-button', function() {
                self.resetForm($(this).closest('.asb-search-form'));
            });

            // 分页点击
            $(document).on('click', '.asb-pagination a', function(e) {
                e.preventDefault();
                self.handlePagination($(this));
            });
        },

        handleSearch: function($form) {
            var self = this;
            var $block = $form.closest('.advanced-search-block');
            var $results = $block.find('.asb-results-list');
            var $loading = $block.find('.asb-loading');
            var $pagination = $block.find('.asb-pagination');
            var $count = $block.find('.asb-results-count');

            // 显示加载状态
            $loading.show();

            // 收集表单数据 - 包括复选框
            var formData = self.serializeForm($form);
            var data = {
                action: 'asb_search',
                nonce: asb_frontend.nonce
            };

            // 合并表单数据
            $.extend(data, formData);

            // 获取每页显示数量
            var blockData = $block.data('attributes');
            if (blockData && blockData.postsPerPage) {
                data.posts_per_page = blockData.postsPerPage;
            }

            // 发送AJAX请求
            $.ajax({
                url: asb_frontend.ajax_url,
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        self.renderResults($block, response.data);
                        self.renderPagination($pagination, response);
                        self.updateResultsCount($count, response);
                        self.updateURLParams($form);
                    }
                },
                error: function(xhr, status, error) {
                    $results.html('<div class="asb-error">' + (asb_frontend.strings.error || 'An error occurred') + '</div>');
                },
                complete: function() {
                    $loading.hide();
                }
            });
        },



        updateFormState: function($form) {
            // 更新表单中的所有字段状态
            var data = {};

            // 处理文本输入
            $form.find('input[type="text"]').each(function() {
                var $input = $(this);
                if ($input.val().trim()) {
                    data[$input.attr('name')] = $input.val().trim();
                }
            });

            // 处理单选选择框
            $form.find('select:not([multiple])').each(function() {
                var $select = $(this);
                if ($select.val()) {
                    data[$select.attr('name')] = $select.val();
                }
            });

            // 处理多选框（tags）
            $form.find('select[multiple]').each(function() {
                var $select = $(this);
                var values = $select.val();
                if (values && values.length > 0) {
                    data[$select.attr('name')] = values;
                }
            });

            // 处理隐藏字段（page_id等）
            $form.find('input[type="hidden"]').each(function() {
                var $input = $(this);
                if ($input.val()) {
                    data[$input.attr('name')] = $input.val();
                }
            });

            // 保存当前表单状态
            $form.data('current-state', data);
        },

        bindEvents: function() {
            var self = this;

            // 表单提交
            $(document).on('submit', '.asb-search-form', function(e) {
                e.preventDefault();
                self.handleSearch($(this));
            });

            // 表单字段变化 - 修复tags多选框事件
            $(document).on('change', '.asb-search-form select', function() {
                var $form = $(this).closest('.asb-search-form');
                self.updateFormState($form);
                // 立即更新URL但不要立即搜索（除非是单选框）
                if (!$(this).is('[multiple]')) {
                    self.updateURLParams($form);
                }
            });

            // 文本输入变化
            $(document).on('input', '.asb-search-form input[type="text"]', function() {
                var $form = $(this).closest('.asb-search-form');
                self.updateFormState($form);
            });

            // 重置按钮
            $(document).on('click', '.asb-reset-button', function() {
                self.resetForm($(this).closest('.asb-search-form'));
            });

            // 分页点击
            $(document).on('click', '.asb-pagination a', function(e) {
                e.preventDefault();
                self.handlePagination($(this));
            });

            // 关键词输入自动搜索（防抖）
            var searchTimer;
            $(document).on('input', '#asb-keyword', function() {
                var $form = $(this).closest('.asb-search-form');
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function() {
                    self.handleSearch($form);
                }, 800);
            });
        },

        handleSearch: function($form) {
            var self = this;
            var $block = $form.closest('.advanced-search-block');
            var $results = $block.find('.asb-results-list');
            var $loading = $block.find('.asb-loading');
            var $pagination = $block.find('.asb-pagination');
            var $count = $block.find('.asb-results-count');

            // 显示加载状态
            $loading.show();
            $results.html('');

            // 收集表单数据 - 修复多选框数据收集
            var formData = self.serializeForm($form);
            var data = {
                action: 'asb_search',
                nonce: asb_frontend.nonce
            };

            // 合并表单数据
            $.extend(data, formData);

            // 获取每页显示数量
            var blockData = $block.data('attributes');
            if (blockData && blockData.postsPerPage) {
                data.posts_per_page = blockData.postsPerPage;
            }

            // 发送AJAX请求
            $.ajax({
                url: asb_frontend.ajax_url,
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        self.renderResults($block, response.data);
                        self.renderPagination($pagination, response);
                        self.updateResultsCount($count, response);
                        self.updateURLParams($form);

                        // 保存搜索状态到localStorage
                        self.saveSearchState($form, formData);
                    }
                },
                error: function(xhr, status, error) {
                    $results.html('<div class="asb-error">' + (asb_frontend.strings.error || 'An error occurred') + '</div>');
                },
                complete: function() {
                    $loading.hide();
                }
            });
        },

        serializeForm: function($form) {
            var data = {};

            // 处理文本输入
            $form.find('input[type="text"]').each(function() {
                var $input = $(this);
                if ($input.val().trim()) {
                    data[$input.attr('name')] = $input.val().trim();
                }
            });

            // 处理选择框
            $form.find('select').each(function() {
                var $select = $(this);
                if ($select.val()) {
                    data[$select.attr('name')] = $select.val();
                }
            });

            // 处理复选框（tags）
            var tags = [];
            $form.find('input[name="tags[]"]:checked').each(function() {
                tags.push($(this).val());
            });

            if (tags.length > 0) {
                data['tags'] = tags;
            }

            // 处理隐藏字段
            $form.find('input[type="hidden"]').each(function() {
                var $input = $(this);
                var name = $input.attr('name');
                if (name && name !== 'tags[]') { // 排除复选框的同名隐藏字段
                    data[name] = $input.val();
                }
            });

            return data;
        },

        updateURLParams: function($form) {
            var params = {};

            // 获取当前页面ID（如果存在）
            var pageId = this.getCurrentPageId();
            if (pageId) {
                params['page_id'] = pageId;
            }

            // 获取表单数据
            var formData = this.serializeForm($form);

            // 合并参数
            $.extend(params, formData);

            // 移除空值
            $.each(params, function(key, value) {
                if (value === '' || (Array.isArray(value) && value.length === 0)) {
                    delete params[key];
                }
            });

            // 添加搜索标识
            params['asb_search'] = '1';

            // 构建URL
            var queryString = $.param(params, true);
            var newUrl = window.location.pathname;

            // 如果有参数才添加问号
            if (queryString) {
                newUrl += '?' + queryString;
            }

            // 更新浏览器URL（不刷新页面）
            history.replaceState({formData: formData}, '', newUrl);
        },

        getCurrentPageId: function() {
            // 从URL获取page_id
            var urlParams = new URLSearchParams(window.location.search);
            var pageId = urlParams.get('page_id');

            if (!pageId) {
                // 尝试从隐藏字段获取
                var $hiddenInput = $('input[name="page_id"]');
                if ($hiddenInput.length) {
                    pageId = $hiddenInput.val();
                }
            }

            return pageId;
        },

        loadInitialResults: function() {
            var self = this;
            $('.advanced-search-block').each(function() {
                var $block = $(this);
                var $form = $block.find('.asb-search-form');

                // 从URL恢复表单状态
                self.restoreFormFromURL($form);

                // 检查是否有搜索参数
                var params = self.getUrlParams();
                var hasSearchParams = false;

                $.each(params, function(key, value) {
                    if (['q', 'cat', 'tags', 'page'].includes(key) && value) {
                        hasSearchParams = true;
                        return false;
                    }
                });

                if (hasSearchParams) {
                    // 有搜索参数，执行搜索
                    self.handleSearch($form);
                }
                // 注意：初始文章已经由PHP渲染，所以这里不需要再加载
            });
        },

        restoreFormFromURL: function($form) {
            var params = this.getUrlParams();

            // 恢复文本字段
            if (params.q) {
                $form.find('input[name="q"]').val(params.q);
            }

            // 恢复分类选择
            if (params.cat) {
                $form.find('select[name="cat"]').val(params.cat);
            }

            // 恢复标签复选框
            if (params.tags) {
                var tagsArray = Array.isArray(params.tags) ? params.tags : [params.tags];
                $form.find('input[name="tags[]"]').each(function() {
                    var $checkbox = $(this);
                    var value = $checkbox.val();
                    if (tagsArray.includes(value)) {
                        $checkbox.prop('checked', true);
                    } else {
                        $checkbox.prop('checked', false);
                    }
                });
            }

            // 恢复页码
            if (params.page) {
                $form.find('input[name="page"]').remove();
                $form.append('<input type="hidden" name="page" value="' + params.page + '">');
            }

            // 更新表单状态
            this.updateFormState($form);
        },

        getUrlParams: function() {
            var params = {};
            var urlParams = new URLSearchParams(window.location.search);

            // 遍历所有URL参数
            for (var pair of urlParams.entries()) {
                var key = pair[0];
                var value = pair[1];

                // 处理数组参数（如tags[]=1&tags[]=2）
                if (key.endsWith('[]')) {
                    key = key.replace('[]', '');
                    if (!params[key]) {
                        params[key] = [];
                    }
                    params[key].push(value);
                } else {
                    params[key] = value;
                }
            }

            return params;
        },

        loadLatestPosts: function($block, showPagination) {
            var self = this;
            var $results = $block.find('.asb-results-list');
            var $loading = $block.find('.asb-loading');
            var $pagination = $block.find('.asb-pagination');
            var blockData = $block.data('attributes');

            $loading.show();

            // 设置默认分页参数
            var page = 1;
            var perPage = blockData ? blockData.postsPerPage : 10;

            $.ajax({
                url: asb_frontend.rest_url + 'posts',
                type: 'GET',
                data: {
                    per_page: perPage,
                    page: page,
                    _embed: true
                },
                success: function(posts, status, xhr) {
                    // 获取总文章数和页数
                    var total = parseInt(xhr.getResponseHeader('X-WP-Total') || 0);
                    var totalPages = parseInt(xhr.getResponseHeader('X-WP-TotalPages') || 0);

                    var formattedPosts = posts.map(function(post) {
                        return self.formatPost(post);
                    });

                    self.renderResults($block, formattedPosts);

                    // 始终显示分页（如果有需要）
                    if (showPagination && totalPages > 1) {
                        self.renderPagination($pagination, {
                            total: total,
                            total_pages: totalPages,
                            current_page: page
                        });
                    }

                    // 更新结果计数
                    $block.find('.asb-results-count').text(total + ' ' + (asb_frontend.strings.results_found || 'results found'));
                },
                error: function() {
                    $results.html('<div class="asb-error">' + (asb_frontend.strings.load_error || 'Failed to load posts') + '</div>');
                },
                complete: function() {
                    $loading.hide();
                }
            });
        },

        formatPost: function(post) {
            return {
                id: post.id,
                title: post.title && post.title.rendered ? post.title.rendered : '',
                excerpt: post.excerpt && post.excerpt.rendered ?
                    post.excerpt.rendered.replace(/<[^>]+>/g, '').substring(0, 200) + '...' : '',
                content: post.content && post.content.rendered ? post.content.rendered : '',
                permalink: post.link || '#',
                date: post.date ? new Date(post.date).toLocaleDateString() : '',
                author: post._embedded && post._embedded.author && post._embedded.author[0] ?
                    post._embedded.author[0].name : '',
                categories: post._embedded && post._embedded['wp:term'] ?
                    post._embedded['wp:term'].filter(function(tax) {
                        return tax[0] && tax[0].taxonomy === 'category';
                    }).map(function(cat) {
                        return cat[0].name;
                    }) : [],
                tags: post._embedded && post._embedded['wp:term'] ?
                    post._embedded['wp:term'].filter(function(tax) {
                        return tax[0] && tax[0].taxonomy === 'post_tag';
                    }).map(function(tag) {
                        return tag[0].name;
                    }) : [],
                thumbnail: post._embedded && post._embedded['wp:featuredmedia'] &&
                post._embedded['wp:featuredmedia'][0] ?
                    post._embedded['wp:featuredmedia'][0].source_url : ''
            };
        },

        renderResults: function($block, posts) {
            var $results = $block.find('.asb-results-list');

            if (!posts || posts.length === 0) {
                $results.html('<div class="asb-no-results">' + (asb_frontend.strings.no_results || 'No results found') + '</div>');
                return;
            }

            var html = '<div class="asb-results-grid">';

            $.each(posts, function(i, post) {
                html += '<article class="asb-post-item">';
                html += '<div class="asb-post-content">';
                html += '<h3 class="asb-post-title"><a href="' + post.permalink + '">' + post.title + '</a></h3>';

                if (post.thumbnail) {
                    html += '<div class="asb-post-thumbnail"><img src="' + post.thumbnail + '" alt="' + post.title + '"></div>';
                }

                html += '<div class="asb-post-excerpt">' + post.excerpt + '</div>';
                html += '<div class="asb-post-meta">';
                html += '<span class="asb-post-date">' + post.date + '</span>';
                html += '<span class="asb-post-author">' + (asb_frontend.strings.by || 'by') + ' ' + post.author + '</span>';

                if (post.categories && post.categories.length > 0) {
                    html += '<span class="asb-post-categories">';
                    html += (asb_frontend.strings.in || 'in') + ' ' + post.categories.join(', ');
                    html += '</span>';
                }

                html += '</div>';
                html += '</div>';
                html += '</article>';
            });

            html += '</div>';
            $results.html(html);
        },

        renderPagination: function($pagination, response) {
            if (!$pagination || !response || !response.total_pages || response.total_pages <= 1) {
                if ($pagination) {
                    $pagination.html('');
                }
                return;
            }

            var current = response.current_page || 1;
            var total = response.total_pages;
            var html = '<div class="asb-pagination-links">';

            if (current > 1) {
                html += '<a href="#" data-page="' + (current - 1) + '" class="asb-page-prev">« ' + (asb_frontend.strings.previous || 'Previous') + '</a>';
            }

            for (var i = 1; i <= total; i++) {
                if (i === 1 || i === total || (i >= current - 2 && i <= current + 2)) {
                    var active = (i === current) ? ' active' : '';
                    html += '<a href="#" data-page="' + i + '" class="asb-page-number' + active + '">' + i + '</a>';
                } else if (i === current - 3 || i === current + 3) {
                    html += '<span class="asb-page-dots">...</span>';
                }
            }

            if (current < total) {
                html += '<a href="#" data-page="' + (current + 1) + '" class="asb-page-next">' + (asb_frontend.strings.next || 'Next') + ' »</a>';
            }

            html += '</div>';
            $pagination.html(html);
        },

        updateResultsCount: function($count, response) {
            if (!$count || !response) return;

            var total = response.total || 0;
            var text = total + ' ' + (asb_frontend.strings.results_found || 'results found');
            $count.text(text);
        },

        handlePagination: function($link) {
            var page = $link.data('page');
            var $form = $link.closest('.advanced-search-block').find('.asb-search-form');
            $form.find('input[name="page"]').remove();
            $form.append('<input type="hidden" name="page" value="' + page + '">');
            this.handleSearch($form);
        },

        saveSearchState: function($form, formData) {
            try {
                var state = {
                    formData: formData,
                    timestamp: new Date().getTime()
                };
                localStorage.setItem('asb_last_search', JSON.stringify(state));
            } catch (e) {
                console.log('Could not save search state:', e);
            }
        },

        resetForm: function($form) {
            // 重置表单字段
            $form.find('input[type="text"]').val('');
            $form.find('select').val('').trigger('change');
            $form.find('input[name="page"]').remove();

            // 保留page_id
            var pageId = this.getCurrentPageId();
            if (pageId) {
                $form.find('input[name="page_id"]').remove();
                $form.append('<input type="hidden" name="page_id" value="' + pageId + '">');
            }

            // 执行搜索（获取最新文章）
            this.handleSearch($form);

            // 清除URL参数但保留page_id
            var newUrl = window.location.pathname;
            if (pageId) {
                newUrl += '?page_id=' + pageId;
            }
            history.replaceState(null, '', newUrl);
        }
    };

    // 初始化
    $(document).ready(function() {
        if (typeof asb_frontend !== 'undefined') {
            AdvancedSearch.init();
        }
    });

})(jQuery);