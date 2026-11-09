<?php

return [
    'botijas' => [
        'name' => 'Vendas de botijas',
        'description' => 'Vendas, alocação às bancas, despesas, produtos e locais de venda.',
        'icon' => 'i-flame',
        'entry_routes' => [
            ['permission' => 'products.manage', 'route' => 'dashboard.index'],
            ['permission' => 'sales.create', 'route' => 'sales.index'],
            ['permission' => 'sales.history.view', 'route' => 'sales.history'],
            ['permission' => 'expenses.create', 'route' => 'expenses.index'],
            ['permission' => 'expenses.approve', 'route' => 'expenses.index'],
            ['permission' => 'expenses.view', 'route' => 'expenses.index'],
            ['permission' => 'allocations.manage', 'route' => 'allocations.index'],
            ['permission' => 'allocations.request', 'route' => 'allocation-requests.index'],
            ['permission' => 'allocations.approve', 'route' => 'allocation-requests.index'],
            ['permission' => 'allocations.dispatch', 'route' => 'allocation-requests.index'],
            ['permission' => 'allocations.receive', 'route' => 'allocation-requests.index'],
            ['permission' => 'locations.manage', 'route' => 'locations.index'],
            ['permission' => 'sales.points.assign', 'route' => 'sales.points.index'],
        ],
        'menu' => [
            [
                'label' => 'VISÃO GERAL',
                'items' => [
                    ['label' => 'Dashboard', 'route' => 'dashboard.index', 'active' => 'dashboard.*', 'permission' => 'products.manage', 'icon' => 'i-grid'],
                ],
            ],
            [
                'label' => 'OPERAÇÃO',
                'items' => [
                    ['label' => 'Vendas', 'route' => 'sales.index', 'active' => 'sales.index', 'permission' => 'sales.create', 'icon' => 'i-receipt', 'badge' => 'POS'],
                    ['label' => 'Histórico geral', 'route' => 'sales.history', 'active' => 'sales.history', 'permission' => 'sales.history.view', 'icon' => 'i-file'],
                    ['label' => 'Despesas Operacionais', 'route' => 'expenses.index', 'active' => 'expenses.*', 'permissions' => ['expenses.create', 'expenses.approve', 'expenses.view'], 'icon' => 'i-receipt'],
                    ['label' => 'Alocar às bancas', 'route' => 'allocations.index', 'active' => 'allocations.*', 'permission' => 'allocations.manage', 'icon' => 'i-move'],
                    ['label' => 'Pedidos às bancas', 'route' => 'allocation-requests.index', 'active' => 'allocation-requests.*', 'permissions' => ['allocations.request', 'allocations.approve', 'allocations.dispatch', 'allocations.receive'], 'icon' => 'i-file'],
                    ['label' => 'Botijas avariadas', 'route' => 'damages.index', 'active' => 'damages.*', 'permissions' => ['products.manage', 'sales.create'], 'icon' => 'i-box'],
                ],
            ],
            [
                'label' => 'CONFIGURAÇÃO',
                'items' => [
                    ['label' => 'Produtos', 'route' => 'products.index', 'active' => 'products.*', 'permission' => 'products.manage', 'icon' => 'i-box'],
                    ['label' => 'Locais de venda', 'route' => 'locations.index', 'active' => 'locations.*', 'permission' => 'locations.manage', 'icon' => 'i-pin'],
                    ['label' => 'Atribuir postos', 'route' => 'sales.points.index', 'active' => 'sales.points.*', 'permission' => 'sales.points.assign', 'icon' => 'i-users'],
                ],
            ],
        ],
    ],
    'acessos' => [
        'name' => 'Gestão de acessos',
        'description' => 'Utilizadores, grupos e permissões de acesso.',
        'icon' => 'i-shield',
        'entry_routes' => [
            ['permission' => 'access.manage', 'route' => 'access.users'],
        ],
        'menu' => [
            [
                'label' => 'GESTÃO DE ACESSOS',
                'items' => [
                    ['label' => 'Utilizadores', 'route' => 'access.users', 'active' => 'access.users*', 'permission' => 'access.manage', 'icon' => 'i-users'],
                    ['label' => 'Grupos e acessos', 'route' => 'access.groups', 'active' => 'access.groups*', 'permission' => 'access.manage', 'icon' => 'i-shield'],
                ],
            ],
        ],
    ],
    'clientes' => [
        'name' => 'Gestão de Clientes',
        'description' => 'Registo de pessoas e organizações, contactos e estado dos clientes.',
        'icon' => 'i-customer',
        'entry_routes' => [
            ['permission' => 'customers.manage', 'route' => 'customers.index'],
        ],
        'menu' => [
            [
                'label' => 'GESTÃO DE CLIENTES',
                'items' => [
                    ['label' => 'Clientes', 'route' => 'customers.index', 'active' => 'customers.*', 'permission' => 'customers.manage', 'icon' => 'i-customer'],
                ],
            ],
        ],
    ],
    'aduaneiros' => [
        'name' => 'Processos Aduaneiros',
        'description' => 'Entradas, importadores, etapas, comunicações e anexos.',
        'icon' => 'i-file',
        'entry_routes' => [
            ['permission' => 'customs.manage', 'route' => 'customs.index'],
        ],
        'menu' => [
            [
                'label' => 'PROCESSOS ADUANEIROS',
                'items' => [
                    ['label' => 'Processos', 'route' => 'customs.index', 'active' => 'customs.*', 'permission' => 'customs.manage', 'icon' => 'i-file'],
                ],
            ],
        ],
    ],
];
