// assets/block.js - 修复版本
(function(blocks, element, components, editor, i18n, data, apiFetch, blockEditor) {
    // 检查所有依赖是否可用
    if (!blocks || !element || !components || !editor || !i18n) {
        console.error('Advanced Search: Missing WordPress dependencies');
        return;
    }

    var __ = i18n.__;
    var el = element.createElement;
    var registerBlockType = blocks.registerBlockType;
    var TextControl = components.TextControl;
    var SelectControl = components.SelectControl;
    var CheckboxControl = components.CheckboxControl;
    var RangeControl = components.RangeControl;
    var PanelBody = components.PanelBody;
    var InspectorControls = editor.InspectorControls || blockEditor.InspectorControls;
    var useBlockProps = editor.useBlockProps || blockEditor.useBlockProps;
    var BlockControls = editor.BlockControls || blockEditor.BlockControls;
    var ToolbarGroup = components.ToolbarGroup;
    var ToolbarButton = components.ToolbarButton;
    var Placeholder = components.Placeholder;
    var Spinner = components.Spinner;

    // 注册区块
    var metadata = {
        title: __('Advanced Search', 'advanced-search'),
        description: __('Display advanced search form with results', 'advanced-search'),
        icon: 'search',
        category: 'advanced-search',
        keywords: [__('search', 'advanced-search'), __('filter', 'advanced-search'), __('posts', 'advanced-search')],
        supports: {
            html: false,
            align: true,
            alignWide: true
        }
    };

    var blockAttributes = {
        blockId: {
            type: 'string',
            default: ''
        },
        postsPerPage: {
            type: 'number',
            default: 10
        },
        showCategory: {
            type: 'boolean',
            default: true
        },
        showTags: {
            type: 'boolean',
            default: false
        },
        showPagination: {
            type: 'boolean',
            default: false
        }
    };

    var Edit = function(props) {
        var attributes = props.attributes;
        var setAttributes = props.setAttributes;

        var blockProps = useBlockProps ? useBlockProps({
            className: 'advanced-search-block-editor'
        }) : { className: 'advanced-search-block-editor' };

        // 创建预览界面
        return el('div', blockProps,
            InspectorControls && el(InspectorControls, {},
                el(PanelBody, {title: __('Settings', 'advanced-search'), initialOpen: true},
                    el(RangeControl, {
                        label: __('Posts per page', 'advanced-search'),
                        value: attributes.postsPerPage,
                        onChange: function(value) {
                            setAttributes({postsPerPage: value});
                        },
                        min: 1,
                        max: 50
                    }),
                    el(CheckboxControl, {
                        label: __('Show category filter', 'advanced-search'),
                        checked: attributes.showCategory,
                        onChange: function(value) {
                            setAttributes({showCategory: value});
                        }
                    }),
                    el(CheckboxControl, {
                        label: __('Show tags filter', 'advanced-search'),
                        checked: attributes.showTags,
                        onChange: function(value) {
                            setAttributes({showTags: value});
                        }
                    }),
                    el(CheckboxControl, {
                        label: __('Show pagination', 'advanced-search'),
                        checked: attributes.showPagination,
                        onChange: function(value) {
                            setAttributes({showPagination: value});
                        }
                    })
                )
            ),
            el('div', {className: 'asb-editor-preview'},
                el('h3', {}, __('Advanced Search Block', 'advanced-search')),
                el('p', {}, __('This block will display an advanced search form with results.', 'advanced-search')),
                el('div', {className: 'asb-form-preview'},
                    el('div', {className: 'asb-field-preview'},
                        el('label', {}, __('Keyword', 'advanced-search')),
                        el('div', {className: 'asb-input-preview'}, __('Text input field', 'advanced-search'))
                    ),
                    attributes.showCategory && el('div', {className: 'asb-field-preview'},
                        el('label', {}, __('Category', 'advanced-search')),
                        el('div', {className: 'asb-select-preview'}, __('Category dropdown', 'advanced-search'))
                    ),
                    attributes.showTags && el('div', {className: 'asb-field-preview'},
                        el('label', {}, __('Tags', 'advanced-search')),
                        el('div', {className: 'asb-select-preview'}, __('Tags multiple select', 'advanced-search'))
                    ),
                    el('div', {className: 'asb-buttons-preview'},
                        el('button', {className: 'button button-primary'}, __('Search', 'advanced-search')),
                        el('button', {className: 'button'}, __('Reset', 'advanced-search'))
                    )
                ),
                el('div', {className: 'asb-results-preview'},
                    el('h4', {}, __('Search Results', 'advanced-search')),
                    el('p', {}, __('Search results will be displayed here.', 'advanced-search'))
                )
            )
        );
    };

    var Save = function() {
        // 动态区块，无需保存内容
        return null;
    };

    // 注册区块
    registerBlockType('advanced-search/block', {
        ...metadata,
        attributes: blockAttributes,
        edit: Edit,
        save: Save
    });

    console.log('Advanced Search Block registered successfully');

})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.editor, window.wp.i18n, window.wp.data, window.wp.apiFetch, window.wp.blockEditor);