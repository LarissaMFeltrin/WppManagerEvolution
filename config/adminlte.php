<?php

return [
    'title' => 'WppManager',
    'title_prefix' => '',
    'title_postfix' => ' | WppManager',

    'use_ico_only' => false,
    'use_full_favicon' => false,

    'google_fonts' => [
        'allowed' => true,
    ],

    'logo' => '<b>Wpp</b>Manager',
    'logo_img' => 'vendor/adminlte/dist/img/AdminLTELogo.png',
    'logo_img_class' => 'brand-image img-circle elevation-3',
    'logo_img_xl' => null,
    'logo_img_xl_class' => 'brand-image-xs',
    'logo_img_alt' => 'WppManager',

    'auth_logo' => [
        'enabled' => false,
        'img' => [
            'path' => 'vendor/adminlte/dist/img/AdminLTELogo.png',
            'alt' => 'Auth Logo',
            'class' => '',
            'width' => 50,
            'height' => 50,
        ],
    ],

    'preloader' => [
        'enabled' => false,
    ],

    'usermenu_enabled' => true,
    'usermenu_header' => true,
    'usermenu_header_class' => 'bg-primary',
    'usermenu_image' => false,
    'usermenu_desc' => true,
    'usermenu_profile_url' => false,

    'layout_topnav' => null,
    'layout_boxed' => null,
    'layout_fixed_sidebar' => true,
    'layout_fixed_navbar' => true,
    'layout_fixed_footer' => null,
    'layout_dark_mode' => null,

    'classes_auth_card' => 'card-outline card-primary',
    'classes_auth_header' => '',
    'classes_auth_body' => '',
    'classes_auth_footer' => '',
    'classes_auth_icon' => '',
    'classes_auth_btn' => 'btn-flat btn-primary',

    'classes_body' => '',
    'classes_brand' => '',
    'classes_brand_text' => '',
    'classes_content_wrapper' => '',
    'classes_content_header' => '',
    'classes_content' => '',
    'classes_sidebar' => 'sidebar-dark-primary elevation-4',
    'classes_sidebar_nav' => '',
    'classes_topnav' => 'navbar-white navbar-light',
    'classes_topnav_nav' => 'navbar-expand',
    'classes_topnav_container' => 'container',

    'sidebar_mini' => 'lg',
    'sidebar_collapse' => false,
    'sidebar_collapse_auto_size' => false,
    'sidebar_collapse_remember' => false,
    'sidebar_collapse_remember_no_transition' => true,
    'sidebar_scrollbar_theme' => 'os-theme-light',
    'sidebar_scrollbar_auto_hide' => 'l',
    'sidebar_nav_accordion' => true,
    'sidebar_nav_animation_speed' => 300,

    'right_sidebar' => false,
    'right_sidebar_icon' => 'fas fa-cogs',
    'right_sidebar_theme' => 'dark',
    'right_sidebar_slide' => true,
    'right_sidebar_push' => true,
    'right_sidebar_scrollbar_theme' => 'os-theme-light',
    'right_sidebar_scrollbar_auto_hide' => 'l',

    'use_route_url' => true,
    'dashboard_url' => 'admin.dashboard',
    'logout_url' => 'logout',
    'login_url' => 'login',
    'register_url' => false,
    'password_reset_url' => false,
    'password_email_url' => false,
    'profile_url' => false,
    'disable_darkmode_routes' => false,

    'laravel_asset_bundling' => false,

    'menu' => [
        // Navbar
        [
            'type' => 'fullscreen-widget',
            'topnav_right' => true,
        ],

        // Sidebar
        ['header' => 'MONITORAMENTO'],
        [
            'text' => 'Dashboard',
            'route' => 'admin.dashboard',
            'icon' => 'fas fa-fw fa-tachometer-alt',
        ],
        [
            'text' => 'Empresas',
            'route' => 'admin.empresas.index',
            'icon' => 'fas fa-fw fa-building',
        ],

        ['header' => 'WHATSAPP'],
        [
            'text' => 'Instancias',
            'route' => 'admin.whatsapp.index',
            'icon' => 'fab fa-fw fa-whatsapp',
        ],
        [
            'text' => 'Nova Instancia',
            'route' => 'admin.whatsapp.create',
            'icon' => 'fas fa-fw fa-plus',
        ],

        ['header' => 'ATENDIMENTO'],
        [
            'text' => 'Painel de Conversas',
            'route' => 'admin.painel',
            'icon' => 'fas fa-fw fa-headset',
        ],
        [
            'text' => 'Fila de Espera',
            'route' => 'admin.fila',
            'icon' => 'fas fa-fw fa-users-cog',
        ],
        [
            'text' => 'Meu Console',
            'route' => 'admin.chat',
            'icon' => 'fas fa-fw fa-comment-dots',
        ],
        [
            'text' => 'Contatos',
            'route' => 'admin.contatos.index',
            'icon' => 'fas fa-fw fa-address-book',
        ],

        ['header' => 'MONITORAMENTO'],
        [
            'text' => 'Monitor',
            'route' => 'admin.monitor',
            'icon' => 'fas fa-fw fa-chart-bar',
        ],
        [
            'text' => 'Supervisao',
            'route' => 'admin.supervisao',
            'icon' => 'fas fa-fw fa-eye',
        ],
        [
            'text' => 'Historico Conversas',
            'route' => 'admin.historico',
            'icon' => 'fas fa-fw fa-history',
        ],
        [
            'text' => 'Importar Historico',
            'route' => 'admin.import.index',
            'icon' => 'fas fa-fw fa-file-import',
        ],
        [
            'text' => 'Logs de Webhook',
            'route' => 'admin.logs',
            'icon' => 'fas fa-fw fa-file-alt',
        ],
        [
            'text' => 'Saude do Sistema',
            'route' => 'admin.saude',
            'icon' => 'fas fa-fw fa-heartbeat text-danger',
        ],
        [
            'text' => 'Sincronizar Contatos',
            'route' => 'admin.contatos.sincronizar.page',
            'icon' => 'fas fa-fw fa-sync',
        ],

        ['header' => 'CONFIGURACOES'],
        [
            'text' => 'Usuarios',
            'route' => 'admin.users.index',
            'icon' => 'fas fa-fw fa-users',
        ],
        [
            'text' => 'Atendentes',
            'route' => 'admin.users.index',
            'icon' => 'fas fa-fw fa-user-tie',
            'url' => 'admin/users?role=agent',
        ],
    ],

    'filters' => [
        JeroenNoten\LaravelAdminLte\Menu\Filters\GateFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\HrefFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\SearchFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\ActiveFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\ClassesFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\LangFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\DataFilter::class,
    ],

    'plugins' => [
        'Datatables' => [
            'active' => true,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js',
                ],
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js',
                ],
                [
                    'type' => 'css',
                    'asset' => false,
                    'location' => '//cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css',
                ],
            ],
        ],
        'Select2' => [
            'active' => true,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js',
                ],
                [
                    'type' => 'css',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css',
                ],
                [
                    'type' => 'css',
                    'asset' => false,
                    'location' => '//cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css',
                ],
            ],
        ],
        'Sweetalert2' => [
            'active' => true,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdn.jsdelivr.net/npm/sweetalert2@11',
                ],
            ],
        ],
        'InstanceMonitor' => [
            'active' => true,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => true,
                    'location' => 'js/instance-monitor.js',
                ],
            ],
        ],
    ],

    'iframe' => [
        'default_tab' => [
            'url' => null,
            'title' => null,
        ],
        'buttons' => [
            'close' => true,
            'close_all' => true,
            'close_all_other' => true,
            'scroll_left' => true,
            'scroll_right' => true,
            'fullscreen' => true,
        ],
        'options' => [
            'loading_screen' => 1000,
            'auto_show_new_tab' => true,
            'use_navbar_items' => true,
        ],
    ],

    'livewire' => false,
];
