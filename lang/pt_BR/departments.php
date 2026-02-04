<?php

return [
    'department' => 'Departamento',
    'departments' => 'Departamentos',
    'create' => 'Criar Departamento',
    'edit' => 'Editar Departamento',
    'delete' => 'Excluir Departamento',

    'fields' => [
        'name' => 'Nome',
        'slug' => 'Slug',
        'description' => 'Descrição',
        'email' => 'E-mail',
        'is_active' => 'Ativo',
        'sort_order' => 'Ordem',
    ],

    'messages' => [
        'created' => 'Departamento criado com sucesso.',
        'updated' => 'Departamento atualizado com sucesso.',
        'deleted' => 'Departamento excluído com sucesso.',
        'not_found' => 'Departamento não encontrado.',
        'has_tickets' => 'Não é possível excluir departamento que possui chamados.',
    ],

    'roles' => [
        'operator' => 'Operador',
        'manager' => 'Gerente',
        'admin' => 'Administrador',
    ],
];
