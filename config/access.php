<?php

return [
    'permissions' => [
        'products.manage' => 'Gerir produtos e dashboard',
        'locations.manage' => 'Gerir locais de venda',
        'allocations.manage' => 'Alocar botijas às bancas',
        'allocations.request' => 'Solicitar botijas para a banca',
        'allocations.approve' => 'Aprovar pedidos de botijas',
        'allocations.dispatch' => 'Registar saída e gerar guia de estoque',
        'allocations.receive' => 'Confirmar entrega na banca com comprovativo',
        'sales.create' => 'Registar vendas a grosso e por unidade',
        'sales.profit.view' => 'Ver margem de lucro das vendas',
        'sales.history.view' => 'Consultar histórico geral de vendas',
        'sales.points.assign' => 'Atribuir postos de venda a utilizadores',
        'expenses.create' => 'Registar despesas operacionais',
        'expenses.approve' => 'Aprovar, rejeitar e anular despesas operacionais',
        'expenses.view' => 'Consultar despesas operacionais e relatórios',
        'customers.manage' => 'Gerir clientes',
        'customs.manage' => 'Gerir processos aduaneiros e anexos',
        'access.manage' => 'Gerir utilizadores e grupos',
        'access.grant.botijas' => 'Atribuir acessos do módulo Vendas de botijas',
        'access.grant.clientes' => 'Atribuir acessos do módulo Gestão de Clientes',
        'access.grant.aduaneiros' => 'Atribuir acessos do módulo Processos Aduaneiros',
        'access.grant.acessos' => 'Atribuir acessos do módulo Gestão de acessos',
    ],
    'modules' => [
        'botijas' => [
            'name' => 'Vendas de botijas', 'grant' => 'access.grant.botijas',
            'permissions' => ['products.manage', 'locations.manage', 'allocations.manage', 'allocations.request', 'allocations.approve', 'allocations.dispatch', 'allocations.receive', 'sales.create', 'sales.profit.view', 'sales.history.view', 'sales.points.assign', 'expenses.create', 'expenses.approve', 'expenses.view'],
        ],
        'clientes' => [
            'name' => 'Gestão de Clientes', 'grant' => 'access.grant.clientes',
            'permissions' => ['customers.manage'],
        ],
        'aduaneiros' => [
            'name' => 'Processos Aduaneiros', 'grant' => 'access.grant.aduaneiros',
            'permissions' => ['customs.manage'],
        ],
        'acessos' => [
            'name' => 'Gestão de acessos', 'grant' => 'access.grant.acessos',
            'permissions' => ['access.manage'],
        ],
    ],
];
